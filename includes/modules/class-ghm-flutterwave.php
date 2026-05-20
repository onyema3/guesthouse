<?php
/**
 * Flutterwave Payment Gateway for GuestHouse Manager
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
        add_action('wp_ajax_nopriv_ghm_flw_webhook',array(__CLASS__,'handle_webhook'));
        add_action('wp_ajax_ghm_flw_webhook',       array(__CLASS__,'handle_webhook'));
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
        wp_enqueue_script('flutterwave-inline','https://checkout.flutterwave.com/v3.js',array(),null,true);
        wp_localize_script('ghm-public','ghmFlutterwave',array(
            'enabled'    => true,
            'public_key' => self::public_key(),
            'currency'   => strtoupper(get_option('ghm_currency','NGN')),
        ));
    }

    public static function ajax_init() {
        if (!check_ajax_referer('ghm_public_nonce','nonce',false)) {
            wp_send_json_error(array('message'=>'Security check failed.')); exit;
        }
        if (!self::is_enabled()) { wp_send_json_error(array('message'=>'Flutterwave not configured.')); exit; }

        $data = array_map('sanitize_text_field', $_POST);

        // Find or create customer
        $customer = GHM_Customers::get_customer_by_email($data['email'] ?? '');
        if (!$customer) {
            $customer_id = GHM_Customers::save_customer(array(
                'first_name'=>$data['first_name']??'','last_name'=>$data['last_name']??'',
                'email'=>$data['email']??'','phone'=>$data['phone']??'',
            ));
            if (is_wp_error($customer_id)) { wp_send_json_error(array('message'=>$customer_id->get_error_message())); exit; }
        } else {
            $customer_id = $customer->id;
        }

        $amount     = GHM_Bookings::calculate_amount($data['room_id']??0,$data['check_in']??'',$data['check_out']??'',$data['booking_type']??'room');

        // Apply discount if provided
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

        $booking_id = GHM_Bookings::create_booking(array_merge($data,array(
            'customer_id'=>$customer_id,'total_amount'=>$amount,'discount_amount'=>$discount_amount,'status'=>'pending',
        )));

        if (is_wp_error($booking_id)) { wp_send_json_error(array('message'=>$booking_id->get_error_message())); exit; }
        $booking = GHM_Bookings::get_booking($booking_id);

        $tx_ref = 'GHM-FLW-'.$booking->booking_ref.'-'.time();
        set_transient('ghm_flw_ref_'.$tx_ref, $booking_id, 3*HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'tx_ref'      => $tx_ref,
            'booking_ref' => $booking->booking_ref,
            'booking_id'  => $booking_id,
            'amount'      => $amount,
            'currency'    => strtoupper(get_option('ghm_currency','NGN')),
            'email'       => $data['email'],
            'name'        => trim(($data['first_name']??'').' '.($data['last_name']??'')),
            'phone'       => $data['phone'] ?? '',
            'hotel_name'  => get_option('ghm_hotel_name',get_bloginfo('name')),
            'description' => 'Booking: '.$booking->booking_ref.' — '.$booking->room_name,
        ));
        exit;
    }

    public static function ajax_verify() {
        if (!check_ajax_referer('ghm_public_nonce','nonce',false)) {
            wp_send_json_error(array('message'=>'Security check failed.')); exit;
        }
        $tx_ref     = sanitize_text_field($_POST['tx_ref']    ?? '');
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $trx_id     = sanitize_text_field($_POST['transaction_id'] ?? '');

        if (!$tx_ref || !$booking_id) { wp_send_json_error(array('message'=>'Missing parameters.')); exit; }

        $result = self::verify_on_flutterwave($trx_id ?: $tx_ref);
        if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message())); exit; }

        if ($result['status'] !== 'successful') {
            GHM_Bookings::update_booking($booking_id, array('status'=>'cancelled','payment_status'=>'failed'));
            wp_send_json_error(array('message'=>'Payment was not successful.')); exit;
        }

        $amount_paid = (float)$result['amount'];
        GHM_Payments::record_payment(array(
            'booking_id'    =>$booking_id,'amount'=>$amount_paid,
            'currency'      =>$result['currency'] ?? get_option('ghm_currency','NGN'),
            'method'        =>'online','transaction_id'=>$tx_ref,
            'notes'         =>'Flutterwave: '.$result['payment_type'].' — '.$result['processor_response'],
        ));

        $booking = GHM_Bookings::get_booking($booking_id);
        wp_send_json_success(array('booking_ref'=>$booking->booking_ref,'amount'=>$amount_paid));
        exit;
    }

    /**
     * Verify a Flutterwave payment made for the balance on an EXISTING booking
     * (called from the guest portal). Records the payment and updates booking status.
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

    public static function handle_webhook() {
        $secret   = self::secret_key();
        $sig      = $_SERVER['HTTP_VERIF_HASH'] ?? '';
        if ($sig !== $secret) { http_response_code(401); exit('Unauthorized'); }

        $body  = json_decode(file_get_contents('php://input'), true);
        if (!$body || $body['event'] !== 'charge.completed') { http_response_code(200); exit('OK'); }

        $tx_ref     = $body['data']['tx_ref'] ?? '';
        $booking_id = (int)get_transient('ghm_flw_ref_'.$tx_ref);

        if ($booking_id > 0 && $body['data']['status'] === 'successful') {
            $booking = GHM_Bookings::get_booking($booking_id);
            if ($booking && $booking->payment_status !== 'paid') {
                GHM_Payments::record_payment(array(
                    'booking_id'    =>$booking_id,
                    'amount'        =>(float)$body['data']['amount'],
                    'currency'      =>$body['data']['currency'] ?? get_option('ghm_currency','NGN'),
                    'method'        =>'online','transaction_id'=>$tx_ref,
                    'notes'         =>'Flutterwave webhook',
                ));
                delete_transient('ghm_flw_ref_'.$tx_ref);
            }
        }
        http_response_code(200); exit('OK');
    }

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

    public static function webhook_url() {
        return add_query_arg('action','ghm_flw_webhook',admin_url('admin-ajax.php'));
    }
}

add_action('plugins_loaded', array('GHM_Flutterwave','init'), 20);
