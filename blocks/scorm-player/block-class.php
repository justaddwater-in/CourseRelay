<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CourseRelay_Block {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    public static function register() {
        if ( ! function_exists( 'register_block_type' ) ) return;

        register_block_type(
            COURSERELAY_DIR . 'blocks/scorm-player/block.json',
            array(
                'render_callback' => array( __CLASS__, 'render_block' ),
            )
        );

        // Enqueue editor script
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
    }

    public static function render_block( $attributes ) {
        ob_start();
        include COURSERELAY_DIR . 'blocks/scorm-player/render.php';
        return ob_get_clean();
    }

    public static function enqueue_editor_assets() {
        wp_enqueue_script(
            'courserelay-block-editor',
            COURSERELAY_URL . 'blocks/scorm-player/index.js',
            array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch' ),
            COURSERELAY_VERSION,
            true
        );
    }

    // ── REST API ───────────────────────────────────────────────────────────────

    public static function register_rest_routes() {
        register_rest_route( 'courserelay/v1', '/packages', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_packages' ),
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
        ) );
    }

    public static function rest_get_packages( $request ) {
        $per_page = (int) $request->get_param( 'per_page' ) ?: 100;
        $pkgs     = CourseRelay_Package::get_all_packages( array( 'per_page' => $per_page ) );

        $data = array();
        foreach ( (array) $pkgs as $pkg ) {
            $data[] = array(
                'id'            => (int) $pkg->post_id,
                'title'         => $pkg->post_title,
                'version'       => $pkg->version,
                'mastery_score' => (float) $pkg->mastery_score,
            );
        }

        return rest_ensure_response( $data );
    }
}
