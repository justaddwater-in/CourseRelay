<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SCORM Runtime
 *
 * Handles three AJAX endpoints:
 *   scormpleted_launch  — outputs a full HTML page containing the SCORM JS API
 *                          and an iframe pointing to the content's launch file.
 *   scormpleted_commit  — saves CMI data sent by the SCORM package.
 *   scormpleted_finish  — updates total_time, detects completion, triggers LearnDash.
 *
 * Architecture mirrors GrassBlade's scorm/functions.php but is fully independent.
 */
class Scormpleted_Runtime {

    public static function init() {
        // Launch — outputs full HTML page, no wp_head/wp_footer
        add_action( 'wp_ajax_scormpleted_launch',        array( __CLASS__, 'handle_launch' ) );
        add_action( 'wp_ajax_nopriv_scormpleted_launch', array( __CLASS__, 'handle_launch' ) );

        // Commit — save CMI data
        add_action( 'wp_ajax_scormpleted_commit',        array( __CLASS__, 'handle_commit' ) );
        add_action( 'wp_ajax_nopriv_scormpleted_commit', array( __CLASS__, 'handle_commit' ) );

        // Finish — end session, trigger completion
        add_action( 'wp_ajax_scormpleted_finish',        array( __CLASS__, 'handle_finish' ) );
        add_action( 'wp_ajax_nopriv_scormpleted_finish', array( __CLASS__, 'handle_finish' ) );
    }

    // ── CMI Key Maps (SCORM 1.2 vs 2004) ─────────────────────────────────────

    public static function cmi_keys( $version = '1.2' ) {
        $keys_12 = array(
            'cmi.score.raw'            => 'cmi.core.score.raw',
            'cmi.location'             => 'cmi.core.lesson_location',
            'cmi.credit'               => 'cmi.core.credit',
            'cmi.completion_status'    => 'cmi.core.lesson_status',
            'cmi.entry'                => 'cmi.core.entry',
            'cmi.exit'                 => 'cmi.core.exit',
            'cmi.total_time'           => 'cmi.core.total_time',
            'cmi.session_time'         => 'cmi.core.session_time',
            'cmi.mode'                 => 'cmi.core.lesson_mode',
            'cmi.learner_id'           => 'cmi.core.student_id',
            'cmi.learner_name'         => 'cmi.core.student_name',
            'cmi.content_id'           => 'cmi.core.content_id',
            'cmi.registration_id'      => 'cmi.core.registration_id',
            'cmi.scaled_passing_score' => 'adlcp:masteryscore',
            'cmi.launch_data'          => 'cmi.launch_data',
            'cmi.suspend_data'         => 'cmi.suspend_data',
            'cmi.interactions._count'  => 'cmi.interactions._count',
        );
        $keys_2004 = array(
            'cmi.score.raw'            => 'cmi.score.raw',
            'cmi.location'             => 'cmi.location',
            'cmi.credit'               => 'cmi.credit',
            'cmi.completion_status'    => 'cmi.completion_status',
            'cmi.success_status'       => 'cmi.success_status',
            'cmi.entry'                => 'cmi.entry',
            'cmi.exit'                 => 'cmi.exit',
            'cmi.total_time'           => 'cmi.total_time',
            'cmi.session_time'         => 'cmi.session_time',
            'cmi.mode'                 => 'cmi.mode',
            'cmi.learner_id'           => 'cmi.learner_id',
            'cmi.learner_name'         => 'cmi.learner_name',
            'cmi.content_id'           => 'cmi.content_id',
            'cmi.registration_id'      => 'cmi.registration_id',
            'cmi.scaled_passing_score' => 'cmi.scaled_passing_score',
            'cmi.launch_data'          => 'cmi.launch_data',
            'cmi.suspend_data'         => 'cmi.suspend_data',
            'cmi.interactions._count'  => 'cmi.interactions._count',
            'cmi.objectives._count'    => 'cmi.objectives._count',
        );
        return ( $version === '2004' || $version === 'scorm2004' ) ? $keys_2004 : $keys_12;
    }

    // ── LAUNCH ─────────────────────────────────────────────────────────────────

    public static function handle_launch() {
        // SCORM launch is called by the Gutenberg block iframe src URL.
        // Nonce verification is not possible here because the URL is embedded
        // as an iframe src attribute — no form submission or JS nonce available.
        // Access is controlled by wp_get_current_user() check below.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $content_id = ! empty( $_REQUEST['content_id'] ) ? intval( $_REQUEST['content_id'] ) : 0;

        if ( empty( $content_id ) ) {
            status_header( 400 );
            echo 'Missing content_id';
            exit;
        }

        $pkg  = Scormpleted_Package::get_package( $content_id );
        $user = wp_get_current_user();

        if ( ! $pkg ) {
            status_header( 404 );
            echo 'Content not found';
            exit;
        }

        if ( empty( $user->ID ) ) {
            status_header( 403 );
            echo 'Login required';
            exit;
        }

        $registration  = Scormpleted_Registration::get( $content_id, $user->ID );
        $scorm_version = ( $pkg->version === 'scorm2004' ) ? '2004' : '1.2';
        $keys          = self::cmi_keys( $scorm_version );

        // Load existing CMI data for resume
        $scorm_data = Scormpleted_DB::get_scorm_data( $content_id, $registration, null, '', $user->ID );

        // Build initial CMI cache (mirrors GrassBlade's scorm_launch)
        if ( empty( $scorm_data ) ) {
            $scorm_data = array(
                $keys['cmi.score.raw']            => '',
                $keys['cmi.scaled_passing_score'] => $pkg->mastery_score,
                $keys['cmi.launch_data']          => '',
                $keys['cmi.suspend_data']         => '',
                $keys['cmi.location']             => '',
                $keys['cmi.credit']               => 'credit',
                $keys['cmi.completion_status']    => 'not attempted',
                $keys['cmi.entry']                => 'ab-initio',
                $keys['cmi.exit']                 => '',
                $keys['cmi.total_time']           => '0000:00:00',
                $keys['cmi.session_time']         => '',
                $keys['cmi.interactions._count']  => '0',
            );
        }

        $scorm_data[ $keys['cmi.mode'] ]            = 'normal';
        $scorm_data[ $keys['cmi.learner_id'] ]      = $user->user_email;
        $scorm_data[ $keys['cmi.learner_name'] ]    = $user->display_name;
        $scorm_data[ $keys['cmi.content_id'] ]      = $content_id;
        $scorm_data[ $keys['cmi.registration_id'] ] = $registration;

        $post       = get_post( $content_id );
        $title      = $post ? $post->post_title : 'SCORM Content';
        $ajax_url   = admin_url( 'admin-ajax.php' );
        $is_2004    = ( $scorm_version === '2004' );

        $GB_SCORM = array(
            'content_id'     => $content_id,
            'scorm_version'  => $scorm_version,
            'content_name'   => $title,
            'ajax_url'       => $ajax_url,
            'cache'          => $scorm_data,
            'mastery_score'  => $pkg->mastery_score,
            'activity_id'    => $pkg->activity_id ?: $pkg->content_url,
            'commit_checks'  => array( $keys['cmi.suspend_data'], $keys['cmi.completion_status'], $keys['cmi.location'] ),
            'is_2004'        => $is_2004,
        );

        $runtime_url_12   = SCORMPLETED_URL . 'assets/js/scorm-runtime-12.js?v=' . SCORMPLETED_VERSION;
        $runtime_url_2004 = SCORMPLETED_URL . 'assets/js/scorm-runtime-2004.js?v=' . SCORMPLETED_VERSION;
        $src              = esc_url( $pkg->src );

        // Output a standalone HTML page — no WP head/footer
        header( 'Content-Type: text/html; charset=utf-8' );
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $title ); ?></title>
<script>
var SCORMPLETED = <?php echo wp_json_encode( $GB_SCORM ); ?>;
var cache = SCORMPLETED.cache;
</script>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 100%; height: 100%; overflow: hidden; }
iframe { width: 100%; height: 100%; border: none; display: block; }
</style>
</head>
<body>
<?php if ( $is_2004 ) : ?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone SCORM launch page, not a WP template ?>
<script src="<?php echo esc_url( $runtime_url_2004 ); ?>"></script>
<iframe
    title="<?php echo esc_attr( $title ); ?>"
    src="<?php echo esc_url( $src ); ?>"
    name="course"
    allowfullscreen
    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
    onbeforeunload="API_1484_11.Terminate('');"
    onunload="API_1484_11.Terminate('');"
></iframe>
<?php else : ?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone SCORM launch page, not a WP template ?>
<script src="<?php echo esc_url( $runtime_url_12 ); ?>"></script>
<iframe
    title="<?php echo esc_attr( $title ); ?>"
    src="<?php echo esc_url( $src ); ?>"
    name="course"
    allowfullscreen
    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
    onbeforeunload="API.LMSFinish('');"
    onunload="API.LMSFinish('');"
></iframe>
<?php endif; ?>
</body>
</html>
<?php
        exit;
    }

    // ── COMMIT ─────────────────────────────────────────────────────────────────

    public static function handle_commit() {
        $user = wp_get_current_user();
        if ( empty( $user->ID ) ) {
            echo wp_json_encode( 'Guest User' );
            exit;
        }

        // SCORM commit is called directly by the SCORM JS runtime (LMSCommit/SetValue).
        // The JS runtime has no access to WP nonces. Security is provided by
        // wp_get_current_user() check above — only logged-in users can commit data.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
        $params        = isset( $_REQUEST['params'] ) ? wp_unslash( $_REQUEST['params'] ) : array();
        $data          = isset( $params['data'] )          ? (array) $params['data']          : array();
        $scorm_version = isset( $params['scorm_version'] ) ? sanitize_text_field( wp_unslash( $params['scorm_version'] ) ) : '1.2';
        $keys          = self::cmi_keys( $scorm_version );

        $content_id      = isset( $data[ $keys['cmi.content_id'] ] )     ? intval( $data[ $keys['cmi.content_id'] ] )                         : 0;
        $registration_id = isset( $data[ $keys['cmi.registration_id'] ] ) ? sanitize_text_field( $data[ $keys['cmi.registration_id'] ] )      : '';

        if ( empty( $content_id ) || empty( $registration_id ) ) {
            echo wp_json_encode( 'Error: missing content_id or registration_id' );
            exit;
        }

        self::log( "handle_commit: user={$user->ID} content=$content_id reg=$registration_id version=$scorm_version" );

        // ── OWNERSHIP VALIDATION ───────────────────────────────────────────────
        $pkg = Scormpleted_Package::get_package( $content_id );
        if ( ! $pkg ) {
            self::log( "handle_commit: content_id=$content_id not found — aborting." );
            echo wp_json_encode( 'Error: content not found' );
            exit;
        }

        $expected_registration = Scormpleted_Registration::get( $content_id, $user->ID );
        self::log( "handle_commit: expected_reg=$expected_registration posted_reg=$registration_id match=" . ( hash_equals( $expected_registration, $registration_id ) ? 'yes' : 'NO' ) );
        if ( ! hash_equals( $expected_registration, $registration_id ) ) {
            self::log( "handle_commit: registration mismatch — aborting." );
            echo wp_json_encode( 'Error: invalid registration' );
            exit;
        }

        // ── EARLY EXIT IF ALREADY COMPLETED ───────────────────────────────────
        // Once a completion record exists for this registration, skip the
        // maybe_complete check entirely. This prevents the commit flood that
        // occurs when the SCORM content keeps firing SetValue after completion.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $already_done = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}scormpleted_completions
             WHERE user_id = %d AND content_id = %d AND registration = %s LIMIT 1",
            $user->ID, $content_id, $registration_id
        ) );

        if ( $already_done ) {
            // Still save the CMI data but skip the completion check
            foreach ( $data as $var_key => $var_value ) {
                Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $var_key, $var_value, $user->ID );
            }
            echo wp_json_encode( 'Commit Success' );
            exit;
        }

        $completion_key = $keys['cmi.completion_status'];
        $exit_key       = $keys['cmi.exit'];
        $entry_key      = $keys['cmi.entry'];

        foreach ( $data as $var_key => $var_value ) {
            if ( $var_key === $entry_key ) {
                // entry: set to '' if completed/passed, 'resume' if suspended
                $status = isset( $data[ $completion_key ] ) ? strtolower( trim( $data[ $completion_key ] ) ) : '';
                $exit   = isset( $data[ $exit_key ] )       ? strtolower( trim( $data[ $exit_key ] ) )       : '';
                if ( in_array( $status, array( 'completed', 'passed' ), true ) ) {
                    Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $entry_key, '', $user->ID );
                } elseif ( $exit === 'suspend' ) {
                    Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $entry_key, 'resume', $user->ID );
                }
            } else {
                Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $var_key, $var_value, $user->ID );
            }
        }

        // Check completion on every commit (handles packages that never call Finish)
        self::maybe_complete( $content_id, $registration_id, $user->ID, $data, $keys, $scorm_version );

        echo wp_json_encode( 'Commit Success' );
        exit;
    }

    // ── FINISH ─────────────────────────────────────────────────────────────────

    public static function handle_finish() {
        $user = wp_get_current_user();
        if ( empty( $user->ID ) ) {
            echo wp_json_encode( 'Guest User' );
            exit;
        }

        // SCORM finish is called by the JS runtime (LMSFinish/Terminate) — no nonce possible.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $params        = isset( $_REQUEST['params'] ) ? wp_unslash( $_REQUEST['params'] ) : array();
        $data          = isset( $params['data'] )          ? (array) $params['data']          : array();
        $scorm_version = isset( $params['scorm_version'] ) ? sanitize_text_field( wp_unslash( $params['scorm_version'] ) ) : '1.2';
        $keys          = self::cmi_keys( $scorm_version );

        $content_id      = isset( $data[ $keys['cmi.content_id'] ] )      ? intval( $data[ $keys['cmi.content_id'] ] )                        : 0;
        $registration_id = isset( $data[ $keys['cmi.registration_id'] ] ) ? sanitize_text_field( $data[ $keys['cmi.registration_id'] ] )      : '';

        if ( empty( $content_id ) || empty( $registration_id ) ) {
            echo wp_json_encode( 'Finish Success' );
            exit;
        }

        // ── OWNERSHIP VALIDATION ───────────────────────────────────────────────
        $pkg = Scormpleted_Package::get_package( $content_id );
        if ( ! $pkg ) {
            echo wp_json_encode( 'Finish Success' );
            exit;
        }

        $expected_registration = Scormpleted_Registration::get( $content_id, $user->ID );
        if ( ! hash_equals( $expected_registration, $registration_id ) ) {
            echo wp_json_encode( 'Finish Success' );
            exit;
        }

        $completion_key = $keys['cmi.completion_status'];
        $exit_key       = $keys['cmi.exit'];
        $entry_key      = $keys['cmi.entry'];
        $total_time_key = $keys['cmi.total_time'];
        $session_key    = $keys['cmi.session_time'];

        // If lesson status is 'not attempted', upgrade to 'completed'
        $current_status = Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $completion_key, '', $user->ID );
        if ( $current_status === 'not attempted' ) {
            Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $completion_key, 'completed', $user->ID );
        }

        // Update total_time = old total_time + session_time
        $total_time   = Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $total_time_key, '0000:00:00', $user->ID );
        $session_time = Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $session_key, '', $user->ID );
        $new_total    = self::seconds_to_time( self::time_to_seconds( $total_time ) + self::time_to_seconds( $session_time ) );
        Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $total_time_key, $new_total, $user->ID );
        Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $session_key, '', $user->ID );

        // Update entry based on exit
        $exit = Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $exit_key, '', $user->ID );
        Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $entry_key, '', $user->ID );
        $final_status = Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $completion_key, '', $user->ID );
        if ( $exit === 'suspend' && ! in_array( strtolower( $final_status ), array( 'completed', 'passed' ), true ) ) {
            Scormpleted_DB::set_scorm_data( $content_id, $registration_id, $entry_key, 'resume', $user->ID );
        }

        // Try completion
        self::maybe_complete( $content_id, $registration_id, $user->ID, $data, $keys, $scorm_version );

        echo wp_json_encode( 'Finish Success' );
        exit;
    }

    // ── Completion Detection ───────────────────────────────────────────────────

    /**
     * Check if SCORM data indicates completion and trigger LearnDash if so.
     * Called from both commit and finish.
     */
    public static function maybe_complete( $content_id, $registration_id, $user_id, $data, $keys, $scorm_version ) {
        self::log( "maybe_complete: content=$content_id reg=$registration_id user=$user_id" );

        $pkg = Scormpleted_Package::get_package( $content_id );
        if ( ! $pkg ) {
            self::log( "maybe_complete: package not found — aborting." );
            return;
        }
        if ( ! $pkg->completion_tracking ) {
            self::log( "maybe_complete: completion_tracking disabled — aborting." );
            return;
        }

        // All completion types trigger the LearnDash mark-complete on SCORM completion.
        // The completion_type only controls what happens to the button UI — not whether
        // the backend completion record is written.

        // Read status from the request data first (most current), fall back to DB
        $completion_key = $keys['cmi.completion_status'];
        $score_key      = $keys['cmi.score.raw'];

        $lesson_status = isset( $data[ $completion_key ] )
            ? strtolower( trim( $data[ $completion_key ] ) )
            : strtolower( trim( Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $completion_key, '', $user_id ) ) );

        $success_status = '';
        if ( $scorm_version === '2004' && isset( $keys['cmi.success_status'] ) ) {
            $success_key    = $keys['cmi.success_status'];
            $success_status = isset( $data[ $success_key ] )
                ? strtolower( trim( $data[ $success_key ] ) )
                : strtolower( trim( Scormpleted_DB::get_scorm_data( $content_id, $registration_id, $success_key, '', $user_id ) ) );
        }

        self::log( "maybe_complete: lesson_status=$lesson_status success_status=$success_status mastery={$pkg->mastery_score}" );

        $final_status = self::resolve_status( $lesson_status, $success_status, $pkg->mastery_score, $data, $score_key );
        self::log( "maybe_complete: final_status=$final_status" );

        if ( ! in_array( $final_status, array( 'passed', 'completed' ), true ) ) {
            self::log( "maybe_complete: not a completion status — skipping." );
            return;
        }

        $score = isset( $data[ $score_key ] ) && $data[ $score_key ] !== ''
            ? floatval( $data[ $score_key ] )
            : null;

        // Check if already recorded for this registration
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $already = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}scormpleted_completions
             WHERE user_id = %d AND content_id = %d AND registration = %s LIMIT 1",
            $user_id, $content_id, $registration_id
        ) );
        if ( $already ) {
            self::log( "maybe_complete: already recorded (id=$already) — skipping." );
            return;
        }

        // Find the correct attached LearnDash post for THIS user
        // Content can be used in multiple lessons/courses — pick the one
        // the user is actually enrolled in, not just the first one found.
        $attached  = self::get_attached_posts( $content_id );
        self::log( "maybe_complete: attached posts = " . wp_json_encode( $attached ) );

        $post_id   = 0;
        $course_id = 0;

        foreach ( $attached as $attached_post ) {
            $candidate_post_id = (int) $attached_post->post_id;
            $candidate_course  = 0;

            if ( function_exists( 'learndash_get_course_id' ) ) {
                $candidate_course = (int) learndash_get_course_id( $candidate_post_id );
            }

            // Check if this user has access to this course
            $has_access = true;
            if ( $candidate_course && function_exists( 'sfwd_lms_has_access' ) ) {
                $has_access = sfwd_lms_has_access( $candidate_course, $user_id );
            }

            self::log( "maybe_complete: checking post=$candidate_post_id course=$candidate_course has_access=" . ( $has_access ? 'yes' : 'no' ) );

            if ( $has_access ) {
                $post_id   = $candidate_post_id;
                $course_id = $candidate_course;
                break; // Use first post the user has access to
            }
        }

        self::log( "maybe_complete: selected post_id=$post_id course_id=$course_id score=$score" );

        // Store completion record
        Scormpleted_DB::store_completion( $content_id, $registration_id, $user_id, $post_id, $course_id, $final_status, $score );
        self::log( "maybe_complete: completion record stored." );

        // Trigger LearnDash
        if ( $post_id ) {
            self::log( "maybe_complete: calling mark_complete user=$user_id post=$post_id course=$course_id" );
            Scormpleted_LearnDash::mark_complete( $user_id, $post_id, $course_id );
        } else {
            self::log( "maybe_complete: post_id=0 — no LearnDash post found for content_id=$content_id. Is _scormpleted_content_id meta set on the lesson?" );
        }

        self::log( "maybe_complete: done. user=$user_id content=$content_id status=$final_status score=$score post=$post_id" );
    }

    /**
     * Determine a final pass/complete/fail status from SCORM 1.2 and 2004 fields,
     * optionally applying mastery score logic.
     */
    private static function resolve_status( $lesson_status, $success_status, $mastery_score, $data, $score_key ) {
        // Direct pass/fail from lesson_status (SCORM 1.2)
        if ( in_array( $lesson_status, array( 'passed', 'completed', 'failed' ), true ) ) {
            // For 'completed', check mastery score
            if ( $lesson_status === 'completed' && $mastery_score > 0 ) {
                $score = isset( $data[ $score_key ] ) && $data[ $score_key ] !== '' ? floatval( $data[ $score_key ] ) : null;
                if ( ! is_null( $score ) ) {
                    return ( $score >= $mastery_score ) ? 'passed' : 'failed';
                }
            }
            return $lesson_status;
        }

        // SCORM 2004 success_status
        if ( in_array( $success_status, array( 'passed', 'failed' ), true ) ) {
            return $success_status;
        }

        return $lesson_status ?: 'incomplete';
    }

    /**
     * Get LearnDash posts (lessons/topics/quizzes) that embed a given content_id.
     * Stored as post meta '_scormpleted_content_id' on the lesson post.
     */
    public static function get_attached_posts( $content_id ) {
        global $wpdb;
        $cache_key = 'scormpleted_attached_' . $content_id;
        $result    = wp_cache_get( $cache_key, 'scormpleted' );
        if ( false !== $result ) return $result;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID AS post_id, p.post_type FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_scormpleted_content_id'
               AND pm.meta_value = %s
               AND p.post_status = 'publish'",
            (string) $content_id
        ) );

        wp_cache_set( $cache_key, $result, 'scormpleted', 300 );
        return $result ?: array();
    }

    // ── Time Helpers ───────────────────────────────────────────────────────────

    public static function time_to_seconds( $time_str ) {
        if ( empty( $time_str ) ) return 0;
        $time_str = trim( (string) $time_str );

        // ISO 8601 duration (SCORM 2004) e.g. PT1H30M
        if ( strpos( $time_str, 'P' ) === 0 ) {
            preg_match( '/PT?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?/', $time_str, $m );
            $h = isset( $m[1] ) ? (int) $m[1] : 0;
            $i = isset( $m[2] ) ? (int) $m[2] : 0;
            $s = isset( $m[3] ) ? (float) $m[3] : 0;
            return $h * 3600 + $i * 60 + $s;
        }

        // SCORM 1.2 format: HH:MM:SS or HHHH:MM:SS
        $parts   = array_reverse( explode( ':', $time_str ) );
        $seconds = (float) $parts[0];
        if ( isset( $parts[1] ) ) $seconds += (int) $parts[1] * 60;
        if ( isset( $parts[2] ) ) $seconds += (int) $parts[2] * 3600;
        return $seconds;
    }

    public static function seconds_to_time( $seconds ) {
        $h = (int) ( $seconds / 3600 );
        $seconds -= $h * 3600;
        $m = (int) ( $seconds / 60 );
        $s = (int) ( $seconds - $m * 60 );
        return sprintf( '%04d:%02d:%02d', $h, $m, $s );
    }

    // ── Debug Logging ──────────────────────────────────────────────────────────

    public static function log( $msg ) {
        if ( ! Scormpleted_Settings::is_debug() ) return;

        $log_file = WP_CONTENT_DIR . '/scormpleted-debug.log';
        $htaccess  = WP_CONTENT_DIR . '/scormpleted-debug-deny.htaccess';

        // Protect log from direct web access on first write
        if ( ! file_exists( $htaccess ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents(
                $htaccess,
                "<Files \"scormpleted-debug.log\">\n    Require all denied\n    Deny from all\n</Files>\n"
            );
        }

        // ── Log rotation — cap at 1MB ─────────────────────────────────────────
        // When the log exceeds 1MB, keep only the last 200 lines so it never
        // grows unbounded even if debug mode is accidentally left on.
        if ( file_exists( $log_file ) && filesize( $log_file ) > 1048576 ) {
            $lines    = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            $trimmed  = array_slice( $lines, -200 );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $log_file, implode( "\n", $trimmed ) . "\n" );
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(
            '[Scormpleted ' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $msg . "\n",
            3,
            $log_file
        );
    }
}
