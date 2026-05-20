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
     * Creates a pending booking, then returns Paystack metadata so the
     * frontend can call PaystackPop.newTransaction().
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

        // 1. Find or create customer
        $customer = GHM_Customers::get_customer_by_email( $data['email'] ?? '' );
        if ( ! $customer ) {
            $customer_id = GHM_Customers::save_customer( array(
                'first_name' => $data['first_name'] ?? '',
                'last_name'  => $data['last_name']  ?? '',
                'email'      => $data['email']       ?? '',
                'phone'      => $data['phone']       ?? '',
                'country'    => $data['country']     ?? '',
            ) );
            if ( is_wp_error( $customer_id ) ) {
                wp_send_json_error( array( 'message' => $customer_id->get_error_message() ) );
                exit;
            }
        } else {
            $customer_id = $customer->id;
        }

        // 2. Calculate amount
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

        // 2b. Apply discount if provided
        $discount_amount = 0;
        $discount_id     = absint( $data['discount_id'] ?? 0 );
        if ( $discount_id && class_exists('GHM_Discounts') ) {
            $discount_amount = (float)( $data['discount_amount'] ?? 0 );
            if ( $discount_amount > 0 && $discount_amount <= $amount ) {
                $amount = round( $amount - $discount_amount, 2 );
                GHM_Discounts::apply( $discount_id );
            }
        }

        // Also accept pre-calculated total from frontend if discount was applied client-side
        $client_total = (float)( $data['total_amount'] ?? 0 );
        if ( $client_total > 0 && $client_total <= $amount ) {
            $amount = $client_total;
        }

        // 3. Create a PENDING booking
        $booking_id = GHM_Bookings::create_booking( array_merge( $data, array(
            'customer_id'     => $customer_id,
            'total_amount'    => $amount,
            'discount_amount' => $discount_amount,
            'status'          => 'pending',
            'payment_status'  => 'unpaid',
        ) ) );

        if ( is_wp_error( $booking_id ) ) {
            wp_send_json_error( array( 'message' => $booking_id->get_error_message() ) );
            exit;
        }

        $booking  = GHM_Bookings::get_booking( $booking_id );
        $currency = strtoupper( get_option( 'ghm_currency', 'NGN' ) );

        // Paystack amount is in kobo/pesewas/cents (smallest unit × 100)
        $ps_amount = intval( round( $amount * 100 ) );

        // Store booking_id in Paystack metadata so webhook can retrieve it
        $reference = 'GHM-' . $booking->booking_ref . '-' . time();
        update_post_meta( 0, '_ghm_ps_ref_' . $reference, $booking_id ); // won't work — use transient
        set_transient( 'ghm_ps_ref_' . $reference, $booking_id, 3 * HOUR_IN_SECONDS );

        wp_send_json_success( array(
            'reference'   => $reference,
            'booking_ref' => $booking->booking_ref,
            'booking_id'  => $booking_id,
            'amount'      => $ps_amount,
            'currency'    => $currency,
            'email'       => $data['email'],
            'name'        => trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ),
            'hotel_name'  => get_option( 'ghm_hotel_name', get_bloginfo('name') ),
            'meta'        => array(
                'booking_ref' => $booking->booking_ref,
                'room'        => $booking->room_name,
                'check_in'    => $data['check_in'],
                'check_out'   => $data['check_out'],
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

        $reference  = sanitize_text_field( $_POST['reference'] ?? '' );
        $booking_id = absint( $_POST['booking_id'] ?? 0 );

        if ( ! $reference || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Missing reference or booking ID.' ) );
            exit;
        }

        $result = self::verify_on_paystack( $reference );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            exit;
        }

        if ( $result['status'] !== 'success' ) {
            // Mark booking as cancelled
            GHM_Bookings::update_booking( $booking_id, array( 'status' => 'cancelled', 'payment_status' => 'failed' ) );
            wp_send_json_error( array( 'message' => 'Payment was not successful. Please try again.' ) );
            exit;
        }

        // record_payment will auto-confirm the booking via maybe_confirm_on_payment()
        $amount_paid = $result['amount'] / 100; // convert from kobo back to naira/dollars

        GHM_Payments::record_payment( array(
            'booking_id'     => $booking_id,
            'amount'         => $amount_paid,
            'currency'       => $result['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
            'method'         => 'online',
            'transaction_id' => $reference,
            'notes'          => 'Paystack online payment. Channel: ' . ( $result['channel'] ?? 'unknown' ),
        ) );

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
            $reference  = $event['data']['reference'] ?? '';
            $booking_id = (int) get_transient( 'ghm_ps_ref_' . $reference );

            if ( $booking_id > 0 ) {
                $booking = GHM_Bookings::get_booking( $booking_id );
                if ( $booking && $booking->payment_status !== 'paid' ) {
                    // record_payment auto-confirms when fully paid
                    $amount_paid = ( $event['data']['amount'] ?? 0 ) / 100;
                    GHM_Payments::record_payment( array(
                        'booking_id'     => $booking_id,
                        'amount'         => $amount_paid,
                        'currency'       => $event['data']['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
                        'method'         => 'online',
                        'transaction_id' => $reference,
                        'notes'          => 'Paystack webhook: charge.success',
                    ) );
                    delete_transient( 'ghm_ps_ref_' . $reference );
                }
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
