<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CourseRelay_LearnDash {

    // Completion type constants — mirror GrassBlade's values exactly
    const TYPE_HIDE_BUTTON           = 'hide_button';
    const TYPE_HIDDEN_UNTIL_COMPLETE = 'hidden_until_complete';
    const TYPE_DISABLE_UNTIL_COMPLETE= 'disable_until_complete';
    const TYPE_AUTO_REDIRECT         = 'completion_move_nextlevel';

    public static function init() {
        // Single hook — registers/enqueues both the completion CSS and the
        // footer completion-watcher script via core WP APIs.
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
    }

    // ── Button Handling ────────────────────────────────────────────────────────

    /**
     * Main handler — fires on wp_enqueue_scripts.
     * Checks if current post has SCORM content and enqueues appropriate CSS/JS.
     * Mirrors GrassBlade's grassblade_learndash_hide_mark_complete_button() logic,
     * but delivers assets through wp_enqueue_style()/wp_enqueue_script() instead
     * of printing raw <style>/<script> tags.
     */
    public static function enqueue_frontend_assets() {
        global $post;
        if ( empty( $post->ID ) ) return;

        // Only act on LearnDash lesson/topic/quiz post types
        if ( ! in_array( $post->post_type, array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ), true ) ) return;

        $content_id = self::get_post_scorm_content( $post->ID );
        if ( empty( $content_id ) ) return;

        $pkg = CourseRelay_Package::get_package( $content_id );
        if ( ! $pkg || ! $pkg->completion_tracking ) return;

        $completion_type = $pkg->completion_type ?: CourseRelay_Settings::get( 'completion_type' );
        $is_completed    = self::is_content_completed( $content_id );

        CourseRelay_Runtime::log( "enqueue_frontend_assets: post={$post->ID} content=$content_id type=$completion_type completed=" . ( $is_completed ? 'yes' : 'no' ) );

        switch ( $completion_type ) {

            case self::TYPE_HIDE_BUTTON:
            case self::TYPE_HIDDEN_UNTIL_COMPLETE:
                // Hide button until SCORM completes, then show normally
                if ( ! $is_completed ) {
                    self::enable_next_link();
                    self::enqueue_hide_css();
                }
                break;

            case self::TYPE_DISABLE_UNTIL_COMPLETE:
                // Show button but disabled until SCORM completes
                if ( ! $is_completed ) {
                    self::enable_next_link();
                    self::enqueue_disable_css();
                }
                break;

            case self::TYPE_AUTO_REDIRECT:
                // Always hide the button — footer script handles redirect on completion
                self::enable_next_link();
                self::enqueue_hide_css();
                break;
        }

        // Completion-watcher script — handles auto-redirect and JS-side
        // button show/enable once SCORM reports completion.
        wp_enqueue_script(
            'courserelay-frontend',
            COURSERELAY_URL . 'assets/js/courserelay-frontend.js',
            array( 'jquery' ),
            COURSERELAY_VERSION,
            true
        );

        wp_localize_script( 'courserelay-frontend', 'CourseRelayLD', array(
            'completion_type' => $completion_type,
            'content_id'      => $content_id,
            'post_id'         => $post->ID,
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'courserelay_check' ),
            'is_completed'    => $is_completed,
            'next_url'        => self::get_next_step_url( $post->ID ),
            'types'           => array(
                'hide'     => self::TYPE_HIDE_BUTTON,
                'hidden'   => self::TYPE_HIDDEN_UNTIL_COMPLETE,
                'disable'  => self::TYPE_DISABLE_UNTIL_COMPLETE,
                'redirect' => self::TYPE_AUTO_REDIRECT,
            ),
        ) );
    }

    // ── CSS Enqueue Helpers ───────────────────────────────────────────────────

    /**
     * Register (once) and enqueue an empty stylesheet handle to attach our
     * inline completion-button CSS to, so it goes through wp_add_inline_style()
     * instead of a raw <style> tag.
     */
    private static function courserelay_style_handle() {
        static $enqueued = false;
        if ( ! $enqueued ) {
            wp_register_style( 'courserelay-frontend', false, array(), COURSERELAY_VERSION );
            wp_enqueue_style( 'courserelay-frontend' );
            $enqueued = true;
        }
        return 'courserelay-frontend';
    }

    /**
     * Enqueue CSS to hide the Mark Complete button.
     * Uses the same selectors as GrassBlade to cover all LD theme variants.
     */
    private static function enqueue_hide_css() {
        wp_add_inline_style( self::courserelay_style_handle(), '
            #sfwd-mark-complete,
            .learndash-wrapper form.sfwd-mark-complete,
            form.sfwd-mark-complete,
            .learndash-wrapper:not(.ld-registration__outer-wrapper) #sfwd-mark-complete,
            .learndash-wrapper:not(.ld-registration__outer-wrapper) .sfwd-mark-complete,
            .learndash-wrapper:not(.ld-registration__outer-wrapper):not(.learndash-wrapper--modern) .sfwd-mark-complete {
                display: none !important;
            }
        ' );
    }

    /**
     * Enqueue CSS to show but disable the Mark Complete button.
     */
    private static function enqueue_disable_css() {
        wp_add_inline_style( self::courserelay_style_handle(), '
            #learndash_mark_complete_button,
            .learndash_mark_complete_button {
                opacity: 0.5 !important;
                cursor: not-allowed !important;
                pointer-events: none !important;
            }
        ' );
    }

    /**
     * Enable the Next Lesson link so learners aren't stuck when button is hidden.
     * Mirrors GrassBlade's approach exactly.
     */
    private static function enable_next_link() {
        add_filter( 'learndash_show_next_link', function( $r, $u, $p ) {
            return true;
        }, 100, 3 );
    }

    // ── Completion State ───────────────────────────────────────────────────────

    /**
     * Check if the current user has completed a given content_id.
     * Mirrors GrassBlade's post_contents_completed() logic.
     */
    public static function is_content_completed( $content_id, $user_id = null ) {
        if ( empty( $user_id ) ) $user_id = get_current_user_id();
        if ( empty( $user_id ) ) return false;

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $record = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}courserelay_completions
             WHERE user_id = %d AND content_id = %d LIMIT 1",
            $user_id, $content_id
        ) );

        return ! empty( $record );
    }

    /**
     * Get the SCORM content_id attached to a post.
     */
    public static function get_post_scorm_content( $post_id ) {
        return (int) get_post_meta( $post_id, '_courserelay_content_id', true );
    }

    /**
     * Get the next step URL in a LearnDash course.
     */
    private static function get_next_step_url( $post_id ) {
        if ( ! function_exists( 'learndash_get_next_lesson_link' ) ) return '';
        $next = learndash_get_next_lesson_link( $post_id );
        if ( empty( $next ) ) return '';
        // learndash_get_next_lesson_link returns an <a> tag — extract href
        preg_match( '/href=["\']([^"\']+)["\']/', $next, $matches );
        return ! empty( $matches[1] ) ? esc_url( $matches[1] ) : '';
    }

    // ── AJAX: Check completion (polled by footer JS) ───────────────────────────

    public static function ajax_check_completion() {
        check_ajax_referer( 'courserelay_check', 'nonce' );
        $content_id = isset( $_POST['content_id'] ) ? intval( $_POST['content_id'] ) : 0;
        $completed  = self::is_content_completed( $content_id );
        wp_send_json_success( array( 'completed' => $completed ) );
    }

    // ── Mark Complete ──────────────────────────────────────────────────────────

    /**
     * Mark a LearnDash lesson/topic complete for a user.
     * Handles shared steps, child topics, and direct user meta fallback.
     */
    public static function mark_complete( $user_id, $post_id, $course_id = 0 ) {
        if ( ! function_exists( 'learndash_process_mark_complete' ) ) {
            CourseRelay_Runtime::log( "mark_complete: LearnDash not active." );
            return false;
        }

        $post_type = get_post_type( $post_id );
        CourseRelay_Runtime::log( "mark_complete: user=$user_id post=$post_id type=$post_type course=$course_id" );

        // Resolve course_id if not provided
        if ( empty( $course_id ) ) {
            $course_id = function_exists( 'learndash_get_course_id' )
                ? (int) learndash_get_course_id( $post_id )
                : (int) get_post_meta( $post_id, 'course_id', true );
        }

        // Complete child topics first (LearnDash blocks lesson if topics incomplete)
        if ( $post_type === 'sfwd-lessons' && ! empty( $course_id ) && function_exists( 'learndash_get_lesson_topics' ) ) {
            $topics = (array) learndash_get_lesson_topics( $post_id, false, $course_id );
            CourseRelay_Runtime::log( count( $topics ) . ' child topic(s) found.' );
            foreach ( $topics as $topic ) {
                $topic_id = is_object( $topic ) ? $topic->ID : $topic;
                if ( ! get_user_meta( $user_id, 'completed_' . $topic_id, true ) ) {
                    $r = learndash_process_mark_complete( $user_id, $topic_id, false, $course_id );
                    if ( ! $r ) update_user_meta( $user_id, 'completed_' . $topic_id, time() );
                    CourseRelay_Runtime::log( "  topic $topic_id: " . ( $r ? 'OK' : 'direct meta' ) );
                }
            }
        }

        // Mark the lesson/topic itself
        $result = learndash_process_mark_complete( $user_id, $post_id, false, $course_id ?: null );
        CourseRelay_Runtime::log( "learndash_process_mark_complete: " . wp_json_encode( $result ) );
        if ( $result ) {
            self::purge_cache( $user_id, $post_id, $course_id );
            return true;
        }

        // Already complete?
        if ( get_user_meta( $user_id, 'completed_' . $post_id, true ) ) {
            CourseRelay_Runtime::log( "Already marked complete." );
            return true;
        }

        // Fallback: write user meta directly
        CourseRelay_Runtime::log( "Using direct fallback for post $post_id." );
        $courserelay_time = time();
        update_user_meta( $user_id, 'completed_' . $post_id, $courserelay_time );
        self::update_course_progress( $user_id, $post_id, $course_id, $post_type );

        // Update LearnDash activity table — drives the course page status display
        self::update_learndash_activity( $user_id, $post_id, $course_id, $post_type, $courserelay_time );

        // Fire LearnDash completion hooks so certificates/notifications work
        $courserelay_post_obj = get_post( $post_id );
        if ( $courserelay_post_obj ) {
            if ( $post_type === 'sfwd-lessons' ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing LearnDash's own hook, not defining a new one.
                do_action( 'learndash_lesson_completed', array(
                    'user'   => get_userdata( $user_id ),
                    'course' => get_post( $course_id ),
                    'lesson' => $courserelay_post_obj,
                ) );
            } elseif ( $post_type === 'sfwd-topic' ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing LearnDash's own hook, not defining a new one.
                do_action( 'learndash_topic_completed', array(
                    'user'   => get_userdata( $user_id ),
                    'course' => get_post( $course_id ),
                    'topic'  => $courserelay_post_obj,
                ) );
            }
        }

        // Retry LD mark complete — now that meta is set it may succeed and
        // properly update the course completion status
        if ( $course_id ) {
            $courserelay_retry = learndash_process_mark_complete( $user_id, $post_id, false, $course_id );
            CourseRelay_Runtime::log( "Retry after fallback: " . wp_json_encode( $courserelay_retry ) );
        }

        self::purge_cache( $user_id, $post_id, $course_id );
        return true;
    }

    /**
     * Purge page cache after completion so the student sees updated status
     * immediately without needing to log out and back in.
     *
     * Supports:
     *   - LiteSpeed Cache (do_action 'litespeed_purge_post')
     *   - WP Rocket (rocket_clean_post)
     *   - W3 Total Cache (w3tc_pgcache_flush_post)
     *   - WP Super Cache (wp_cache_post_change)
     *   - Cloudflare (cloudflare_purge_by_url via CF plugin)
     *   - Generic (clean_post_cache)
     */
    private static function purge_cache( $user_id, $post_id, $course_id ) {
        CourseRelay_Runtime::log( "purge_cache: post=$post_id course=$course_id user=$user_id" );

        // ── LiteSpeed Cache ───────────────────────────────────────────────────
        if ( class_exists( 'LiteSpeed_Cache_API' ) || defined( 'LSCWP_V' ) ) {
    // Purge the lesson/topic post
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing LiteSpeed Cache's own hook, not defining a new one.
    do_action( 'litespeed_purge_post', $post_id );
    // Purge the course page
    if ( $course_id ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing LiteSpeed Cache's own hook, not defining a new one.
        do_action( 'litespeed_purge_post', $course_id );
    }
    // Purge the user's private cache (ESI blocks etc.)
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing LiteSpeed Cache's own hook, not defining a new one.
    do_action( 'litespeed_purge_all_private' );
    CourseRelay_Runtime::log( "purge_cache: LiteSpeed purged." );
}

        // ── WP Rocket ─────────────────────────────────────────────────────────
        if ( function_exists( 'rocket_clean_post' ) ) {
            rocket_clean_post( $post_id );
            if ( $course_id ) rocket_clean_post( $course_id );
            CourseRelay_Runtime::log( "purge_cache: WP Rocket purged." );
        }

        // ── W3 Total Cache ────────────────────────────────────────────────────
        if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
            w3tc_pgcache_flush_post( $post_id );
            if ( $course_id ) w3tc_pgcache_flush_post( $course_id );
            CourseRelay_Runtime::log( "purge_cache: W3TC purged." );
        }

        // ── WP Super Cache ────────────────────────────────────────────────────
        if ( function_exists( 'wp_cache_post_change' ) ) {
            wp_cache_post_change( $post_id );
            if ( $course_id ) wp_cache_post_change( $course_id );
            CourseRelay_Runtime::log( "purge_cache: WP Super Cache purged." );
        }

        // ── WordPress object cache ────────────────────────────────────────────
        clean_post_cache( $post_id );
        if ( $course_id ) clean_post_cache( $course_id );

        // ── Allow third-party plugins to hook in ──────────────────────────────
        do_action( 'courserelay_after_completion', $user_id, $post_id, $course_id );
    }

    /**
     * Update LearnDash's wp_learndash_user_activity table.
     * This table drives the course progress display on the frontend (LD 3.4+).
     * Without it the course page shows "not started" even if user meta is set.
     */
    private static function update_learndash_activity( $user_id, $post_id, $course_id, $post_type, $time ) {
        // Use LD's own function if available (preferred — handles all edge cases)
        if ( function_exists( 'learndash_update_user_activity' ) ) {
            $courserelay_activity_type = 'lesson';
            if ( $post_type === 'sfwd-topic' ) $courserelay_activity_type = 'topic';
            if ( $post_type === 'sfwd-quiz' )  $courserelay_activity_type = 'quiz';

            learndash_update_user_activity( array(
                'user_id'            => $user_id,
                'post_id'            => $post_id,
                'course_id'          => $course_id,
                'activity_type'      => $courserelay_activity_type,
                'activity_status'    => true,
                'activity_started'   => $time,
                'activity_completed' => $time,
                'activity_updated'   => $time,
            ) );
            CourseRelay_Runtime::log( "learndash_update_user_activity: post=$post_id type=$courserelay_activity_type" );
            return;
        }

        // Fallback: write directly to activity table for older LD versions
        global $wpdb;
        $courserelay_table = $wpdb->prefix . 'learndash_user_activity';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $courserelay_table
            )
        );

        if ( $table_exists !== $courserelay_table ) {
            CourseRelay_Runtime::log( "learndash_user_activity table not found." );
            return;
        }

$table = esc_sql( $wpdb->prefix . 'learndash_user_activity' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$courserelay_existing = $wpdb->get_var(
    $wpdb->prepare(
        'SELECT activity_id FROM `' . esc_sql( $courserelay_table ) . '` WHERE user_id = %d AND post_id = %d AND course_id = %d LIMIT 1',
        $user_id,
        $post_id,
        $course_id
    )
);

        $courserelay_activity_type = 'lesson';
        if ( $post_type === 'sfwd-topic' ) $courserelay_activity_type = 'topic';
        if ( $post_type === 'sfwd-quiz' )  $courserelay_activity_type = 'quiz';

        if ( $courserelay_existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $courserelay_table,
                array( 'activity_status' => 1, 'activity_completed' => $time, 'activity_updated' => $time ),
                array( 'activity_id' => $courserelay_existing )
            );
            CourseRelay_Runtime::log( "Updated learndash_user_activity id=$courserelay_existing" );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert( $courserelay_table, array(
                'user_id'            => $user_id,
                'post_id'            => $post_id,
                'course_id'          => $course_id,
                'activity_type'      => $courserelay_activity_type,
                'activity_status'    => 1,
                'activity_started'   => $time,
                'activity_completed' => $time,
                'activity_updated'   => $time,
            ) );
            CourseRelay_Runtime::log( "Inserted learndash_user_activity post=$post_id type=$courserelay_activity_type" );
        }
    }

    /**
     * Update the LearnDash course progress array in user meta.
     */
    private static function update_course_progress( $user_id, $post_id, $course_id, $post_type ) {
        if ( empty( $course_id ) ) return;

        $progress = get_user_meta( $user_id, '_sfwd-course_progress', true );
        if ( ! is_array( $progress ) ) $progress = array();
        if ( empty( $progress[ $course_id ] ) ) {
            $progress[ $course_id ] = array( 'lessons' => array(), 'topics' => array() );
        }

        if ( $post_type === 'sfwd-topic' ) {
            $lesson_id = function_exists( 'learndash_course_get_single_parent_step' )
                ? learndash_course_get_single_parent_step( $course_id, $post_id ) : 0;
            if ( $lesson_id ) {
                $progress[ $course_id ]['topics'][ $lesson_id ][ $post_id ] = 1;
            }
        } else {
            $progress[ $course_id ]['lessons'][ $post_id ] = 1;
        }

        update_user_meta( $user_id, '_sfwd-course_progress', $progress );
        CourseRelay_Runtime::log( "Updated course progress: course=$course_id post=$post_id" );
    }
}
