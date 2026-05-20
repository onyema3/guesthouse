<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( __CLASS__, 'register_ajax' ) );
    }

    public static function register_menus() {
        add_menu_page( 'GuestHouse Manager', 'GuestHouse', 'read', 'ghm-dashboard',
            array( __CLASS__, 'page_dashboard' ), 'dashicons-building', 3 );

        $subs = array(
            // page-slug, menu-title, cap, method
            array( 'ghm-dashboard',    'Dashboard',        'read',                  'page_dashboard'    ),
            array( 'ghm-rooms',        'Rooms',            'ghm_manage_rooms',      'page_rooms'        ),
            array( 'ghm-workspaces',   'Workspaces',       'ghm_manage_rooms',      'page_workspaces'   ),
            array( 'ghm-bookings',     'Bookings',         'ghm_manage_bookings',   'page_bookings'     ),
            array( 'ghm-calendar',     'Calendar',         'ghm_manage_bookings',   'page_calendar'     ),
            array( 'ghm-customers',    'Customers',        'ghm_manage_customers',  'page_customers'    ),
            array( 'ghm-payments',     'Payments',         'ghm_manage_payments',   'page_payments'     ),
            array( 'ghm-deposits',     'Deposits',         'ghm_manage_payments',   'page_deposits'     ),
            array( 'ghm-housekeeping', 'Housekeeping',     'ghm_manage_bookings',   'page_housekeeping' ),
            array( 'ghm-service-requests', 'Service Requests', 'ghm_manage_bookings', 'page_service_requests' ),
            array( 'ghm-maintenance',  'Maintenance',      'ghm_manage_rooms',      'page_maintenance'  ),
            array( 'ghm-discounts',    'Discounts',        'ghm_manage_payments',   'page_discounts'    ),
            array( 'ghm-waitlist',     'Waitlist',         'ghm_manage_bookings',   'page_waitlist'     ),
            array( 'ghm-pricing',      'Dynamic Pricing',  'ghm_manage_rooms',      'page_pricing'      ),
            array( 'ghm-staff',        'Staff',            'ghm_manage_staff',      'page_staff'        ),
            array( 'ghm-permissions',  'Permissions',      'manage_options',        'page_permissions'  ),
            array( 'ghm-reports',      'Reports',          'ghm_view_reports',      'page_reports'      ),
            array( 'ghm-forecast',     'Forecast',         'ghm_view_reports',      'page_forecast'     ),
            array( 'ghm-activity',     'Activity Log',     'ghm_view_reports',      'page_activity'     ),
            array( 'ghm-reviews',      'Reviews',          'manage_options',        'page_reviews'      ),
            array( 'ghm-settings',     'Settings',         'manage_options',        'page_settings'     ),
        );

        foreach ( $subs as $s ) {
            add_submenu_page( 'ghm-dashboard', $s[1], $s[1], $s[2], $s[0], array( __CLASS__, $s[3] ) );
        }
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'ghm-' ) === false ) return;
        wp_enqueue_style(  'ghm-admin',  GHM_PLUGIN_URL . 'admin/css/ghm-admin.css',  array(), GHM_VERSION );
        wp_enqueue_script( 'ghm-charts', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), null, true );
        wp_enqueue_script( 'ghm-admin',  GHM_PLUGIN_URL . 'admin/js/ghm-admin.js',   array( 'jquery', 'ghm-charts' ), GHM_VERSION, true );
        wp_localize_script( 'ghm-admin', 'ghmAdmin', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'ghm_nonce' ),
            'currency'        => get_option( 'ghm_currency', 'NGN' ),
            'currency_symbol' => get_option( 'ghm_currency_symbol', '₦' ),
        ) );
    }

    /* ── AJAX registration ──────────────────────────────────────── */
    public static function register_ajax() {
        $handlers = array(
            // Housekeeping
            'ghm_hk_update'               => 'ajax_hk_update',
            // Maintenance
            'ghm_save_maintenance'        => 'ajax_save_maintenance',
            'ghm_delete_maintenance'      => 'ajax_delete_maintenance',
            // Discounts
            'ghm_save_discount'           => 'ajax_save_discount',
            'ghm_delete_discount'         => 'ajax_delete_discount',
            // Deposits
            'ghm_collect_deposit'         => 'ajax_collect_deposit',
            'ghm_refund_deposit'          => 'ajax_refund_deposit',
            'ghm_forfeit_deposit'         => 'ajax_forfeit_deposit',
            // Dynamic pricing
            'ghm_save_pricing_rule'       => 'ajax_save_pricing_rule',
            'ghm_delete_pricing_rule'     => 'ajax_delete_pricing_rule',
            'ghm_toggle_dynamic_pricing'  => 'ajax_toggle_dynamic_pricing',
            // Permissions / PIN
            'ghm_clear_staff_pin'         => 'ajax_clear_staff_pin',
            'ghm_set_staff_pin'           => 'ajax_set_staff_pin',
            // Service Requests (admin)
            'ghm_update_service_request'  => 'ajax_update_service_request',
            'ghm_delete_service_request'  => 'ajax_delete_service_request',
            // Reviews (admin approve)
            'ghm_approve_review'          => 'ajax_approve_review',
            'ghm_delete_review'           => 'ajax_delete_review',
            // Export
            'ghm_export_csv'              => 'ajax_export_csv',
        );

        foreach ( $handlers as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
        }

        // Public AJAX
        add_action( 'wp_ajax_nopriv_ghm_validate_discount', array( __CLASS__, 'ajax_validate_discount' ) );
        add_action( 'wp_ajax_ghm_validate_discount',        array( __CLASS__, 'ajax_validate_discount' ) );
    }

    /* ── Security helper ────────────────────────────────────────── */
    private static function verify( $cap = '' ) {
        if ( ! check_ajax_referer( 'ghm_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) ); exit;
        }
        if ( $cap && ! current_user_can( 'administrator' ) && ! current_user_can( $cap ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) ); exit;
        }
    }

    /* ── AJAX: Housekeeping ─────────────────────────────────────── */
    public static function ajax_hk_update() {
        self::verify( 'ghm_manage_bookings' );
        $id = GHM_Housekeeping::upsert( absint( $_POST['room_id'] ), $_POST );
        wp_send_json_success( array( 'id' => $id ) ); exit;
    }

    /* ── AJAX: Maintenance ──────────────────────────────────────── */
    public static function ajax_save_maintenance() {
        self::verify( 'ghm_manage_rooms' );
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GHM_Maintenance::save( $_POST, $id );
        is_wp_error( $result )
            ? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
            : wp_send_json_success( array( 'id' => $result ) );
        exit;
    }

    public static function ajax_delete_maintenance() {
        self::verify( 'ghm_manage_rooms' );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ghm_maintenance', array( 'id' => absint( $_POST['id'] ) ) );
        wp_send_json_success(); exit;
    }

    /* ── AJAX: Discounts ────────────────────────────────────────── */
    public static function ajax_save_discount() {
        self::verify( 'ghm_manage_payments' );
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GHM_Discounts::save( $_POST, $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } elseif ( empty( $result ) ) {
            wp_send_json_error( array( 'message' => 'Failed to save discount code. Please check your input and try again.' ) );
        } else {
            wp_send_json_success( array( 'id' => $result ) );
        }
        exit;
    }

    public static function ajax_delete_discount() {
        self::verify( 'ghm_manage_payments' );
        GHM_Discounts::delete( absint( $_POST['id'] ) );
        wp_send_json_success(); exit;
    }

    public static function ajax_validate_discount() {
        if ( ! check_ajax_referer( 'ghm_public_nonce', 'nonce', false )
          && ! check_ajax_referer( 'ghm_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) ); exit;
        }
        $result = GHM_Discounts::validate(
            sanitize_text_field( $_POST['code']    ?? '' ),
            (float)( $_POST['amount']              ?? 0 ),
            absint( $_POST['room_id']              ?? 0 )
        );
        is_wp_error( $result )
            ? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
            : wp_send_json_success( $result );
        exit;
    }

    /* ── AJAX: Deposits ─────────────────────────────────────────── */
    public static function ajax_collect_deposit() {
        self::verify( 'ghm_manage_payments' );
        // Resolve booking by ref
        $ref     = strtoupper( sanitize_text_field( $_POST['booking_ref'] ?? '' ) );
        $booking = GHM_Bookings::get_booking_by_ref( $ref );
        if ( ! $booking ) {
            wp_send_json_error( array( 'message' => 'Booking reference not found.' ) ); exit;
        }
        $data              = $_POST;
        $data['booking_id']= $booking->id;
        $result = GHM_Deposits::collect( $data );
        is_wp_error( $result )
            ? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
            : wp_send_json_success( array( 'id' => $result ) );
        exit;
    }

    public static function ajax_refund_deposit() {
        self::verify( 'ghm_manage_payments' );
        $result = GHM_Deposits::refund( absint( $_POST['id'] ), sanitize_textarea_field( $_POST['notes'] ?? '' ) );
        is_wp_error( $result )
            ? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
            : wp_send_json_success();
        exit;
    }

    public static function ajax_forfeit_deposit() {
        self::verify( 'ghm_manage_payments' );
        $result = GHM_Deposits::forfeit( absint( $_POST['id'] ), sanitize_textarea_field( $_POST['reason'] ?? '' ) );
        is_wp_error( $result )
            ? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
            : wp_send_json_success();
        exit;
    }

    /* ── AJAX: Dynamic Pricing ──────────────────────────────────── */
    public static function ajax_save_pricing_rule() {
        self::verify( 'ghm_manage_rooms' );
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GHM_Dynamic_Pricing::save_rule( $_POST, $id );
        is_wp_error( $result )
            ? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
            : wp_send_json_success( array( 'id' => $result ) );
        exit;
    }

    public static function ajax_delete_pricing_rule() {
        self::verify( 'ghm_manage_rooms' );
        GHM_Dynamic_Pricing::delete_rule( absint( $_POST['id'] ) );
        wp_send_json_success(); exit;
    }

    public static function ajax_toggle_dynamic_pricing() {
        self::verify( 'ghm_manage_rooms' );
        update_option( 'ghm_dynamic_pricing_enabled', absint( $_POST['val'] ) );
        wp_send_json_success(); exit;
    }

    /* ── AJAX: PIN ──────────────────────────────────────────────── */
    public static function ajax_clear_staff_pin() {
        self::verify( 'manage_options' );
        delete_user_meta( absint( $_POST['user_id'] ), 'ghm_pin' );
        wp_send_json_success(); exit;
    }

    public static function ajax_set_staff_pin() {
        self::verify( 'manage_options' );
        $user_id = absint( $_POST['user_id'] ?? 0 );
        $pin     = preg_replace( '/\D/', '', (string) ( $_POST['pin'] ?? '' ) );
        if ( ! $user_id || strlen( $pin ) < 4 || strlen( $pin ) > 8 ) {
            wp_send_json_error( array( 'message' => 'PIN must be 4–8 digits.' ) ); exit;
        }
        // Reuse existing helper if available, else store hashed PIN directly
        if ( class_exists( 'GHM_PIN_Login' ) && method_exists( 'GHM_PIN_Login', 'set_pin' ) ) {
            GHM_PIN_Login::set_pin( $user_id, $pin );
        } else {
            update_user_meta( $user_id, 'ghm_pin', wp_hash( $pin ) );
        }
        wp_send_json_success(); exit;
    }

    /* ── AJAX: Service Requests (admin) ─────────────────────────── */
    public static function ajax_update_service_request() {
        self::verify( 'ghm_manage_bookings' );
        global $wpdb;
        $id     = absint( $_POST['id'] ?? 0 );
        $status = sanitize_key( $_POST['status'] ?? '' );
        $allowed = array( 'pending', 'in_progress', 'resolved', 'cancelled' );
        if ( ! $id || ! in_array( $status, $allowed, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) ); exit;
        }
        $update = array( 'status' => $status );
        if ( $status === 'resolved' ) {
            $update['resolved_at'] = current_time( 'mysql' );
        }
        $wpdb->update( $wpdb->prefix . 'ghm_service_requests', $update, array( 'id' => $id ) );
        wp_send_json_success(); exit;
    }

    public static function ajax_delete_service_request() {
        self::verify( 'ghm_manage_bookings' );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ghm_service_requests', array( 'id' => absint( $_POST['id'] ?? 0 ) ) );
        wp_send_json_success(); exit;
    }

    /* ── AJAX: Reviews ──────────────────────────────────────────── */
    public static function ajax_approve_review() {
        self::verify( 'manage_options' );
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'ghm_reviews', array( 'status' => 'approved' ), array( 'id' => absint( $_POST['id'] ) ) );
        wp_send_json_success(); exit;
    }

    public static function ajax_delete_review() {
        self::verify( 'manage_options' );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ghm_reviews', array( 'id' => absint( $_POST['id'] ) ) );
        wp_send_json_success(); exit;
    }

    /* ── AJAX: CSV Export ───────────────────────────────────────── */
    public static function ajax_export_csv() {
        self::verify( 'ghm_manage_payments' );
        $type = sanitize_key( $_POST['type'] ?? 'bookings' );
        if ( $type === 'payments' ) {
            GHM_Export::payments_csv(
                sanitize_text_field( $_POST['from'] ?? '' ),
                sanitize_text_field( $_POST['to']   ?? '' )
            );
        } else {
            GHM_Export::bookings_csv( array( 'status' => sanitize_key( $_POST['status'] ?? '' ) ) );
        }
    }

    /* ── Page renderers ─────────────────────────────────────────── */
    public static function page_dashboard() {
        $stats      = GHM_Reports::get_dashboard_stats();
        $chart_data = GHM_Reports::get_revenue_chart( 6 );
        $maint_open = GHM_Maintenance::count_open();
        include GHM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public static function page_rooms() {
        $rooms = GHM_Rooms::get_rooms( array( 'search' => sanitize_text_field( $_GET['s'] ?? '' ) ) );
        include GHM_PLUGIN_DIR . 'admin/views/rooms.php';
    }

    public static function page_workspaces() {
        $workspaces = GHM_Workspaces::get_workspaces( array( 'search' => sanitize_text_field( $_GET['s'] ?? '' ) ) );
        include GHM_PLUGIN_DIR . 'admin/views/workspaces.php';
    }

    public static function page_bookings() {
        $args     = array(
            'status'  => sanitize_key( $_GET['status'] ?? '' ),
            'search'  => sanitize_text_field( $_GET['s'] ?? '' ),
            'limit'   => 20,
            'offset'  => absint( ( ( $_GET['paged'] ?? 1 ) - 1 ) ) * 20,
        );
        $bookings = GHM_Bookings::get_bookings( $args );
        $total    = GHM_Bookings::count_bookings( $args );
        include GHM_PLUGIN_DIR . 'admin/views/bookings.php';
    }

    public static function page_calendar() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/calendar.php';
    }

    public static function page_customers() {
        $view              = sanitize_key( $_GET['view'] ?? 'list' );
        $customer          = ( $view === 'edit' && ! empty( $_GET['id'] ) ) ? GHM_Customers::get_customer( absint( $_GET['id'] ) ) : null;
        $customer_bookings = $customer ? GHM_Customers::get_customer_bookings( $customer->id ) : array();
        $customers         = GHM_Customers::get_customers( array(
            'search' => sanitize_text_field( $_GET['s'] ?? '' ),
            'limit'  => 20,
            'offset' => absint( ( ( $_GET['paged'] ?? 1 ) - 1 ) ) * 20,
        ) );
        include GHM_PLUGIN_DIR . 'admin/views/customers.php';
    }

    public static function page_payments() {
        $payments = GHM_Payments::get_payments( array( 'limit' => 30 ) );
        include GHM_PLUGIN_DIR . 'admin/views/payments.php';
    }

    public static function page_deposits() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/deposits.php';
    }

    public static function page_housekeeping() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/housekeeping.php';
    }

    public static function page_service_requests() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/service-requests.php';
    }

    public static function page_maintenance() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/maintenance.php';
    }

    public static function page_discounts() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/discounts.php';
    }

    public static function page_waitlist() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/waitlist.php';
    }

    public static function page_pricing() {
        GHM_Dynamic_Pricing::create_table();
        include GHM_PLUGIN_DIR . 'admin/views/modules/pricing.php';
    }

    public static function page_staff() {
        $staff = GHM_Staff::get_staff();
        include GHM_PLUGIN_DIR . 'admin/views/staff.php';
    }

    public static function page_permissions() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/permissions.php';
    }

    public static function page_reports() {
        $stats      = GHM_Reports::get_dashboard_stats();
        $top_rooms  = GHM_Reports::get_top_rooms( 5 );
        $occupancy  = GHM_Reports::get_occupancy_rate();
        $chart_data = GHM_Reports::get_revenue_chart( 12 );
        $activity   = GHM_Reports::get_recent_activity( 15 );
        $channels   = GHM_Channels::get_breakdown();
        include GHM_PLUGIN_DIR . 'admin/views/reports.php';
    }

    public static function page_forecast() {
        GHM_Dynamic_Pricing::create_table(); // ensure table exists
        include GHM_PLUGIN_DIR . 'admin/views/modules/forecast.php';
    }

    public static function page_activity() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/activity.php';
    }

    public static function page_reviews() {
        include GHM_PLUGIN_DIR . 'admin/views/modules/reviews.php';
    }

    public static function page_settings() {
        include GHM_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
