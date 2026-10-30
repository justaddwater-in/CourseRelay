<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SCORM Runtime
 *
 * Handles three AJAX endpoints:
 *   courserelay_launch  — outputs a full HTML page containing the SCORM JS API
 *                          and an iframe pointing to the content's launch file.
 *   courserelay_commit  — saves CMI data sent by the SCORM package.
 *   courserelay_finish  — updates total_time, detects completion, triggers LearnDash.
 *
 * Implements the standard SCORM 1.2 (LMSCommit/LMSFinish) and SCORM 2004
 * (Commit/Terminate) runtime lifecycle independently of any other plugin.
 */
class CourseRelay_Runtime {

	public static function init() {
		// Launch — outputs full HTML page, no wp_head/wp_footer
		add_action( 'wp_ajax_courserelay_launch', array( __CLASS__, 'handle_launch' ) );

		// Commit — save CMI data
		add_action( 'wp_ajax_courserelay_commit', array( __CLASS__, 'handle_commit' ) );

		// Finish — end session, trigger completion
		add_action( 'wp_ajax_courserelay_finish', array( __CLASS__, 'handle_finish' ) );
	}

	// ── CMI Key Maps (SCORM 1.2 vs 2004) ─────────────────────────────────────

	public static function cmi_keys( $version = '1.2' ) {
		$keys_12   = array(
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
		$content_id = isset( $_GET['content_id'] ) ? absint( $_GET['content_id'] ) : 0;
		$post_id    = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$requested_display_mode = isset( $_GET['display_mode'] ) ? sanitize_key( wp_unslash( $_GET['display_mode'] ) ) : 'embedded';
		$display_mode = in_array( $requested_display_mode, array( 'new_tab', 'same_tab' ), true ) ? $requested_display_mode : 'embedded';
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'courserelay_launch' ) ) {
			status_header( 403 );
			echo esc_html__( 'Invalid launch request.', 'courserelay' );
			exit;
		}

		if ( empty( $content_id ) ) {
			status_header( 400 );
			echo esc_html__( 'Missing content_id.', 'courserelay' );
			exit;
		}

		$pkg  = CourseRelay_Package::get_package( $content_id );
		$user = wp_get_current_user();

		if ( ! $pkg ) {
			status_header( 404 );
			echo esc_html__( 'Content not found.', 'courserelay' );
			exit;
		}

		if ( empty( $user->ID ) ) {
			status_header( 403 );
			echo esc_html__( 'Login required.', 'courserelay' );
			exit;
		}

		// ── ENROLLMENT / ACCESS VALIDATION ─────────────────────────────────────
		if ( ! self::user_can_access_content( $content_id, $user->ID, $post_id ) ) {
			self::log( "handle_launch: user={$user->ID} denied access to content_id=$content_id." );
			status_header( 403 );
			echo esc_html__( 'You do not have access to this content.', 'courserelay' );
			exit;
		}

		$registration  = CourseRelay_Registration::get( $content_id, $user->ID );
		$scorm_version = ( $pkg->version === 'scorm2004' ) ? '2004' : '1.2';
		$keys          = self::cmi_keys( $scorm_version );

		// Load existing CMI data for resume
		$scorm_data = CourseRelay_DB::get_scorm_data( $content_id, $registration, null, '', $user->ID );

		// Build initial CMI cache with SCORM-spec default values for a fresh attempt
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

		$post     = get_post( $content_id );
		$title    = $post ? $post->post_title : 'SCORM Content';
		$ajax_url = admin_url( 'admin-ajax.php' );
		$is_2004  = ( $scorm_version === '2004' );

		$GB_SCORM = array(
			'content_id'    => $content_id,
			'post_id'       => $post_id,
			'scorm_version' => $scorm_version,
			'content_name'  => $title,
			'ajax_url'      => $ajax_url,
			'cache'         => $scorm_data,
			'mastery_score' => $pkg->mastery_score,
			'activity_id'   => $pkg->activity_id ?: $pkg->content_url,
			'commit_checks' => array( $keys['cmi.suspend_data'], $keys['cmi.completion_status'], $keys['cmi.location'] ),
			'is_2004'       => $is_2004,
			// Runtime commit/finish endpoints can't use a form nonce field (they're
			// called by the SCORM JS API, not a submitted form), so the nonce is
			// handed to the JS runtime here and sent back with every AJAX call.
			'nonce'         => wp_create_nonce( 'courserelay_runtime' ),
		);

		$src = esc_url( $pkg->src );

		// ── Register/enqueue the runtime script through core WP APIs ───────────
		// This is a standalone page (no wp_head/wp_footer), so wp_enqueue_scripts
		// never fires here — we register, localize, and enqueue the handle
		// ourselves, then print it immediately with wp_print_scripts().
		$runtime_handle = $is_2004 ? 'courserelay-runtime-2004' : 'courserelay-runtime-12';
		$runtime_url    = $is_2004
			? COURSERELAY_URL . 'assets/js/scorm-runtime-2004.js'
			: COURSERELAY_URL . 'assets/js/scorm-runtime-12.js';

		wp_register_script( $runtime_handle, $runtime_url, array(), COURSERELAY_VERSION, false );
		wp_localize_script( $runtime_handle, 'COURSERELAY', $GB_SCORM );
		wp_add_inline_script( $runtime_handle, 'var cache = COURSERELAY.cache;', 'before' );
		wp_enqueue_script( $runtime_handle );

		wp_register_style( 'courserelay-launch-page', false, array(), COURSERELAY_VERSION );
		wp_add_inline_style(
			'courserelay-launch-page',
			'* { margin: 0; padding: 0; box-sizing: border-box; }
             html, body { width: 100%; min-height: 100%; }
             body { overflow: hidden; }
             body.courserelay-standalone { height: 100vh; height: 100dvh; display: flex; flex-direction: column; background: #1a1a1a; color: #eee; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
             .courserelay-player-header { min-height: 56px; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex: 0 0 auto; background: #111; border-bottom: 1px solid #333; color: #fff; font: 14px/1.4 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
             .courserelay-player-header strong { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
             .courserelay-player-header__status { flex: 0 0 auto; font-size: 12px; opacity: .82; }
             .courserelay-player-header a { display: inline-flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid #555; border-radius: 28px; padding: 6px 12px; font-size: 13px; white-space: nowrap; }
             .courserelay-player-header a:hover, .courserelay-player-header a:focus-visible { background: #333; color: #fff; }
             .courserelay-player-frame { width: 100%; height: 100vh; height: 100dvh; border: none; display: block; background: #fff; }
             body.courserelay-standalone .courserelay-player-frame { height: auto; min-height: 0; flex: 1 1 auto; }
             @media (max-width: 600px) { .courserelay-player-header { gap: 8px; padding: 8px 10px; } .courserelay-player-header__status { display: none; } .courserelay-player-header a { padding: 6px 9px; font-size: 12px; } }' 
		);
		wp_enqueue_style( 'courserelay-launch-page' );

		// Output a standalone HTML page — no WP head/footer
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $title ); ?></title>
		<?php wp_print_styles( 'courserelay-launch-page' ); ?>
</head>
<body class="<?php echo in_array( $display_mode, array( 'new_tab', 'same_tab' ), true ) ? 'courserelay-standalone' : 'courserelay-embedded'; ?>">
		<?php if ( in_array( $display_mode, array( 'new_tab', 'same_tab' ), true ) ) : ?>
<header class="courserelay-player-header">
	<strong><?php echo esc_html( $title ); ?></strong>
	<span class="courserelay-player-header__status" aria-label="SCORM player">SCORM Player</span>
	<a href="<?php echo esc_url( $post_id ? get_permalink( $post_id ) : home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Return to topic', 'courserelay' ); ?></a>
</header>
		<?php endif; ?>
		<?php wp_print_scripts( $runtime_handle ); ?>
		<?php if ( $is_2004 ) : ?>
<iframe
	class="courserelay-player-frame"
	title="<?php echo esc_attr( $title ); ?>"
	src="<?php echo esc_url( $src ); ?>"
	name="course"
	allowfullscreen
	sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-presentation"
	allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
	onbeforeunload="API_1484_11.Terminate('');"
	onunload="API_1484_11.Terminate('');"
></iframe>
		<?php else : ?>
<iframe
	class="courserelay-player-frame"
	title="<?php echo esc_attr( $title ); ?>"
	src="<?php echo esc_url( $src ); ?>"
	name="course"
	allowfullscreen
	sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-presentation"
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

		// SCORM runtime data is nested, so sanitize the full structure first and
		// apply the stricter per-key validation again before database writes.
		$params = isset( $_POST['params'] ) && is_array( $_POST['params'] )
			? map_deep( wp_unslash( $_POST['params'] ), 'sanitize_textarea_field' )
			: array();

		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'courserelay_runtime' ) ) {
			echo wp_json_encode( 'Error: invalid nonce' );
			exit;
		}

		$data          = isset( $params['data'] ) ? (array) $params['data'] : array();
		$scorm_version = isset( $params['scorm_version'] ) ? sanitize_text_field( $params['scorm_version'] ) : '1.2';
		$keys          = self::cmi_keys( $scorm_version );

		$content_id      = isset( $data[ $keys['cmi.content_id'] ] ) ? intval( $data[ $keys['cmi.content_id'] ] ) : 0;
		$registration_id = isset( $data[ $keys['cmi.registration_id'] ] ) ? sanitize_text_field( $data[ $keys['cmi.registration_id'] ] ) : '';

		if ( empty( $content_id ) || empty( $registration_id ) ) {
			echo wp_json_encode( 'Error: missing content_id or registration_id' );
			exit;
		}

		self::log( "handle_commit: user={$user->ID} content=$content_id reg=$registration_id version=$scorm_version" );

		// ── OWNERSHIP VALIDATION ───────────────────────────────────────────────
		$pkg = CourseRelay_Package::get_package( $content_id );
		if ( ! $pkg ) {
			self::log( "handle_commit: content_id=$content_id not found — aborting." );
			echo wp_json_encode( 'Error: content not found' );
			exit;
		}

		$post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
		if ( ! self::user_can_access_content( $content_id, $user->ID, $post_id ) ) {
			echo wp_json_encode( 'Error: access denied' );
			exit;
		}

		$expected_registration = CourseRelay_Registration::get( $content_id, $user->ID );
		self::log( "handle_commit: expected_reg=$expected_registration posted_reg=$registration_id match=" . ( hash_equals( $expected_registration, $registration_id ) ? 'yes' : 'NO' ) );
		if ( ! hash_equals( $expected_registration, $registration_id ) ) {
			self::log( 'handle_commit: registration mismatch — aborting.' );
			echo wp_json_encode( 'Error: invalid registration' );
			exit;
		}

		// ── EARLY EXIT IF ALREADY COMPLETED ───────────────────────────────────
		// Once a completion record exists for this registration, skip the
		// maybe_complete check entirely. This prevents the commit flood that
		// occurs when the SCORM content keeps firing SetValue after completion.
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already_done = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}courserelay_completions
             WHERE user_id = %d AND content_id = %d AND registration = %s LIMIT 1",
				$user->ID,
				$content_id,
				$registration_id
			)
		);

		if ( $already_done ) {
			// Keep saving runtime state and retry LearnDash completion. The SCORM
			// record may have been created before its lesson association was resolved.
			foreach ( $data as $var_key => $var_value ) {
				CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $var_key, $var_value, $user->ID );
			}
			self::maybe_complete( $content_id, $registration_id, $user->ID, $data, $keys, $scorm_version, $post_id );
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
				$exit   = isset( $data[ $exit_key ] ) ? strtolower( trim( $data[ $exit_key ] ) ) : '';
				if ( in_array( $status, array( 'completed', 'passed' ), true ) ) {
					CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $entry_key, '', $user->ID );
				} elseif ( $exit === 'suspend' ) {
					CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $entry_key, 'resume', $user->ID );
				}
			} else {
				CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $var_key, $var_value, $user->ID );
			}
		}

		// Check completion on every commit (handles packages that never call Finish)
		self::maybe_complete( $content_id, $registration_id, $user->ID, $data, $keys, $scorm_version, $post_id );

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

		// SCORM runtime data is nested, so sanitize the full structure first and
		// apply the stricter per-key validation again before database writes.
		$params = isset( $_POST['params'] ) && is_array( $_POST['params'] )
			? map_deep( wp_unslash( $_POST['params'] ), 'sanitize_textarea_field' )
			: array();

		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'courserelay_runtime' ) ) {
			echo wp_json_encode( 'Error: invalid nonce' );
			exit;
		}

		$data          = isset( $params['data'] ) ? (array) $params['data'] : array();
		$scorm_version = isset( $params['scorm_version'] ) ? sanitize_text_field( $params['scorm_version'] ) : '1.2';
		$keys          = self::cmi_keys( $scorm_version );

		$content_id      = isset( $data[ $keys['cmi.content_id'] ] ) ? intval( $data[ $keys['cmi.content_id'] ] ) : 0;
		$registration_id = isset( $data[ $keys['cmi.registration_id'] ] ) ? sanitize_text_field( $data[ $keys['cmi.registration_id'] ] ) : '';

		if ( empty( $content_id ) || empty( $registration_id ) ) {
			echo wp_json_encode( 'Finish Success' );
			exit;
		}


		// ── OWNERSHIP VALIDATION ───────────────────────────────────────────────
		$pkg = CourseRelay_Package::get_package( $content_id );
		if ( ! $pkg ) {
			echo wp_json_encode( 'Error: content not found' );
			exit;
		}

		$post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
		if ( ! self::user_can_access_content( $content_id, $user->ID, $post_id ) ) {
			echo wp_json_encode( 'Error: access denied' );
			exit;
		}

		$expected_registration = CourseRelay_Registration::get( $content_id, $user->ID );
		if ( ! hash_equals( $expected_registration, $registration_id ) ) {
			echo wp_json_encode( 'Finish Success' );
			exit;
		}

		// Persist the final in-memory CMI snapshot as well. A package may call
		// Finish/Terminate immediately after SetValue without a successful Commit.
		foreach ( $data as $var_key => $var_value ) {
			CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $var_key, $var_value, $user->ID );
		}

		$completion_key = $keys['cmi.completion_status'];
		$exit_key       = $keys['cmi.exit'];
		$entry_key      = $keys['cmi.entry'];
		$total_time_key = $keys['cmi.total_time'];
		$session_key    = $keys['cmi.session_time'];

		// Do not infer completion from LMSFinish/Terminate alone. Some packages call
		// it on unload even when the learner has not completed the activity. The
		// explicit SCORM status must be persisted by SetValue/Commit or included here.
		// Update total_time = old total_time + session_time
		$total_time   = CourseRelay_DB::get_scorm_data( $content_id, $registration_id, $total_time_key, '0000:00:00', $user->ID );
		$session_time = CourseRelay_DB::get_scorm_data( $content_id, $registration_id, $session_key, '', $user->ID );
		$new_total    = self::seconds_to_time( self::time_to_seconds( $total_time ) + self::time_to_seconds( $session_time ) );
		CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $total_time_key, $new_total, $user->ID );
		CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $session_key, '', $user->ID );

		// Update entry based on exit
		$exit = CourseRelay_DB::get_scorm_data( $content_id, $registration_id, $exit_key, '', $user->ID );
		CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $entry_key, '', $user->ID );
		$final_status = CourseRelay_DB::get_scorm_data( $content_id, $registration_id, $completion_key, '', $user->ID );
		if ( $exit === 'suspend' && ! in_array( strtolower( $final_status ), array( 'completed', 'passed' ), true ) ) {
			CourseRelay_DB::set_scorm_data( $content_id, $registration_id, $entry_key, 'resume', $user->ID );
		}

		// Try completion
		self::maybe_complete( $content_id, $registration_id, $user->ID, $data, $keys, $scorm_version, $post_id );

		echo wp_json_encode( 'Finish Success' );
		exit;
	}

	// ── Completion Detection ───────────────────────────────────────────────────

	/**
	 * Check if SCORM data indicates completion and trigger LearnDash if so.
	 * Called from both commit and finish.
	 */
	public static function maybe_complete( $content_id, $registration_id, $user_id, $data, $keys, $scorm_version, $source_post_id = 0 ) {
		self::log( "maybe_complete: content=$content_id reg=$registration_id user=$user_id" );

		$pkg = CourseRelay_Package::get_package( $content_id );
		if ( ! $pkg ) {
			self::log( 'maybe_complete: package not found — aborting.' );
			return;
		}
		if ( ! $pkg->completion_tracking ) {
			self::log( 'maybe_complete: completion_tracking disabled — aborting.' );
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
			: strtolower( trim( CourseRelay_DB::get_scorm_data( $content_id, $registration_id, $completion_key, '', $user_id ) ) );

		$success_status = '';
		if ( $scorm_version === '2004' && isset( $keys['cmi.success_status'] ) ) {
			$success_key    = $keys['cmi.success_status'];
			$success_status = isset( $data[ $success_key ] )
				? strtolower( trim( $data[ $success_key ] ) )
				: strtolower( trim( CourseRelay_DB::get_scorm_data( $content_id, $registration_id, $success_key, '', $user_id ) ) );
		}

		self::log( "maybe_complete: lesson_status=$lesson_status success_status=$success_status mastery={$pkg->mastery_score}" );

		$final_status = self::resolve_status( $lesson_status, $success_status, $pkg->mastery_score, $data, $score_key );
		self::log( "maybe_complete: final_status=$final_status" );

		if ( ! in_array( $final_status, array( 'passed', 'completed' ), true ) ) {
			self::log( 'maybe_complete: not a completion status — skipping.' );
			return;
		}

		$score = isset( $data[ $score_key ] ) && $data[ $score_key ] !== ''
			? floatval( $data[ $score_key ] )
			: null;

		// Check if already recorded for this registration
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}courserelay_completions
             WHERE user_id = %d AND content_id = %d AND registration = %s LIMIT 1",
				$user_id,
				$content_id,
				$registration_id
			)
		);
		if ( $already ) {
			// Do not return here. An earlier completion attempt may have saved the
			// SCORM record before the lesson association/cache was available. Resolve
			// the LearnDash step again so a recorded completion can still unlock it.
			self::log( "maybe_complete: record already exists (id=$already); retrying LearnDash association." );
		}

		// Find the correct attached LearnDash post for THIS user
		// Content can be used in multiple lessons/courses — pick the one
		// the user is actually enrolled in, not just the first one found.
		$attached = self::get_attached_posts( $content_id );
		if ( $source_post_id ) {
			usort(
				$attached,
				static function ( $a, $b ) use ( $source_post_id ) {
					$a_match = (int) $a->post_id === (int) $source_post_id;
					$b_match = (int) $b->post_id === (int) $source_post_id;
					return $a_match === $b_match ? 0 : ( $a_match ? -1 : 1 );
				}
			);
		}
		self::log( 'maybe_complete: attached posts = ' . wp_json_encode( $attached ) );

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
		CourseRelay_DB::store_completion( $content_id, $registration_id, $user_id, $post_id, $course_id, $final_status, $score );
		self::log( 'maybe_complete: completion record stored.' );

		// Mark the LearnDash step complete only after every completion-tracked
		// SCORM block attached to that step has completed. This avoids advancing
		// too early when a lesson contains multiple packages.
		if ( $post_id ) {
			$all_packages_complete = CourseRelay_LearnDash::are_post_scorm_contents_completed( $post_id, $user_id );
			self::log( "maybe_complete: step=$post_id all_packages_complete=" . ( $all_packages_complete ? 'yes' : 'no' ) );
			if ( $all_packages_complete ) {
				self::log( "maybe_complete: calling mark_complete user=$user_id post=$post_id course=$course_id" );
				CourseRelay_LearnDash::mark_complete( $user_id, $post_id, $course_id );
			}
		} else {
			self::log( "maybe_complete: post_id=0 — no LearnDash post found for content_id=$content_id. Is _courserelay_content_id meta set on the lesson?" );
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
	 * Verify that a logged-in user may access a SCORM package.
	 * Administrators may access all packages. For learner requests, the package
	 * must be attached to a published post the user can access. LearnDash course
	 * enrollment is checked when the attached post belongs to a LearnDash course.
	 *
	 * @param int $content_id SCORM package post ID.
	 * @param int $user_id    WordPress user ID.
	 * @param int $post_id    Optional post that initiated the launch.
	 * @return bool
	 */
	public static function user_can_access_content( $content_id, $user_id, $post_id = 0 ) {
		if ( empty( $content_id ) || empty( $user_id ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$attached = self::get_attached_posts( $content_id );
		if ( empty( $attached ) ) {
			return false;
		}

		foreach ( $attached as $attached_post ) {
			$candidate_post_id = (int) $attached_post->post_id;
			if ( $post_id && $candidate_post_id !== $post_id ) {
				continue;
			}

			$candidate_course = function_exists( 'learndash_get_course_id' )
				? (int) learndash_get_course_id( $candidate_post_id )
				: 0;

			if ( $candidate_course && function_exists( 'sfwd_lms_has_access' ) ) {
				if ( sfwd_lms_has_access( $candidate_course, $user_id ) ) {
					return true;
				}
				continue;
			}

			if ( user_can( $user_id, 'read_post', $candidate_post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get LearnDash posts (lessons/topics/quizzes) that embed a given content_id.
	 * Stored as post meta '_courserelay_content_id' on the lesson post.
	 */
	public static function get_attached_posts( $content_id ) {
		global $wpdb;
		$cache_key = 'courserelay_attached_' . $content_id;
		$result    = wp_cache_get( $cache_key, 'courserelay' );
		if ( false !== $result ) {
			return $result;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID AS post_id, p.post_type FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_courserelay_content_id'
               AND pm.meta_value = %s
               AND p.post_status = 'publish'",
				(string) $content_id
			)
		);
		$result = is_array($result) ? $result : array();
		$found_ids = array();
		foreach ( $result as $row ) { $found_ids[] = (int) $row->post_id; }

		// Dynamic blocks may not have rendered yet (for example on the first
		// frontend request, or when a page cache is being populated). Inspect the
		// saved block attributes as a reliable fallback for package association.
		$block_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID AS post_id, post_type, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('sfwd-lessons', 'sfwd-topic', 'sfwd-quiz')
               AND post_content LIKE %s",
				'%' . $wpdb->esc_like('courserelay/scorm-player') . '%'
			)
		);
		foreach ( (array) $block_posts as $block_post ) {
			if ( in_array( (int) $block_post->post_id, $found_ids, true ) ) { continue; }
			$blocks = function_exists('parse_blocks') ? parse_blocks( $block_post->post_content ) : array();
			if ( self::post_blocks_contain_content( $blocks, $content_id ) ) {
				$result[] = (object) array( 'post_id' => (int) $block_post->post_id, 'post_type' => $block_post->post_type );
				$found_ids[] = (int) $block_post->post_id;
			}
		}

		wp_cache_set( $cache_key, $result, 'courserelay', 30 );
		return $result;
	}

	/** Check nested Gutenberg blocks for a selected package ID. */
	private static function post_blocks_contain_content( $blocks, $content_id ) {
		foreach ( (array) $blocks as $block ) {
			if ( isset($block['blockName']) && 'courserelay/scorm-player' === $block['blockName']
				&& isset($block['attrs']['contentId']) && absint($block['attrs']['contentId']) === absint($content_id) ) {
				return true;
			}
			if ( ! empty($block['innerBlocks']) && self::post_blocks_contain_content($block['innerBlocks'], $content_id) ) {
				return true;
			}
		}
		return false;
	}

	// ── Time Helpers ───────────────────────────────────────────────────────────

	public static function time_to_seconds( $time_str ) {
		if ( empty( $time_str ) ) {
			return 0;
		}
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
		if ( isset( $parts[1] ) ) {
			$seconds += (int) $parts[1] * 60;
		}
		if ( isset( $parts[2] ) ) {
			$seconds += (int) $parts[2] * 3600;
		}
		return $seconds;
	}

	public static function seconds_to_time( $seconds ) {
		$h        = (int) ( $seconds / 3600 );
		$seconds -= $h * 3600;
		$m        = (int) ( $seconds / 60 );
		$s        = (int) ( $seconds - $m * 60 );
		return sprintf( '%04d:%02d:%02d', $h, $m, $s );
	}

	// ── Debug Logging ──────────────────────────────────────────────────────────

	public static function log( $msg ) {
		if ( ! CourseRelay_Settings::is_debug() ) {
			return;
		}

		// Debug logs are optional diagnostics, not an audit trail. Redact
		// learner identifiers and registration tokens before anything is written.
		$msg = (string) $msg;
		$msg = preg_replace( '/\\b(user(?:_id)?|uid)\\s*[=:]\\s*\\d+\\b/i', '$1=[redacted]', $msg );
		$msg = preg_replace( '/\\b(reg(?:istration)?(?:_id)?|registration)\\s*[=:]\\s*[^\\s,;]+/i', '$1=[redacted]', $msg );
		$msg = preg_replace( '/\\b[a-z0-9._%+\\-]+@[a-z0-9.\\-]+\\.[a-z]{2,}\\b/i', '[redacted-email]', $msg );

		$upload_dir = wp_upload_dir();

		$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'courserelay';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$log_file = trailingslashit( $log_dir ) . 'courserelay-debug.log';
		$htaccess = trailingslashit( $log_dir ) . '.htaccess';

		// Protect log from direct web access on first write
		if ( ! file_exists( $htaccess ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$htaccess,
				"<Files \"courserelay-debug.log\">\n    Require all denied\n    Deny from all\n</Files>\n"
			);
		}

		// ── Log rotation — cap at 1MB ─────────────────────────────────────────
		// When the log exceeds 1MB, keep only the last 200 lines so it never
		// grows unbounded even if debug mode is accidentally left on.
		if ( file_exists( $log_file ) && filesize( $log_file ) > 1048576 ) {
			$lines   = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			$trimmed = array_slice( $lines, -200 );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_file, implode( "\n", $trimmed ) . "\n" );
		}

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			'[CourseRelay ' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $msg . "\n",
			3,
			$log_file
		);
	}
}
