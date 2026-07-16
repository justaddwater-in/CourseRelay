<?php
/**
 * Plugin Name: CourseRelay
 * Plugin URI:  https://justaddwater.in/courserelay
 * Description: Complete SCORM player and LMS integration for LearnDash. Upload SCORM packages, embed them in lessons, and track completion — no external LRS required.
 * Version:     1.0.0
 * Author:      Justaddwater
 * Author URI:  https://justaddwater.in
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: courserelay
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'COURSERELAY_VERSION',  '1.0.0' );
define( 'COURSERELAY_FILE',     __FILE__ );
define( 'COURSERELAY_DIR',      plugin_dir_path( __FILE__ ) );
define( 'COURSERELAY_URL',      plugin_dir_url( __FILE__ ) );
define( 'COURSERELAY_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoload includes ──────────────────────────────────────────────────────────
require_once COURSERELAY_DIR . 'includes/class-db.php';
require_once COURSERELAY_DIR . 'includes/class-scorm-package.php';
require_once COURSERELAY_DIR . 'includes/class-scorm-runtime.php';
require_once COURSERELAY_DIR . 'includes/class-settings.php';
require_once COURSERELAY_DIR . 'includes/class-learndash.php';
require_once COURSERELAY_DIR . 'includes/class-registration.php';
require_once COURSERELAY_DIR . 'admin/class-admin.php';
require_once COURSERELAY_DIR . 'blocks/scorm-player/block-class.php';

// ── Boot ───────────────────────────────────────────────────────────────────────
function courserelay_init() {
    CourseRelay_DB::init();
    CourseRelay_Settings::init();
    CourseRelay_Package::init();
    CourseRelay_Runtime::init();
    CourseRelay_LearnDash::init();
    CourseRelay_Block::init();

    // LearnDash completion poll AJAX
    add_action( 'wp_ajax_courserelay_check_completion', array( 'CourseRelay_LearnDash', 'ajax_check_completion' ) );

    if ( is_admin() ) {
        CourseRelay_Admin::init();
    }
}
add_action( 'plugins_loaded', 'courserelay_init' );

// ── Activation / Deactivation ──────────────────────────────────────────────────
register_activation_hook( __FILE__, 'courserelay_activate' );
function courserelay_activate() {
    CourseRelay_DB::install();

    // Protect the SCORM uploads directory from PHP execution
    $upload_dir  = wp_upload_dir();
    $scorm_dir   = trailingslashit( $upload_dir['basedir'] ) . 'courserelay';
    $htaccess    = $scorm_dir . '/.htaccess';
    if ( ! is_dir( $scorm_dir ) ) {
        wp_mkdir_p( $scorm_dir );
    }
    if ( ! file_exists( $htaccess ) ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents(
            $htaccess,
            "# CourseRelay: prevent PHP execution in SCORM content directory\n" .
            "<FilesMatch \"\\.ph(p[2-9]?|tml)$\">\n" .
            "    Require all denied\n" .
            "    Deny from all\n" .
            "</FilesMatch>\n"
        );
    }

    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'courserelay_deactivate' );
function courserelay_deactivate() {
    flush_rewrite_rules();
}

// ── Block registration (needs to be at init, not plugins_loaded) ───────────────
add_action( 'init', array( 'CourseRelay_Block', 'register' ) );
