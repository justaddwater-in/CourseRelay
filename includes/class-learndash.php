<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Scormpleted_LearnDash {

    // Completion type constants — mirror GrassBlade's values exactly
    const TYPE_HIDE_BUTTON           = 'hide_button';
    const TYPE_HIDDEN_UNTIL_COMPLETE = 'hidden_until_complete';
    const TYPE_DISABLE_UNTIL_COMPLETE= 'disable_until_complete';
    const TYPE_AUTO_REDIRECT         = 'completion_move_nextlevel';

    public static function init() {
        // CSS injection approach (mirrors GrassBlade exactly)
        // Runs at wp_print_scripts so inline <style> lands in <head>
        add_action( 'wp_print_scripts', array( __CLASS__, 'handle_completion_button' ) );

        // Also inject JS for auto-redirect and disable types
        add_action( 'wp_footer', array( __CLASS__, 'handle_completion_footer' ) );
    }

    // ── Button Handling ────────────────────────────────────────────────────────

    /**
     * Main handler — fires on wp_print_scripts.
     * Checks if current post has SCORM content and injects appropriate CSS/behaviour.
     * Mirrors GrassBlade's grassblade_learndash_hide_mark_complete_button().
     */
    public static function handle_completion_button() {
        global $post;
        if ( empty( $post->ID ) ) return;

        // Only act on LearnDash lesson/topic/quiz post types
        if ( ! in_array( $post->post_type, array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ), true ) ) return;

        $content_id = self::get_post_scorm_content( $post->ID );
        if ( empty( $content_id ) ) return;

        $pkg = Scormpleted_Package::get_package( $content_id );
        if ( ! $pkg || ! $pkg->completion_tracking ) return;

        $completion_type = $pkg->completion_type ?: Scormpleted_Settings::get( 'completion_type' );
        $is_completed    = self::is_content_completed( $content_id );

        Scormpleted_Runtime::log( "handle_completion_button: post={$post->ID} content=$content_id type=$completion_type completed=" . ( $is_completed ? 'yes' : 'no' ) );

        switch ( $completion_type ) {

            case self::TYPE_HIDE_BUTTON:
            case self::TYPE_HIDDEN_UNTIL_COMPLETE:
                // Hide button until SCORM completes, then show normally
                if ( ! $is_completed ) {
                    self::enable_next_link();
                    self::inject_hide_css();
                }
                break;

            case self::TYPE_DISABLE_UNTIL_COMPLETE:
                // Show button but disabled until SCORM completes
                if ( ! $is_completed ) {
                    self::enable_next_link();
                    self::inject_disable_css();
                }
                break;

            case self::TYPE_AUTO_REDIRECT:
                // Always hide the button — JS in footer handles redirect on completion
                self::enable_next_link();
                self::inject_hide_css();
                break;
        }
    }

    /**
     * Footer JS — handles auto-redirect and JS-side button show/enable on completion.
     * Polls for completion status and reacts accordingly.
     */
    public static function handle_completion_footer() {
        global $post;
        if ( empty( $post->ID ) ) return;
        if ( ! in_array( $post->post_type, array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ), true ) ) return;

        $content_id = self::get_post_scorm_content( $post->ID );
        if ( empty( $content_id ) ) return;

        $pkg = Scormpleted_Package::get_package( $content_id );
        if ( ! $pkg || ! $pkg->completion_tracking ) return;

        $completion_type = $pkg->completion_type ?: Scormpleted_Settings::get( 'completion_type' );

        // Data passed to JS
        $js_data = array(
            'completion_type' => $completion_type,
            'content_id'      => $content_id,
            'post_id'         => $post->ID,
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'scormpleted_check' ),
            'is_completed'    => self::is_content_completed( $content_id ),
            'next_url'        => self::get_next_step_url( $post->ID ),
            'types'           => array(
                'hide'     => self::TYPE_HIDE_BUTTON,
                'hidden'   => self::TYPE_HIDDEN_UNTIL_COMPLETE,
                'disable'  => self::TYPE_DISABLE_UNTIL_COMPLETE,
                'redirect' => self::TYPE_AUTO_REDIRECT,
            ),
        );
        ?>
        <script type="text/javascript">
        (function($) {
            var cfg = <?php echo wp_json_encode( $js_data ); ?>;
            var type = cfg.completion_type;

            // Check for completion after SCORM iframe posts a message
            // or after a poll interval
            window.addEventListener('message', function(e) {
                if (e.data && e.data.scormpleted_completed) {
                    onScormCompleted();
                }
            });

            // Poll every 3s in case postMessage isn't used
            var pollInterval = setInterval(function() {
                $.post(cfg.ajax_url, {
                    action: 'scormpleted_check_completion',
                    nonce:  cfg.nonce,
                    content_id: cfg.content_id,
                    post_id: cfg.post_id
                }, function(res) {
                    if (res.success && res.data.completed) {
                        clearInterval(pollInterval);
                        onScormCompleted();
                    }
                });
            }, 3000);

            // If already complete on load, act immediately
            if (cfg.is_completed) {
                onScormCompleted();
            }

            function onScormCompleted() {
                clearInterval(pollInterval);

                if (type === cfg.types.redirect) {
                    // Auto-redirect to next step
                    if (cfg.next_url) {
                        window.location.href = cfg.next_url;
                    }
                    return;
                }

                if (type === cfg.types.hide || type === cfg.types.hidden) {
                    // Show the Mark Complete button
                    $('#sfwd-mark-complete, .sfwd-mark-complete, .learndash_mark_complete_button').show();
                    return;
                }

                if (type === cfg.types.disable) {
                    // Enable the disabled button
                    $('#learndash_mark_complete_button, .learndash_mark_complete_button')
                        .prop('disabled', false)
                        .removeClass('scormpleted-btn-disabled')
                        .css({'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto'});
                    return;
                }
            }
        }(jQuery));
        </script>
        <?php
    }

    // ── CSS Injectors ──────────────────────────────────────────────────────────

    /**
     * Inject CSS to hide the Mark Complete button.
     * Uses the same selectors as GrassBlade to cover all LD theme variants.
     */
    private static function inject_hide_css() {
        ?>
        <style type="text/css">
            #sfwd-mark-complete,
            .learndash-wrapper form.sfwd-mark-complete,
            form.sfwd-mark-complete,
            .learndash-wrapper:not(.ld-registration__outer-wrapper) #sfwd-mark-complete,
            .learndash-wrapper:not(.ld-registration__outer-wrapper) .sfwd-mark-complete,
            .learndash-wrapper:not(.ld-registration__outer-wrapper):not(.learndash-wrapper--modern) .sfwd-mark-complete {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Inject CSS to show but disable the Mark Complete button.
     */
    private static function inject_disable_css() {
        ?>
        <style type="text/css">
            #learndash_mark_complete_button,
            .learndash_mark_complete_button {
                opacity: 0.5 !important;
                cursor: not-allowed !important;
                pointer-events: none !important;
            }
        </style>
        <?php
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
            "SELECT id FROM {$wpdb->prefix}scormpleted_completions
             WHERE user_id = %d AND content_id = %d LIMIT 1",
            $user_id, $content_id
        ) );

        return ! empty( $record );
    }

    /**
     * Get the SCORM content_id attached to a post.
     */
    public static function get_post_scorm_content( $post_id ) {
        return (int) get_post_meta( $post_id, '_scormpleted_content_id', true );
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
        check_ajax_referer( 'scormpleted_check', 'nonce' );
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
            Scormpleted_Runtime::log( "mark_complete: LearnDash not active." );
            return false;
        }

        $post_type = get_post_type( $post_id );
        Scormpleted_Runtime::log( "mark_complete: user=$user_id post=$post_id type=$post_type course=$course_id" );

        // Resolve course_id if not provided
        if ( empty( $course_id ) ) {
            $course_id = function_exists( 'learndash_get_course_id' )
                ? (int) learndash_get_course_id( $post_id )
                : (int) get_post_meta( $post_id, 'course_id', true );
        }

        // Complete child topics first (LearnDash blocks lesson if topics incomplete)
        if ( $post_type === 'sfwd-lessons' && ! empty( $course_id ) && function_exists( 'learndash_get_lesson_topics' ) ) {
            $topics = (array) learndash_get_lesson_topics( $post_id, false, $course_id );
            Scormpleted_Runtime::log( count( $topics ) . ' child topic(s) found.' );
            foreach ( $topics as $topic ) {
                $topic_id = is_object( $topic ) ? $topic->ID : $topic;
                if ( ! get_user_meta( $user_id, 'completed_' . $topic_id, true ) ) {
                    $r = learndash_process_mark_complete( $user_id, $topic_id, false, $course_id );
                    if ( ! $r ) update_user_meta( $user_id, 'completed_' . $topic_id, time() );
                    Scormpleted_Runtime::log( "  topic $topic_id: " . ( $r ? 'OK' : 'direct meta' ) );
                }
            }
        }

        // Mark the lesson/topic itself
        $result = learndash_process_mark_complete( $user_id, $post_id, false, $course_id ?: null );
        Scormpleted_Runtime::log( "learndash_process_mark_complete: " . wp_json_encode( $result ) );
        if ( $result ) {
            self::purge_cache( $user_id, $post_id, $course_id );
            return true;
        }

        // Already complete?
        if ( get_user_meta( $user_id, 'completed_' . $post_id, true ) ) {
            Scormpleted_Runtime::log( "Already marked complete." );
            return true;
        }

        // Fallback: write user meta directly
        Scormpleted_Runtime::log( "Using direct fallback for post $post_id." );
        $scormpleted_time = time();
        update_user_meta( $user_id, 'completed_' . $post_id, $scormpleted_time );
        self::update_course_progress( $user_id, $post_id, $course_id, $post_type );

        // Update LearnDash activity table — drives the course page status display
        self::update_learndash_activity( $user_id, $post_id, $course_id, $post_type, $scormpleted_time );

        // Fire LearnDash completion hooks so certificates/notifications work
        $scormpleted_post_obj = get_post( $post_id );
        if ( $scormpleted_post_obj ) {
            if ( $post_type === 'sfwd-lessons' ) {
                do_action( 'learndash_lesson_completed', array(
                    'user'   => get_userdata( $user_id ),
                    'course' => get_post( $course_id ),
                    'lesson' => $scormpleted_post_obj,
                ) );
            } elseif ( $post_type === 'sfwd-topic' ) {
                do_action( 'learndash_topic_completed', array(
                    'user'   => get_userdata( $user_id ),
                    'course' => get_post( $course_id ),
                    'topic'  => $scormpleted_post_obj,
                ) );
            }
        }

        // Retry LD mark complete — now that meta is set it may succeed and
        // properly update the course completion status
        if ( $course_id ) {
            $scormpleted_retry = learndash_process_mark_complete( $user_id, $post_id, false, $course_id );
            Scormpleted_Runtime::log( "Retry after fallback: " . wp_json_encode( $scormpleted_retry ) );
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
        Scormpleted_Runtime::log( "purge_cache: post=$post_id course=$course_id user=$user_id" );

        // ── LiteSpeed Cache ───────────────────────────────────────────────────
        if ( class_exists( 'LiteSpeed_Cache_API' ) || defined( 'LSCWP_V' ) ) {
            // Purge the lesson/topic post
            do_action( 'litespeed_purge_post', $post_id );
            // Purge the course page
            if ( $course_id ) {
                do_action( 'litespeed_purge_post', $course_id );
            }
            // Purge the user's private cache (ESI blocks etc.)
            do_action( 'litespeed_purge_all_private' );
            Scormpleted_Runtime::log( "purge_cache: LiteSpeed purged." );
        }

        // ── WP Rocket ─────────────────────────────────────────────────────────
        if ( function_exists( 'rocket_clean_post' ) ) {
            rocket_clean_post( $post_id );
            if ( $course_id ) rocket_clean_post( $course_id );
            Scormpleted_Runtime::log( "purge_cache: WP Rocket purged." );
        }

        // ── W3 Total Cache ────────────────────────────────────────────────────
        if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
            w3tc_pgcache_flush_post( $post_id );
            if ( $course_id ) w3tc_pgcache_flush_post( $course_id );
            Scormpleted_Runtime::log( "purge_cache: W3TC purged." );
        }

        // ── WP Super Cache ────────────────────────────────────────────────────
        if ( function_exists( 'wp_cache_post_change' ) ) {
            wp_cache_post_change( $post_id );
            if ( $course_id ) wp_cache_post_change( $course_id );
            Scormpleted_Runtime::log( "purge_cache: WP Super Cache purged." );
        }

        // ── WordPress object cache ────────────────────────────────────────────
        clean_post_cache( $post_id );
        if ( $course_id ) clean_post_cache( $course_id );

        // ── Allow third-party plugins to hook in ──────────────────────────────
        do_action( 'scormpleted_after_completion', $user_id, $post_id, $course_id );
    }

    /**
     * Update LearnDash's wp_learndash_user_activity table.
     * This table drives the course progress display on the frontend (LD 3.4+).
     * Without it the course page shows "not started" even if user meta is set.
     */
    private static function update_learndash_activity( $user_id, $post_id, $course_id, $post_type, $time ) {
        // Use LD's own function if available (preferred — handles all edge cases)
        if ( function_exists( 'learndash_update_user_activity' ) ) {
            $scormpleted_activity_type = 'lesson';
            if ( $post_type === 'sfwd-topic' ) $scormpleted_activity_type = 'topic';
            if ( $post_type === 'sfwd-quiz' )  $scormpleted_activity_type = 'quiz';

            learndash_update_user_activity( array(
                'user_id'            => $user_id,
                'post_id'            => $post_id,
                'course_id'          => $course_id,
                'activity_type'      => $scormpleted_activity_type,
                'activity_status'    => true,
                'activity_started'   => $time,
                'activity_completed' => $time,
                'activity_updated'   => $time,
            ) );
            Scormpleted_Runtime::log( "learndash_update_user_activity: post=$post_id type=$scormpleted_activity_type" );
            return;
        }

        // Fallback: write directly to activity table for older LD versions
        global $wpdb;
        $scormpleted_table = $wpdb->prefix . 'learndash_user_activity';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$scormpleted_table}'" ) !== $scormpleted_table ) {
            Scormpleted_Runtime::log( "learndash_user_activity table not found." );
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $scormpleted_existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT activity_id FROM `{$scormpleted_table}` WHERE user_id = %d AND post_id = %d AND course_id = %d LIMIT 1",
            $user_id, $post_id, $course_id
        ) );

        $scormpleted_activity_type = 'lesson';
        if ( $post_type === 'sfwd-topic' ) $scormpleted_activity_type = 'topic';
        if ( $post_type === 'sfwd-quiz' )  $scormpleted_activity_type = 'quiz';

        if ( $scormpleted_existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $scormpleted_table,
                array( 'activity_status' => 1, 'activity_completed' => $time, 'activity_updated' => $time ),
                array( 'activity_id' => $scormpleted_existing )
            );
            Scormpleted_Runtime::log( "Updated learndash_user_activity id=$scormpleted_existing" );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert( $scormpleted_table, array(
                'user_id'            => $user_id,
                'post_id'            => $post_id,
                'course_id'          => $course_id,
                'activity_type'      => $scormpleted_activity_type,
                'activity_status'    => 1,
                'activity_started'   => $time,
                'activity_completed' => $time,
                'activity_updated'   => $time,
            ) );
            Scormpleted_Runtime::log( "Inserted learndash_user_activity post=$post_id type=$scormpleted_activity_type" );
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
        Scormpleted_Runtime::log( "Updated course progress: course=$course_id post=$post_id" );
    }
}
