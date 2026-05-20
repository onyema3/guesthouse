<?php
/**
 * Flutterwave Payment Gateway for GuestHouse Manager
 * Uses the REDIRECT flow (Standard Payment API) to avoid CSP issues.
 * Covers Nigeria, Ghana, Kenya, South Africa, Uganda, Tanzania and more.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Flutterwave {

    const API_BASE = 'https://api.flutterwave.com/v3';

    public static function init() {
        add_action('wp_ajax_ghm_flw_init',          array(__CLASS__,'ajax_init'));
        add_action('wp_ajax_nopriv_ghm_flw_init',   array(__CLASS__,'ajax_init'));
        add_action('wp_ajax_ghm_flw_verify',        array(__CLASS__,'ajax_verify'));
        add_action('wp_ajax_nopriv_ghm_flw_verify', array(__CLASS__,'ajax_verify'));
        add_action('wp_ajax_ghm_flw_verify_balance',        array(__CLASS__,'ajax_verify_balance'));
        add_action('wp_ajax_nopriv_ghm_flw_verify_balance', array(__CLASS__,'ajax_verify_balance'));

        // Callback endpoint: Flutterwave redirects back here after payment
        add_action('wp_ajax_ghm_flw_callback',        array(__CLASS__,'handle_callback'));
        add_action('wp_ajax_nopriv_ghm_flw_callback', array(__CLASS__,'handle_callback'));

        // Webhook
        add_action('wp_ajax_nopriv_ghm_flw_webhook',array(__CLASS__,'handle_webhook'));
        add_action('wp_ajax_ghm_flw_webhook',       array(__CLASS__,'handle_webhook'));

        // Enqueue — no external JS needed for redirect flow
        add_action('wp_enqueue_scripts',            array(__CLASS__,'maybe_enqueue'));
    }

    public static function public_key() {
        return get_option('ghm_flw_test_mode',1)
            ? get_option('ghm_flw_test_public_key','')
            : get_option('ghm_flw_live_public_key','');
    }

    public static function secret_key() {
        return get_option('ghm_flw_test_mode',1)
            ? get_option('ghm_flw_test_secret_key','')
            : get_option('ghm_flw_live_secret_key','');
    }

    public static function is_enabled() {
        return (bool)get_option('ghm_flw_enabled',0) && self::public_key() && self::secret_key();
    }

    public static function maybe_enqueue() {
        if ( !self::is_enabled() ) return;
        // No external script needed for redirect flow!
        wp_localize_script('ghm-public','ghmFlutterwave',array(
            'enabled'    => true,
            'public_key' => self::public_key(),
            'currency'   => strtoupper(get_option('ghm_currency','NGN')),
            'mode'       => 'redirect',
        ));
    }

    /* ── AJAX: Initialise (Redirect Flow) ─────────────────────── */

    /**
     * Validates booking data, calls Flutterwave Standard Payment API,
     * and returns the payment link for the frontend to redirect to.
     */
    public static function ajax_init() {
        if (!check_ajax_referer('ghm_public_nonce','nonce',false)) {
            wp_send_json_error(array('message'=>'Security check failed.')); exit;
        }
        if (!self::is_enabled()) { wp_send_json_error(array('message'=>'Flutterwave not configured.')); exit; }

        $data = array_map('sanitize_text_field', $_POST);

        $amount = GHM_Bookings::calculate_amount($data['room_id']??0,$data['check_in']??'',$data['check_out']??'',$data['booking_type']??'room');

        if ( $amount <= 0 ) {
            wp_send_json_error(array('message'=>'Could not calculate booking amount.')); exit;
        }

        // Apply discount if provided
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

        // Check room availability
        $room_available = GHM_Rooms::is_room_available(
            absint( $data['room_id'] ?? 0 ),
            $data['check_in']  ?? '',
            $data['check_out'] ?? ''
        );
        if ( ! $room_available ) {
            wp_send_json_error(array('message'=>'This room is no longer available for the selected dates.')); exit;
        }

        $tx_ref = 'GHM-FLW-' . strtoupper( wp_generate_password( 8, false ) ) . '-' . time();

        // Store booking data in transient
        $booking_data = $data;
        $booking_data['calculated_amount'] = $amount;
        $booking_data['discount_amount']   = $discount_amount;
        $booking_data['discount_id']       = $discount_id;
        set_transient('ghm_flw_pending_'.$tx_ref, $booking_data, 3*HOUR_IN_SECONDS);

        $room       = GHM_Rooms::get_room( absint( $data['room_id'] ?? 0 ) );
        $hotel_name = get_option('ghm_hotel_name', get_bloginfo('name'));
        $currency   = strtoupper(get_option('ghm_currency','NGN'));

        // Build callback URL
        $callback_url = add_query_arg( 'action', 'ghm_flw_callback', admin_url( 'admin-ajax.php' ) );

        // Call Flutterwave Standard Payment API
        $payload = array(
            'tx_ref'          => $tx_ref,
            'amount'          => $amount,
            'currency'        => $currency,
            'redirect_url'    => $callback_url,
            'payment_options' => 'card,banktransfer,ussd,mobilemoney',
            'customer'        => array(
                'email'        => $data['email'] ?? '',
                'name'         => trim(($data['first_name']??'').' '.($data['last_name']??'')),
                'phone_number' => $data['phone'] ?? '',
            ),
            'customizations'  => array(
                'title'       => $hotel_name,
                'description' => 'Booking — ' . ($room ? $room->name : ''),
            ),
            'meta'            => array(
                'booking_ref' => '',
                'tx_ref'      => $tx_ref,
            ),
        );

        $response = wp_remote_post( self::API_BASE . '/payments', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . self::secret_key(),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Could not connect to Flutterwave. Please try again.' ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body || ( $body['status'] ?? '' ) !== 'success' ) {
            $msg = $body['message'] ?? 'Flutterwave initialization failed.';
            wp_send_json_error( array( 'message' => $msg ) );
            exit;
        }

        // Return the payment link for the frontend to redirect to
        wp_send_json_success(array(
            'payment_link' => $body['data']['link'],
            'tx_ref'       => $tx_ref,
            'mode'         => 'redirect',
        ));
        exit;
    }

    /* ── Callback: Flutterwave redirects back here ────────────── */

    /**
     * After the guest pays on Flutterwave's hosted page, they are redirected
     * back to this URL with ?tx_ref=XXX&transaction_id=YYY&status=ZZZ.
     * We verify the payment, create the booking, and redirect to confirmation.
     */
    public static function handle_callback() {
        $tx_ref = sanitize_text_field( $_GET['tx_ref'] ?? '' );
        $trx_id = sanitize_text_field( $_GET['transaction_id'] ?? '' );
        $status = sanitize_text_field( $_GET['status'] ?? '' );

        if ( ! $tx_ref ) {
            wp_die( 'Missing transaction reference. Please contact support.', 'Payment Error', array( 'response' => 400 ) );
        }

        // If Flutterwave tells us it was cancelled
        if ( $status === 'cancelled' ) {
            wp_redirect( home_url( '/?ghm_payment=cancelled&gateway=flutterwave&ref=' . urlencode($tx_ref) ) );
            exit;
        }

        // Retrieve stored booking data
        $booking_data = get_transient( 'ghm_flw_pending_' . $tx_ref );
        if ( ! $booking_data ) {
            // Already processed (maybe by webhook)
            wp_redirect( home_url( '/?ghm_payment=already_processed&ref=' . urlencode($tx_ref) ) );
            exit;
        }

        // Verify on Flutterwave
        $result = self::verify_on_flutterwave( $trx_id ?: $tx_ref );

        if ( is_wp_error( $result ) ) {
            wp_die( 'Payment verification failed: ' . esc_html( $result->get_error_message() ), 'Payment Error', array( 'response' => 500 ) );
        }

        if ( ( $result['status'] ?? '' ) !== 'successful' ) {
            delete_transient( 'ghm_flw_pending_' . $tx_ref );
            wp_redirect( home_url( '/?ghm_payment=failed&gateway=flutterwave&ref=' . urlencode($tx_ref) ) );
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
            ) );
            if ( is_wp_error( $customer_id ) ) {
                wp_die( 'Payment received but booking creation failed. Contact support with ref: ' . esc_html($tx_ref), 'Booking Error' );
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
            wp_die( 'Payment received but booking creation failed. Contact support with ref: ' . esc_html($tx_ref), 'Booking Error' );
        }

        $amount_paid = (float) $result['amount'];

        GHM_Payments::record_payment( array(
            'booking_id'     => $booking_id,
            'amount'         => $amount_paid,
            'currency'       => $result['currency'] ?? get_option('ghm_currency','NGN'),
            'method'         => 'online',
            'transaction_id' => $tx_ref,
            'notes'          => 'Flutterwave redirect payment: ' . ($result['payment_type']??'') . ' — ' . ($result['processor_response']??''),
        ) );

        delete_transient( 'ghm_flw_pending_' . $tx_ref );

        $booking = GHM_Bookings::get_booking( $booking_id );

        // Redirect to confirmation
        wp_redirect( home_url( '/?ghm_payment=success&gateway=flutterwave&ref=' . urlencode($booking->booking_ref) . '&amount=' . $amount_paid ) );
        exit;
    }

    /* ── AJAX: Verify (inline flow fallback / portal) ─────────── */

    public static function ajax_verify() {
        if (!check_ajax_referer('ghm_public_nonce','nonce',false)) {
            wp_send_json_error(array('message'=>'Security check failed.')); exit;
        }
        $tx_ref     = sanitize_text_field($_POST['tx_ref']    ?? '');
        $trx_id     = sanitize_text_field($_POST['transaction_id'] ?? '');

        if (!$tx_ref) { wp_send_json_error(array('message'=>'Missing parameters.')); exit; }

        $booking_data = get_transient('ghm_flw_pending_'.$tx_ref);
        if ( !$booking_data ) {
            wp_send_json_error(array('message'=>'Payment session expired. Please try again.')); exit;
        }

        $result = self::verify_on_flutterwave($trx_id ?: $tx_ref);
        if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message())); exit; }

        if ($result['status'] !== 'successful') {
            wp_send_json_error(array('message'=>'Payment was not successful.')); exit;
        }

        // Payment verified — create booking
        $customer = GHM_Customers::get_customer_by_email($booking_data['email'] ?? '');
        if (!$customer) {
            $customer_id = GHM_Customers::save_customer(array(
                'first_name'=>$booking_data['first_name']??'','last_name'=>$booking_data['last_name']??'',
                'email'=>$booking_data['email']??'','phone'=>$booking_data['phone']??'',
            ));
            if (is_wp_error($customer_id)) {
                wp_send_json_error(array('message'=>'Payment received but booking creation failed. Contact support with ref: '.$tx_ref)); exit;
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

        $booking_id = GHM_Bookings::create_booking(array_merge($booking_data, array(
            'customer_id'=>$customer_id,'total_amount'=>$amount,'discount_amount'=>$discount_amount,
            'status'=>'confirmed','payment_status'=>'paid',
        )));

        if (is_wp_error($booking_id)) {
            wp_send_json_error(array('message'=>'Payment received but booking creation failed. Contact support with ref: '.$tx_ref)); exit;
        }

        $amount_paid = (float)$result['amount'];
        GHM_Payments::record_payment(array(
            'booking_id'    =>$booking_id,'amount'=>$amount_paid,
            'currency'      =>$result['currency'] ?? get_option('ghm_currency','NGN'),
            'method'        =>'online','transaction_id'=>$tx_ref,
            'notes'         =>'Flutterwave: '.($result['payment_type']??'').' — '.($result['processor_response']??''),
        ));

        delete_transient('ghm_flw_pending_'.$tx_ref);

        $booking = GHM_Bookings::get_booking($booking_id);
        wp_send_json_success(array('booking_ref'=>$booking->booking_ref,'amount'=>$amount_paid));
        exit;
    }

    /**
     * Verify balance payment from guest portal.
     */
    public static function ajax_verify_balance() {
        if ( ! check_ajax_referer( 'ghm_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) ); exit;
        }
        $booking_id = absint( $_POST['booking_id']     ?? 0 );
        $tx_ref     = sanitize_text_field( $_POST['tx_ref']         ?? '' );
        $trx_id     = sanitize_text_field( $_POST['transaction_id'] ?? '' );
        if ( ! $booking_id || ( ! $tx_ref && ! $trx_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing parameters.' ) ); exit;
        }

        $booking = GHM_Bookings::get_booking( $booking_id );
        if ( ! $booking ) {
            wp_send_json_error( array( 'message' => 'Booking not found.' ) ); exit;
        }

        $result = self::verify_on_flutterwave( $trx_id ?: $tx_ref );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) ); exit;
        }
        if ( ($result['status'] ?? '') !== 'successful' ) {
            wp_send_json_error( array( 'message' => 'Payment was not successful.' ) ); exit;
        }

        $amount_paid = (float) $result['amount'];
        GHM_Payments::record_payment( array(
            'booking_id'     => $booking_id,
            'amount'         => $amount_paid,
            'currency'       => $result['currency'] ?? get_option( 'ghm_currency', 'NGN' ),
            'method'         => 'online',
            'transaction_id' => $tx_ref ?: $trx_id,
            'notes'          => 'Flutterwave (portal balance): ' . ( $result['payment_type'] ?? '' )
                                . ' — ' . ( $result['processor_response'] ?? '' ),
        ) );

        $booking = GHM_Bookings::get_booking( $booking_id );
        wp_send_json_success( array(
            'booking_ref' => $booking->booking_ref,
            'amount'      => $amount_paid,
        ) );
        exit;
    }

    /* ── Webhook ──────────────────────────────────────────────── */

    public static function handle_webhook() {
        $secret   = self::secret_key();
        $sig      = $_SERVER['HTTP_VERIF_HASH'] ?? '';
        if ($sig !== $secret) { http_response_code(401); exit('Unauthorized'); }

        $body  = json_decode(file_get_contents('php://input'), true);
        if (!$body || $body['event'] !== 'charge.completed') { http_response_code(200); exit('OK'); }

        $tx_ref       = $body['data']['tx_ref'] ?? '';
        $booking_data = get_transient('ghm_flw_pending_'.$tx_ref);

        if ($booking_data && $body['data']['status'] === 'successful') {
            $customer = GHM_Customers::get_customer_by_email($booking_data['email'] ?? '');
            if (!$customer) {
                $customer_id = GHM_Customers::save_customer(array(
                    'first_name'=>$booking_data['first_name']??'','last_name'=>$booking_data['last_name']??'',
                    'email'=>$booking_data['email']??'','phone'=>$booking_data['phone']??'',
                ));
            } else {
                $customer_id = $customer->id;
            }

            if ( !is_wp_error($customer_id) ) {
                $amount          = (float)( $booking_data['calculated_amount'] ?? 0 );
                $discount_amount = (float)( $booking_data['discount_amount']   ?? 0 );
                $discount_id     = absint( $booking_data['discount_id']        ?? 0 );

                if ( $discount_id && $discount_amount > 0 && class_exists('GHM_Discounts') ) {
                    GHM_Discounts::apply( $discount_id );
                }

                $booking_id = GHM_Bookings::create_booking(array_merge($booking_data, array(
                    'customer_id'=>$customer_id,'total_amount'=>$amount,'discount_amount'=>$discount_amount,
                    'status'=>'confirmed','payment_status'=>'paid',
                )));

                if ( !is_wp_error($booking_id) ) {
                    GHM_Payments::record_payment(array(
                        'booking_id'    =>$booking_id,
                        'amount'        =>(float)$body['data']['amount'],
                        'currency'      =>$body['data']['currency'] ?? get_option('ghm_currency','NGN'),
                        'method'        =>'online','transaction_id'=>$tx_ref,
                        'notes'         =>'Flutterwave webhook',
                    ));
                }
            }

            delete_transient('ghm_flw_pending_'.$tx_ref);
        }
        http_response_code(200); exit('OK');
    }

    /* ── Server-side Verification ─────────────────────────────── */

    private static function verify_on_flutterwave($id) {
        $secret = self::secret_key();
        if (!$secret) return new WP_Error('no_key','Flutterwave secret key not configured.');

        $response = wp_remote_get(self::API_BASE.'/transactions/'.$id.'/verify', array(
            'headers'=>array('Authorization'=>'Bearer '.$secret,'Content-Type'=>'application/json'),
            'timeout'=>30,
        ));
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body || $body['status'] !== 'success') return new WP_Error('flw_error',$body['message']??'Flutterwave error.');
        return $body['data'];
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    public static function webhook_url() {
        return add_query_arg('action','ghm_flw_webhook',admin_url('admin-ajax.php'));
    }

    public static function callback_url() {
        return add_query_arg('action','ghm_flw_callback',admin_url('admin-ajax.php'));
    }
}

add_action('plugins_loaded', array('GHM_Flutterwave','init'), 20);
