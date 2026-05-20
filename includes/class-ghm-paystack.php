<?php
/**
 * Paystack Payment Gateway for GuestHouse Manager
 * Uses the REDIRECT flow (not inline popup) to avoid CSP issues.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Paystack {

    const API_BASE = 'https://api.paystack.co';

    public static function init() {
        // AJAX: initialise transaction (redirect flow)
        add_action( 'wp_ajax_ghm_paystack_init',        array( __CLASS__, 'ajax_init_transaction' ) );
        add_action( 'wp_ajax_nopriv_ghm_paystack_init', array( __CLASS__, 'ajax_init_transaction' ) );

        // AJAX: verify transaction (used by portal balance payments)
        add_action( 'wp_ajax_ghm_paystack_verify',        array( __CLASS__, 'ajax_verify_transaction' ) );
        add_action( 'wp_ajax_nopriv_ghm_paystack_verify', array( __CLASS__, 'ajax_verify_transaction' ) );

        // Callback endpoint: Paystack redirects back here after payment
        add_action( 'wp_ajax_ghm_paystack_callback',        array( __CLASS__, 'handle_callback' ) );
        add_action( 'wp_ajax_nopriv_ghm_paystack_callback', array( __CLASS__, 'handle_callback' ) );

        // AJAX: initialise balance payment from guest portal (redirect flow)
        add_action( 'wp_ajax_ghm_paystack_init_balance',        array( __CLASS__, 'ajax_init_balance' ) );
        add_action( 'wp_ajax_nopriv_ghm_paystack_init_balance', array( __CLASS__, 'ajax_init_balance' ) );

        // Webhook endpoint
        add_action( 'wp_ajax_nopriv_ghm_paystack_webhook', array( __CLASS__, 'handle_webhook' ) );
        add_action( 'wp_ajax_ghm_paystack_webhook',        array( __CLASS__, 'handle_webhook' ) );

        // Enqueue — no longer loads inline.js (not needed for redirect flow)
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
        // No external script needed for redirect flow!
        // Just pass config to the public JS so it knows Paystack is enabled
        wp_localize_script( 'ghm-public', 'ghmPaystack', array(
            'enabled'    => true,
            'public_key' => self::public_key(),
            'currency'   => strtoupper( get_option( 'ghm_currency', 'NGN' ) ),
            'mode'       => 'redirect',
        ) );
    }

    /* ── AJAX: Initialise (Redirect Flow) ─────────────────────── */

    /**
     * Validates booking data, calls Paystack Initialize Transaction API,
     * and returns the authorization_url for the frontend to redirect to.
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

        // Store booking data in transient
        $booking_data = $data;
        $booking_data['calculated_amount'] = $amount;
        $booking_data['discount_amount']   = $discount_amount;
        $booking_data['discount_id']       = $discount_id;
        set_transient( 'ghm_ps_pending_' . $reference, $booking_data, 3 * HOUR_IN_SECONDS );

        // Build callback URL (Paystack redirects here after payment)
        $callback_url = add_query_arg( 'action', 'ghm_paystack_callback', admin_url( 'admin-ajax.php' ) );

        // Call Paystack Initialize Transaction API
        $room = GHM_Rooms::get_room( absint( $data['room_id'] ?? 0 ) );
        $hotel_name = get_option( 'ghm_hotel_name', get_bloginfo('name') );

        $payload = array(
            'email'        => $data['email'] ?? '',
            'amount'       => $ps_amount,
            'currency'     => $currency,
            'reference'    => $reference,
            'callback_url' => $callback_url,
            'metadata'     => array(
                'custom_fields' => array(
                    array( 'display_name' => 'Hotel',     'variable_name' => 'hotel',     'value' => $hotel_name ),
                    array( 'display_name' => 'Room',      'variable_name' => 'room',      'value' => $room ? $room->name : '' ),
                    array( 'display_name' => 'Check-In',  'variable_name' => 'check_in',  'value' => $data['check_in'] ?? '' ),
                    array( 'display_name' => 'Check-Out', 'variable_name' => 'check_out', 'value' => $data['check_out'] ?? '' ),
                ),
            ),
        );

        $response = wp_remote_post( self::API_BASE . '/transaction/initialize', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . self::secret_key(),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Could not connect to Paystack. Please try again.' ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body || empty( $body['status'] ) || ! $body['status'] ) {
            $msg = $body['message'] ?? 'Paystack initialization failed.';
            wp_send_json_error( array( 'message' => $msg ) );
            exit;
        }

        // Return the authorization URL for the frontend to redirect to
        wp_send_json_success( array(
            'authorization_url' => $body['data']['authorization_url'],
            'reference'         => $body['data']['reference'] ?? $reference,
            'mode'              => 'redirect',
        ) );
        exit;
    }

    /* ── Callback: Paystack redirects back here ───────────────── */

    /**
     * After the guest pays on Paystack's hosted page, they are redirected
     * back to this URL with ?reference=XXX. We verify the payment, create
     * the booking, and redirect to a confirmation page.
     */
    public static function handle_callback() {
        $reference = sanitize_text_field( $_GET['reference'] ?? $_GET['trxref'] ?? '' );
        $is_balance = isset( $_GET['balance'] ) && $_GET['balance'] === '1';

        if ( ! $reference ) {
            wp_die( 'Missing payment reference. Please contact support.', 'Payment Error', array( 'response' => 400 ) );
        }

        // ── Balance payment callback ──
        if ( $is_balance ) {
            $balance_data = get_transient( 'ghm_ps_balance_' . $reference );
            if ( ! $balance_data ) {
                wp_redirect( home_url( '/?ghm_payment=already_processed&ref=' . urlencode($reference) ) );
                exit;
            }

            $result = self::verify_on_paystack( $reference );
            if ( is_wp_error( $result ) || ( $result['status'] ?? '' ) !== 'success' ) {
                wp_redirect( home_url( '/?ghm_payment=failed&gateway=paystack&ref=' . urlencode($reference) ) );
                exit;
            }

            $booking_id  = (int) $balance_data['booking_id'];
            $amount_paid = ( $result['amount'] ?? 0 ) / 100;

            GHM_Payments::record_payment( array(
                'booking_id'     => $booking_id,
                'amount'         => $amount_paid,
                'currency'       => $result['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
                'method'         => 'online',
                'transaction_id' => $reference,
                'notes'          => 'Paystack portal balance payment. Channel: ' . ( $result['channel'] ?? 'unknown' ),
            ) );

            delete_transient( 'ghm_ps_balance_' . $reference );

            $booking = GHM_Bookings::get_booking( $booking_id );
            wp_redirect( home_url( '/?ghm_payment=success&gateway=paystack&ref=' . urlencode($booking->booking_ref) . '&amount=' . $amount_paid ) );
            exit;
        }

        // ── New booking payment callback ──

        // Retrieve stored booking data
        $booking_data = get_transient( 'ghm_ps_pending_' . $reference );
        if ( ! $booking_data ) {
            // Already processed (maybe by webhook) — redirect to homepage
            wp_redirect( home_url( '/?ghm_payment=already_processed&ref=' . urlencode($reference) ) );
            exit;
        }

        // Verify on Paystack
        $result = self::verify_on_paystack( $reference );

        if ( is_wp_error( $result ) ) {
            wp_die( 'Payment verification failed: ' . esc_html( $result->get_error_message() ), 'Payment Error', array( 'response' => 500 ) );
        }

        if ( ( $result['status'] ?? '' ) !== 'success' ) {
            // Payment failed or was abandoned
            delete_transient( 'ghm_ps_pending_' . $reference );
            wp_redirect( home_url( '/?ghm_payment=failed&ref=' . urlencode($reference) ) );
            exit;
        }

        // Payment verified — create the booking
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
                wp_die( 'Payment received but booking creation failed. Contact support with reference: ' . esc_html($reference), 'Booking Error' );
            }
        } else {
            $customer_id = $customer->id;
        }

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

        if ( is_wp_error( $booking_id ) ) {
            wp_die( 'Payment received but booking creation failed. Contact support with reference: ' . esc_html($reference), 'Booking Error' );
        }

        $amount_paid = ( $result['amount'] ?? 0 ) / 100;

        GHM_Payments::record_payment( array(
            'booking_id'     => $booking_id,
            'amount'         => $amount_paid,
            'currency'       => $result['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
            'method'         => 'online',
            'transaction_id' => $reference,
            'notes'          => 'Paystack redirect payment. Channel: ' . ( $result['channel'] ?? 'unknown' ),
        ) );

        delete_transient( 'ghm_ps_pending_' . $reference );

        $booking = GHM_Bookings::get_booking( $booking_id );

        // Redirect to confirmation page with booking ref
        wp_redirect( home_url( '/?ghm_payment=success&gateway=paystack&ref=' . urlencode($booking->booking_ref) . '&amount=' . $amount_paid ) );
        exit;
    }

    /* ── AJAX: Verify (for portal balance payments) ───────────── */

    /**
     * Initialise a Paystack transaction for a balance payment on an existing booking.
     * Returns authorization_url for the portal to redirect to.
     */
    public static function ajax_init_balance() {
        if ( ! check_ajax_referer( 'ghm_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
            exit;
        }
        if ( ! self::is_enabled() ) {
            wp_send_json_error( array( 'message' => 'Paystack is not configured.' ) );
            exit;
        }

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $amount     = floatval( $_POST['amount'] ?? 0 );
        $email      = sanitize_email( $_POST['email'] ?? '' );
        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $ref        = sanitize_text_field( $_POST['ref'] ?? '' );

        if ( ! $booking_id || $amount <= 0 || ! $email ) {
            wp_send_json_error( array( 'message' => 'Missing required parameters.' ) );
            exit;
        }

        $booking = GHM_Bookings::get_booking( $booking_id );
        if ( ! $booking ) {
            wp_send_json_error( array( 'message' => 'Booking not found.' ) );
            exit;
        }

        $currency  = strtoupper( get_option( 'ghm_currency', 'NGN' ) );
        $ps_amount = intval( round( $amount * 100 ) );
        $reference = 'PORTAL-PS-' . strtoupper( wp_generate_password( 6, false ) ) . '-' . time();

        // Store booking_id in transient for callback verification
        set_transient( 'ghm_ps_balance_' . $reference, array(
            'booking_id' => $booking_id,
            'amount'     => $amount,
        ), 3 * HOUR_IN_SECONDS );

        // Build callback URL
        $callback_url = add_query_arg( array(
            'action' => 'ghm_paystack_callback',
            'balance' => '1',
        ), admin_url( 'admin-ajax.php' ) );

        $payload = array(
            'email'        => $email,
            'amount'       => $ps_amount,
            'currency'     => $currency,
            'reference'    => $reference,
            'callback_url' => $callback_url,
            'metadata'     => array(
                'custom_fields' => array(
                    array( 'display_name' => 'Booking Ref', 'variable_name' => 'booking_ref', 'value' => $ref ),
                    array( 'display_name' => 'Type',        'variable_name' => 'type',        'value' => 'balance_payment' ),
                ),
            ),
        );

        $response = wp_remote_post( self::API_BASE . '/transaction/initialize', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . self::secret_key(),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Could not connect to Paystack.' ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $body || empty( $body['status'] ) || ! $body['status'] ) {
            wp_send_json_error( array( 'message' => $body['message'] ?? 'Paystack initialization failed.' ) );
            exit;
        }

        wp_send_json_success( array(
            'authorization_url' => $body['data']['authorization_url'],
            'reference'         => $body['data']['reference'] ?? $reference,
        ) );
        exit;
    }

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

        // Payment verified — create booking
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

        $amount_paid = $result['amount'] / 100;

        GHM_Payments::record_payment( array(
            'booking_id'     => $booking_id,
            'amount'         => $amount_paid,
            'currency'       => $result['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
            'method'         => 'online',
            'transaction_id' => $reference,
            'notes'          => 'Paystack payment. Channel: ' . ( $result['channel'] ?? 'unknown' ),
        ) );

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

        return $body['data'];
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    public static function webhook_url() {
        return add_query_arg( 'action', 'ghm_paystack_webhook', admin_url( 'admin-ajax.php' ) );
    }

    public static function callback_url() {
        return add_query_arg( 'action', 'ghm_paystack_callback', admin_url( 'admin-ajax.php' ) );
    }
}

add_action( 'plugins_loaded', array( 'GHM_Paystack', 'init' ), 20 );
