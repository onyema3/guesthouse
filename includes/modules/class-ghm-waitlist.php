<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Waitlist {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_waitlist (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id     BIGINT UNSIGNED NOT NULL,
            first_name  VARCHAR(100) NOT NULL,
            last_name   VARCHAR(100) NOT NULL,
            email       VARCHAR(200) NOT NULL,
            phone       VARCHAR(50),
            check_in    DATE NOT NULL,
            check_out   DATE NOT NULL,
            adults      INT NOT NULL DEFAULT 1,
            status      VARCHAR(20) NOT NULL DEFAULT 'waiting',
            notified_at DATETIME DEFAULT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY room_id (room_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function add( $data ) {
        global $wpdb;
        $fields = array(
            'room_id'    => absint( $data['room_id'] ),
            'first_name' => sanitize_text_field( $data['first_name'] ),
            'last_name'  => sanitize_text_field( $data['last_name'] ),
            'email'      => sanitize_email( $data['email'] ),
            'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
            'check_in'   => sanitize_text_field( $data['check_in'] ),
            'check_out'  => sanitize_text_field( $data['check_out'] ),
            'adults'     => absint( $data['adults'] ?? 1 ),
        );
        $wpdb->insert( $wpdb->prefix . 'ghm_waitlist', $fields );
        return $wpdb->insert_id;
    }

    public static function get_for_room( $room_id, $check_in, $check_out ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_waitlist WHERE room_id=%d AND status='waiting'
             AND check_in <= %s AND check_out >= %s ORDER BY created_at ASC",
            $room_id, $check_out, $check_in
        ) );
    }

    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT w.*, r.name AS room_name, r.room_number FROM {$wpdb->prefix}ghm_waitlist w
             LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = w.room_id
             WHERE w.status = 'waiting' ORDER BY w.created_at ASC"
        );
    }

    public static function notify_on_cancellation( $booking_id ) {
        $booking = GHM_Bookings::get_booking( $booking_id );
        if ( ! $booking ) return;
        $waiters = self::get_for_room( $booking->room_id, $booking->check_in, $booking->check_out );
        foreach ( $waiters as $w ) {
            self::send_notification( $w, $booking );
        }
    }

    private static function send_notification( $waiter, $booking ) {
        global $wpdb;
        $hotel   = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym     = get_option('ghm_currency_symbol', '₦');
        $subject = "[{$hotel}] A room just became available!";
        $body    = "<p>Dear {$waiter->first_name},</p>
                    <p>Great news! The <strong>{$booking->room_name}</strong> you were waiting for has just become available for your dates:</p>
                    <p><strong>Check-in:</strong> " . date_i18n('l, F j, Y', strtotime($waiter->check_in)) . "<br>
                    <strong>Check-out:</strong> " . date_i18n('l, F j, Y', strtotime($waiter->check_out)) . "</p>
                    <p>Book now before it fills up again: <a href='" . home_url() . "'>" . home_url() . "</a></p>";
        wp_mail( $waiter->email, $subject, $body, array('Content-Type: text/html; charset=UTF-8') );
        $wpdb->update( $wpdb->prefix.'ghm_waitlist', array('notified_at'=>current_time('mysql')), array('id'=>$waiter->id) );
    }
}

add_action( 'ghm_booking_cancelled', array('GHM_Waitlist','notify_on_cancellation') );
