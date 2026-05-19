<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Ajax {

    public static function init() {
        $actions = array(
            'ghm_save_room'          => 'save_room',
            'ghm_delete_room'        => 'delete_room',
            'ghm_get_room'           => 'get_room',
            'ghm_save_booking'       => 'save_booking',
            'ghm_cancel_booking'     => 'cancel_booking',
            'ghm_checkin'            => 'checkin_booking',
            'ghm_checkout'           => 'checkout_booking',
            'ghm_calc_amount'        => 'calc_amount',
            'ghm_check_availability' => 'check_availability',
            'ghm_save_customer'      => 'save_customer',
            'ghm_delete_customer'    => 'delete_customer',
            'ghm_get_customer'       => 'get_customer',
            'ghm_search_customers'   => 'search_customers',
            'ghm_record_payment'     => 'record_payment',
            'ghm_save_staff'         => 'save_staff',
            'ghm_delete_staff'       => 'delete_staff',
            'ghm_get_chart_data'     => 'get_chart_data',
        );

        foreach ( $actions as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
        }

        add_action( 'wp_ajax_nopriv_ghm_public_booking',      array( __CLASS__, 'public_booking' ) );
        add_action( 'wp_ajax_ghm_public_booking',             array( __CLASS__, 'public_booking' ) );
        add_action( 'wp_ajax_nopriv_ghm_check_availability',  array( __CLASS__, 'check_availability' ) );
        add_action( 'wp_ajax_nopriv_ghm_calc_amount',         array( __CLASS__, 'calc_amount' ) );
    }

    /**
     * Verify nonce and optionally check capability.
     * Returns true on success, sends JSON error and exits on failure.
     */
    private static function verify( $cap = '' ) {
        // Verify nonce
        $nonce_ok = check_ajax_referer( 'ghm_nonce', 'nonce', false );
        if ( ! $nonce_ok ) {
            wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
            exit;
        }

        // If no capability required, pass
        if ( empty( $cap ) ) return;

        // Administrators always pass
        if ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) return;

        // Check specific cap
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
            exit;
        }
    }

    /* ========== ROOMS ========== */

    public static function save_room() {
        self::verify( 'ghm_manage_rooms' );
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GHM_Rooms::save_room( $_POST, $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'id' => $result, 'message' => $id ? 'Room updated.' : 'Room created.' ) );
            exit;
        }
        exit;
    }

    public static function delete_room() {
        self::verify( 'ghm_manage_rooms' );
        $result = GHM_Rooms::delete_room( absint( $_POST['id'] ?? 0 ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'message' => 'Room deleted.' ) );
            exit;
        }
        exit;
    }

    public static function get_room() {
        self::verify();
        $room = GHM_Rooms::get_room( absint( $_POST['id'] ?? 0 ) );
        if ( $room ) {
            wp_send_json_success( $room );
            exit;
        } else {
            wp_send_json_error( array( 'message' => 'Room not found.' ) );
            exit;
        }
        exit;
    }

    /* ========== BOOKINGS ========== */

    public static function save_booking() {
        self::verify( 'ghm_manage_bookings' );
        $id = absint( $_POST['id'] ?? 0 );
        if ( $id ) {
            $result = GHM_Bookings::update_booking( $id, $_POST );
        } else {
            $result = GHM_Bookings::create_booking( $_POST );
        }
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'id' => $result ) );
            exit;
        }
        exit;
    }

    public static function cancel_booking() {
        self::verify( 'ghm_manage_bookings' );
        $result = GHM_Bookings::cancel_booking( absint( $_POST['id'] ?? 0 ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'message' => 'Booking cancelled.' ) );
            exit;
        }
        exit;
    }

    public static function checkin_booking() {
        self::verify( 'ghm_manage_bookings' );
        $result = GHM_Bookings::checkin_booking( absint( $_POST['id'] ?? 0 ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'message' => 'Guest checked in.' ) );
            exit;
        }
        exit;
    }

    public static function checkout_booking() {
        self::verify( 'ghm_manage_bookings' );
        $result = GHM_Bookings::checkout_booking( absint( $_POST['id'] ?? 0 ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'message' => 'Guest checked out.' ) );
            exit;
        }
        exit;
    }

    public static function calc_amount() {
        // No auth needed — public-facing too
        $amount = GHM_Bookings::calculate_amount(
            absint( $_POST['room_id'] ?? 0 ),
            sanitize_text_field( $_POST['check_in']  ?? '' ),
            sanitize_text_field( $_POST['check_out'] ?? '' ),
            sanitize_text_field( $_POST['booking_type'] ?? 'room' )
        );
        wp_send_json_success( array( 'amount' => $amount ) );
        exit;
    }

    public static function check_availability() {
        $available = GHM_Rooms::is_room_available(
            absint( $_POST['room_id']  ?? 0 ),
            sanitize_text_field( $_POST['check_in']  ?? '' ),
            sanitize_text_field( $_POST['check_out'] ?? '' ),
            absint( $_POST['exclude'] ?? 0 )
        );
        wp_send_json_success( array( 'available' => $available ) );
        exit;
    }

    /* ========== CUSTOMERS ========== */

    public static function save_customer() {
        self::verify( 'ghm_manage_customers' );
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GHM_Customers::save_customer( $_POST, $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'id' => $result ) );
            exit;
        }
        exit;
    }

    public static function delete_customer() {
        self::verify( 'ghm_manage_customers' );
        $result = GHM_Customers::delete_customer( absint( $_POST['id'] ?? 0 ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'message' => 'Customer deleted.' ) );
            exit;
        }
        exit;
    }

    public static function get_customer() {
        self::verify();
        $customer = GHM_Customers::get_customer( absint( $_POST['id'] ?? 0 ) );
        if ( $customer ) {
            wp_send_json_success( $customer );
            exit;
        } else {
            wp_send_json_error( array( 'message' => 'Customer not found.' ) );
            exit;
        }
        exit;
    }

    public static function search_customers() {
        self::verify();
        $results = GHM_Customers::get_customers( array(
            'search' => sanitize_text_field( $_POST['q'] ?? '' ),
            'limit'  => 10,
        ) );
        wp_send_json_success( $results );
        exit;
    }

    /* ========== PAYMENTS ========== */

    public static function record_payment() {
        self::verify( 'ghm_manage_payments' );
        $result = GHM_Payments::record_payment( $_POST );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'id' => $result, 'message' => 'Payment recorded.' ) );
            exit;
        }
        exit;
    }

    /* ========== STAFF ========== */

    public static function save_staff() {
        self::verify( 'ghm_manage_staff' );
        $id     = absint( $_POST['id'] ?? 0 );
        $result = $id ? GHM_Staff::update_staff( $id, $_POST ) : GHM_Staff::create_staff( $_POST );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        } else {
            wp_send_json_success( array( 'id' => $result ) );
            exit;
        }
        exit;
    }

    public static function delete_staff() {
        self::verify( 'ghm_manage_staff' );
        GHM_Staff::delete_staff( absint( $_POST['id'] ?? 0 ) );
        wp_send_json_success( array( 'message' => 'Staff member removed.' ) );
        exit;
    }

    /* ========== REPORTS ========== */

    public static function get_chart_data() {
        self::verify();
        $type = sanitize_text_field( $_POST['chart'] ?? 'revenue' );
        switch ( $type ) {
            case 'revenue':
                wp_send_json_success( GHM_Reports::get_revenue_chart( absint( $_POST['months'] ?? 6 ) ) );
                exit;
                break;
            case 'status':
                wp_send_json_success( GHM_Reports::get_booking_status_breakdown() );
                exit;
                break;
            case 'payment_methods':
                wp_send_json_success( GHM_Reports::get_payment_method_breakdown() );
                exit;
                break;
            default:
                wp_send_json_error( array( 'message' => 'Unknown chart type.' ) );
                exit;
        }
        exit;
    }

    /* ========== PUBLIC BOOKING ========== */

    public static function public_booking() {
        if ( ! check_ajax_referer( 'ghm_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
            exit;
        }

        $data = array_map( 'sanitize_text_field', $_POST );

        $customer = GHM_Customers::get_customer_by_email( $data['email'] ?? '' );
        if ( ! $customer ) {
            $customer_id = GHM_Customers::save_customer( array(
                'first_name' => $data['first_name'] ?? '',
                'last_name'  => $data['last_name']  ?? '',
                'email'      => $data['email']      ?? '',
                'phone'      => $data['phone']      ?? '',
            ) );
        } else {
            $customer_id = $customer->id;
        }

        if ( is_wp_error( $customer_id ) ) {
            wp_send_json_error( array( 'message' => $customer_id->get_error_message() ) );
            exit;
        }

        $amount = GHM_Bookings::calculate_amount( $data['room_id'] ?? 0, $data['check_in'] ?? '', $data['check_out'] ?? '', $data['booking_type'] ?? 'room' );

        // Apply discount if code provided
        $discount_amount = 0;
        $discount_id     = absint( $data['discount_id'] ?? 0 );
        if ( $discount_id && class_exists('GHM_Discounts') ) {
            $discount_amount = (float)( $data['discount_amount'] ?? 0 );
            $final_amount    = max( 0, round( $amount - $discount_amount, 2 ) );
            GHM_Discounts::apply( $discount_id );
        } else {
            $final_amount = $amount;
        }

        // Apply tax
        if ( class_exists('GHM_Tax') && GHM_Tax::get_tax_rate() > 0 && !GHM_Tax::is_inclusive() ) {
            $tax_calc    = GHM_Tax::calculate( $final_amount );
            $tax_amount  = $tax_calc['tax'];
            $final_amount= $tax_calc['total'];
        } else {
            $tax_amount = 0;
        }

        $booking_id = GHM_Bookings::create_booking( array_merge( $data, array(
            'customer_id'     => $customer_id,
            'total_amount'    => $final_amount,
            'discount_amount' => $discount_amount,
            'tax_amount'      => $tax_amount,
            'source'          => sanitize_text_field( $data['source'] ?? 'direct_website' ),
        ) ) );

        if ( is_wp_error( $booking_id ) ) {
            wp_send_json_error( array( 'message' => $booking_id->get_error_message() ) );
            exit;
        }

        $booking = GHM_Bookings::get_booking( $booking_id );
        wp_send_json_success( array( 'booking_ref' => $booking->booking_ref, 'amount' => $amount ) );
        exit;
    }
}
