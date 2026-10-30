<?php
/**
 * Server-side render for the SCORM Player block.
 *
 * @var array $attributes Block attributes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$courserelay_content_id = ! empty( $attributes['contentId'] ) ? absint( $attributes['contentId'] ) : 0;
if ( ! $courserelay_content_id ) {
	echo '<p style="color:#999;font-style:italic;">' . esc_html__( 'No SCORM content selected.', 'courserelay' ) . '</p>';
	return;
}

$courserelay_pkg  = CourseRelay_Package::get_package( $courserelay_content_id );
$courserelay_user = wp_get_current_user();
if ( ! $courserelay_pkg ) {
	echo '<p style="color:#c00;">' . esc_html__( 'SCORM content not found.', 'courserelay' ) . '</p>';
	return;
}

$courserelay_global     = CourseRelay_Settings::get();
$courserelay_width_raw  = ! empty( $attributes['width'] ) ? $attributes['width'] : ( ! empty( $courserelay_pkg->width ) ? $courserelay_pkg->width : $courserelay_global['width'] );
$courserelay_height_raw = ! empty( $attributes['height'] ) ? $attributes['height'] : ( ! empty( $courserelay_pkg->height ) ? $courserelay_pkg->height : $courserelay_global['height'] );
if ( is_numeric( $courserelay_height_raw ) ) {
	$courserelay_height_raw .= 'px';
}

$courserelay_current_post_id = get_the_ID();
$courserelay_display_mode = isset( $attributes['displayMode'] ) ? sanitize_key( $attributes['displayMode'] ) : 'embedded';
if ( ! in_array( $courserelay_display_mode, array( 'embedded', 'modal', 'new_tab', 'same_tab' ), true ) ) {
	$courserelay_display_mode = 'embedded';
}

// Keep a separate metadata row per package. A lesson may contain more than one
// SCORM block; update_post_meta() would overwrite the previous package link.
if ( $courserelay_current_post_id && in_array( get_post_type( $courserelay_current_post_id ), array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ), true ) ) {
	$courserelay_existing_ids = array_map( 'strval', (array) get_post_meta( $courserelay_current_post_id, '_courserelay_content_id', false ) );
	if ( ! in_array( (string) $courserelay_content_id, $courserelay_existing_ids, true ) ) {
		add_post_meta( $courserelay_current_post_id, '_courserelay_content_id', (string) $courserelay_content_id, false );
	}
	wp_cache_delete( 'courserelay_attached_' . $courserelay_content_id, 'courserelay' );
}

$courserelay_launch_url = add_query_arg(
	array(
		'action'       => 'courserelay_launch',
		'content_id'   => $courserelay_content_id,
		'post_id'      => $courserelay_current_post_id,
		// The popup uses the plain (header-less) launch page, same as embedded.
		'display_mode' => 'modal' === $courserelay_display_mode ? 'embedded' : $courserelay_display_mode,
		'_wpnonce'     => wp_create_nonce( 'courserelay_launch' ),
	),
	admin_url( 'admin-ajax.php' )
);
$courserelay_post_title = get_the_title( $courserelay_content_id );
$courserelay_attempt_status = __( 'Not started', 'courserelay' );
$courserelay_topic_status = __( 'Not started', 'courserelay' );
$courserelay_status_tone = 'neutral';
$courserelay_score = '';
$courserelay_can_resume = false;
$courserelay_last_activity = '';
if ( ! empty( $courserelay_user->ID ) ) {
    global $wpdb;
    $courserelay_registration = CourseRelay_Registration::get( $courserelay_content_id, $courserelay_user->ID );
    $courserelay_scorm_data = CourseRelay_DB::get_scorm_data( $courserelay_content_id, $courserelay_registration, null, '', $courserelay_user->ID );
    $courserelay_is_2004 = isset( $courserelay_pkg->version ) && 'scorm2004' === $courserelay_pkg->version;
    $courserelay_raw_status = $courserelay_is_2004
        ? ( $courserelay_scorm_data['cmi.completion_status'] ?? 'not attempted' )
        : ( $courserelay_scorm_data['cmi.core.lesson_status'] ?? 'not attempted' );
    $courserelay_success_status = $courserelay_scorm_data['cmi.success_status'] ?? '';
    $courserelay_raw_score = $courserelay_is_2004
        ? ( $courserelay_scorm_data['cmi.score.raw'] ?? '' )
        : ( $courserelay_scorm_data['cmi.core.score.raw'] ?? '' );
    $courserelay_suspend_data = $courserelay_scorm_data['cmi.suspend_data'] ?? '';
    $courserelay_location = $courserelay_is_2004
        ? ( $courserelay_scorm_data['cmi.location'] ?? '' )
        : ( $courserelay_scorm_data['cmi.core.lesson_location'] ?? '' );
    if ( 'passed' === $courserelay_raw_status || 'passed' === $courserelay_success_status ) {
        $courserelay_attempt_status = __( 'Passed', 'courserelay' );
        $courserelay_topic_status = __( 'Completed', 'courserelay' );
        $courserelay_status_tone = 'success';
    } elseif ( 'failed' === $courserelay_raw_status || 'failed' === $courserelay_success_status ) {
        $courserelay_attempt_status = __( 'Failed', 'courserelay' );
        $courserelay_topic_status = __( 'In progress', 'courserelay' );
        $courserelay_status_tone = 'info';
    } elseif ( 'completed' === $courserelay_raw_status ) {
        $courserelay_attempt_status = __( 'Completed', 'courserelay' );
        $courserelay_topic_status = __( 'Completed', 'courserelay' );
        $courserelay_status_tone = 'success';
    } elseif ( 'incomplete' === $courserelay_raw_status || 'browsed' === $courserelay_raw_status || 'unknown' === $courserelay_raw_status || ! empty( $courserelay_suspend_data ) || ! empty( $courserelay_location ) ) {
        $courserelay_attempt_status = ( ! empty( $courserelay_suspend_data ) || ! empty( $courserelay_location ) ) ? __( 'Paused - saved progress', 'courserelay' ) : __( 'Started - in progress', 'courserelay' );
        $courserelay_can_resume = true;
        $courserelay_topic_status = __( 'In progress', 'courserelay' );
        $courserelay_status_tone = 'info';
    }
    $courserelay_score = is_numeric( $courserelay_raw_score ) ? ( rtrim( rtrim( number_format( (float) $courserelay_raw_score, 2, '.', '' ), '0' ), '.' ) . '%' ) : '';
    $courserelay_last_activity = $wpdb->get_var( $wpdb->prepare(
        "SELECT MAX(updated_at) FROM {$wpdb->prefix}courserelay_scorm_data WHERE content_id = %d AND user_id = %d AND registration_id = %s",
        $courserelay_content_id, $courserelay_user->ID, $courserelay_registration
    ) );
}
?>
<div class="courserelay-player-wrap" data-content-id="<?php echo esc_attr( $courserelay_content_id ); ?>" data-post-id="<?php echo esc_attr( $courserelay_current_post_id ); ?>" style="width:<?php echo esc_attr( $courserelay_width_raw ); ?>;max-width:100%;">
	<?php if ( empty( $courserelay_user->ID ) ) : ?>
		<p style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
			<?php esc_html_e( 'Please log in to access this content.', 'courserelay' ); ?>
		</p>
	<?php elseif ( 'modal' === $courserelay_display_mode ) : ?>
		<section class="courserelay-module-card" aria-label="<?php esc_attr_e( 'SCORM module', 'courserelay' ); ?>">
			<div class="courserelay-module-card__header">
				<h3 class="courserelay-module-card__title"><?php echo esc_html( $courserelay_post_title ); ?></h3>
				<span class="courserelay-module-card__status courserelay-module-card__status--<?php echo esc_attr( $courserelay_status_tone ); ?>"><span aria-hidden="true"></span><?php echo esc_html( $courserelay_topic_status ); ?></span>
			</div>
            <?php if ( 'modal' !== $courserelay_display_mode && ( 'Not started' !== $courserelay_attempt_status || $courserelay_score || $courserelay_last_activity ) ) : ?>
                <div class="courserelay-module-card__meta-grid">
                    <div class="courserelay-module-card__meta-item"><span><?php esc_html_e( 'Attempt status', 'courserelay' ); ?></span><strong><?php echo esc_html( $courserelay_attempt_status ); ?></strong></div>
                    <?php if ( '' !== $courserelay_score ) : ?><div class="courserelay-module-card__meta-item"><span><?php esc_html_e( 'Score', 'courserelay' ); ?></span><strong><?php echo esc_html( $courserelay_score ); ?></strong></div><?php endif; ?>
                    <?php if ( $courserelay_last_activity ) : ?><div class="courserelay-module-card__meta-item"><span><?php esc_html_e( 'Last activity', 'courserelay' ); ?></span><strong><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $courserelay_last_activity ) ); ?></strong></div><?php endif; ?>
                </div>
            <?php endif; ?>
			<div class="courserelay-module-card__actions">
				<button type="button" class="courserelay-launch-btn" data-courserelay-modal data-courserelay-src="<?php echo esc_url( $courserelay_launch_url ); ?>" data-courserelay-title="<?php echo esc_attr( $courserelay_post_title ); ?>" data-courserelay-close="<?php echo esc_attr__( 'Close', 'courserelay' ); ?>" aria-haspopup="dialog"><?php esc_html_e( 'Launch module', 'courserelay' ); ?></button>
			</div>
		</section>
	<?php elseif ( 'new_tab' === $courserelay_display_mode || 'same_tab' === $courserelay_display_mode ) : ?>
		<section class="courserelay-module-card" aria-label="<?php esc_attr_e( 'SCORM module', 'courserelay' ); ?>">
			<div class="courserelay-module-card__header">
				<h3 class="courserelay-module-card__title"><?php echo esc_html( $courserelay_post_title ); ?></h3>
				<span class="courserelay-module-card__status courserelay-module-card__status--<?php echo esc_attr( $courserelay_status_tone ); ?>"><span aria-hidden="true"></span><?php echo esc_html( $courserelay_topic_status ); ?></span>
			</div>
            <?php if ( 'modal' !== $courserelay_display_mode && ( 'Not started' !== $courserelay_attempt_status || $courserelay_score || $courserelay_last_activity ) ) : ?>
                <div class="courserelay-module-card__meta-grid">
                    <div class="courserelay-module-card__meta-item"><span><?php esc_html_e( 'Attempt status', 'courserelay' ); ?></span><strong><?php echo esc_html( $courserelay_attempt_status ); ?></strong></div>
                    <?php if ( '' !== $courserelay_score ) : ?><div class="courserelay-module-card__meta-item"><span><?php esc_html_e( 'Score', 'courserelay' ); ?></span><strong><?php echo esc_html( $courserelay_score ); ?></strong></div><?php endif; ?>
                    <?php if ( $courserelay_last_activity ) : ?><div class="courserelay-module-card__meta-item"><span><?php esc_html_e( 'Last activity', 'courserelay' ); ?></span><strong><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $courserelay_last_activity ) ); ?></strong></div><?php endif; ?>
                </div>
            <?php endif; ?>
			<div class="courserelay-module-card__actions">
				<a class="courserelay-launch-btn" href="<?php echo esc_url( $courserelay_launch_url ); ?>" <?php echo 'new_tab' === $courserelay_display_mode ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $courserelay_can_resume ? __( 'Resume module', 'courserelay' ) : __( 'Launch module', 'courserelay' ) ); ?></a>
			</div>
		</section>
	<?php else : ?>
		<iframe src="<?php echo esc_url( $courserelay_launch_url ); ?>" width="<?php echo esc_attr( $courserelay_width_raw ); ?>" height="<?php echo esc_attr( $courserelay_height_raw ); ?>" style="border:none;display:block;max-width:100%;" title="<?php echo esc_attr( $courserelay_post_title ); ?>" allowfullscreen sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-presentation" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"></iframe>
	<?php endif; ?>
</div>
