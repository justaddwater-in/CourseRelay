<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Scormpleted_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // AJAX handlers for admin actions
        add_action( 'wp_ajax_scormpleted_upload',         array( __CLASS__, 'ajax_upload' ) );
        add_action( 'wp_ajax_scormpleted_delete_content', array( __CLASS__, 'ajax_delete' ) );
        add_action( 'wp_ajax_scormpleted_save_settings',  array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_scormpleted_reset_user',     array( __CLASS__, 'ajax_reset_user' ) );
        add_action( 'wp_ajax_scormpleted_update_content', array( __CLASS__, 'ajax_update_content' ) );
    }

    // ── Menus ──────────────────────────────────────────────────────────────────

    public static function add_menus() {
        add_menu_page(
            __( 'Scormpleted', 'scormpleted' ),
            __( 'Scormpleted', 'scormpleted' ),
            'manage_options',
            'scormpleted',
            array( __CLASS__, 'page_content' ),
            'dashicons-welcome-learn-more',
            58
        );

        add_submenu_page(
            'scormpleted',
            __( 'SCORM Content', 'scormpleted' ),
            __( 'Content', 'scormpleted' ),
            'manage_options',
            'scormpleted',
            array( __CLASS__, 'page_content' )
        );

        add_submenu_page(
            'scormpleted',
            __( 'Add New Content', 'scormpleted' ),
            __( 'Add New', 'scormpleted' ),
            'manage_options',
            'scormpleted-add',
            array( __CLASS__, 'page_add' )
        );

        add_submenu_page(
            'scormpleted',
            __( 'Completion Reports', 'scormpleted' ),
            __( 'Reports', 'scormpleted' ),
            'manage_options',
            'scormpleted-reports',
            array( __CLASS__, 'page_reports' )
        );

        add_submenu_page(
            'scormpleted',
            __( 'Settings', 'scormpleted' ),
            __( 'Settings', 'scormpleted' ),
            'manage_options',
            'scormpleted-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    // ── Assets ─────────────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'scormpleted' ) === false ) return;

        wp_enqueue_style(
            'scormpleted-admin',
            SCORMPLETED_URL . 'assets/css/admin.css',
            array(),
            SCORMPLETED_VERSION
        );
        wp_enqueue_script(
            'scormpleted-admin',
            SCORMPLETED_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SCORMPLETED_VERSION,
            true
        );
        wp_localize_script( 'scormpleted-admin', 'ScormcompletedAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'scormpleted_admin' ),
            'strings'  => array(
                'uploading'     => __( 'Uploading and processing…', 'scormpleted' ),
                'upload_done'   => __( 'Upload complete!', 'scormpleted' ),
                'upload_error'  => __( 'Upload failed.', 'scormpleted' ),
                'confirm_delete'=> __( 'Delete this SCORM package? This cannot be undone.', 'scormpleted' ),
                'saving'        => __( 'Saving…', 'scormpleted' ),
                'saved'         => __( 'Settings saved.', 'scormpleted' ),
            ),
        ) );
    }

    // ── Page: Content List ─────────────────────────────────────────────────────

    public static function page_content() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $pkgs      = Scormpleted_Package::get_all_packages( array( 'per_page' => 50 ) );
        $total     = Scormpleted_Package::count_packages();
        include SCORMPLETED_DIR . 'admin/views/content-list.php';
    }

    // ── Page: Add New ──────────────────────────────────────────────────────────

    public static function page_add() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $global = Scormpleted_Settings::get();
        include SCORMPLETED_DIR . 'admin/views/content-add.php';
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
                       FROM {$wpdb->prefix}scormpleted_completions c
                       LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                       LEFT JOIN {$wpdb->posts} p ON c.content_id = p.ID
                       WHERE {$where_sql}
                       ORDER BY c.completed_at DESC";

        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}scormpleted_completions c WHERE {$where_sql}";

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

        $scormpleted_report_data = array(
            'per_page'   => $per_page,
            'paged'      => $paged,
            'filter_uid' => $filter_uid,
            'filter_cid' => $filter_cid,
            'completions'=> $completions ?: array(),
            'total'      => $total,
        );

        include SCORMPLETED_DIR . 'admin/views/reports.php';
    }

    // ── Page: Settings ────────────────────────────────────────────────────────

    public static function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $settings = Scormpleted_Settings::get();
        include SCORMPLETED_DIR . 'admin/views/settings.php';
    }

    // ── AJAX: Upload ──────────────────────────────────────────────────────────

    public static function ajax_upload() {
        check_ajax_referer( 'scormpleted_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        if ( empty( $_FILES['scorm_file'] ) ) {
            wp_send_json_error( __( 'No file uploaded.', 'scormpleted' ) );
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
        $result = Scormpleted_Package::handle_upload( $_FILES['scorm_file'], $title, $settings );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message'  => sprintf(
                    /* translators: %s is the title of the uploaded SCORM content. */
                    __( 'Successfully uploaded: %s', 'scormpleted' ),
                    esc_html( $result['title'] )
                ),
                'post_id'  => $result['post_id'],
                'redirect' => admin_url( 'admin.php?page=scormpleted' ),
            ) );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }

    // ── AJAX: Delete ──────────────────────────────────────────────────────────

    public static function ajax_delete() {
        check_ajax_referer( 'scormpleted_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( empty( $post_id ) ) wp_send_json_error( 'Missing post_id' );

        Scormpleted_Package::delete_package( $post_id );
        wp_send_json_success( array( 'message' => __( 'Deleted.', 'scormpleted' ) ) );
    }

    // ── AJAX: Update content settings ─────────────────────────────────────────

    public static function ajax_update_content() {
        check_ajax_referer( 'scormpleted_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( empty( $post_id ) ) wp_send_json_error( 'Missing post_id' );

        $pkg = Scormpleted_Package::get_package( $post_id );
        if ( ! $pkg ) wp_send_json_error( 'Content not found' );

        $title = isset( $_POST['content_title'] ) ? sanitize_text_field( wp_unslash( $_POST['content_title'] ) ) : '';
        if ( ! empty( $title ) ) {
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
        }

        $scormpleted_allowed_types = array( 'hide_button', 'hidden_until_complete', 'disable_until_complete', 'completion_move_nextlevel' );
        $scormpleted_raw_type      = isset( $_POST['completion_type'] ) ? sanitize_text_field( wp_unslash( $_POST['completion_type'] ) ) : $pkg->completion_type;
        $updated = array(
            'post_id'             => $post_id,
            'version'             => $pkg->version,
            'src'                 => $pkg->src,
            'content_url'         => $pkg->content_url,
            'content_path'        => $pkg->content_path,
            'launch_file'         => $pkg->launch_file,
            'mastery_score'       => isset( $_POST['mastery_score'] )       ? floatval( $_POST['mastery_score'] )                                                            : $pkg->mastery_score,
            'completion_tracking' => isset( $_POST['completion_tracking'] ) ? (int) $_POST['completion_tracking']                                                            : $pkg->completion_tracking,
            'completion_type'     => in_array( $scormpleted_raw_type, $scormpleted_allowed_types, true ) ? $scormpleted_raw_type : 'hide_button',
            'width'               => isset( $_POST['width'] )               ? sanitize_text_field( wp_unslash( $_POST['width'] ) )                                           : $pkg->width,
            'height'              => isset( $_POST['height'] )              ? sanitize_text_field( wp_unslash( $_POST['height'] ) )                                          : $pkg->height,
            'activity_id'         => $pkg->activity_id,
        );

        Scormpleted_Package::save_package_data( $updated );
        wp_cache_delete( 'scormpleted_pkg_' . $post_id, 'scormpleted' );
        wp_send_json_success( array( 'message' => __( 'Saved.', 'scormpleted' ) ) );
    }

    // ── AJAX: Save settings ────────────────────────────────────────────────────

    public static function ajax_save_settings() {
        check_ajax_referer( 'scormpleted_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        // Each value is sanitized individually inside Scormpleted_Settings::save()
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
        Scormpleted_Settings::save( $data );
        wp_send_json_success( array( 'message' => __( 'Settings saved.', 'scormpleted' ) ) );
    }

    // ── AJAX: Reset user data ─────────────────────────────────────────────────

    public static function ajax_reset_user() {
        check_ajax_referer( 'scormpleted_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $user_id    = isset( $_POST['user_id'] )    ? intval( $_POST['user_id'] )    : 0;
        $content_id = isset( $_POST['content_id'] ) ? intval( $_POST['content_id'] ) : 0;
        $post_id    = isset( $_POST['post_id'] )    ? intval( $_POST['post_id'] )    : 0;
        $course_id  = isset( $_POST['course_id'] )  ? intval( $_POST['course_id'] )  : 0;

        if ( $user_id && $content_id ) {
            Scormpleted_DB::delete_scorm_data( $content_id, $user_id );
            Scormpleted_Registration::reset( $content_id, $user_id );

            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete(
                $wpdb->prefix . 'scormpleted_completions',
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

        wp_send_json_success( array( 'message' => __( 'User data reset.', 'scormpleted' ) ) );
    }
}
