<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Channels {

    public static function get_channels() {
        return array(
            'direct_website' => 'Direct — Website',
            'direct_phone'   => 'Direct — Phone Call',
            'walk_in'        => 'Walk-in',
            'booking_com'    => 'Booking.com',
            'airbnb'         => 'Airbnb',
            'expedia'        => 'Expedia',
            'google'         => 'Google Hotels',
            'whatsapp'       => 'WhatsApp',
            'referral'       => 'Referral',
            'corporate'      => 'Corporate / Company',
            'travel_agent'   => 'Travel Agent',
            'social_media'   => 'Social Media',
            'other'          => 'Other',
        );
    }

    public static function get_breakdown( $from = '', $to = '' ) {
        global $wpdb;
        $where = "source IS NOT NULL AND source != ''";
        if ( $from ) $where .= $wpdb->prepare(' AND created_at >= %s', $from);
        if ( $to )   $where .= $wpdb->prepare(' AND created_at <= %s', $to . ' 23:59:59');
        return $wpdb->get_results(
            "SELECT source, COUNT(*) AS bookings, SUM(total_amount) AS revenue
             FROM {$wpdb->prefix}ghm_bookings WHERE $where
             GROUP BY source ORDER BY bookings DESC"
        );
    }
}
