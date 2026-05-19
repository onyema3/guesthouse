<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Payments {

    public static function get_payments( $args = array() ) {
        global $wpdb;
        $defaults = array( 'booking_id' => 0, 'limit' => 20, 'offset' => 0 );
        $args     = wp_parse_args( $args, $defaults );
        $where    = '1=1';
        if ( $args['booking_id'] ) {
            $where .= $wpdb->prepare( ' AND p.booking_id = %d', $args['booking_id'] );
        }
        $limit = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );

        return $wpdb->get_results(
            "SELECT p.*, b.booking_ref,
             CONCAT(c.first_name,' ',c.last_name) AS customer_name
             FROM {$wpdb->prefix}ghm_payments p
             LEFT JOIN {$wpdb->prefix}ghm_bookings b  ON b.id = p.booking_id
             LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = p.customer_id
             WHERE $where ORDER BY p.created_at DESC $limit"
        );
    }

    /**
     * Record a payment against a booking.
     *
     * After saving the payment:
     *  - Updates paid_amount and payment_status on the booking.
     *  - Calls GHM_Bookings::maybe_confirm_on_payment() which upgrades
     *    status from 'booked' → 'confirmed' when fully paid.
     */
    public static function record_payment( $data ) {
        global $wpdb;

        $booking = GHM_Bookings::get_booking( absint( $data['booking_id'] ?? 0 ) );
        if ( ! $booking ) {
            return new WP_Error( 'not_found', 'Booking not found.' );
        }

        $amount = (float)( $data['amount'] ?? 0 );
        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', 'Payment amount must be greater than zero.' );
        }

        // --- 1. Insert payment record ---
        $fields = array(
            'booking_id'      => $booking->id,
            'customer_id'     => $booking->customer_id,
            'amount'          => $amount,
            'currency'        => sanitize_text_field( $data['currency']       ?? get_option( 'ghm_currency', 'NGN' ) ),
            'method'          => sanitize_text_field( $data['method']         ?? 'cash' ),
            'status'          => 'completed',
            'transaction_id'  => sanitize_text_field( $data['transaction_id'] ?? '' ),
            'notes'           => sanitize_textarea_field( $data['notes']      ?? '' ),
            'created_by'      => get_current_user_id(),
        );

        $wpdb->insert( $wpdb->prefix . 'ghm_payments', $fields );
        $payment_id = $wpdb->insert_id;

        // --- 2. Recalculate paid_amount on booking ---
        $new_paid   = round( (float) $booking->paid_amount + $amount, 2 );
        $total      = (float) $booking->total_amount;
        $pay_status = $new_paid >= $total && $total > 0 ? 'paid' : 'partial';

        $wpdb->update(
            $wpdb->prefix . 'ghm_bookings',
            array(
                'paid_amount'    => $new_paid,
                'payment_status' => $pay_status,
            ),
            array( 'id' => $booking->id )
        );

        // --- 3. Auto-confirm booking when fully paid ---
        GHM_Bookings::maybe_confirm_on_payment( $booking->id );

        // --- 4. Update customer lifetime spend ---
        GHM_Customers::update_customer_spent( $booking->customer_id );

        do_action( 'ghm_payment_recorded', $payment_id, $fields );

        return $payment_id;
    }

    public static function get_total_revenue( $from = '', $to = '' ) {
        global $wpdb;
        $where = "status = 'completed'";
        if ( $from ) $where .= $wpdb->prepare( ' AND created_at >= %s', $from );
        if ( $to )   $where .= $wpdb->prepare( ' AND created_at <= %s', $to . ' 23:59:59' );
        return (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}ghm_payments WHERE $where" );
    }

    public static function get_payment_methods() {
        return array(
            'cash'          => __( 'Cash',                'guesthouse-manager' ),
            'card'          => __( 'Credit/Debit Card',   'guesthouse-manager' ),
            'bank_transfer' => __( 'Bank Transfer',       'guesthouse-manager' ),
            'mobile_money'  => __( 'Mobile Money',        'guesthouse-manager' ),
            'online'        => __( 'Online (Paystack)',   'guesthouse-manager' ),
            'other'         => __( 'Other',               'guesthouse-manager' ),
        );
    }
}
