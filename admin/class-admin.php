<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CourseRelay_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // AJAX handlers for admin actions
        add_action( 'wp_ajax_courserelay_upload',         array( __CLASS__, 'ajax_upload' ) );
        add_action( 'wp_ajax_courserelay_delete_content', array( __CLASS__, 'ajax_delete' ) );
        add_action( 'wp_ajax_courserelay_save_settings',  array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_courserelay_reset_user',     array( __CLASS__, 'ajax_reset_user' ) );
        add_action( 'wp_ajax_courserelay_update_content', array( __CLASS__, 'ajax_update_content' ) );

        // CSV export (not an AJAX call — triggers a file download)
        add_action( 'admin_post_courserelay_export_report', array( __CLASS__, 'export_report_csv' ) );
    }

    // ── Menus ──────────────────────────────────────────────────────────────────

    public static function add_menus() {
        add_menu_page(
            __( 'CourseRelay', 'courserelay' ),
            __( 'CourseRelay', 'courserelay' ),
            'manage_options',
            'courserelay',
            array( __CLASS__, 'page_content' ),
            'dashicons-welcome-learn-more',
            58
        );

        add_submenu_page(
            'courserelay',
            __( 'SCORM Content', 'courserelay' ),
            __( 'Content', 'courserelay' ),
            'manage_options',
            'courserelay',
            array( __CLASS__, 'page_content' )
        );

        add_submenu_page(
            'courserelay',
            __( 'Add New Content', 'courserelay' ),
            __( 'Add New', 'courserelay' ),
            'manage_options',
            'courserelay-add',
            array( __CLASS__, 'page_add' )
        );

        add_submenu_page(
            'courserelay',
            __( 'Completion Reports', 'courserelay' ),
            __( 'Reports', 'courserelay' ),
            'manage_options',
            'courserelay-reports',
            array( __CLASS__, 'page_reports' )
        );

        add_submenu_page(
            'courserelay',
            __( 'Settings', 'courserelay' ),
            __( 'Settings', 'courserelay' ),
            'manage_options',
            'courserelay-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    // ── Assets ─────────────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'courserelay' ) === false ) return;

        wp_enqueue_style(
            'courserelay-admin',
            COURSERELAY_URL . 'assets/css/admin.css',
            array(),
            COURSERELAY_VERSION
        );
        wp_enqueue_script(
            'courserelay-admin',
            COURSERELAY_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            COURSERELAY_VERSION,
            true
        );
        wp_localize_script( 'courserelay-admin', 'CourseRelayAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'courserelay_admin' ),
            'strings'  => array(
                'uploading'     => __( 'Uploading and processing…', 'courserelay' ),
                'upload_done'   => __( 'Upload complete!', 'courserelay' ),
                'upload_error'  => __( 'Upload failed.', 'courserelay' ),
                'confirm_delete'=> __( 'Delete this SCORM package? This cannot be undone.', 'courserelay' ),
                'saving'        => __( 'Saving…', 'courserelay' ),
                'saved'         => __( 'Settings saved.', 'courserelay' ),
            ),
        ) );
    }

    // ── Page: Content List ─────────────────────────────────────────────────────

    public static function page_content() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $pkgs      = CourseRelay_Package::get_all_packages( array( 'per_page' => 50 ) );
        $total     = CourseRelay_Package::count_packages();
        include COURSERELAY_DIR . 'admin/views/content-list.php';
    }

    // ── Page: Add New ──────────────────────────────────────────────────────────

    public static function page_add() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $global = CourseRelay_Settings::get();
        include COURSERELAY_DIR . 'admin/views/content-add.php';
    }

    // ── Page: Reports ──────────────────────────────────────────────────────────

    public static function page_reports() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );

        global $wpdb;

        $per_page   = 50;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged      = isset( $_GET['paged'] )      ? max( 1, intval( $_GET['paged'] ) )      : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_uid = isset( $_GET['user_id'] )    ? intval( $_GET['user_id'] )               : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_cid = isset( $_GET['content_id'] ) ? intval( $_GET['content_id'] )            : 0;
        $offset     = ( $paged - 1 ) * $per_page;

        // Build safe query with placeholders only
        $where_parts  = array( '1=1' );
        $where_values = array();
        if ( $filter_uid ) { $where_parts[] = 'c.user_id = %d';    $where_values[] = $filter_uid; }
        if ( $filter_cid ) { $where_parts[] = 'c.content_id = %d'; $where_values[] = $filter_cid; }
        $where_sql = implode( ' AND ', $where_parts );

        $select_sql = "SELECT c.*, u.user_email, p.post_title AS content_title
                       FROM {$wpdb->prefix}courserelay_completions c
                       LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                       LEFT JOIN {$wpdb->posts} p ON c.content_id = p.ID
                       WHERE {$where_sql}
                       ORDER BY c.completed_at DESC";

        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}courserelay_completions c WHERE {$where_sql}";

        if ( empty( $where_values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $completions = $wpdb->get_results( $wpdb->prepare( $select_sql . ' LIMIT %d OFFSET %d', $per_page, $offset ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $count_sql );
        } else {
    $select_vals = array_merge( $where_values, array( $per_page, $offset ) );
    $count_vals  = $where_values;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $completions = $wpdb->get_results( $wpdb->prepare( $select_sql . ' LIMIT %d OFFSET %d', ...$select_vals ) );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$count_vals ) );
}

        $courserelay_report_data = array(
            'per_page'   => $per_page,
            'paged'      => $paged,
            'filter_uid' => $filter_uid,
            'filter_cid' => $filter_cid,
            'completions'=> $completions ?: array(),
            'total'      => $total,
        );

        include COURSERELAY_DIR . 'admin/views/reports.php';
    }

    // ── CSV Export ────────────────────────────────────────────────────────────

    /**
     * Streams the same (filtered) completion report as a CSV download.
     * Reuses the same $_GET filters as page_reports() so "Export" always
     * matches whatever the admin currently has on screen.
     */
    public static function export_report_csv() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        check_admin_referer( 'courserelay_export_report' );

        global $wpdb;

        $filter_uid = isset( $_GET['user_id'] )    ? intval( $_GET['user_id'] )    : 0;
        $filter_cid = isset( $_GET['content_id'] ) ? intval( $_GET['content_id'] ) : 0;

        $where_parts  = array( '1=1' );
        $where_values = array();
        if ( $filter_uid ) { $where_parts[] = 'c.user_id = %d';    $where_values[] = $filter_uid; }
        if ( $filter_cid ) { $where_parts[] = 'c.content_id = %d'; $where_values[] = $filter_cid; }
        $where_sql = implode( ' AND ', $where_parts );

        // Hard cap so a huge site can't exhaust memory/time on one export.
        $select_sql = "SELECT c.*, u.user_email, p.post_title AS content_title
                       FROM {$wpdb->prefix}courserelay_completions c
                       LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                       LEFT JOIN {$wpdb->posts} p ON c.content_id = p.ID
                       WHERE {$where_sql}
                       ORDER BY c.completed_at DESC
                       LIMIT 50000";

        if ( empty( $where_values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( $select_sql );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$where_values ) );
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=courserelay-completions-' . gmdate( 'Y-m-d' ) . '.csv' );

        // Writing directly to the php://output stream for a CSV download — this is not
        // a local filesystem file, so the WP_Filesystem API does not apply here.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'User ID', 'User Email', 'Content ID', 'Content Title', 'Status', 'Score (%)', 'Lesson', 'Completed At' ) );

        foreach ( (array) $rows as $row ) {
            fputcsv( $out, array(
                $row->user_id,
                $row->user_email,
                $row->content_id,
                $row->content_title,
                $row->status,
                $row->score !== null ? number_format( (float) $row->score, 1 ) : '',
                $row->post_id ? get_the_title( $row->post_id ) : '',
                $row->completed_at,
            ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $out );
        exit;
    }

    // ── Page: Settings ────────────────────────────────────────────────────────

    public static function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $settings = CourseRelay_Settings::get();
        include COURSERELAY_DIR . 'admin/views/settings.php';
    }

    // ── AJAX: Upload ──────────────────────────────────────────────────────────

    public static function ajax_upload() {
        check_ajax_referer( 'courserelay_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        if ( empty( $_FILES['scorm_file'] ) ) {
            wp_send_json_error( __( 'No file uploaded.', 'courserelay' ) );
        }

        $title    = ! empty( $_POST['content_title'] ) ? sanitize_text_field( wp_unslash( $_POST['content_title'] ) ) : '';
        $allowed_types = array( 'hide_button', 'hidden_until_complete', 'disable_until_complete', 'completion_move_nextlevel' );
        $raw_type      = isset( $_POST['completion_type'] ) ? sanitize_text_field( wp_unslash( $_POST['completion_type'] ) ) : 'hide_button';
        $settings = array(
            'mastery_score'       => isset( $_POST['mastery_score'] )       ? floatval( $_POST['mastery_score'] )                                          : 80,
            'completion_tracking' => isset( $_POST['completion_tracking'] ) ? (int) $_POST['completion_tracking']                                          : 1,
            'completion_type'     => in_array( $raw_type, $allowed_types, true ) ? $raw_type : 'hide_button',
            'width'               => isset( $_POST['width'] )               ? sanitize_text_field( wp_unslash( $_POST['width'] ) )                         : '100%',
            'height'              => isset( $_POST['height'] )              ? sanitize_text_field( wp_unslash( $_POST['height'] ) )                        : '600px',
        );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $result = CourseRelay_Package::handle_upload( $_FILES['scorm_file'], $title, $settings );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message'  => sprintf(
                    /* translators: %s is the title of the uploaded SCORM content. */
                    __( 'Successfully uploaded: %s', 'courserelay' ),
                    esc_html( $result['title'] )
                ),
                'post_id'  => $result['post_id'],
                'redirect' => admin_url( 'admin.php?page=courserelay' ),
            ) );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }

    // ── AJAX: Delete ──────────────────────────────────────────────────────────

    public static function ajax_delete() {
        check_ajax_referer( 'courserelay_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( empty( $post_id ) ) wp_send_json_error( 'Missing post_id' );

        CourseRelay_Package::delete_package( $post_id );
        wp_send_json_success( array( 'message' => __( 'Deleted.', 'courserelay' ) ) );
    }

    // ── AJAX: Update content settings ─────────────────────────────────────────

    public static function ajax_update_content() {
        check_ajax_referer( 'courserelay_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( empty( $post_id ) ) wp_send_json_error( 'Missing post_id' );

        $pkg = CourseRelay_Package::get_package( $post_id );
        if ( ! $pkg ) wp_send_json_error( 'Content not found' );

        $title = isset( $_POST['content_title'] ) ? sanitize_text_field( wp_unslash( $_POST['content_title'] ) ) : '';
        if ( ! empty( $title ) ) {
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
        }

        $courserelay_allowed_types = array( 'hide_button', 'hidden_until_complete', 'disable_until_complete', 'completion_move_nextlevel' );
        $courserelay_raw_type      = isset( $_POST['completion_type'] ) ? sanitize_text_field( wp_unslash( $_POST['completion_type'] ) ) : $pkg->completion_type;
        $updated = array(
            'post_id'             => $post_id,
            'version'             => $pkg->version,
            'src'                 => $pkg->src,
            'content_url'         => $pkg->content_url,
            'content_path'        => $pkg->content_path,
            'launch_file'         => $pkg->launch_file,
            'mastery_score'       => isset( $_POST['mastery_score'] )       ? floatval( $_POST['mastery_score'] )                                                            : $pkg->mastery_score,
            'completion_tracking' => isset( $_POST['completion_tracking'] ) ? (int) $_POST['completion_tracking']                                                            : $pkg->completion_tracking,
            'completion_type'     => in_array( $courserelay_raw_type, $courserelay_allowed_types, true ) ? $courserelay_raw_type : 'hide_button',
            'width'               => isset( $_POST['width'] )               ? sanitize_text_field( wp_unslash( $_POST['width'] ) )                                           : $pkg->width,
            'height'              => isset( $_POST['height'] )              ? sanitize_text_field( wp_unslash( $_POST['height'] ) )                                          : $pkg->height,
            'activity_id'         => $pkg->activity_id,
        );

        CourseRelay_Package::save_package_data( $updated );
        wp_cache_delete( 'courserelay_pkg_' . $post_id, 'courserelay' );
        wp_send_json_success( array( 'message' => __( 'Saved.', 'courserelay' ) ) );
    }

    // ── AJAX: Save settings ────────────────────────────────────────────────────

    public static function ajax_save_settings() {
        check_ajax_referer( 'courserelay_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        // Each value is sanitized individually inside CourseRelay_Settings::save()
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
        CourseRelay_Settings::save( $data );
        wp_send_json_success( array( 'message' => __( 'Settings saved.', 'courserelay' ) ) );
    }

    // ── AJAX: Reset user data ─────────────────────────────────────────────────

    public static function ajax_reset_user() {
        check_ajax_referer( 'courserelay_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $user_id    = isset( $_POST['user_id'] )    ? intval( $_POST['user_id'] )    : 0;
        $content_id = isset( $_POST['content_id'] ) ? intval( $_POST['content_id'] ) : 0;
        $post_id    = isset( $_POST['post_id'] )    ? intval( $_POST['post_id'] )    : 0;
        $course_id  = isset( $_POST['course_id'] )  ? intval( $_POST['course_id'] )  : 0;

        if ( $user_id && $content_id ) {
            CourseRelay_DB::delete_scorm_data( $content_id, $user_id );
            CourseRelay_Registration::reset( $content_id, $user_id );

            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete(
                $wpdb->prefix . 'courserelay_completions',
                array( 'user_id' => $user_id, 'content_id' => $content_id ),
                array( '%d', '%d' )
            );
        }

        if ( $user_id && $post_id ) {
            delete_user_meta( $user_id, 'completed_' . $post_id );
        }

        if ( $user_id && $post_id && $course_id ) {
            $progress = get_user_meta( $user_id, '_sfwd-course_progress', true );
            if ( ! empty( $progress[ $course_id ] ) ) {
                unset( $progress[ $course_id ]['lessons'][ $post_id ] );
                unset( $progress[ $course_id ]['topics'][ $post_id ] );
                update_user_meta( $user_id, '_sfwd-course_progress', $progress );
            }
        }

        wp_send_json_success( array( 'message' => __( 'User data reset.', 'courserelay' ) ) );
    }
}
