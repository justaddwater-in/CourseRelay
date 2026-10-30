<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages SCORM registration IDs.
 * A registration ID uniquely identifies one learner's attempt at one piece of content.
 * We store it in user meta so resume works correctly.
 */
class CourseRelay_Registration {

	/**
	 * Get (or create) a registration ID for a user+content pair.
	 * Format: {uuid} stored in user meta.
	 */
	public static function get( $content_id, $user_id = null ) {
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		if ( empty( $user_id ) ) {
			return self::generate();
		}

		$meta_key = '_courserelay_reg_' . $content_id;
		$reg      = get_user_meta( $user_id, $meta_key, true );

		if ( empty( $reg ) ) {
			$reg = self::generate();
			update_user_meta( $user_id, $meta_key, $reg );
		}

		return $reg;
	}

	/**
	 * Reset registration for a user+content (force new attempt).
	 */
	public static function reset( $content_id, $user_id ) {
		$meta_key = '_courserelay_reg_' . $content_id;
		$new_reg  = self::generate();
		update_user_meta( $user_id, $meta_key, $new_reg );
		return $new_reg;
	}

	/**
	 * Generate a UUID v4.
	 */
	public static function generate() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}
}
