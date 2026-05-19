<?php
/**
 * GHM Guest Portal
 * Guests log in with booking reference + email.
 * No WordPress account required.
 * Session-based authentication stored in PHP session / transients.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Guest_Portal {

    const SESSION_KEY  = 'ghm_guest_booking_id';
    const SESSION_LIFE = 3600; // 1 hour

    public static function init() {
        // Create tables
        add_action( 'init', array( __CLASS__, 'create_tables' ), 4 );

        // Shortcode
        add_shortcode( 'ghm_guest_portal', array( __CLASS__, 'render' ) );

        // AJAX – login
        add_action( 'wp_ajax_nopriv_ghm_portal_login',   array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_ghm_portal_login',           array( __CLASS__, 'ajax_login' ) );

        // AJAX – logout
        add_action( 'wp_ajax_nopriv_ghm_portal_logout',  array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_ghm_portal_logout',          array( __CLASS__, 'ajax_logout' ) );

        // AJAX – service request
        add_action( 'wp_ajax_nopriv_ghm_portal_service', array( __CLASS__, 'ajax_service_request' ) );
        add_action( 'wp_ajax_ghm_portal_service',         array( __CLASS__, 'ajax_service_request' ) );

        // AJAX – submit review
        add_action( 'wp_ajax_nopriv_ghm_portal_review',  array( __CLASS__, 'ajax_submit_review' ) );
        add_action( 'wp_ajax_ghm_portal_review',          array( __CLASS__, 'ajax_submit_review' ) );

        // Enqueue assets on pages that have the shortcode
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

        // Start PHP session early
        add_action( 'init', array( __CLASS__, 'start_session' ), 1 );
    }

    /* ── Session ─────────────────────────────────────────────────── */

    public static function start_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    public static function get_session_booking_id() {
        return $_SESSION[ self::SESSION_KEY ] ?? 0;
    }

    public static function set_session( $booking_id ) {
        $_SESSION[ self::SESSION_KEY ] = $booking_id;
    }

    public static function clear_session() {
        unset( $_SESSION[ self::SESSION_KEY ] );
    }

    /* ── Tables ──────────────────────────────────────────────────── */

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $service_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_service_requests (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id  BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            type        VARCHAR(100) NOT NULL,
            message     TEXT,
            status      VARCHAR(50) NOT NULL DEFAULT 'pending',
            resolved_at DATETIME DEFAULT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset;";

        $review_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_reviews (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id      BIGINT UNSIGNED NOT NULL,
            customer_id     BIGINT UNSIGNED NOT NULL,
            rating          TINYINT NOT NULL DEFAULT 5,
            cleanliness     TINYINT DEFAULT NULL,
            service         TINYINT DEFAULT NULL,
            comfort         TINYINT DEFAULT NULL,
            value           TINYINT DEFAULT NULL,
            title           VARCHAR(200),
            comment         TEXT,
            status          VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            UNIQUE KEY      booking_id (booking_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $service_sql );
        dbDelta( $review_sql );
    }

    /* ── Enqueue ─────────────────────────────────────────────────── */

    public static function enqueue() {
        // Only load on pages with the shortcode – check post content
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'ghm_guest_portal' ) ) return;

        wp_enqueue_style(  'ghm-public', GHM_PLUGIN_URL . 'public/css/ghm-public.css', array(), GHM_VERSION );
        wp_enqueue_style(  'ghm-portal', GHM_PLUGIN_URL . 'public/css/ghm-portal.css', array(), GHM_VERSION );
        wp_enqueue_script( 'ghm-portal', GHM_PLUGIN_URL . 'public/js/ghm-portal.js', array('jquery'), GHM_VERSION, true );
        wp_localize_script( 'ghm-portal', 'ghmPortal', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ghm_portal_nonce' ),
            'currency' => get_option( 'ghm_currency_symbol', '₦' ),
        ) );

        // Paystack for balance payment in portal
        if ( class_exists('GHM_Paystack') && GHM_Paystack::is_enabled() ) {
            wp_enqueue_script('paystack-inline','https://js.paystack.co/v2/inline.js',array(),null,true);
            wp_localize_script('ghm-portal','ghmPaystack',array(
                'enabled'    => true,
                'public_key' => GHM_Paystack::public_key(),
                'currency'   => strtoupper(get_option('ghm_currency','NGN')),
            ));
            // Also make available to ghm-public nonce for verify calls
            wp_localize_script('ghm-portal','ghmPublic',array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ghm_public_nonce'),
            ));
        }

        // Flutterwave for balance payment in portal
        if ( class_exists('GHM_Flutterwave') && GHM_Flutterwave::is_enabled() ) {
            wp_enqueue_script('flutterwave-inline','https://checkout.flutterwave.com/v3.js',array(),null,true);
            wp_localize_script('ghm-portal','ghmFlutterwave',array(
                'enabled'    => true,
                'public_key' => GHM_Flutterwave::public_key(),
                'currency'   => strtoupper(get_option('ghm_currency','NGN')),
            ));
            // Ensure the public nonce is available even if Paystack isn't enabled
            wp_localize_script('ghm-portal','ghmPublic',array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ghm_public_nonce'),
            ));
        }
    }

    /* ── AJAX: Login ─────────────────────────────────────────────── */

    public static function ajax_login() {
        check_ajax_referer( 'ghm_portal_nonce', 'nonce' );

        $ref   = strtoupper( sanitize_text_field( $_POST['booking_ref'] ?? '' ) );
        $email = sanitize_email( $_POST['email'] ?? '' );

        if ( ! $ref || ! $email ) {
            wp_send_json_error( array('message' => 'Please enter your booking reference and email address.') );
            exit;
        }

        $booking = GHM_Bookings::get_booking_by_ref( $ref );
        if ( ! $booking ) {
            wp_send_json_error( array('message' => 'Booking reference not found. Please check and try again.') );
            exit;
        }

        // Verify email matches customer
        $customer = GHM_Customers::get_customer( $booking->customer_id );
        if ( ! $customer || strtolower( trim($customer->email) ) !== strtolower( trim($email) ) ) {
            wp_send_json_error( array('message' => 'The email address does not match our records for this booking.') );
            exit;
        }

        // Check booking is not cancelled
        if ( $booking->status === 'cancelled' ) {
            wp_send_json_error( array('message' => 'This booking has been cancelled.') );
            exit;
        }

        self::set_session( $booking->id );
        wp_send_json_success( array('message' => 'Logged in.') );
        exit;
    }

    /* ── AJAX: Logout ────────────────────────────────────────────── */

    public static function ajax_logout() {
        self::clear_session();
        wp_send_json_success();
        exit;
    }

    /* ── AJAX: Service Request ───────────────────────────────────── */

    public static function ajax_service_request() {
        check_ajax_referer( 'ghm_portal_nonce', 'nonce' );

        $booking_id = self::get_session_booking_id();
        if ( ! $booking_id ) {
            wp_send_json_error( array('message' => 'Session expired. Please log in again.') );
            exit;
        }

        $booking = GHM_Bookings::get_booking( $booking_id );
        if ( ! $booking || $booking->status !== 'checked_in' ) {
            wp_send_json_error( array('message' => 'Service requests are only available during your stay.') );
            exit;
        }

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ghm_service_requests', array(
            'booking_id'  => $booking_id,
            'customer_id' => $booking->customer_id,
            'type'        => sanitize_text_field( $_POST['type'] ?? 'other' ),
            'message'     => sanitize_textarea_field( $_POST['message'] ?? '' ),
            'status'      => 'pending',
        ) );

        // Notify admin via WhatsApp if enabled
        if ( class_exists('GHM_WhatsApp') && GHM_WhatsApp::is_enabled() ) {
            $admin_phone = get_option('ghm_wa_admin_phone','');
            if ( $admin_phone ) {
                $type_label = ucfirst( str_replace('_',' ', sanitize_text_field($_POST['type']??'request') ) );
                $msg = "🔔 Service Request — Room {$booking->room_number}\n"
                     . "Guest: {$booking->customer_name}\n"
                     . "Type: {$type_label}\n"
                     . "Message: " . sanitize_text_field($_POST['message']??'') . "\n"
                     . "Booking: {$booking->booking_ref}";
                GHM_WhatsApp::send( $admin_phone, $msg );
            }
        }

        wp_send_json_success( array('message' => 'Your request has been sent to the front desk.') );
        exit;
    }

    /* ── AJAX: Review ────────────────────────────────────────────── */

    public static function ajax_submit_review() {
        check_ajax_referer( 'ghm_portal_nonce', 'nonce' );

        $booking_id = self::get_session_booking_id();
        if ( ! $booking_id ) {
            wp_send_json_error( array('message' => 'Session expired. Please log in again.') );
            exit;
        }

        $booking = GHM_Bookings::get_booking( $booking_id );
        if ( ! $booking || $booking->status !== 'checked_out' ) {
            wp_send_json_error( array('message' => 'Reviews can only be submitted after checkout.') );
            exit;
        }

        global $wpdb;
        // Check if review already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ghm_reviews WHERE booking_id = %d", $booking_id
        ) );

        $fields = array(
            'booking_id'  => $booking_id,
            'customer_id' => $booking->customer_id,
            'rating'      => max(1, min(5, absint($_POST['rating']      ?? 5))),
            'cleanliness' => max(1, min(5, absint($_POST['cleanliness'] ?? 5))),
            'service'     => max(1, min(5, absint($_POST['service']     ?? 5))),
            'comfort'     => max(1, min(5, absint($_POST['comfort']     ?? 5))),
            'value'       => max(1, min(5, absint($_POST['value']       ?? 5))),
            'title'       => sanitize_text_field( $_POST['title']   ?? '' ),
            'comment'     => sanitize_textarea_field( $_POST['comment'] ?? '' ),
            'status'      => 'pending', // Admin approves before display
        );

        if ( $existing ) {
            $wpdb->update( $wpdb->prefix . 'ghm_reviews', $fields, array('id' => $existing) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'ghm_reviews', $fields );
        }

        wp_send_json_success( array('message' => 'Thank you for your review! It will be published after moderation.') );
        exit;
    }

    /* ── Helper: get service requests for a booking ──────────────── */

    public static function get_service_requests( $booking_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_service_requests WHERE booking_id = %d ORDER BY created_at DESC",
            $booking_id
        ) );
    }

    public static function get_review( $booking_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_reviews WHERE booking_id = %d", $booking_id
        ) );
    }

    /* ── Shortcode render ────────────────────────────────────────── */

    public static function render( $atts ) {
        $booking_id = self::get_session_booking_id();
        $booking    = $booking_id ? GHM_Bookings::get_booking( $booking_id ) : null;
        $customer   = $booking    ? GHM_Customers::get_customer( $booking->customer_id ) : null;

        ob_start();
        if ( $booking && $customer ) {
            $payments         = GHM_Payments::get_payments( array('booking_id'=>$booking_id,'limit'=>20) );
            $service_requests = self::get_service_requests( $booking_id );
            $review           = self::get_review( $booking_id );
            include GHM_PLUGIN_DIR . 'templates/portal-dashboard.php';
        } else {
            include GHM_PLUGIN_DIR . 'templates/portal-login.php';
        }
        return ob_get_clean();
    }

    public static function get_service_types() {
        return array(
            'housekeeping'    => '🧹 Housekeeping / Room Cleaning',
            'extra_towels'    => '🛁 Extra Towels / Toiletries',
            'room_service'    => '🍽️ Room Service / Food Order',
            'maintenance'     => '🔧 Maintenance Issue',
            'laundry'         => '👕 Laundry Service',
            'transport'       => '🚗 Transport / Taxi Request',
            'wake_up_call'    => '⏰ Wake-up Call',
            'late_checkout'   => '🕐 Late Checkout Request',
            'early_checkin'   => '🕘 Early Check-in Request',
            'extra_bed'       => '🛏️ Extra Bed / Cot',
            'airport_pickup'  => '✈️ Airport Pickup',
            'other'           => '💬 Other Request',
        );
    }
}

add_action( 'plugins_loaded', array( 'GHM_Guest_Portal', 'init' ), 20 );
