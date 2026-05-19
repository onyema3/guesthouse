<?php
/**
 * GuestHouse Manager REST API
 * Endpoint base: /wp-json/ghm/v1/
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_REST_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'ghm/v1';

        // Rooms
        register_rest_route($ns, '/rooms',           array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_rooms'),        'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/rooms/(?P<id>\d+)',array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_room'),         'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/rooms/available', array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_available'),    'permission_callback'=>'__return_true'));

        // Bookings
        register_rest_route($ns, '/bookings',              array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_bookings'),   'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/bookings',              array('methods'=>'POST', 'callback'=>array(__CLASS__,'create_booking'), 'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/bookings/(?P<id>\d+)',  array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_booking'),    'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/bookings/(?P<id>\d+)',  array('methods'=>'PATCH','callback'=>array(__CLASS__,'update_booking'), 'permission_callback'=>array(__CLASS__,'auth')));

        // Customers
        register_rest_route($ns, '/customers',             array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_customers'),  'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/customers',             array('methods'=>'POST', 'callback'=>array(__CLASS__,'create_customer'),'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/customers/(?P<id>\d+)', array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_customer'),   'permission_callback'=>array(__CLASS__,'auth')));

        // Payments
        register_rest_route($ns, '/payments',              array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_payments'),   'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/payments',              array('methods'=>'POST', 'callback'=>array(__CLASS__,'create_payment'), 'permission_callback'=>array(__CLASS__,'auth')));

        // Reports
        register_rest_route($ns, '/reports/summary',       array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_summary'),    'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/reports/revenue',       array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_revenue'),    'permission_callback'=>array(__CLASS__,'auth')));

        // Housekeeping
        register_rest_route($ns, '/housekeeping',          array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_housekeeping'),'permission_callback'=>array(__CLASS__,'auth')));
        register_rest_route($ns, '/housekeeping/(?P<room_id>\d+)', array('methods'=>'PATCH','callback'=>array(__CLASS__,'update_housekeeping'),'permission_callback'=>array(__CLASS__,'auth')));

        // iCal export (public)
        register_rest_route($ns, '/ical/(?P<room_id>\d+)', array('methods'=>'GET',  'callback'=>array(__CLASS__,'get_ical'),       'permission_callback'=>'__return_true'));
    }

    /* ── Auth ────────────────────────────────────────────────────── */
    public static function auth( $request ) {
        $api_key = $request->get_header('X-GHM-API-Key') ?: ($request->get_param('api_key') ?? '');
        $stored  = get_option('ghm_api_key','');
        if ( $stored && $api_key === $stored ) return true;
        // Also accept WP auth
        return current_user_can('ghm_manage_bookings') || current_user_can('manage_options');
    }

    /* ── Rooms ───────────────────────────────────────────────────── */
    public static function get_rooms( $request ) {
        $args = array(
            'type'   => sanitize_text_field($request->get_param('type') ?? ''),
            'status' => sanitize_text_field($request->get_param('status') ?? ''),
            'limit'  => absint($request->get_param('limit') ?? 20),
        );
        return rest_ensure_response( GHM_Rooms::get_rooms($args) );
    }

    public static function get_room( $request ) {
        $room = GHM_Rooms::get_room( absint($request['id']) );
        if ( ! $room ) return new WP_Error('not_found','Room not found', array('status'=>404));
        return rest_ensure_response( $room );
    }

    public static function get_available( $request ) {
        $check_in  = sanitize_text_field($request->get_param('check_in')  ?? '');
        $check_out = sanitize_text_field($request->get_param('check_out') ?? '');
        $type      = sanitize_text_field($request->get_param('type')      ?? '');
        if ( ! $check_in || ! $check_out ) return new WP_Error('missing_dates','check_in and check_out required',array('status'=>400));
        return rest_ensure_response( GHM_Workspaces::get_available_rooms($check_in, $check_out, $type) );
    }

    /* ── Bookings ────────────────────────────────────────────────── */
    public static function get_bookings( $request ) {
        $args = array(
            'status'   => sanitize_text_field($request->get_param('status')   ?? ''),
            'limit'    => absint($request->get_param('limit')                 ?? 20),
            'offset'   => absint($request->get_param('offset')                ?? 0),
            'search'   => sanitize_text_field($request->get_param('search')   ?? ''),
        );
        $bookings = GHM_Bookings::get_bookings($args);
        $total    = GHM_Bookings::count_bookings($args);
        return rest_ensure_response( array(
            'data'  => $bookings,
            'total' => $total,
            'pages' => ceil($total / max(1,$args['limit'])),
        ) );
    }

    public static function get_booking( $request ) {
        $b = GHM_Bookings::get_booking( absint($request['id']) );
        if ( ! $b ) return new WP_Error('not_found','Booking not found',array('status'=>404));
        return rest_ensure_response($b);
    }

    public static function create_booking( $request ) {
        $data = $request->get_json_params() ?: $request->get_params();
        $result = GHM_Bookings::create_booking($data);
        if ( is_wp_error($result) ) return $result;
        return rest_ensure_response( array('id'=>$result,'message'=>'Booking created') );
    }

    public static function update_booking( $request ) {
        $data   = $request->get_json_params() ?: $request->get_params();
        $result = GHM_Bookings::update_booking( absint($request['id']), $data );
        if ( is_wp_error($result) ) return $result;
        return rest_ensure_response( array('message'=>'Updated') );
    }

    /* ── Customers ───────────────────────────────────────────────── */
    public static function get_customers( $request ) {
        return rest_ensure_response( GHM_Customers::get_customers(array(
            'search' => sanitize_text_field($request->get_param('search') ?? ''),
            'limit'  => absint($request->get_param('limit') ?? 20),
        )) );
    }

    public static function get_customer( $request ) {
        $c = GHM_Customers::get_customer( absint($request['id']) );
        if ( ! $c ) return new WP_Error('not_found','Customer not found',array('status'=>404));
        return rest_ensure_response($c);
    }

    public static function create_customer( $request ) {
        $data   = $request->get_json_params() ?: $request->get_params();
        $result = GHM_Customers::save_customer($data);
        if ( is_wp_error($result) ) return $result;
        return rest_ensure_response( array('id'=>$result) );
    }

    /* ── Payments ────────────────────────────────────────────────── */
    public static function get_payments( $request ) {
        return rest_ensure_response( GHM_Payments::get_payments(array(
            'limit' => absint($request->get_param('limit') ?? 20),
        )) );
    }

    public static function create_payment( $request ) {
        $data   = $request->get_json_params() ?: $request->get_params();
        $result = GHM_Payments::record_payment($data);
        if ( is_wp_error($result) ) return $result;
        return rest_ensure_response( array('id'=>$result,'message'=>'Payment recorded') );
    }

    /* ── Reports ─────────────────────────────────────────────────── */
    public static function get_summary( $request ) {
        return rest_ensure_response( GHM_Reports::get_dashboard_stats() );
    }

    public static function get_revenue( $request ) {
        $months = absint($request->get_param('months') ?? 6);
        return rest_ensure_response( GHM_Reports::get_revenue_chart($months) );
    }

    /* ── Housekeeping ────────────────────────────────────────────── */
    public static function get_housekeeping( $request ) {
        return rest_ensure_response( GHM_Housekeeping::get_all() );
    }

    public static function update_housekeeping( $request ) {
        $data = $request->get_json_params() ?: $request->get_params();
        $id   = GHM_Housekeeping::upsert( absint($request['room_id']), $data );
        return rest_ensure_response( array('id'=>$id,'message'=>'Housekeeping updated') );
    }

    /* ── iCal ────────────────────────────────────────────────────── */
    public static function get_ical( $request ) {
        $room_id  = absint($request['room_id']);
        $room     = GHM_Rooms::get_room($room_id);
        if ( ! $room ) return new WP_Error('not_found','Room not found',array('status'=>404));

        global $wpdb;
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_bookings WHERE room_id=%d AND status NOT IN ('cancelled','no_show') ORDER BY check_in ASC",
            $room_id
        ) );

        $ical  = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//GuestHouse Manager//EN\r\n";
        $ical .= "X-WR-CALNAME:" . self::ical_escape($room->name) . " — Bookings\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";

        foreach ( $bookings as $b ) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . $b->booking_ref . "@ghm\r\n";
            $ical .= "DTSTART;VALUE=DATE:" . date('Ymd', strtotime($b->check_in)) . "\r\n";
            $ical .= "DTEND;VALUE=DATE:"   . date('Ymd', strtotime($b->check_out)) . "\r\n";
            $ical .= "SUMMARY:" . self::ical_escape("Booked — " . ucfirst($b->status)) . "\r\n";
            $ical .= "DESCRIPTION:Ref: " . $b->booking_ref . "\r\n";
            $ical .= "STATUS:" . ( in_array($b->status,array('confirmed','checked_in')) ? 'CONFIRMED' : 'TENTATIVE' ) . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        return new WP_REST_Response( $ical, 200, array(
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . sanitize_title($room->name) . '.ics"',
        ) );
    }

    private static function ical_escape( $str ) {
        return addcslashes( str_replace(array("\r\n","\n","\r"), "\\n", $str), ',;\\' );
    }
}

add_action( 'plugins_loaded', array('GHM_REST_API','init'), 25 );
