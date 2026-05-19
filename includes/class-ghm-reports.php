<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Reports {

    public static function get_dashboard_stats() {
        global $wpdb;
        $today = date( 'Y-m-d' );

        return array(
            'total_rooms'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms WHERE type != 'workspace'" ),
            'total_workspaces'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms WHERE type = 'workspace'" ),
            'available_rooms'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms WHERE status = 'available' AND type != 'workspace'" ),
            'occupied_rooms'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms WHERE status = 'occupied'" ),
            'total_bookings'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings" ),
            'active_bookings'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE status IN ('booked','confirmed','checked_in')" ),
            'booked_unpaid'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE status = 'booked' AND payment_status != 'paid'" ),
            'confirmed_paid'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE status = 'confirmed'" ),
            'checkins_today'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE DATE(check_in) = %s AND status != 'cancelled'", $today ) ),
            'checkouts_today'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE DATE(check_out) = %s AND status != 'cancelled'", $today ) ),
            'total_customers'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_customers" ),
            'total_staff'        => GHM_Staff::count_staff(),
            'revenue_today'      => (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$wpdb->prefix}ghm_payments WHERE status='completed' AND DATE(created_at) = %s", $today ) ),
            'revenue_this_month' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$wpdb->prefix}ghm_payments WHERE status='completed' AND MONTH(created_at) = %d AND YEAR(created_at) = %d", date('n'), date('Y') ) ),
            'pending_payments'   => (float) $wpdb->get_var( "SELECT SUM(total_amount - paid_amount) FROM {$wpdb->prefix}ghm_bookings WHERE payment_status IN ('unpaid','partial') AND status NOT IN ('cancelled')" ),
        );
    }

    public static function get_revenue_chart( $months = 6 ) {
        global $wpdb;
        $data = array();
        for ( $i = $months - 1; $i >= 0; $i-- ) {
            $month  = date( 'n', strtotime( "-$i months" ) );
            $year   = date( 'Y', strtotime( "-$i months" ) );
            $label  = date( 'M Y', strtotime( "-$i months" ) );
            $revenue = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(amount) FROM {$wpdb->prefix}ghm_payments WHERE status='completed' AND MONTH(created_at)=%d AND YEAR(created_at)=%d",
                $month, $year
            ) );
            $bookings = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE MONTH(created_at)=%d AND YEAR(created_at)=%d AND status != 'cancelled'",
                $month, $year
            ) );
            $data[] = array( 'label' => $label, 'revenue' => $revenue, 'bookings' => $bookings );
        }
        return $data;
    }

    public static function get_occupancy_rate( $from = '', $to = '' ) {
        global $wpdb;
        if ( ! $from ) $from = date( 'Y-m-01' );
        if ( ! $to )   $to   = date( 'Y-m-t' );

        $total_rooms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms WHERE type != 'workspace' AND status != 'inactive'" );
        if ( ! $total_rooms ) return 0;

        $days = ( new DateTime($from) )->diff( new DateTime($to) )->days + 1;
        $total_room_nights = $total_rooms * $days;

        $booked_nights = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(DATEDIFF(LEAST(check_out, %s), GREATEST(check_in, %s)))
             FROM {$wpdb->prefix}ghm_bookings
             WHERE status NOT IN ('cancelled','no_show')
             AND booking_type = 'room'
             AND check_in <= %s AND check_out >= %s",
            $to . ' 23:59:59', $from, $to . ' 23:59:59', $from
        ) );

        return $total_room_nights > 0 ? round( ( $booked_nights / $total_room_nights ) * 100, 1 ) : 0;
    }

    public static function get_top_rooms( $limit = 5 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.name, r.room_number, r.type,
             COUNT(b.id) AS bookings,
             SUM(p.amount) AS revenue
             FROM {$wpdb->prefix}ghm_rooms r
             LEFT JOIN {$wpdb->prefix}ghm_bookings b ON b.room_id = r.id AND b.status != 'cancelled'
             LEFT JOIN {$wpdb->prefix}ghm_payments p ON p.booking_id = b.id AND p.status = 'completed'
             GROUP BY r.id ORDER BY bookings DESC LIMIT %d",
            $limit
        ) );
    }

    public static function get_booking_status_breakdown() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT status, COUNT(*) AS count FROM {$wpdb->prefix}ghm_bookings GROUP BY status"
        );
    }

    public static function get_payment_method_breakdown() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT method, COUNT(*) AS count, SUM(amount) AS total FROM {$wpdb->prefix}ghm_payments WHERE status='completed' GROUP BY method"
        );
    }

    public static function get_recent_activity( $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, u.display_name FROM {$wpdb->prefix}ghm_activity_log l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             ORDER BY l.created_at DESC LIMIT %d",
            $limit
        ) );
    }
}
