<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CourseRelay_Package {

    const POST_TYPE = 'courserelay_content';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
    }

    public static function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'label'               => __( 'SCORM Content', 'courserelay' ),
            'labels'              => array(
                'name'          => __( 'SCORM Content', 'courserelay' ),
                'singular_name' => __( 'SCORM Content', 'courserelay' ),
                'add_new'       => __( 'Add New', 'courserelay' ),
                'add_new_item'  => __( 'Add New SCORM Content', 'courserelay' ),
                'edit_item'     => __( 'Edit SCORM Content', 'courserelay' ),
            ),
            'public'              => false,
            'show_ui'             => false,   // We manage our own admin UI
            'show_in_menu'        => false,
            'show_in_rest'        => true,    // Needed for Gutenberg block
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'capabilities'        => array(
                'create_posts'  => 'manage_options',
            ),
            'map_meta_cap'        => true,
        ) );
    }

    // ── Upload & Extract ───────────────────────────────────────────────────────

    /**
     * Handle a SCORM zip upload.
     * Returns array with 'success' bool and either 'post_id' or 'error' message.
     */
    public static function handle_upload( $file, $title, $settings = array() ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        // Validate file type
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'zip' ) {
            return array( 'success' => false, 'error' => __( 'Only .zip files are accepted.', 'courserelay' ) );
        }

        // Upload to WP uploads
        $upload = wp_handle_upload( $file, array( 'test_form' => false ) );
        if ( isset( $upload['error'] ) ) {
            return array( 'success' => false, 'error' => $upload['error'] );
        }

        // Extract zip
        $extract_result = self::extract_zip( $upload['file'] );
        if ( ! $extract_result['success'] ) {
            wp_delete_file( $upload['file'] );
            return $extract_result;
        }

        // Parse imsmanifest.xml
        $manifest = self::parse_manifest( $extract_result['content_path'] );
        if ( ! $manifest['success'] ) {
            // Clean up extracted files
            self::rmdir_recursive( $extract_result['content_path'] );
            wp_delete_file( $upload['file'] );
            return $manifest;
        }

        // Create post
        $post_title = ! empty( $title )
            ? sanitize_text_field( $title )
            : ( ! empty( $manifest['title'] ) ? sanitize_text_field( $manifest['title'] ) : sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ) );

        $post_id = wp_insert_post( array(
            'post_title'  => $post_title,
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            self::rmdir_recursive( $extract_result['content_path'] );
            wp_delete_file( $upload['file'] );
            return array( 'success' => false, 'error' => $post_id->get_error_message() );
        }

        // Determine content URL from content_path
        $upload_dir  = wp_upload_dir();
        $content_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $extract_result['content_path'] );

        // Build src URL (launch file)
        $launch_file_rel = $manifest['launch_file']; // relative to content root
        $src             = trailingslashit( $content_url ) . ltrim( $launch_file_rel, '/' );

        // Activity ID defaults to content URL
        $activity_id = $content_url;

        // Merge settings
        $global   = CourseRelay_Settings::get();
        $pkg_data = array(
            'post_id'              => $post_id,
            'version'              => $manifest['version'],       // scorm12 | scorm2004
            'src'                  => $src,
            'content_url'          => $content_url,
            'content_path'         => $extract_result['content_path'],
            'launch_file'          => $launch_file_rel,
            'mastery_score'        => isset( $settings['mastery_score'] )     ? floatval( $settings['mastery_score'] )     : floatval( $manifest['mastery_score'] ),
            'completion_tracking'  => isset( $settings['completion_tracking'] ) ? (int) $settings['completion_tracking']  : 1,
            'completion_type'      => isset( $settings['completion_type'] )   ? sanitize_text_field( $settings['completion_type'] ) : $global['completion_type'],
            'width'                => isset( $settings['width'] )             ? sanitize_text_field( $settings['width'] )  : $global['width'],
            'height'               => isset( $settings['height'] )            ? sanitize_text_field( $settings['height'] ) : $global['height'],
            'activity_id'          => $activity_id,
        );

        $saved = self::save_package_data( $pkg_data );

        // Remove the original zip
        wp_delete_file( $upload['file'] );

        if ( ! $saved ) {
            // The post exists but has no matching package row — it would be
            // invisible on the Content list (INNER JOIN) and unusable. Clean
            // up the orphan post and extracted files rather than reporting
            // a false success.
            wp_delete_post( $post_id, true );
            self::rmdir_recursive( $extract_result['content_path'] );
            return array(
                'success' => false,
                'error'   => __( 'The package files were extracted but could not be saved to the database. Check the CourseRelay debug log or your site\'s error log for details.', 'courserelay' ),
            );
        }

        return array( 'success' => true, 'post_id' => $post_id, 'title' => $post_title );
    }

    /**
     * Extract zip to uploads/courserelay/{post_slug}/
     */
    private static function extract_zip( $zip_path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return array( 'success' => false, 'error' => __( 'ZipArchive PHP extension is required.', 'courserelay' ) );
        }

        $upload_dir   = wp_upload_dir();
        $base         = trailingslashit( $upload_dir['basedir'] ) . 'courserelay';
        $folder_name  = sanitize_file_name( pathinfo( $zip_path, PATHINFO_FILENAME ) ) . '_' . time();
        $content_path = trailingslashit( $base ) . $folder_name;

        wp_mkdir_p( $content_path );

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return array( 'success' => false, 'error' => __( 'Could not open zip file.', 'courserelay' ) );
        }

        // ── ZIP SLIP PROTECTION ───────────────────────────────────────────────
        // Validate every entry path before extracting. A malicious zip can contain
        // entries like ../../wp-config.php which would escape the target directory.
        $real_content_path = realpath( $content_path );
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $entry_name = $zip->getNameIndex( $i );
            // Resolve what the full path would be after extraction
            $entry_path = realpath( $content_path . DIRECTORY_SEPARATOR . $entry_name );
            // If realpath returns false the file doesn't exist yet — build the path manually
            if ( false === $entry_path ) {
                $entry_path = $content_path . DIRECTORY_SEPARATOR . $entry_name;
                // Normalize: resolve any ../ sequences
                $entry_path = self::normalize_path( $entry_path );
            }
            // Reject if the resolved path escapes the content directory
            if ( strpos( $entry_path, $real_content_path ) !== 0 ) {
                $zip->close();
                self::rmdir_recursive( $content_path );
                return array( 'success' => false, 'error' => __( 'Invalid SCORM package: contains unsafe file paths.', 'courserelay' ) );
            }
        }

        $zip->extractTo( $content_path );
        $zip->close();

        // ── BLOCK PHP EXECUTION IN EXTRACTED FILES ────────────────────────────
        // Write an .htaccess to deny direct PHP execution inside the SCORM folder.
        // This prevents a PHP shell uploaded inside a zip from being executed.
        $htaccess = $content_path . DIRECTORY_SEPARATOR . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents(
                $htaccess,
                "# CourseRelay: block PHP execution in SCORM content\n" .
                "<FilesMatch \"\\.ph(p[2-9]?|tml)$\">\n" .
                "    Require all denied\n" .
                "    Deny from all\n" .
                "</FilesMatch>\n"
            );
        }

        return array( 'success' => true, 'content_path' => $content_path );
    }

    /**
     * Find imsmanifest.xml — may be in root or one subfolder deep.
     */
    public static function find_manifest( $dir ) {
        $direct = trailingslashit( $dir ) . 'imsmanifest.xml';
        if ( file_exists( $direct ) ) return $direct;

        // Check one level deep
        $items = scandir( $dir );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) continue;
            $sub = trailingslashit( $dir ) . $item;
            if ( is_dir( $sub ) ) {
                $candidate = trailingslashit( $sub ) . 'imsmanifest.xml';
                if ( file_exists( $candidate ) ) return $candidate;
            }
        }
        return false;
    }

    /**
     * Parse imsmanifest.xml — adapted from GrassBlade's subs.php.
     * Returns array: version, launch_file, mastery_score, title, success, error.
     */
    public static function parse_manifest( $content_path ) {
        $manifest_path = self::find_manifest( $content_path );
        if ( ! $manifest_path ) {
            return array( 'success' => false, 'error' => __( 'imsmanifest.xml not found. Is this a valid SCORM package?', 'courserelay' ) );
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        if ( ! $dom->load( $manifest_path ) ) {
            return array( 'success' => false, 'error' => __( 'Could not parse imsmanifest.xml.', 'courserelay' ) );
        }
        libxml_clear_errors();

        // ── Detect version ────────────────────────────────────────────────────
        $version = 'scorm12';
        $manifest_els = $dom->getElementsByTagName( 'manifest' );
        if ( $manifest_els->length > 0 ) {
            $manifest_el = $manifest_els->item( 0 );
            $adlcp_ns    = $manifest_el->getAttribute( 'xmlns:adlcp' );

            $metadata_els = $manifest_el->getElementsByTagName( 'metadata' );
            if ( $metadata_els->length > 0 ) {
                $schema_ver_els = $metadata_els->item(0)->getElementsByTagName( 'schemaversion' );
                if ( $schema_ver_els->length > 0 ) {
                    $schema_ver = $schema_ver_els->item(0)->nodeValue;
                    if ( strpos( $schema_ver, '2004' ) !== false || trim( $schema_ver ) === 'CAM 1.3' ) {
                        $version = 'scorm2004';
                    }
                }
            }
        }

        // ── Mastery score ─────────────────────────────────────────────────────
        $mastery_score = 80;
        $adlcp_ns_attr = isset( $adlcp_ns ) ? $adlcp_ns : '';
        $item_els      = $dom->getElementsByTagName( 'item' );
        foreach ( $item_els as $item ) {
            $ms_nodes = $item->getElementsByTagNameNS( $adlcp_ns_attr, 'masteryscore' );
            if ( $ms_nodes->length > 0 && $ms_nodes->item(0)->textContent !== '' ) {
                $mastery_score = floatval( $ms_nodes->item(0)->textContent );
                break;
            }
        }

        // ── Resources ─────────────────────────────────────────────────────────
        $resources = array();
        $resource_els = $dom->getElementsByTagName( 'resource' );
        foreach ( $resource_els as $res ) {
            $identifier  = $res->getAttribute( 'identifier' );
            $scorm_type  = $res->getAttribute( 'adlcp:scormtype' ) ?: $res->getAttribute( 'adlcp:scormType' );
            $href        = $res->getAttribute( 'href' );
            $resources[ $identifier ] = array(
                'scormtype' => strtolower( $scorm_type ),
                'href'      => $href,
            );
        }

        // ── Items (find first SCO) ────────────────────────────────────────────
        $launch_file  = '';
        $title        = '';

        $item_els = $dom->getElementsByTagName( 'item' );
        foreach ( $item_els as $item ) {
            $identifierref = $item->getAttribute( 'identifierref' );
            if ( empty( $identifierref ) ) continue;
            if ( ! isset( $resources[ $identifierref ] ) ) continue;
            if ( $resources[ $identifierref ]['scormtype'] !== 'sco' ) continue;

            $launch_file = $resources[ $identifierref ]['href'];

            // Title from item
            $title_els = $item->getElementsByTagName( 'title' );
            if ( $title_els->length > 0 ) {
                $title = $title_els->item(0)->nodeValue;
            }
            break; // Use first SCO
        }

        if ( empty( $launch_file ) ) {
            return array( 'success' => false, 'error' => __( 'No SCO launch file found in imsmanifest.xml.', 'courserelay' ) );
        }

        // Resolve relative to manifest subfolder if needed
        $manifest_dir = dirname( $manifest_path );
        if ( $manifest_dir !== $content_path ) {
            // manifest is in a subfolder — prepend subfolder to launch_file
            $subfolder   = basename( $manifest_dir );
            $launch_file = $subfolder . '/' . $launch_file;
        }

        return array(
            'success'       => true,
            'version'       => $version,
            'launch_file'   => $launch_file,
            'mastery_score' => $mastery_score,
            'title'         => $title,
        );
    }

    // ── Package DB Helpers ─────────────────────────────────────────────────────

    public static function save_package_data( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'courserelay_packages';

        // Self-heal: if the packages table doesn't exist yet (e.g. the plugin
        // was deployed by copying files rather than clicking "Activate" in
        // wp-admin, so register_activation_hook never fired), create it now
        // instead of silently failing every upload from here on.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
        if ( ! $table_exists ) {
            if ( class_exists( 'CourseRelay_Runtime' ) ) {
                CourseRelay_Runtime::log( "save_package_data: table {$table} missing — running CourseRelay_DB::install()." );
            }
            CourseRelay_DB::install();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
            if ( ! $table_exists ) {
                if ( class_exists( 'CourseRelay_Runtime' ) ) {
                    CourseRelay_Runtime::log( "save_package_data: table {$table} still missing after install() — wpdb error: " . $wpdb->last_error );
                }
                return false;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$wpdb->prefix}courserelay_packages` WHERE post_id = %d LIMIT 1",
            $data['post_id']
        ) );

        $allowed_completion_types = array( 'hide_button', 'hidden_until_complete', 'disable_until_complete', 'completion_move_nextlevel' );
        $completion_type = sanitize_text_field( $data['completion_type'] );
        if ( ! in_array( $completion_type, $allowed_completion_types, true ) ) {
            $completion_type = 'hide_button';
        }

        $row = array(
            'post_id'             => (int) $data['post_id'],
            'version'             => sanitize_text_field( $data['version'] ),
            'src'                 => esc_url_raw( $data['src'] ),
            'content_url'         => esc_url_raw( $data['content_url'] ),
            'content_path'        => sanitize_text_field( $data['content_path'] ),
            'launch_file'         => sanitize_text_field( $data['launch_file'] ),
            'mastery_score'       => floatval( $data['mastery_score'] ),
            'completion_tracking' => (int) $data['completion_tracking'],
            'completion_type'     => $completion_type,
            'width'               => sanitize_text_field( $data['width'] ),
            'height'              => sanitize_text_field( $data['height'] ),
            'activity_id'         => isset( $data['activity_id'] ) ? esc_url_raw( $data['activity_id'] ) : esc_url_raw( $data['content_url'] ),
        );

        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update( $table, $row, array( 'post_id' => $row['post_id'] ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert( $table, $row );
        }

        if ( false === $result ) {
            if ( class_exists( 'CourseRelay_Runtime' ) ) {
                CourseRelay_Runtime::log( "save_package_data: DB write failed for post_id={$row['post_id']} — wpdb error: " . $wpdb->last_error );
            }
            return false;
        }

        // Bust the cached row so get_package() doesn't serve a stale/empty result.
        wp_cache_delete( 'courserelay_pkg_' . $row['post_id'], 'courserelay' );

        return true;
    }

    public static function get_package( $post_id ) {
        global $wpdb;
        $cache_key = 'courserelay_pkg_' . $post_id;
        $pkg       = wp_cache_get( $cache_key, 'courserelay' );

        if ( false === $pkg ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $pkg = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}courserelay_packages WHERE post_id = %d LIMIT 1",
                $post_id
            ) );
            wp_cache_set( $cache_key, $pkg, 'courserelay', 300 );
        }
        return $pkg;
    }

    public static function get_all_packages( $args = array() ) {
        global $wpdb;
        $defaults = array( 'orderby' => 'p.post_title', 'order' => 'ASC', 'per_page' => 20, 'paged' => 1 );
        $args     = wp_parse_args( $args, $defaults );
        $offset   = ( (int) $args['paged'] - 1 ) * (int) $args['per_page'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, p.post_title, p.post_date
             FROM {$wpdb->prefix}courserelay_packages s
             INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
             WHERE p.post_type = %s AND p.post_status = 'publish'
             ORDER BY p.post_title ASC
             LIMIT %d OFFSET %d",
            self::POST_TYPE, (int) $args['per_page'], $offset
        ) );
    }

    public static function count_packages() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}courserelay_packages s
             INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            self::POST_TYPE
        ) );
    }

    /**
     * Normalize a file path by resolving ../ sequences without requiring the path to exist.
     * Used for zip slip protection.
     */
    private static function normalize_path( $path ) {
        $parts  = array();
        $is_abs = ( strpos( $path, DIRECTORY_SEPARATOR ) === 0 || ( strlen( $path ) > 1 && $path[1] === ':' ) );
        foreach ( explode( DIRECTORY_SEPARATOR, $path ) as $part ) {
            if ( '.' === $part || '' === $part ) continue;
            if ( '..' === $part ) {
                array_pop( $parts );
            } else {
                $parts[] = $part;
            }
        }
        $normalized = implode( DIRECTORY_SEPARATOR, $parts );
        return $is_abs ? DIRECTORY_SEPARATOR . $normalized : $normalized;
    }

    public static function delete_package( $post_id ) {
        global $wpdb;

        $pkg = self::get_package( $post_id );
        if ( $pkg && ! empty( $pkg->content_path ) ) {
            // ── PATH TRAVERSAL PROTECTION ─────────────────────────────────────
            // Validate content_path is inside the expected uploads/courserelay/ dir
            // before deleting. Prevents damage if DB is compromised.
            $upload_dir        = wp_upload_dir();
            $allowed_base      = realpath( trailingslashit( $upload_dir['basedir'] ) . 'courserelay' );
            $real_content_path = realpath( $pkg->content_path );

            if (
                $allowed_base &&
                $real_content_path &&
                strpos( $real_content_path, $allowed_base ) === 0 &&
                is_dir( $real_content_path )
            ) {
                self::rmdir_recursive( $real_content_path );
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'courserelay_packages', array( 'post_id' => $post_id ), array( '%d' ) );
        wp_delete_post( $post_id, true );
        wp_cache_delete( 'courserelay_pkg_' . $post_id, 'courserelay' );
    }

    // ── Utility ────────────────────────────────────────────────────────────────

    private static function rmdir_recursive( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $items = scandir( $dir );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                self::rmdir_recursive( $path );
            } else {
                wp_delete_file( $path );
            }
        }
        global $wp_filesystem;
        if ( ! empty( $wp_filesystem ) ) {
            $wp_filesystem->rmdir( $dir );
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
            rmdir( $dir );
        }
    }
}