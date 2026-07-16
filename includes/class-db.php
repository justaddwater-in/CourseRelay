<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CourseRelay_DB {

    const DB_VERSION_OPTION = 'courserelay_db_version';
    const DB_VERSION        = '1.0';

    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
    }

    // ── Install / Upgrade ──────────────────────────────────────────────────────
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // ── courserelay_packages ──────────────────────────────────────────────
        // Stores metadata about each uploaded SCORM package (post-based).
        // Core data lives in wp_posts; this table stores SCORM-specific fields.
        $sql = "CREATE TABLE {$wpdb->prefix}courserelay_packages (
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

        // ── courserelay_scorm_data ────────────────────────────────────────────
        // CMI runtime data — one row per user/content/registration/var_key.
        $sql = "CREATE TABLE {$wpdb->prefix}courserelay_scorm_data (
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

        // ── courserelay_completions ───────────────────────────────────────────
        // One row per completed attempt — drives LearnDash mark-complete and reports.
        $sql = "CREATE TABLE {$wpdb->prefix}courserelay_completions (
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
        self::maybe_migrate_legacy_scormpleted_data();

        $installed = get_option( self::DB_VERSION_OPTION, '0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::install();
        }
    }

    // ── One-time migration: rename pre-rebrand "scormpleted" data ───────────────
    // Sites that had the plugin installed under its old name ("Scormpleted")
    // stored data under scormpleted_* table/option names and
    // _scormpleted_content_id / _scormpleted_reg_* meta keys. The rebrand to
    // CourseRelay changed all of those identifiers, which would otherwise
    // orphan existing package/completion data and silently break the
    // LearnDash content_id -> lesson lookup. This runs once and is a no-op on
    // fresh installs (nothing to migrate).
    public static function maybe_migrate_legacy_scormpleted_data() {
        if ( get_option( 'courserelay_legacy_migrated' ) ) {
            return;
        }

        global $wpdb;

        // ── Tables: RENAME TABLE only if the old one exists and the new one doesn't. ──
        $table_map = array(
            $wpdb->prefix . 'scormpleted_packages'    => $wpdb->prefix . 'courserelay_packages',
            $wpdb->prefix . 'scormpleted_scorm_data'  => $wpdb->prefix . 'courserelay_scorm_data',
            $wpdb->prefix . 'scormpleted_completions' => $wpdb->prefix . 'courserelay_completions',
        );
        foreach ( $table_map as $old_table => $new_table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );
            if ( $old_exists && ! $new_exists ) {
                // RENAME TABLE identifiers can't be bound via $wpdb->prepare() placeholders
                // (MySQLi placeholders only bind values, not identifiers; the %i identifier
                // placeholder needs WP 6.2+, but this plugin supports WP 5.8+). $old_table
                // and $new_table are not user input — they only ever come from the
                // hardcoded $table_map above — but are explicitly escaped here regardless.
                $safe_old_table = esc_sql( $old_table );
                $safe_new_table = esc_sql( $new_table );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query( "RENAME TABLE `{$safe_old_table}` TO `{$safe_new_table}`" );
            }
        }

        // ── Options ──────────────────────────────────────────────────────────
        $option_map = array(
            'scormpleted_settings'   => 'courserelay_settings',
            'scormpleted_db_version' => self::DB_VERSION_OPTION,
        );
        foreach ( $option_map as $old_option => $new_option ) {
            $old_value = get_option( $old_option, null );
            if ( null !== $old_value && false === get_option( $new_option, false ) ) {
                update_option( $new_option, $old_value );
            }
        }

        // ── Post meta: _scormpleted_content_id -> _courserelay_content_id ──────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.SlowDBQueryMetaQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
            '_courserelay_content_id', '_scormpleted_content_id'
        ) );

        // ── User meta: _scormpleted_reg_{content_id} -> _courserelay_reg_{content_id} ──
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.SlowDBQueryMetaQuery
        $wpdb->query(
            "UPDATE {$wpdb->usermeta}
             SET meta_key = CONCAT( '_courserelay_reg_', SUBSTRING( meta_key, LENGTH('_scormpleted_reg_') + 1 ) )
             WHERE meta_key LIKE '\\_scormpleted\\_reg\\_%'"
        );

        update_option( 'courserelay_legacy_migrated', 1 );

        // In case a persistent object cache (Redis/Memcached) already cached an
        // empty "no attached posts" result before the meta key rename above.
        // wp_cache_flush_group() would be the targeted option but requires WP 6.1+,
        // and this plugin supports WP 5.8+, so a full flush is used instead — this
        // migration runs at most once per site, so the one-time cost is negligible.
        wp_cache_flush();
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
                "SELECT var_key, var_value FROM {$wpdb->prefix}courserelay_scorm_data
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

        // Only persist fields we care about (mirrors GrassBlade's gb_scorm_key_check)
        if ( ! self::should_persist_key( $var_key ) ) return true;

        // Unslash JSON-heavy fields
        if ( in_array( $var_key, array( 'cmi.suspend_data', 'cmi.location', 'cmi.core.lesson_location', 'cmi.launch_data' ), true ) ) {
            $var_value = wp_unslash( $var_value );
        }

        // ── SANITIZATION ─────────────────────────────────────────────────────
        // $var_value originates from raw $_REQUEST data sent by the SCORM JS
        // runtime. Sanitize per-key before it ever reaches the database.
        $var_value = self::sanitize_cmi_value( $var_key, $var_value );

        // ── SIZE LIMIT ────────────────────────────────────────────────────────
        // Protect against DoS via huge suspend_data payloads.
        // SCORM 1.2 spec recommends 4096 chars for suspend_data; we allow 64KB.
        $size_limited_keys = array( 'cmi.suspend_data', 'cmi.core.lesson_location', 'cmi.location' );
        if ( in_array( $var_key, $size_limited_keys, true ) && strlen( $var_value ) > 65536 ) {
            $var_value = substr( $var_value, 0, 65536 );
        }

        $existing = self::get_scorm_data( $content_id, $registration_id, $var_key, null, $user_id );

        if ( ! is_null( $existing ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'courserelay_scorm_data',
                array( 'var_value' => $var_value, 'updated_at' => current_time( 'mysql' ) ),
                array( 'content_id' => $content_id, 'user_id' => $user_id, 'registration_id' => $registration_id, 'var_key' => $var_key )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert( $wpdb->prefix . 'courserelay_scorm_data', array(
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
        unset( $GLOBALS['_courserelay_cache'][ $cache_key ] );

        return true;
    }

    /**
     * Sanitize a single CMI value before it is written to the database.
     * Every persistable key gets a rule appropriate to its expected shape —
     * enumerated fields are locked to their known SCORM vocabulary, numeric
     * fields are cast, time fields are validated by pattern, and free-form
     * bookmark/state fields (suspend_data, location, launch_data) have tags
     * and control characters stripped while keeping their content intact.
     */
    private static function sanitize_cmi_value( $var_key, $var_value ) {
        $var_value = is_scalar( $var_value ) ? (string) $var_value : '';

        switch ( $var_key ) {

            // Free-form bookmark / state data — strip tags, keep the payload.
            case 'cmi.suspend_data':
            case 'cmi.launch_data':
            case 'cmi.location':
            case 'cmi.core.lesson_location':
                return sanitize_textarea_field( $var_value );

            // Numeric score / mastery values.
            case 'cmi.core.score.raw':
            case 'cmi.score.raw':
            case 'adlcp:masteryscore':
            case 'cmi.scaled_passing_score':
                return is_numeric( $var_value ) ? (string) floatval( $var_value ) : '';

            // Enumerated credit flag.
            case 'cmi.core.credit':
            case 'cmi.credit':
                return in_array( $var_value, array( 'credit', 'no-credit' ), true ) ? $var_value : '';

            // Enumerated lesson/completion status.
            case 'cmi.core.lesson_status':
            case 'cmi.completion_status':
                $allowed = array( 'passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted', 'unknown' );
                return in_array( strtolower( $var_value ), $allowed, true ) ? strtolower( $var_value ) : '';

            // Enumerated success status (SCORM 2004 only).
            case 'cmi.success_status':
                $allowed = array( 'passed', 'failed', 'unknown' );
                return in_array( strtolower( $var_value ), $allowed, true ) ? strtolower( $var_value ) : 'unknown';

            // Enumerated entry.
            case 'cmi.core.entry':
            case 'cmi.entry':
                return in_array( $var_value, array( 'ab-initio', 'resume', '' ), true ) ? $var_value : '';

            // Enumerated exit.
            case 'cmi.core.exit':
            case 'cmi.exit':
                return in_array( $var_value, array( 'time-out', 'suspend', 'logout', 'normal', '' ), true ) ? $var_value : '';

            // Time strings — SCORM 1.2 "HHHH:MM:SS(.ss)" or SCORM 2004 ISO 8601 duration.
            case 'cmi.core.total_time':
            case 'cmi.total_time':
            case 'cmi.core.session_time':
            case 'cmi.session_time':
                $is_1_2_format = (bool) preg_match( '/^\d{2,4}:\d{2}:\d{2}(\.\d+)?$/', $var_value );
                $is_iso_8601   = (bool) preg_match( '/^P(?:\d+Y)?(?:\d+M)?(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:[\d.]+S)?)?$/', $var_value );
                return ( $is_1_2_format || $is_iso_8601 ) ? $var_value : '';

            // Anything else persistable — plain text field.
            default:
                return sanitize_text_field( $var_value );
        }
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
            $wpdb->prefix . 'courserelay_scorm_data',
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
            "SELECT id FROM {$wpdb->prefix}courserelay_completions
             WHERE user_id = %d AND content_id = %d AND registration = %s LIMIT 1",
            $user_id, $content_id, $registration
        ) );

        if ( $existing ) return (int) $existing;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert( $wpdb->prefix . 'courserelay_completions', array(
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
