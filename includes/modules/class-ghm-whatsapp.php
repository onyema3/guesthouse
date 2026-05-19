<?php
/**
 * WhatsApp Notifications for GuestHouse Manager
 * Supports: Twilio WhatsApp API, WhatsApp Cloud API (Meta), ultramsg
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_WhatsApp {

    public static function init() {
        add_action( 'ghm_booking_created',     array( __CLASS__, 'on_booking_created' ), 10, 2 );
        add_action( 'ghm_booking_confirmed',   array( __CLASS__, 'on_booking_confirmed' ) );
        add_action( 'ghm_booking_cancelled',   array( __CLASS__, 'on_booking_cancelled' ) );
        add_action( 'ghm_booking_checked_out', array( __CLASS__, 'on_checkout' ) );
        add_action( 'ghm_payment_recorded',    array( __CLASS__, 'on_payment' ), 10, 2 );

        // Daily reminder cron
        add_action( 'ghm_whatsapp_reminders',  array( __CLASS__, 'send_checkin_reminders' ) );
        if ( ! wp_next_scheduled('ghm_whatsapp_reminders') ) {
            wp_schedule_event( strtotime('08:00:00'), 'daily', 'ghm_whatsapp_reminders' );
        }
    }

    /* ── Config ─────────────────────────────────────────────────── */

    public static function is_enabled() {
        return (bool) get_option('ghm_wa_enabled', 0) && self::get_provider();
    }

    public static function get_provider() {
        return get_option('ghm_wa_provider', ''); // 'twilio', 'meta', 'ultramsg'
    }

    /* ── Send Message ────────────────────────────────────────────── */

    public static function send( $phone, $message ) {
        if ( ! self::is_enabled() ) return false;
        if ( empty($phone) ) return false;

        $phone = self::normalise_phone( $phone );
        $provider = self::get_provider();

        switch ( $provider ) {
            case 'twilio':   return self::send_twilio( $phone, $message );
            case 'meta':     return self::send_meta( $phone, $message );
            case 'ultramsg': return self::send_ultramsg( $phone, $message );
            default:         return false;
        }
    }

    /* ── Providers ───────────────────────────────────────────────── */

    private static function send_twilio( $phone, $message ) {
        $sid   = get_option('ghm_wa_twilio_sid', '');
        $token = get_option('ghm_wa_twilio_token', '');
        $from  = get_option('ghm_wa_twilio_from', 'whatsapp:+14155238886');
        if ( ! $sid || ! $token ) return false;

        $response = wp_remote_post(
            "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode("{$sid}:{$token}"),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'From' => $from,
                    'To'   => 'whatsapp:' . $phone,
                    'Body' => $message,
                ),
                'timeout' => 20,
            )
        );
        $code = wp_remote_retrieve_response_code( $response );
        self::log( $phone, $message, $code );
        return $code === 201;
    }

    private static function send_meta( $phone, $message ) {
        $token    = get_option('ghm_wa_meta_token', '');
        $phone_id = get_option('ghm_wa_meta_phone_id', '');
        if ( ! $token || ! $phone_id ) return false;

        $response = wp_remote_post(
            "https://graph.facebook.com/v18.0/{$phone_id}/messages",
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => json_encode( array(
                    'messaging_product' => 'whatsapp',
                    'to'                => ltrim($phone, '+'),
                    'type'              => 'text',
                    'text'              => array( 'body' => $message ),
                ) ),
                'timeout' => 20,
            )
        );
        $code = wp_remote_retrieve_response_code( $response );
        self::log( $phone, $message, $code );
        return $code === 200;
    }

    private static function send_ultramsg( $phone, $message ) {
        $instance = get_option('ghm_wa_ultramsg_instance', '');
        $token    = get_option('ghm_wa_ultramsg_token', '');
        if ( ! $instance || ! $token ) return false;

        $response = wp_remote_post(
            "https://api.ultramsg.com/{$instance}/messages/chat",
            array(
                'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
                'body'    => array(
                    'token'   => $token,
                    'to'      => $phone,
                    'body'    => $message,
                ),
                'timeout' => 20,
            )
        );
        $code = wp_remote_retrieve_response_code( $response );
        self::log( $phone, $message, $code );
        return $code === 200;
    }

    /* ── Message Templates ───────────────────────────────────────── */

    public static function on_booking_created( $booking_id, $data ) {
        if ( ! self::is_enabled() ) return;
        $b      = GHM_Bookings::get_booking( $booking_id );
        $c      = GHM_Customers::get_customer( $b->customer_id );
        if ( ! $b || ! $c || ! $c->phone ) return;

        $hotel  = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym    = get_option('ghm_currency_symbol', '₦');
        $msg    = "🏨 *{$hotel}*\n\n"
                . "Hello {$c->first_name}! Your reservation has been received.\n\n"
                . "📋 *Booking:* {$b->booking_ref}\n"
                . "🛏️ *Room:* {$b->room_name}\n"
                . "📅 *Check-in:* " . date('D, M j Y', strtotime($b->check_in)) . "\n"
                . "📅 *Check-out:* " . date('D, M j Y', strtotime($b->check_out)) . "\n"
                . "💰 *Total:* {$sym}" . number_format($b->total_amount, 2) . "\n\n"
                . "Please note: Status is *Booked*. Payment confirms your reservation.\n"
                . "Quote ref *{$b->booking_ref}* when contacting us.";

        self::send( $c->phone, $msg );

        // Notify admin too
        $admin_phone = get_option('ghm_wa_admin_phone', '');
        if ( $admin_phone ) {
            $admin_msg = "🔔 New booking received!\n"
                       . "Guest: {$c->first_name} {$c->last_name}\n"
                       . "Room: {$b->room_name}\n"
                       . "Check-in: " . date('M j', strtotime($b->check_in)) . " → " . date('M j', strtotime($b->check_out)) . "\n"
                       . "Ref: {$b->booking_ref}";
            self::send( $admin_phone, $admin_msg );
        }
    }

    public static function on_booking_confirmed( $booking_id ) {
        if ( ! self::is_enabled() ) return;
        $b = GHM_Bookings::get_booking( $booking_id );
        $c = GHM_Customers::get_customer( $b->customer_id );
        if ( ! $b || ! $c || ! $c->phone ) return;

        $hotel = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym   = get_option('ghm_currency_symbol', '₦');
        $msg   = "✅ *{$hotel}* — Booking Confirmed!\n\n"
               . "Your booking *{$b->booking_ref}* is now CONFIRMED.\n\n"
               . "🛏️ {$b->room_name}\n"
               . "📅 " . date('D, M j Y', strtotime($b->check_in)) . " → " . date('D, M j Y', strtotime($b->check_out)) . "\n"
               . "💳 Payment received: {$sym}" . number_format($b->paid_amount, 2) . "\n\n"
               . "We look forward to welcoming you! 🙏";
        self::send( $c->phone, $msg );
    }

    public static function on_booking_cancelled( $booking_id ) {
        if ( ! self::is_enabled() ) return;
        $b = GHM_Bookings::get_booking( $booking_id );
        $c = GHM_Customers::get_customer( $b->customer_id );
        if ( ! $b || ! $c || ! $c->phone ) return;

        $hotel = get_option('ghm_hotel_name', get_bloginfo('name'));
        $msg   = "❌ *{$hotel}*\n\n"
               . "Your booking *{$b->booking_ref}* has been cancelled.\n"
               . "If you did not request this, please contact us immediately.";
        self::send( $c->phone, $msg );
    }

    public static function on_checkout( $booking_id ) {
        if ( ! self::is_enabled() ) return;
        $b = GHM_Bookings::get_booking( $booking_id );
        $c = GHM_Customers::get_customer( $b->customer_id );
        if ( ! $b || ! $c || ! $c->phone ) return;

        $hotel = get_option('ghm_hotel_name', get_bloginfo('name'));
        $msg   = "👋 *{$hotel}*\n\n"
               . "Thank you for staying with us, {$c->first_name}!\n"
               . "We hope you had a wonderful experience.\n\n"
               . "We'd love your feedback — it helps us serve you better. 🌟\n"
               . "We look forward to welcoming you again!";
        self::send( $c->phone, $msg );
    }

    public static function on_payment( $payment_id, $data ) {
        if ( ! self::is_enabled() ) return;
        global $wpdb;
        $payment = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ghm_payments WHERE id=%d", $payment_id) );
        if ( ! $payment ) return;
        $b = GHM_Bookings::get_booking( $payment->booking_id );
        $c = GHM_Customers::get_customer( $payment->customer_id );
        if ( ! $b || ! $c || ! $c->phone ) return;

        $hotel   = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym     = get_option('ghm_currency_symbol', '₦');
        $balance = max(0, (float)$b->total_amount - (float)$b->paid_amount);
        $msg     = "💳 *{$hotel}* — Payment Receipt\n\n"
                 . "Payment of *{$sym}" . number_format($payment->amount, 2) . "* received.\n"
                 . "Booking: *{$b->booking_ref}*\n"
                 . "Method: " . ucfirst(str_replace('_',' ',$payment->method)) . "\n";
        if ( $balance > 0 ) {
            $msg .= "Balance due: {$sym}" . number_format($balance, 2) . "\n";
        } else {
            $msg .= "✅ Fully paid!\n";
        }
        self::send( $c->phone, $msg );
    }

    public static function send_checkin_reminders() {
        if ( ! self::is_enabled() ) return;
        global $wpdb;
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $bookings  = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone, c.email
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
             WHERE DATE(b.check_in) = %s AND b.status IN ('booked','confirmed')
             AND c.phone IS NOT NULL AND c.phone != ''",
            $tomorrow
        ) );

        $hotel = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym   = get_option('ghm_currency_symbol', '₦');
        $ci_time = get_option('ghm_checkin_time', '14:00');

        foreach ( $bookings as $b ) {
            $msg = "⏰ *{$hotel}* — Arrival Reminder\n\n"
                 . "Hi {$b->first_name}! Your check-in is *tomorrow*.\n\n"
                 . "🛏️ {$b->room_name}\n"
                 . "🕒 Check-in from: {$ci_time}\n"
                 . "📋 Ref: {$b->booking_ref}\n\n"
                 . "Please bring a valid ID. See you soon! 🙏";
            self::send( $b->phone, $msg );
        }
    }

    /* ── Helpers ─────────────────────────────────────────────────── */

    public static function normalise_phone( $phone ) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if ( substr($phone, 0, 1) === '0' ) {
            $country_code = get_option('ghm_wa_country_code', '234');
            $phone = '+' . $country_code . substr($phone, 1);
        } elseif ( substr($phone, 0, 1) !== '+' ) {
            $phone = '+' . $phone;
        }
        return $phone;
    }

    private static function log( $phone, $message, $code ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ghm_activity_log', array(
            'action'      => 'whatsapp_sent',
            'object_type' => 'whatsapp',
            'details'     => json_encode( array('phone'=>$phone,'status'=>$code,'preview'=>substr($message,0,80)) ),
            'created_at'  => current_time('mysql'),
        ) );
    }
}

add_action( 'plugins_loaded', array('GHM_WhatsApp','init'), 25 );
