<?php
/**
 * Server-side render for the SCORM Player block.
 *
 * @var array $attributes Block attributes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$courserelay_content_id = ! empty( $attributes['contentId'] ) ? intval( $attributes['contentId'] ) : 0;
if ( empty( $courserelay_content_id ) ) {
    echo '<p style="color:#999;font-style:italic;">' . esc_html__( 'No SCORM content selected.', 'courserelay' ) . '</p>';
    return;
}

$courserelay_pkg  = CourseRelay_Package::get_package( $courserelay_content_id );
$courserelay_user = wp_get_current_user();

if ( ! $courserelay_pkg ) {
    echo '<p style="color:#c00;">' . esc_html__( 'SCORM content not found.', 'courserelay' ) . '</p>';
    return;
}

// Determine dimensions — block attributes override package, which overrides global
$courserelay_global = CourseRelay_Settings::get();
$courserelay_width  = ! empty( $attributes['width'] )
    ? esc_attr( $attributes['width'] )
    : ( ! empty( $courserelay_pkg->width ) ? esc_attr( $courserelay_pkg->width ) : esc_attr( $courserelay_global['width'] ) );
$courserelay_height = ! empty( $attributes['height'] )
    ? esc_attr( $attributes['height'] )
    : ( ! empty( $courserelay_pkg->height ) ? esc_attr( $courserelay_pkg->height ) : esc_attr( $courserelay_global['height'] ) );

// Ensure height has a unit
if ( is_numeric( $courserelay_height ) ) {
    $courserelay_height .= 'px';
}

// Build launch URL
$courserelay_launch_url = add_query_arg( array(
    'action'     => 'courserelay_launch',
    'content_id' => $courserelay_content_id,
    '_t'         => time(),
), admin_url( 'admin-ajax.php' ) );

// Store content association on the current post (for completion lookup)
$courserelay_current_post_id = get_the_ID();
if ( $courserelay_current_post_id ) {
    update_post_meta( $courserelay_current_post_id, '_courserelay_content_id', (string) $courserelay_content_id );
}

$courserelay_post_title = get_the_title( $courserelay_content_id );
?>
<div class="courserelay-player-wrap"
    data-content-id="<?php echo esc_attr( $courserelay_content_id ); ?>"
    data-post-id="<?php echo esc_attr( $courserelay_current_post_id ); ?>"
    style="width:<?php echo esc_attr( $courserelay_width ); ?>; max-width:100%;">
    <?php if ( empty( $courserelay_user->ID ) ) : ?>
        <p style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
            <?php esc_html_e( 'Please log in to access this content.', 'courserelay' ); ?>
        </p>
    <?php else : ?>
    <iframe
        src="<?php echo esc_url( $courserelay_launch_url ); ?>"
        width="<?php echo esc_attr( $courserelay_width ); ?>"
        height="<?php echo esc_attr( $courserelay_height ); ?>"
        style="border:none;display:block;max-width:100%;"
        title="<?php echo esc_attr( $courserelay_post_title ); ?>"
        allowfullscreen
        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
    ></iframe>
    <?php endif; ?>
</div>
