<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop custom tables
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}courserelay_packages" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}courserelay_scorm_data" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}courserelay_completions" );

// Delete options
delete_option( 'courserelay_settings' );
delete_option( 'courserelay_db_version' );

// Remove uploaded content folder using WP_Filesystem
$courserelay_upload_dir  = wp_upload_dir();
$courserelay_content_dir = trailingslashit( $courserelay_upload_dir['basedir'] ) . 'courserelay';

if ( is_dir( $courserelay_content_dir ) ) {
    courserelay_uninstall_rmdir( $courserelay_content_dir );
}

/**
 * Recursively remove a directory using WP functions.
 *
 * @param string $courserelay_dir Directory path to remove.
 */
function courserelay_uninstall_rmdir( $courserelay_dir ) {
    if ( ! is_dir( $courserelay_dir ) ) return;

    $courserelay_items = scandir( $courserelay_dir );
    foreach ( $courserelay_items as $courserelay_item ) {
        if ( '.' === $courserelay_item || '..' === $courserelay_item ) continue;
        $courserelay_path = $courserelay_dir . DIRECTORY_SEPARATOR . $courserelay_item;
        if ( is_dir( $courserelay_path ) ) {
            courserelay_uninstall_rmdir( $courserelay_path );
        } else {
            wp_delete_file( $courserelay_path );
        }
    }
    // WP_Filesystem doesn't have rmdir; use WP_Filesystem::rmdir if available,
    // otherwise fall back to native rmdir (only after emptying — safe for uninstall context).
    global $wp_filesystem;
    if ( ! empty( $wp_filesystem ) ) {
        $wp_filesystem->rmdir( $courserelay_dir );
    } else {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        rmdir( $courserelay_dir );
    }
}
