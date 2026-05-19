<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   Tax Engine
================================================================ */
class GHM_Tax {

    public static function get_tax_rate() {
        return (float) get_option('ghm_tax_rate', 0);
    }

    public static function calculate( $amount ) {
        $rate = self::get_tax_rate();
        if ( $rate <= 0 ) return array('subtotal'=>$amount,'tax'=>0,'total'=>$amount);
        $tax = round($amount * ($rate/100), 2);
        return array('subtotal'=>$amount, 'tax'=>$tax, 'total'=>round($amount+$tax,2));
    }

    public static function get_label() {
        return get_option('ghm_tax_label', 'VAT');
    }

    public static function is_inclusive() {
        return (bool) get_option('ghm_tax_inclusive', 0);
    }
}

/* ================================================================
   Google Calendar Sync
================================================================ */
class GHM_GoogleCalendar {

    public static function init() {
        add_action('ghm_booking_created',   array(__CLASS__,'on_booking_event'), 10, 2);
        add_action('ghm_booking_confirmed', array(__CLASS__,'on_confirmed'));
        add_action('ghm_booking_cancelled', array(__CLASS__,'on_cancelled'));
    }

    public static function is_enabled() {
        return (bool) get_option('ghm_gcal_enabled',0)
            && get_option('ghm_gcal_access_token','')
            && get_option('ghm_gcal_calendar_id','');
    }

    public static function on_booking_event( $booking_id, $data ) {
        if ( ! self::is_enabled() ) return;
        $booking = GHM_Bookings::get_booking($booking_id);
        if ( ! $booking ) return;
        $event_id = self::create_event($booking);
        if ( $event_id ) {
            update_post_meta(0,'_ghm_gcal_'.$booking_id, $event_id); // fallback storage
            global $wpdb;
            $wpdb->update($wpdb->prefix.'ghm_bookings', array('notes'=>trim($booking->notes."\nGCal:".$event_id)), array('id'=>$booking_id));
        }
    }

    public static function on_confirmed( $booking_id ) {
        if ( ! self::is_enabled() ) return;
        // Update event color to confirmed
        $booking = GHM_Bookings::get_booking($booking_id);
        self::create_event($booking, '2'); // Green in Google Calendar
    }

    public static function on_cancelled( $booking_id ) {
        if ( ! self::is_enabled() ) return;
        // We re-create with "cancelled" in title rather than delete
        $booking = GHM_Bookings::get_booking($booking_id);
        self::create_event($booking, '11', true);
    }

    private static function create_event( $b, $color_id = '5', $cancelled = false ) {
        $token       = self::get_valid_token();
        if ( ! $token ) return false;
        $calendar_id = get_option('ghm_gcal_calendar_id','primary');
        $hotel       = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym         = get_option('ghm_currency_symbol','₦');

        $title       = ($cancelled ? '❌ CANCELLED — ' : '') . $b->room_name . ' — ' . $b->customer_name;
        $description = "Booking Ref: {$b->booking_ref}\n"
                     . "Guest: {$b->customer_name} ({$b->customer_email})\n"
                     . "Room: {$b->room_name} ({$b->room_number})\n"
                     . "Total: {$sym}" . number_format($b->total_amount,2) . "\n"
                     . "Status: " . ucfirst($b->status);

        $event = array(
            'summary'     => $title,
            'description' => $description,
            'colorId'     => $color_id,
            'start'       => array('date' => date('Y-m-d', strtotime($b->check_in))),
            'end'         => array('date' => date('Y-m-d', strtotime($b->check_out))),
        );

        $response = wp_remote_post(
            "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendar_id) . "/events",
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => json_encode($event),
                'timeout' => 15,
            )
        );

        if ( is_wp_error($response) ) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['id'] ?? false;
    }

    public static function get_valid_token() {
        $token   = get_option('ghm_gcal_access_token','');
        $expires = (int) get_option('ghm_gcal_token_expires', 0);

        if ( $token && time() < $expires - 60 ) return $token;

        // Refresh
        $refresh = get_option('ghm_gcal_refresh_token','');
        if ( ! $refresh ) return false;

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id'     => get_option('ghm_gcal_client_id',''),
                'client_secret' => get_option('ghm_gcal_client_secret',''),
                'refresh_token' => $refresh,
                'grant_type'    => 'refresh_token',
            ),
        ));

        if ( is_wp_error($response) ) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ( empty($data['access_token']) ) return false;

        update_option('ghm_gcal_access_token',   $data['access_token']);
        update_option('ghm_gcal_token_expires',  time() + ($data['expires_in'] ?? 3600));
        return $data['access_token'];
    }

    public static function get_auth_url() {
        $client_id    = get_option('ghm_gcal_client_id','');
        $redirect_uri = admin_url('admin.php?page=ghm-settings&ghm_gcal_callback=1');
        $scope        = 'https://www.googleapis.com/auth/calendar';
        return "https://accounts.google.com/o/oauth2/v2/auth?client_id={$client_id}&redirect_uri=" . urlencode($redirect_uri) . "&scope=" . urlencode($scope) . "&response_type=code&access_type=offline&prompt=consent";
    }

    public static function handle_oauth_callback() {
        if ( empty($_GET['ghm_gcal_callback']) || empty($_GET['code']) ) return;
        $code         = sanitize_text_field($_GET['code']);
        $client_id    = get_option('ghm_gcal_client_id','');
        $client_secret= get_option('ghm_gcal_client_secret','');
        $redirect_uri = admin_url('admin.php?page=ghm-settings&ghm_gcal_callback=1');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
        ));

        if ( ! is_wp_error($response) ) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ( ! empty($data['access_token']) ) {
                update_option('ghm_gcal_access_token',   $data['access_token']);
                update_option('ghm_gcal_refresh_token',  $data['refresh_token'] ?? '');
                update_option('ghm_gcal_token_expires',  time() + ($data['expires_in'] ?? 3600));
            }
        }
        wp_redirect(admin_url('admin.php?page=ghm-settings&ghm_gcal_connected=1'));
        exit;
    }
}

add_action('plugins_loaded', array('GHM_GoogleCalendar','init'), 25);
add_action('admin_init',     array('GHM_GoogleCalendar','handle_oauth_callback'));
