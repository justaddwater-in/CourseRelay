<?php
/**
 * Server-side render for the SCORM Player block.
 *
 * @var array $attributes Block attributes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$scormpleted_content_id = ! empty( $attributes['contentId'] ) ? intval( $attributes['contentId'] ) : 0;
if ( empty( $scormpleted_content_id ) ) {
    echo '<p style="color:#999;font-style:italic;">' . esc_html__( 'No SCORM content selected.', 'scormpleted' ) . '</p>';
    return;
}

$scormpleted_pkg  = Scormpleted_Package::get_package( $scormpleted_content_id );
$scormpleted_user = wp_get_current_user();

if ( ! $scormpleted_pkg ) {
    echo '<p style="color:#c00;">' . esc_html__( 'SCORM content not found.', 'scormpleted' ) . '</p>';
    return;
}

// Determine dimensions — block attributes override package, which overrides global
$scormpleted_global = Scormpleted_Settings::get();
$scormpleted_width  = ! empty( $attributes['width'] )
    ? esc_attr( $attributes['width'] )
    : ( ! empty( $scormpleted_pkg->width ) ? esc_attr( $scormpleted_pkg->width ) : esc_attr( $scormpleted_global['width'] ) );
$scormpleted_height = ! empty( $attributes['height'] )
    ? esc_attr( $attributes['height'] )
    : ( ! empty( $scormpleted_pkg->height ) ? esc_attr( $scormpleted_pkg->height ) : esc_attr( $scormpleted_global['height'] ) );

// Ensure height has a unit
if ( is_numeric( $scormpleted_height ) ) {
    $scormpleted_height .= 'px';
}

// Build launch URL
$scormpleted_launch_url = add_query_arg( array(
    'action'     => 'scormpleted_launch',
    'content_id' => $scormpleted_content_id,
    '_t'         => time(),
), admin_url( 'admin-ajax.php' ) );

// Store content association on the current post (for completion lookup)
$scormpleted_current_post_id = get_the_ID();
if ( $scormpleted_current_post_id ) {
    update_post_meta( $scormpleted_current_post_id, '_scormpleted_content_id', (string) $scormpleted_content_id );
}

$scormpleted_post_title = get_the_title( $scormpleted_content_id );
?>
<div class="scormpleted-player-wrap"
    data-content-id="<?php echo esc_attr( $scormpleted_content_id ); ?>"
    data-post-id="<?php echo esc_attr( $scormpleted_current_post_id ); ?>"
    style="width:<?php echo esc_attr( $scormpleted_width ); ?>; max-width:100%;">
    <?php if ( empty( $scormpleted_user->ID ) ) : ?>
        <p style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
            <?php esc_html_e( 'Please log in to access this content.', 'scormpleted' ); ?>
        </p>
    <?php else : ?>
    <iframe
        src="<?php echo esc_url( $scormpleted_launch_url ); ?>"
        width="<?php echo esc_attr( $scormpleted_width ); ?>"
        height="<?php echo esc_attr( $scormpleted_height ); ?>"
        style="border:none;display:block;max-width:100%;"
        title="<?php echo esc_attr( $scormpleted_post_title ); ?>"
        allowfullscreen
        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
    ></iframe>
    <?php endif; ?>
</div>
