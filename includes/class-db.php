<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Scormpleted_DB {

    const DB_VERSION_OPTION = 'scormpleted_db_version';
    const DB_VERSION        = '1.0';

    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
    }

    // ── Install / Upgrade ──────────────────────────────────────────────────────
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // ── scormpleted_packages ──────────────────────────────────────────────
        // Stores metadata about each uploaded SCORM package (post-based).
        // Core data lives in wp_posts; this table stores SCORM-specific fields.
        $sql = "CREATE TABLE {$wpdb->prefix}scormpleted_packages (
            id          int(11)      NOT NULL AUTO_INCREMENT,
            post_id     int(11)      NOT NULL,
            version     varchar(16)  NOT NULL DEFAULT 'scorm12',
            src         text         NOT NULL,
            content_url text         NOT NULL,
            content_path text        NOT NULL,
            launch_file text         NOT NULL,
            mastery_score float      NOT NULL DEFAULT 80,
            completion_tracking tinyint(1) NOT NULL DEFAULT 1,
            completion_type varchar(32) NOT NULL DEFAULT 'auto',
            width       varchar(16)  NOT NULL DEFAULT '100%',
            height      varchar(16)  NOT NULL DEFAULT '600px',
            activity_id text         NULL,
            created_at  timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) $charset";
        dbDelta( $sql );

        // ── scormpleted_scorm_data ────────────────────────────────────────────
        // CMI runtime data — one row per user/content/registration/var_key.
        $sql = "CREATE TABLE {$wpdb->prefix}scormpleted_scorm_data (
            id              int(11)      NOT NULL AUTO_INCREMENT,
            content_id      int(11)      NOT NULL,
            user_id         int(11)      NOT NULL,
            registration_id varchar(255) NOT NULL,
            var_key         varchar(255) NOT NULL,
            var_value       text         NOT NULL,
            updated_at      timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_content_reg (content_id, user_id, registration_id(8)),
            KEY var_lookup (content_id, user_id, registration_id(8), var_key(32))
        ) $charset";
        dbDelta( $sql );

        // ── scormpleted_completions ───────────────────────────────────────────
        // One row per completed attempt — drives LearnDash mark-complete and reports.
        $sql = "CREATE TABLE {$wpdb->prefix}scormpleted_completions (
            id           int(11)      NOT NULL AUTO_INCREMENT,
            content_id   int(11)      NOT NULL,
            user_id      int(11)      NOT NULL,
            post_id      int(11)      NOT NULL DEFAULT 0,
            course_id    int(11)      NOT NULL DEFAULT 0,
            registration varchar(255) DEFAULT NULL,
            status       varchar(16)  DEFAULT NULL,
            score        float        DEFAULT NULL,
            time_spent   int(11)      DEFAULT NULL,
            completed_at timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_content (user_id, content_id),
            KEY user_post (user_id, post_id)
        ) $charset";
        dbDelta( $sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    public static function maybe_upgrade() {
        $installed = get_option( self::DB_VERSION_OPTION, '0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::install();
        }
    }

    // ── SCORM Data Helpers ─────────────────────────────────────────────────────

    /**
     * Get all CMI vars for a user/content/registration, optionally a single key.
     */
    public static function get_scorm_data( $content_id, $registration_id, $var_key = null, $default = '', $user_id = null ) {
        global $wpdb;

        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }
        if ( empty( $user_id ) ) return is_null( $var_key ) ? array() : $default;

        // Use a static cache to avoid repeated queries within one request
        static $cache = array();
        $cache_key = "{$content_id}_{$registration_id}_{$user_id}";

        if ( empty( $cache[ $cache_key ] ) ) {
            // Static $cache array above serves as the per-request cache for this query.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT var_key, var_value FROM {$wpdb->prefix}scormpleted_scorm_data
                 WHERE content_id = %d AND user_id = %d AND registration_id = %s
                 ORDER BY id ASC",
                $content_id, $user_id, $registration_id
            ), ARRAY_A );

            $cache[ $cache_key ] = array();
            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $cache[ $cache_key ][ $row['var_key'] ] = $row['var_value'];
                }
            }
        }

        if ( is_null( $var_key ) ) return $cache[ $cache_key ];
        return isset( $cache[ $cache_key ][ $var_key ] ) ? $cache[ $cache_key ][ $var_key ] : $default;
    }

    /**
     * Insert or update a single CMI variable.
     */
    public static function set_scorm_data( $content_id, $registration_id, $var_key, $var_value, $user_id = null ) {
        global $wpdb;

        if ( empty( $user_id ) ) $user_id = get_current_user_id();
        if ( empty( $user_id ) ) return false;

        // Unslash JSON-heavy fields
        if ( in_array( $var_key, array( 'cmi.suspend_data', 'cmi.location', 'cmi.core.lesson_location', 'cmi.launch_data' ), true ) ) {
            $var_value = wp_unslash( $var_value );
        }

        // ── SIZE LIMIT ────────────────────────────────────────────────────────
        // Protect against DoS via huge suspend_data payloads.
        // SCORM 1.2 spec recommends 4096 chars for suspend_data; we allow 64KB.
        $size_limited_keys = array( 'cmi.suspend_data', 'cmi.core.lesson_location', 'cmi.location' );
        if ( in_array( $var_key, $size_limited_keys, true ) && strlen( $var_value ) > 65536 ) {
            $var_value = substr( $var_value, 0, 65536 );
        }

        // Only persist fields we care about (mirrors GrassBlade's gb_scorm_key_check)
        if ( ! self::should_persist_key( $var_key ) ) return true;

        $existing = self::get_scorm_data( $content_id, $registration_id, $var_key, null, $user_id );

        if ( ! is_null( $existing ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'scormpleted_scorm_data',
                array( 'var_value' => $var_value, 'updated_at' => current_time( 'mysql' ) ),
                array( 'content_id' => $content_id, 'user_id' => $user_id, 'registration_id' => $registration_id, 'var_key' => $var_key )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert( $wpdb->prefix . 'scormpleted_scorm_data', array(
                'content_id'      => $content_id,
                'user_id'         => $user_id,
                'registration_id' => $registration_id,
                'var_key'         => $var_key,
                'var_value'       => $var_value,
            ) );
        }

        // Bust the static cache for this key
        $cache_key = "{$content_id}_{$registration_id}_{$user_id}";
        // Re-fetch will happen on next get call after cache cleared
        unset( $GLOBALS['_scormpleted_cache'][ $cache_key ] );

        return true;
    }

    /**
     * Which CMI keys should be persisted to the DB.
     * Mirrors GrassBlade's gb_scorm_key_check().
     */
    public static function should_persist_key( $var_key ) {
        static $persistent_keys = array(
            // SCORM 1.2
            'cmi.core.score.raw', 'adlcp:masteryscore',
            'cmi.launch_data', 'cmi.suspend_data',
            'cmi.core.lesson_location', 'cmi.core.credit',
            'cmi.core.lesson_status', 'cmi.core.entry', 'cmi.core.exit',
            'cmi.core.total_time', 'cmi.core.session_time',
            // SCORM 2004
            'cmi.score.raw', 'cmi.location', 'cmi.credit',
            'cmi.completion_status', 'cmi.success_status',
            'cmi.entry', 'cmi.exit', 'cmi.total_time', 'cmi.session_time',
            'cmi.scaled_passing_score',
        );
        return in_array( $var_key, $persistent_keys, true );
    }

    /**
     * Delete all SCORM runtime data for a user/content (for testing reset).
     */
    public static function delete_scorm_data( $content_id, $user_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete(
            $wpdb->prefix . 'scormpleted_scorm_data',
            array( 'content_id' => $content_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
    }

    /**
     * Store a completion record.
     */
    public static function store_completion( $content_id, $registration, $user_id, $post_id, $course_id, $status, $score = null ) {
        global $wpdb;

        // Avoid duplicate records for the same registration
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}scormpleted_completions
             WHERE user_id = %d AND content_id = %d AND registration = %s LIMIT 1",
            $user_id, $content_id, $registration
        ) );

        if ( $existing ) return (int) $existing;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert( $wpdb->prefix . 'scormpleted_completions', array(
            'content_id'   => $content_id,
            'user_id'      => $user_id,
            'post_id'      => $post_id,
            'course_id'    => $course_id,
            'registration' => $registration,
            'status'       => $status,
            'score'        => $score,
            'completed_at' => current_time( 'mysql' ),
        ) );

        return $wpdb->insert_id;
    }
}
