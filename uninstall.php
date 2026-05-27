<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop custom tables
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scormpleted_packages" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scormpleted_scorm_data" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scormpleted_completions" );

// Delete options
delete_option( 'scormpleted_settings' );
delete_option( 'scormpleted_db_version' );

// Remove uploaded content folder using WP_Filesystem
$scormpleted_upload_dir  = wp_upload_dir();
$scormpleted_content_dir = trailingslashit( $scormpleted_upload_dir['basedir'] ) . 'scormpleted';

if ( is_dir( $scormpleted_content_dir ) ) {
    scormpleted_uninstall_rmdir( $scormpleted_content_dir );
}

/**
 * Recursively remove a directory using WP functions.
 *
 * @param string $scormpleted_dir Directory path to remove.
 */
function scormpleted_uninstall_rmdir( $scormpleted_dir ) {
    if ( ! is_dir( $scormpleted_dir ) ) return;

    $scormpleted_items = scandir( $scormpleted_dir );
    foreach ( $scormpleted_items as $scormpleted_item ) {
        if ( '.' === $scormpleted_item || '..' === $scormpleted_item ) continue;
        $scormpleted_path = $scormpleted_dir . DIRECTORY_SEPARATOR . $scormpleted_item;
        if ( is_dir( $scormpleted_path ) ) {
            scormpleted_uninstall_rmdir( $scormpleted_path );
        } else {
            wp_delete_file( $scormpleted_path );
        }
    }
    // WP_Filesystem doesn't have rmdir; use WP_Filesystem::rmdir if available,
    // otherwise fall back to native rmdir (only after emptying — safe for uninstall context).
    global $wp_filesystem;
    if ( ! empty( $wp_filesystem ) ) {
        $wp_filesystem->rmdir( $scormpleted_dir );
    } else {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        rmdir( $scormpleted_dir );
    }
}
