<?php
/**
 * Paystack Payment Gateway for GuestHouse Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Paystack {

    const API_BASE = 'https://api.paystack.co';

    public static function init() {
        // AJAX: initialise transaction
        add_action( 'wp_ajax_ghm_paystack_init',        array( __CLASS__, 'ajax_init_transaction' ) );
        add_action( 'wp_ajax_nopriv_ghm_paystack_init', array( __CLASS__, 'ajax_init_transaction' ) );

        // AJAX: verify transaction after payment
        add_action( 'wp_ajax_ghm_paystack_verify',        array( __CLASS__, 'ajax_verify_transaction' ) );
        add_action( 'wp_ajax_nopriv_ghm_paystack_verify', array( __CLASS__, 'ajax_verify_transaction' ) );

        // Webhook endpoint
        add_action( 'wp_ajax_nopriv_ghm_paystack_webhook', array( __CLASS__, 'handle_webhook' ) );
        add_action( 'wp_ajax_ghm_paystack_webhook',        array( __CLASS__, 'handle_webhook' ) );

        // Enqueue Paystack JS on pages that have the booking shortcode
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
    }

    /* ── Keys ─────────────────────────────────────────────────── */

    public static function public_key() {
        $test = get_option( 'ghm_paystack_test_mode', 1 );
        return $test
            ? get_option( 'ghm_paystack_test_public_key', '' )
            : get_option( 'ghm_paystack_live_public_key', '' );
    }

    public static function secret_key() {
        $test = get_option( 'ghm_paystack_test_mode', 1 );
        return $test
            ? get_option( 'ghm_paystack_test_secret_key', '' )
            : get_option( 'ghm_paystack_live_secret_key', '' );
    }

    public static function is_enabled() {
        return (bool) get_option( 'ghm_paystack_enabled', 0 ) && self::public_key() && self::secret_key();
    }

    /* ── Enqueue ───────────────────────────────────────────────── */

    public static function maybe_enqueue() {
        if ( ! self::is_enabled() ) return;
        wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v2/inline.js', array(), null, true );
        // Pass keys and config to the public JS
        wp_localize_script( 'ghm-public', 'ghmPaystack', array(
            'enabled'    => true,
            'public_key' => self::public_key(),
            'currency'   => strtoupper( get_option( 'ghm_currency', 'NGN' ) ),
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'ghm_public_nonce' ),
        ) );
    }

    /* ── AJAX: Initialise ─────────────────────────────────────── */

    /**
     * Validates booking data and returns Paystack payment parameters.
     * Does NOT create a booking — that happens only after payment is verified.
     */
    public static function ajax_init_transaction() {
        if ( ! check_ajax_referer( 'ghm_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
            exit;
        }

        if ( ! self::is_enabled() ) {
            wp_send_json_error( array( 'message' => 'Paystack is not configured.' ) );
            exit;
        }

        $data = array_map( 'sanitize_text_field', $_POST );

        // 1. Calculate amount
        $amount = GHM_Bookings::calculate_amount(
            absint( $data['room_id'] ?? 0 ),
            $data['check_in']    ?? '',
            $data['check_out']   ?? '',
            $data['booking_type'] ?? 'room'
        );

        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => 'Could not calculate booking amount.' ) );
            exit;
        }

        // 2. Apply discount if provided
        $discount_amount = 0;
        $discount_id     = absint( $data['discount_id'] ?? 0 );
        if ( $discount_id && class_exists('GHM_Discounts') ) {
            $discount_amount = (float)( $data['discount_amount'] ?? 0 );
            if ( $discount_amount > 0 && $discount_amount <= $amount ) {
                $amount = round( $amount - $discount_amount, 2 );
            }
        }

        // Also accept pre-calculated total from frontend if discount was applied client-side
        $client_total = (float)( $data['total_amount'] ?? 0 );
        if ( $client_total > 0 && $client_total < $amount ) {
            $amount = $client_total;
        }

        // 3. Check room availability
        $room_available = GHM_Rooms::is_room_available(
            absint( $data['room_id'] ?? 0 ),
            $data['check_in']  ?? '',
            $data['check_out'] ?? ''
        );
        if ( ! $room_available ) {
            wp_send_json_error( array( 'message' => 'This room is no longer available for the selected dates.' ) );
            exit;
        }

        $currency  = strtoupper( get_option( 'ghm_currency', 'NGN' ) );
        $ps_amount = intval( round( $amount * 100 ) ); // kobo/pesewas/cents
        $reference = 'GHM-PS-' . strtoupper( wp_generate_password( 8, false ) ) . '-' . time();

        // Store booking data in transient so we can create the booking after payment verification
        $booking_data = $data;
        $booking_data['calculated_amount'] = $amount;
        $booking_data['discount_amount']   = $discount_amount;
        $booking_data['discount_id']       = $discount_id;
        set_transient( 'ghm_ps_pending_' . $reference, $booking_data, 3 * HOUR_IN_SECONDS );

        $room = GHM_Rooms::get_room( absint( $data['room_id'] ?? 0 ) );

        wp_send_json_success( array(
            'reference'   => $reference,
            'booking_ref' => '', // No booking yet — created after payment
            'booking_id'  => 0,
            'amount'      => $ps_amount,
            'currency'    => $currency,
            'email'       => $data['email'] ?? '',
            'name'        => trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ),
            'hotel_name'  => get_option( 'ghm_hotel_name', get_bloginfo('name') ),
            'meta'        => array(
                'booking_ref' => '',
                'room'        => $room ? $room->name : '',
                'check_in'    => $data['check_in'] ?? '',
                'check_out'   => $data['check_out'] ?? '',
            ),
        ) );
        exit;
    }

    /* ── AJAX: Verify ─────────────────────────────────────────── */

    public static function ajax_verify_transaction() {
        if ( ! check_ajax_referer( 'ghm_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
            exit;
        }

        $reference = sanitize_text_field( $_POST['reference'] ?? '' );

        if ( ! $reference ) {
            wp_send_json_error( array( 'message' => 'Missing payment reference.' ) );
            exit;
        }

        // Retrieve stored booking data
        $booking_data = get_transient( 'ghm_ps_pending_' . $reference );
        if ( ! $booking_data ) {
            wp_send_json_error( array( 'message' => 'Payment session expired. Please try again.' ) );
            exit;
        }

        $result = self::verify_on_paystack( $reference );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        }

        if ( $result['status'] !== 'success' ) {
            wp_send_json_error( array( 'message' => 'Payment was not successful. Please try again.' ) );
            exit;
        }

        // Payment verified — now create the booking
        $customer = GHM_Customers::get_customer_by_email( $booking_data['email'] ?? '' );
        if ( ! $customer ) {
            $customer_id = GHM_Customers::save_customer( array(
                'first_name' => $booking_data['first_name'] ?? '',
                'last_name'  => $booking_data['last_name']  ?? '',
                'email'      => $booking_data['email']       ?? '',
                'phone'      => $booking_data['phone']       ?? '',
                'country'    => $booking_data['country']     ?? '',
            ) );
            if ( is_wp_error( $customer_id ) ) {
                wp_send_json_error( array( 'message' => 'Payment received but booking creation failed. Contact support with reference: ' . $reference ) );
                exit;
            }
        } else {
            $customer_id = $customer->id;
        }

        $amount          = (float)( $booking_data['calculated_amount'] ?? 0 );
        $discount_amount = (float)( $booking_data['discount_amount']   ?? 0 );
        $discount_id     = absint( $booking_data['discount_id']        ?? 0 );

        // Apply discount usage count now that payment is confirmed
        if ( $discount_id && $discount_amount > 0 && class_exists('GHM_Discounts') ) {
            GHM_Discounts::apply( $discount_id );
        }

        $booking_id = GHM_Bookings::create_booking( array_merge( $booking_data, array(
            'customer_id'     => $customer_id,
            'total_amount'    => $amount,
            'discount_amount' => $discount_amount,
            'status'          => 'confirmed',
            'payment_status'  => 'paid',
        ) ) );

        if ( is_wp_error( $booking_id ) ) {
            wp_send_json_error( array( 'message' => 'Payment received but booking creation failed. Contact support with reference: ' . $reference ) );
            exit;
        }

        // Record payment
        $amount_paid = $result['amount'] / 100;

        GHM_Payments::record_payment( array(
            'booking_id'     => $booking_id,
            'amount'         => $amount_paid,
            'currency'       => $result['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
            'method'         => 'online',
            'transaction_id' => $reference,
            'notes'          => 'Paystack online payment. Channel: ' . ( $result['channel'] ?? 'unknown' ),
        ) );

        // Clean up transient
        delete_transient( 'ghm_ps_pending_' . $reference );

        $booking = GHM_Bookings::get_booking( $booking_id );

        wp_send_json_success( array(
            'booking_ref' => $booking->booking_ref,
            'amount'      => $amount_paid,
            'channel'     => $result['channel'] ?? 'card',
        ) );
        exit;
    }

    /* ── Webhook ──────────────────────────────────────────────── */

    public static function handle_webhook() {
        // Validate Paystack signature
        $secret    = self::secret_key();
        $body      = file_get_contents( 'php://input' );
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

        if ( hash_hmac( 'sha512', $body, $secret ) !== $signature ) {
            http_response_code( 400 );
            exit( 'Invalid signature' );
        }

        $event = json_decode( $body, true );
        if ( ! $event ) { http_response_code( 400 ); exit( 'Bad JSON' ); }

        $event_type = $event['event'] ?? '';

        if ( $event_type === 'charge.success' ) {
            $reference    = $event['data']['reference'] ?? '';
            $booking_data = get_transient( 'ghm_ps_pending_' . $reference );

            if ( $booking_data ) {
                // Create booking from stored data (payment confirmed via webhook)
                $customer = GHM_Customers::get_customer_by_email( $booking_data['email'] ?? '' );
                if ( ! $customer ) {
                    $customer_id = GHM_Customers::save_customer( array(
                        'first_name' => $booking_data['first_name'] ?? '',
                        'last_name'  => $booking_data['last_name']  ?? '',
                        'email'      => $booking_data['email']       ?? '',
                        'phone'      => $booking_data['phone']       ?? '',
                    ) );
                } else {
                    $customer_id = $customer->id;
                }

                if ( ! is_wp_error( $customer_id ) ) {
                    $amount          = (float)( $booking_data['calculated_amount'] ?? 0 );
                    $discount_amount = (float)( $booking_data['discount_amount']   ?? 0 );
                    $discount_id     = absint( $booking_data['discount_id']        ?? 0 );

                    if ( $discount_id && $discount_amount > 0 && class_exists('GHM_Discounts') ) {
                        GHM_Discounts::apply( $discount_id );
                    }

                    $booking_id = GHM_Bookings::create_booking( array_merge( $booking_data, array(
                        'customer_id'     => $customer_id,
                        'total_amount'    => $amount,
                        'discount_amount' => $discount_amount,
                        'status'          => 'confirmed',
                        'payment_status'  => 'paid',
                    ) ) );

                    if ( ! is_wp_error( $booking_id ) ) {
                        $amount_paid = ( $event['data']['amount'] ?? 0 ) / 100;
                        GHM_Payments::record_payment( array(
                            'booking_id'     => $booking_id,
                            'amount'         => $amount_paid,
                            'currency'       => $event['data']['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
                            'method'         => 'online',
                            'transaction_id' => $reference,
                            'notes'          => 'Paystack webhook: charge.success',
                        ) );
                    }
                }

                delete_transient( 'ghm_ps_pending_' . $reference );
            }
        }

        http_response_code( 200 );
        exit( 'OK' );
    }

    /* ── Server-side Verification ─────────────────────────────── */

    private static function verify_on_paystack( $reference ) {
        $secret = self::secret_key();
        if ( ! $secret ) return new WP_Error( 'no_key', 'Paystack secret key not configured.' );

        $response = wp_remote_get(
            self::API_BASE . '/transaction/verify/' . rawurlencode( $reference ),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) return $response;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $body || ! isset( $body['status'] ) ) {
            return new WP_Error( 'bad_response', 'Invalid response from Paystack.' );
        }
        if ( ! $body['status'] ) {
            return new WP_Error( 'paystack_error', $body['message'] ?? 'Paystack error.' );
        }

        return $body['data']; // Contains status, amount, currency, channel, etc.
    }

    /* ── Webhook URL helper ───────────────────────────────────── */

    public static function webhook_url() {
        return add_query_arg( 'action', 'ghm_paystack_webhook', admin_url( 'admin-ajax.php' ) );
    }
}

add_action( 'plugins_loaded', array( 'GHM_Paystack', 'init' ), 20 );
