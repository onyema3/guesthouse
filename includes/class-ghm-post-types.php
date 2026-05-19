<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Post_Types {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        // Nothing needed here — all data in custom tables
        // But register rewrite tags for booking confirmation pages
        add_rewrite_tag( '%ghm_booking_ref%', '([^&]+)' );
        add_rewrite_rule( 'booking-confirmation/([^/]+)/?$', 'index.php?ghm_booking_ref=$matches[1]', 'top' );
    }
}
