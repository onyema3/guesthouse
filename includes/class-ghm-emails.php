<?php
/**
 * Email notifications for GuestHouse Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Emails {

    public static function init() {
        add_action( 'ghm_booking_created',      array( __CLASS__, 'on_booking_created' ), 10, 2 );
        add_action( 'ghm_booking_cancelled',    array( __CLASS__, 'on_booking_cancelled' ) );
        add_action( 'ghm_booking_checked_out',  array( __CLASS__, 'on_checkout' ) );
        add_action( 'ghm_payment_recorded',     array( __CLASS__, 'on_payment' ), 10, 2 );
    }

    public static function on_booking_created( $booking_id, $data ) {
        if ( ! get_option( 'ghm_email_notify', 1 ) ) return;

        $booking  = GHM_Bookings::get_booking( $booking_id );
        $customer = GHM_Customers::get_customer( $booking->customer_id );
        if ( ! $booking || ! $customer ) return;

        $sym = get_option( 'ghm_currency_symbol', '$' );

        // Guest confirmation email
        $guest_subject = sprintf( __('[%s] Booking Confirmed – %s', 'guesthouse-manager'), get_option('ghm_hotel_name','GuestHouse'), $booking->booking_ref );
        $guest_body    = self::wrap( self::booking_table( $booking, $sym ), $guest_subject );
        wp_mail( $customer->email, $guest_subject, $guest_body, self::headers() );

        // Admin notification
        $admin_email   = get_option( 'ghm_admin_email', get_option('admin_email') );
        $admin_subject = sprintf( __('[New Booking] %s – %s', 'guesthouse-manager'), $booking->booking_ref, $booking->customer_name );
        wp_mail( $admin_email, $admin_subject, $guest_body, self::headers() );
    }

    public static function on_booking_cancelled( $booking_id ) {
        $booking  = GHM_Bookings::get_booking( $booking_id );
        $customer = GHM_Customers::get_customer( $booking->customer_id );
        if ( ! $booking || ! $customer ) return;

        $sym     = get_option( 'ghm_currency_symbol', '$' );
        $subject = sprintf( __('[%s] Booking Cancelled – %s', 'guesthouse-manager'), get_option('ghm_hotel_name','GuestHouse'), $booking->booking_ref );
        $content = '<p>Your booking <strong>' . esc_html($booking->booking_ref) . '</strong> has been cancelled.</p>' . self::booking_table( $booking, $sym );
        wp_mail( $customer->email, $subject, self::wrap($content, $subject), self::headers() );
    }

    public static function on_checkout( $booking_id ) {
        $booking  = GHM_Bookings::get_booking( $booking_id );
        $customer = GHM_Customers::get_customer( $booking->customer_id );
        if ( ! $booking || ! $customer ) return;

        $sym     = get_option( 'ghm_currency_symbol', '$' );
        $subject = sprintf( __('[%s] Thank you for your stay!', 'guesthouse-manager'), get_option('ghm_hotel_name','GuestHouse') );
        $content = '<p>Dear ' . esc_html($booking->customer_name) . ',</p>
                    <p>Thank you for staying with us. We hope you had a wonderful experience. We look forward to welcoming you again!</p>'
                    . self::booking_table( $booking, $sym );
        wp_mail( $customer->email, $subject, self::wrap($content, $subject), self::headers() );
    }

    public static function on_payment( $payment_id, $data ) {
        global $wpdb;
        $payment  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ghm_payments WHERE id = %d", $payment_id ) );
        $booking  = GHM_Bookings::get_booking( $payment->booking_id );
        $customer = GHM_Customers::get_customer( $payment->customer_id );
        if ( ! $booking || ! $customer ) return;

        $sym     = get_option( 'ghm_currency_symbol', '$' );
        $subject = sprintf( __('[%s] Payment Receipt – %s', 'guesthouse-manager'), get_option('ghm_hotel_name','GuestHouse'), $booking->booking_ref );
        $content = '<p>Dear ' . esc_html( $booking->customer_name ) . ',</p>
                    <p>We have received a payment of <strong>' . $sym . number_format($payment->amount, 2) . '</strong> for booking <strong>' . esc_html($booking->booking_ref) . '</strong>.</p>
                    <table style="width:100%;border-collapse:collapse;font-size:14px;">
                      <tr><td style="padding:7px 0;color:#6b7280;">Method</td><td style="padding:7px 0;">' . ucfirst($payment->method) . '</td></tr>
                      <tr><td style="padding:7px 0;color:#6b7280;">Transaction ID</td><td style="padding:7px 0;">' . esc_html($payment->transaction_id ?: 'N/A') . '</td></tr>
                      <tr><td style="padding:7px 0;color:#6b7280;">Outstanding Balance</td><td style="padding:7px 0;color:' . ($booking->payment_status === 'paid' ? '#166534' : '#991b1b') . ';">' . $sym . number_format(max(0, $booking->total_amount - $booking->paid_amount), 2) . '</td></tr>
                    </table>';
        wp_mail( $customer->email, $subject, self::wrap($content, $subject), self::headers() );
    }

    /* ---- Helpers ---- */

    private static function booking_table( $booking, $sym ) {
        return '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">
          <tr style="background:#f9fafb;"><td style="padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;">Booking Reference</td><td style="padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#c9a84c;">' . esc_html($booking->booking_ref) . '</td></tr>
          <tr><td style="padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;">Room / Space</td><td style="padding:10px 12px;border:1px solid #e5e7eb;">' . esc_html($booking->room_name) . ' (' . esc_html($booking->room_number) . ')</td></tr>
          <tr style="background:#f9fafb;"><td style="padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;">Check-In</td><td style="padding:10px 12px;border:1px solid #e5e7eb;">' . date_i18n('l, F j, Y \a\t g:i A', strtotime($booking->check_in)) . '</td></tr>
          <tr><td style="padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;">Check-Out</td><td style="padding:10px 12px;border:1px solid #e5e7eb;">' . date_i18n('l, F j, Y \a\t g:i A', strtotime($booking->check_out)) . '</td></tr>
          <tr style="background:#f9fafb;"><td style="padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;">Total Amount</td><td style="padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#c9a84c;">' . $sym . number_format($booking->total_amount, 2) . '</td></tr>
          <tr><td style="padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;">Payment Status</td><td style="padding:10px 12px;border:1px solid #e5e7eb;">' . ucfirst($booking->payment_status) . '</td></tr>
        </table>';
    }

    private static function wrap( $content, $title ) {
        $hotel = get_option( 'ghm_hotel_name', get_bloginfo('name') );
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:DM Sans,sans-serif;">
          <div style="max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
            <div style="background:linear-gradient(135deg,#1a1a2e,#2a3055);padding:28px 32px;">
              <h1 style="color:#e8c97a;font-family:Georgia,serif;font-size:22px;margin:0;">' . esc_html($hotel) . '</h1>
              <p style="color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px;">' . esc_html($title) . '</p>
            </div>
            <div style="padding:28px 32px;color:#374151;font-size:14px;line-height:1.6;">' . $content . '</div>
            <div style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">
              ' . esc_html($hotel) . ' &mdash; ' . esc_html(get_bloginfo('url')) . '
            </div>
          </div>
        </body></html>';
    }

    private static function headers() {
        return array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('ghm_hotel_name','GuestHouse') . ' <' . get_option('ghm_admin_email', get_option('admin_email')) . '>',
        );
    }
}

// Init emails
add_action( 'plugins_loaded', array( 'GHM_Emails', 'init' ), 20 );
