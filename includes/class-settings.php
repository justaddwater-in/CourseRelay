<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Scormpleted_Settings {

    const OPTION_KEY = 'scormpleted_settings';

    private static $defaults = array(
        'width'              => '100%',
        'height'             => '600',
        'completion_type'    => 'hide_button',  // hide_button | hidden_until_complete | disable_until_complete | completion_move_nextlevel
        'new_window'         => '0',
        'allow_guest'        => '0',
        'debug'              => '0',   // Off by default — enable only when troubleshooting
    );

    public static function init() {
        // Nothing needed at init for settings — pages registered via Admin class
    }

    public static function get( $key = null, $default = null ) {
        $saved    = get_option( self::OPTION_KEY, array() );
        $settings = wp_parse_args( $saved, self::$defaults );

        if ( is_null( $key ) ) return $settings;
        return isset( $settings[ $key ] ) ? $settings[ $key ] : ( is_null( $default ) ? ( isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : '' ) : $default );
    }

    public static function save( $data ) {
        $clean = array();
        foreach ( self::$defaults as $key => $default ) {
            if ( isset( $data[ $key ] ) ) {
                $clean[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }
        update_option( self::OPTION_KEY, $clean );
    }

    public static function is_debug() {
        // Allow forcing debug via wp-config.php: define( 'SCORMPLETED_DEBUG', true );
        if ( defined( 'SCORMPLETED_DEBUG' ) && SCORMPLETED_DEBUG ) return true;
        return (bool) self::get( 'debug' );
    }
}
