<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CourseRelay WordPress privacy integration.
 *
 * CourseRelay stores SCORM learner/session data in custom tables and
 * registration IDs in user meta. These hooks make that data available to
 * the WordPress Privacy Tools and remove it when a user requests erasure.
 */
class CourseRelay_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'delete_user', array( __CLASS__, 'delete_user_data' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['courserelay'] = array(
			'exporter_friendly_name' => __( 'CourseRelay learner data', 'courserelay' ),
			'callback'               => array( __CLASS__, 'export_user_data' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['courserelay'] = array(
			'eraser_friendly_name' => __( 'CourseRelay learner data', 'courserelay' ),
			'callback'             => array( __CLASS__, 'erase_user_data' ),
		);
		return $erasers;
	}

	public static function export_user_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'courserelay_scorm_data';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT content_id, registration_id, var_key, var_value, updated_at
				FROM {$table}
				WHERE user_id = %d
				ORDER BY content_id, registration_id, var_key",
				$user->ID
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$export = array();
		foreach ( (array) $rows as $row ) {
			$content = get_post( (int) $row->content_id );
			$export[] = array(
				'name'  => sprintf(
					/* translators: 1: SCORM content title, 2: CMI key. */
					__( '%1$s — %2$s', 'courserelay' ),
					$content ? $content->post_title : __( 'SCORM content', 'courserelay' ),
					$row->var_key
				),
				'value' => sprintf(
					/* translators: 1: SCORM value, 2: update date. */
					__( '%1$s (updated %2$s)', 'courserelay' ),
					$row->var_value,
					$row->updated_at
				),
			);
		}

		$completion_table = $wpdb->prefix . 'courserelay_completions';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$completions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT content_id, status, score, time_spent, completed_at
				FROM {$completion_table}
				WHERE user_id = %d
				ORDER BY completed_at",
				$user->ID
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $completions as $row ) {
			$content = get_post( (int) $row->content_id );
			$export[] = array(
				'name'  => sprintf(
					/* translators: SCORM content title. */
					__( '%s — completion record', 'courserelay' ),
					$content ? $content->post_title : __( 'SCORM content', 'courserelay' )
				),
				'value' => wp_json_encode(
					array(
						'status'       => $row->status,
						'score'        => $row->score,
						'time_spent'   => $row->time_spent,
						'completed_at' => $row->completed_at,
					)
				),
			);
		}

		return array(
			'data' => $export ? array(
				array(
					'group_id'    => 'courserelay',
					'group_label' => __( 'CourseRelay', 'courserelay' ),
					'item_id'     => 'courserelay-user-' . $user->ID,
					'data'        => $export,
				),
			) : array(),
			'done' => true,
		);
	}

	public static function erase_user_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$removed = self::delete_user_data( $user->ID );

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public static function delete_user_data( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;
		$scorm_table      = $wpdb->prefix . 'courserelay_scorm_data';
		$completion_table = $wpdb->prefix . 'courserelay_completions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted_scorm = $wpdb->delete( $scorm_table, array( 'user_id' => $user_id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted_comp = $wpdb->delete( $completion_table, array( 'user_id' => $user_id ), array( '%d' ) );

		// Registration IDs are learner-specific user meta and must not survive erasure.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
				$user_id,
				'_courserelay_reg_%'
			)
		);

		foreach ( (array) $meta_keys as $meta_key ) {
			delete_user_meta( $user_id, $meta_key );
		}

		return (bool) ( $deleted_scorm || $deleted_comp || $meta_keys );
	}

	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__(
			'CourseRelay stores SCORM learner and session data in the site database to provide resume, progress, score, and completion tracking. This can include a WordPress user ID, learner name and email address supplied to the SCORM runtime, SCORM CMI values, registration IDs, scores, progress, and completion timestamps.',
			'courserelay'
		) . '</p>';

		$content .= '<p>' . esc_html__(
			'CourseRelay does not send learner data to an external LRS or third-party analytics service. Site administrators can use WordPress Tools → Export Personal Data and Erase Personal Data to export or erase CourseRelay learner data.',
			'courserelay'
		) . '</p>';

		wp_add_privacy_policy_content(
			'CourseRelay',
			wp_kses_post( $content )
		);
	}
}