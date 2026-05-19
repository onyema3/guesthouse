<?php
/**
 * GHM Utilities: Forecasting, CSV Export, Caching, PIN Login, Role Permissions
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   Revenue Forecasting
================================================================ */
class GHM_Forecasting {

    /**
     * Project revenue for the next N days based on confirmed/booked reservations.
     */
    public static function get_forecast( $days = 30 ) {
        global $wpdb;
        $today    = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days} days"));

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, r.price_night, r.price_hour, r.type AS room_type
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = b.room_id
             WHERE b.status IN ('booked','confirmed','checked_in')
             AND b.check_out >= %s AND b.check_in <= %s",
            $today, $end_date
        ));

        $forecast     = array();
        $daily        = array();
        $total_expected = 0;
        $total_confirmed= 0;

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $daily[$date] = 0;
        }

        foreach ($bookings as $b) {
            $remaining = (float)$b->total_amount - (float)$b->paid_amount;
            if ($remaining <= 0) continue;

            $in    = max($today, date('Y-m-d', strtotime($b->check_in)));
            $out   = min($end_date, date('Y-m-d', strtotime($b->check_out)));
            $nights= max(1,(int)(new DateTime($in))->diff(new DateTime($out))->days);

            // Only distribute remaining unpaid amount
            $per_night = $nights > 0 ? $remaining / $nights : $remaining;

            $cur = new DateTime($in);
            $end = new DateTime($out);
            while ($cur < $end) {
                $d = $cur->format('Y-m-d');
                if (isset($daily[$d])) $daily[$d] += $per_night;
                $cur->modify('+1 day');
            }

            $total_expected += $remaining;
            if ($b->status === 'confirmed') $total_confirmed += $remaining;
        }

        return array(
            'daily'           => $daily,
            'total_expected'  => round($total_expected, 2),
            'total_confirmed' => round($total_confirmed, 2),
            'days'            => $days,
            'period_end'      => $end_date,
        );
    }
}

/* ================================================================
   CSV / Accounting Export
================================================================ */
class GHM_Export {

    /**
     * Stream a CSV download of payments.
     */
    public static function payments_csv( $from = '', $to = '' ) {
        global $wpdb;
        $where  = "p.status = 'completed'";
        $params = array();
        if ($from) { $where .= ' AND p.created_at >= %s'; $params[] = $from; }
        if ($to)   { $where .= ' AND p.created_at <= %s'; $params[] = $to.' 23:59:59'; }

        $sql = "SELECT p.created_at, b.booking_ref, CONCAT(c.first_name,' ',c.last_name) AS guest,
                c.email, r.name AS room, p.amount, p.currency, p.method, p.transaction_id, p.notes
                FROM {$wpdb->prefix}ghm_payments p
                LEFT JOIN {$wpdb->prefix}ghm_bookings b  ON b.id  = p.booking_id
                LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id  = p.customer_id
                LEFT JOIN {$wpdb->prefix}ghm_rooms r     ON r.id  = b.room_id
                WHERE $where ORDER BY p.created_at DESC";

        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql,$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        $filename = 'ghm-payments-'.date('Y-m-d').'.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Pragma: no-cache'); header('Expires: 0');

        $fh = fopen('php://output','w');
        fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8
        fputcsv($fh, array('Date','Booking Ref','Guest Name','Email','Room','Amount','Currency','Method','Transaction ID','Notes'));
        foreach ($rows as $row) {
            $row['created_at'] = date('Y-m-d H:i', strtotime($row['created_at']));
            fputcsv($fh, array_values($row));
        }
        fclose($fh);
        exit;
    }

    /**
     * Stream a CSV of bookings.
     */
    public static function bookings_csv( $args = array() ) {
        $bookings = GHM_Bookings::get_bookings(array_merge($args, array('limit'=>9999)));
        $filename = 'ghm-bookings-'.date('Y-m-d').'.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Pragma: no-cache');

        $fh = fopen('php://output','w');
        fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, array('Booking Ref','Guest','Email','Phone','Room','Check-In','Check-Out','Adults','Children','Total','Paid','Balance','Status','Payment Status','Source','Created'));
        foreach ($bookings as $b) {
            fputcsv($fh, array(
                $b->booking_ref,$b->customer_name,$b->customer_email,$b->customer_phone,
                $b->room_name,date('Y-m-d',strtotime($b->check_in)),date('Y-m-d',strtotime($b->check_out)),
                $b->adults,$b->children,
                number_format($b->total_amount,2),number_format($b->paid_amount,2),
                number_format(max(0,$b->total_amount-$b->paid_amount),2),
                $b->status,$b->payment_status,$b->source??'',
                date('Y-m-d H:i',strtotime($b->created_at)),
            ));
        }
        fclose($fh);
        exit;
    }

    /**
     * Handle download request — triggered on any admin page load.
     * Works from both the payments page and direct URLs.
     */
    public static function handle_download() {
        if ( empty($_GET['ghm_export']) ) return;
        if ( ! current_user_can('ghm_manage_payments') ) return;
        if ( ! check_admin_referer('ghm_export') ) return;

        $type   = sanitize_key($_GET['ghm_export']);
        $format = sanitize_key($_GET['ghm_export_format'] ?? 'csv');

        // Print mode — render HTML table and trigger browser print
        if ( $format === 'print' ) {
            self::print_report($type, sanitize_text_field($_GET['from']??''), sanitize_text_field($_GET['to']??''));
            exit;
        }

        if ( $type === 'payments' ) {
            self::payments_csv(
                sanitize_text_field($_GET['from'] ?? ''),
                sanitize_text_field($_GET['to']   ?? '')
            );
        } elseif ( $type === 'bookings' ) {
            self::bookings_csv(array('status' => sanitize_key($_GET['status'] ?? '')));
        } elseif ( $type === 'activity' ) {
            self::activity_csv(
                sanitize_text_field($_GET['from'] ?? ''),
                sanitize_text_field($_GET['to']   ?? '')
            );
        }
        exit;
    }

    /**
     * Print-friendly HTML report — opens in same window, browser handles PDF via Ctrl+P
     */
    private static function print_report($type, $from = '', $to = '') {
        global $wpdb;
        $hotel = get_option('ghm_hotel_name', get_bloginfo('name'));
        $sym   = get_option('ghm_currency_symbol', '₦');
        $from  = $from ?: date('Y-m-01');
        $to    = $to   ?: date('Y-m-t');

        if ($type === 'payments') {
            $where  = "p.status='completed'";
            $params = array();
            $where .= $wpdb->prepare(' AND p.created_at >= %s', $from.' 00:00:00');
            $where .= $wpdb->prepare(' AND p.created_at <= %s', $to.' 23:59:59');
            $rows = $wpdb->get_results(
                "SELECT p.created_at, b.booking_ref, CONCAT(c.first_name,' ',c.last_name) AS guest,
                 r.name AS room, p.amount, p.currency, p.method, p.transaction_id
                 FROM {$wpdb->prefix}ghm_payments p
                 LEFT JOIN {$wpdb->prefix}ghm_bookings b  ON b.id  = p.booking_id
                 LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id  = p.customer_id
                 LEFT JOIN {$wpdb->prefix}ghm_rooms r     ON r.id  = b.room_id
                 WHERE $where ORDER BY p.created_at DESC"
            );
            $total = array_sum(array_column((array)$rows,'amount'));
            $title = 'Payment Report';
            $cols  = array('Date','Booking Ref','Guest','Room','Amount','Currency','Method','Transaction ID');
            $data  = array_map(function($r) use ($sym) {
                return array(
                    date('M j, Y H:i', strtotime($r->created_at)),
                    $r->booking_ref, $r->guest, $r->room,
                    $sym.number_format($r->amount,2), $r->currency,
                    ucfirst(str_replace('_',' ',$r->method)), $r->transaction_id??'—'
                );
            }, (array)$rows);
            $summary = '<strong>Total Revenue: '.$sym.number_format($total,2).'</strong> &bull; '.count((array)$rows).' transactions';
        } else {
            $bookings = GHM_Bookings::get_bookings(array('limit'=>500,'date_from'=>$from,'date_to'=>$to));
            $title = 'Bookings Report';
            $cols  = array('Ref','Guest','Room','Check-In','Check-Out','Total','Paid','Balance','Status');
            $data  = array_map(function($b) use ($sym) {
                return array(
                    $b->booking_ref, $b->customer_name, $b->room_name,
                    date('M j, Y',strtotime($b->check_in)), date('M j, Y',strtotime($b->check_out)),
                    $sym.number_format($b->total_amount,2), $sym.number_format($b->paid_amount,2),
                    $sym.number_format(max(0,$b->total_amount-$b->paid_amount),2),
                    ucfirst(str_replace('_',' ',$b->status))
                );
            }, (array)$bookings);
            $summary = count((array)$bookings).' bookings &bull; Period: '.date('M j, Y',strtotime($from)).' – '.date('M j, Y',strtotime($to));
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>'.esc_html($hotel).' — '.esc_html($title).'</title>
        <style>
          body{font-family:Arial,sans-serif;font-size:12px;color:#111;margin:24px;}
          h1{font-size:20px;margin:0 0 4px;}
          .meta{color:#666;font-size:12px;margin-bottom:16px;}
          table{width:100%;border-collapse:collapse;margin-top:12px;}
          th{background:#1a1a2e;color:#fff;padding:8px 10px;text-align:left;font-size:11px;}
          td{padding:7px 10px;border-bottom:1px solid #eee;font-size:12px;}
          tr:nth-child(even) td{background:#f9f9f9;}
          .summary{margin-top:16px;padding:10px 14px;background:#f3f4f6;border-radius:6px;font-size:13px;}
          .footer{margin-top:24px;font-size:11px;color:#999;text-align:center;}
          @media print{button{display:none!important;}}
        </style></head><body>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div><h1>'.esc_html($hotel).'</h1><div class="meta">'.esc_html($title).' &bull; Generated '.date('F j, Y 	 g:i A').'</div></div>
          <button onclick="window.print()" style="padding:8px 16px;background:#c9a84c;border:none;border-radius:6px;font-weight:bold;cursor:pointer;">🖨 Print / Save PDF</button>
        </div>
        <table><thead><tr>';
        foreach ($cols as $col) echo '<th>'.esc_html($col).'</th>';
        echo '</tr></thead><tbody>';
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>'.esc_html($cell).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>
        <div class="summary">'.$summary.'</div>
        <div class="footer">'.esc_html($hotel).' &mdash; Confidential</div>
        <script>window.onload=function(){window.print();}</script>
        </body></html>';
    }

    /**
     * CSV export of activity log
     */
    public static function activity_csv($from = '', $to = '') {
        $log      = GHM_Activity_Report::get_report(array('from'=>$from,'to'=>$to,'limit'=>9999));
        $filename = 'ghm-activity-'.date('Y-m-d').'.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Pragma: no-cache');
        $fh = fopen('php://output','w');
        fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, array('Date','Staff','Action','Object Type','Object ID','IP Address'));
        foreach ((array)$log as $e) {
            fputcsv($fh, array(
                date('Y-m-d H:i', strtotime($e->created_at)),
                $e->display_name ?? 'System',
                ucwords(str_replace('_',' ',$e->action)),
                $e->object_type ?? '',
                $e->object_id   ?? '',
                $e->ip_address  ?? '',
            ));
        }
        fclose($fh);
    }
}
add_action('admin_init', array('GHM_Export','handle_download'));

/* ================================================================
   Transient Cache Layer
================================================================ */
class GHM_Cache {

    const PREFIX   = 'ghm_cache_';
    const TTL      = 300; // 5 minutes

    public static function get($key) {
        return get_transient(self::PREFIX.$key);
    }

    public static function set($key, $value, $ttl = null) {
        set_transient(self::PREFIX.$key, $value, $ttl ?? self::TTL);
    }

    public static function delete($key) {
        delete_transient(self::PREFIX.$key);
    }

    public static function flush() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ghm_cache_%' OR option_name LIKE '_transient_timeout_ghm_cache_%'");
    }

    // Auto-bust cache on booking/payment changes
    public static function init() {
        add_action('ghm_booking_created',  array(__CLASS__,'bust_stats'));
        add_action('ghm_booking_updated',  array(__CLASS__,'bust_stats'));
        add_action('ghm_booking_cancelled',array(__CLASS__,'bust_stats'));
        add_action('ghm_payment_recorded', array(__CLASS__,'bust_stats'));
    }

    public static function bust_stats($id = 0) {
        self::delete('dashboard_stats');
        self::delete('revenue_chart_6');
        self::delete('revenue_chart_12');
        self::delete('occupancy_rate');
    }
}

add_action('plugins_loaded', array('GHM_Cache','init'), 20);

// Patch GHM_Reports to use cache
add_action('init', function() {
    if (!class_exists('GHM_Reports')) return;

    // Override get_dashboard_stats with cached version
    if (!has_filter('ghm_dashboard_stats')) {
        add_filter('ghm_dashboard_stats', function($stats) {
            $cached = GHM_Cache::get('dashboard_stats');
            if ($cached !== false) return $cached;
            GHM_Cache::set('dashboard_stats', $stats);
            return $stats;
        });
    }
}, 20);


/* ================================================================
   Role Permissions UI
================================================================ */
class GHM_Permissions {

    public static function get_capabilities() {
        return array(
            'ghm_manage_rooms'     => 'Manage Rooms & Workspaces',
            'ghm_manage_bookings'  => 'Manage Bookings',
            'ghm_manage_customers' => 'Manage Customers / CRM',
            'ghm_manage_payments'  => 'Record & View Payments',
            'ghm_view_reports'     => 'View Reports & Analytics',
            'ghm_manage_staff'     => 'Manage Staff',
        );
    }

    public static function get_ghm_roles() {
        return array('ghm_staff','ghm_manager');
    }

    public static function save_permissions($role_slug, $caps_to_grant) {
        $role = get_role($role_slug);
        if (!$role) return;
        foreach (self::get_capabilities() as $cap => $label) {
            if (in_array($cap, (array)$caps_to_grant)) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }
        // Always keep read
        $role->add_cap('read');
    }
}


/* ================================================================
   PIN / Quick Login (Front Desk Mode)
================================================================ */
class GHM_PIN_Login {

    public static function init() {
        // Legacy admin-ajax path (kept for backwards compatibility).
        add_action('wp_ajax_nopriv_ghm_pin_login', array(__CLASS__,'ajax_pin_login'));
        add_action('wp_ajax_ghm_pin_login',        array(__CLASS__,'ajax_pin_login'));
        add_shortcode('ghm_pin_login',             array(__CLASS__,'render'));

        // Dedicated REST endpoint — bypasses caching plugins & stale nonces.
        add_action('rest_api_init', array(__CLASS__, 'register_rest_route'));

        // Admin-only diagnostic. Works both inside wp-admin and on the front-end.
        // Visit any page with ?ghm_pin_diag=YOUR_PIN while logged in as admin.
        add_action('admin_init', array(__CLASS__, 'maybe_run_diagnostic'));
        add_action('init',       array(__CLASS__, 'maybe_run_diagnostic'));
    }

    /**
     * REST endpoint: POST /wp-json/ghm/v1/pin-login
     * Accepts { pin } and on success returns { success:true, redirect:... }.
     * Skips the WP nonce gymnastics that were silently failing on cached pages.
     */
    public static function register_rest_route() {
        register_rest_route( 'ghm/v1', '/pin-login', array(
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => array( __CLASS__, 'rest_pin_login' ),
            'args'                => array(
                'pin' => array( 'required' => true, 'type' => 'string' ),
            ),
        ) );
    }

    public static function rest_pin_login( WP_REST_Request $request ) {
        $pin   = self::normalize_pin( $request->get_param( 'pin' ) );
        $error = array( 'success' => false, 'message' => 'Invalid PIN. Please try again.' );

        if ( strlen( $pin ) < 4 ) {
            self::log( 'rest: rejected (too short ' . strlen( $pin ) . ')' );
            return new WP_REST_Response( $error, 200 );
        }

        $user_id = self::find_user_by_pin( $pin );
        if ( ! $user_id ) {
            self::log( 'rest: rejected (no hash match)' );
            return new WP_REST_Response( $error, 200 );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            self::log( "rest: rejected (user_id=$user_id has meta but no WP user)" );
            return new WP_REST_Response( $error, 200 );
        }

        if ( self::is_deleted_staff( $user_id ) ) {
            self::log( "rest: rejected (user_id=$user_id soft-deleted)" );
            return new WP_REST_Response( $error, 200 );
        }

        self::sign_in_user( $user_id, $user );

        self::log( "rest: success user_id=$user_id login=$user->user_login" );
        return new WP_REST_Response( array(
            'success'  => true,
            'redirect' => admin_url( 'admin.php?page=ghm-dashboard' ),
        ), 200 );
    }

    public static function ajax_pin_login() {
        // Normalize identically to set_pin() so save & login always agree.
        $pin     = self::normalize_pin( $_POST['pin'] ?? '' );

        // Generic failure response — never leaks which PINs exist or why.
        $invalid = array( 'message' => 'Invalid PIN. Please try again.' );

        if ( strlen( $pin ) < 4 ) {
            self::log( 'rejected: too short (' . strlen( $pin ) . ')' );
            wp_send_json_error( $invalid ); exit;
        }

        $user_id = self::find_user_by_pin( $pin );
        if ( ! $user_id ) {
            self::log( 'rejected: no user matches hashed PIN' );
            wp_send_json_error( $invalid ); exit;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            self::log( "rejected: user_id=$user_id has PIN meta but get_user_by returned nothing" );
            wp_send_json_error( $invalid ); exit;
        }

        // Permissive role check.
        // The fact that an admin saved a PIN for this user IS the authorization.
        // We just refuse two things: subscribers/customers (no `read` cap on admin)
        // — actually `read` is enough to land on admin-ajax — and explicitly
        // marked staff records with status='deleted'.
        if ( self::is_deleted_staff( $user_id ) ) {
            self::log( "rejected: user_id=$user_id is marked deleted in ghm_staff" );
            wp_send_json_error( $invalid ); exit;
        }

        self::sign_in_user( $user_id, $user );

        self::log( "success: user_id=$user_id login=$user->user_login roles=" . implode( ',', (array) $user->roles ) );
        wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=ghm-dashboard' ) ) );
        exit;
    }

    /**
     * Sign a user in via the PIN keypad.
     *
     * Detaches GHM_Staff_Access::redirect_staff_on_login (and any other staff
     * redirect listeners on `wp_login`) before firing the action so they can't
     * issue a 302 mid-response. Without this, the browser silently follows the
     * redirect, gets HTML back, fails to parse the expected JSON and reports
     * "Invalid PIN" — even though authentication itself succeeded.
     */
    private static function sign_in_user( $user_id, $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id, true );

        $had_redirect_hook = false;
        if ( class_exists( 'GHM_Staff_Access' ) && method_exists( 'GHM_Staff_Access', 'redirect_staff_on_login' ) ) {
            $had_redirect_hook = remove_action(
                'wp_login',
                array( 'GHM_Staff_Access', 'redirect_staff_on_login' ),
                10
            );
        }

        do_action( 'wp_login', $user->user_login, $user );

        if ( $had_redirect_hook ) {
            add_action(
                'wp_login',
                array( 'GHM_Staff_Access', 'redirect_staff_on_login' ),
                10,
                2
            );
        }
    }

    /**
     * Check ghm_staff table for a soft-deleted entry tied to this WP user.
     */
    private static function is_deleted_staff( $user_id ) {
        global $wpdb;
        $row = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}ghm_staff WHERE wp_user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ) );
        return $row === 'deleted';
    }

    /**
     * Lightweight debug logger — only writes when WP_DEBUG_LOG is on.
     */
    private static function log( $msg ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[GHM PIN Login] ' . $msg );
        }
    }

    /**
     * Admin-only diagnostic.
     * Visit any wp-admin page with ?ghm_pin_diag=YOUR_PIN to dump exactly
     * what the lookup finds for that PIN. Output is plain text to the admin
     * (only administrators see it). No PIN is stored in logs by this tool.
     */
    public static function maybe_run_diagnostic() {
        if ( empty( $_GET['ghm_pin_diag'] ) ) return;
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;

        // Only run once per request so the admin_init + init hooks don't double-fire.
        static $ran = false;
        if ( $ran ) return;
        $ran = true;

        $raw      = (string) $_GET['ghm_pin_diag'];
        $pin      = self::normalize_pin( $raw );
        $hash     = $pin ? wp_hash( $pin ) : '';
        $matches  = $hash ? $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            'ghm_pin', $hash
        ) ) : array();

        $all_with_pins = $wpdb->get_results(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ghm_pin'"
        );

        // Make sure no theme/output buffer has already started writing HTML.
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }
        nocache_headers();
        header( 'Content-Type: text/plain; charset=UTF-8' );

        $version = defined( 'GHM_VERSION' ) ? GHM_VERSION : 'undefined';
        echo "GHM PIN diagnostic\n";
        echo "------------------\n";
        echo "Plugin version on server : {$version}\n";
        echo "Diagnostic version       : 2 (with front-end fallback)\n";
        echo "Site URL                 : " . site_url() . "\n";
        echo "Logged in admin user_id  : " . get_current_user_id() . "\n";
        echo "Multisite                : " . ( is_multisite() ? 'yes' : 'no' ) . "\n";
        echo "wp_usermeta table        : {$wpdb->usermeta}\n\n";

        echo "Raw input length         : " . strlen( $raw ) . "\n";
        echo "Normalized PIN length    : " . strlen( $pin ) . "\n";
        echo "Hash sample (first 8)    : " . substr( $hash, 0, 8 ) . "...\n";
        echo "Number of matching users : " . count( $matches ) . "\n\n";

        if ( ! count( $matches ) ) {
            echo "  >> No user has a stored PIN whose wp_hash() matches the input.\n";
            echo "  >> If the staff PIN was set via the admin UI, this means either:\n";
            echo "     1. The save did not happen (capability or AJAX failure on Set PIN), or\n";
            echo "     2. The hash secret (SECURE_AUTH_KEY) changed since the PIN was saved.\n\n";
        }

        foreach ( $matches as $m ) {
            $u = get_user_by( 'id', $m->user_id );
            if ( ! $u ) {
                echo "- user_id={$m->user_id}  (WP user record missing!)\n";
                continue;
            }
            $deleted = self::is_deleted_staff( $u->ID ) ? 'YES (will be rejected)' : 'no';
            echo "- user_id={$u->ID}  login={$u->user_login}  email={$u->user_email}\n";
            echo "    roles  : " . implode( ', ', (array) $u->roles ) . "\n";
            echo "    deleted: $deleted\n";
        }

        echo "\nAll users with a stored PIN: " . count( $all_with_pins ) . "\n";
        foreach ( $all_with_pins as $r ) {
            $u = get_user_by( 'id', $r->user_id );
            if ( $u ) {
                echo "  user_id={$u->ID}  login={$u->user_login}  roles=" . implode( ',', (array) $u->roles ) . "\n";
            } else {
                echo "  user_id={$r->user_id}  (orphaned meta row, no WP user)\n";
            }
        }
        exit;
    }

    /**
     * Normalize a PIN: strip any non-digit characters, trim, and cap at 8.
     * Used by both set_pin() and ajax_pin_login() so the hash always matches.
     */
    public static function normalize_pin( $raw ) {
        $pin = preg_replace( '/\D/', '', (string) $raw );
        return substr( (string) $pin, 0, 8 );
    }

    /**
     * Look up a user by their stored PIN hash.
     * Goes directly against wp_usermeta so it isn't affected by role / blog
     * filtering applied by WP_User_Query (which can silently exclude non-admins
     * in some multisite or caching configurations).
     */
    private static function find_user_by_pin( $pin ) {
        global $wpdb;
        if ( strlen( $pin ) < 4 ) return false;
        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
              WHERE meta_key = %s AND meta_value = %s
              ORDER BY user_id ASC LIMIT 1",
            'ghm_pin', wp_hash( $pin )
        ) );
        return $user_id ? (int) $user_id : false;
    }

    public static function set_pin( $user_id, $pin ) {
        $pin = self::normalize_pin( $pin );
        if ( strlen( $pin ) < 4 || strlen( $pin ) > 8 ) return false;
        update_user_meta( $user_id, 'ghm_pin', wp_hash( $pin ) );
        return true;
    }

    public static function render($atts) {
        // Ensure scripts are available for PIN login page
        if (!wp_script_is('ghm-public','enqueued')) {
            wp_enqueue_script('ghm-public-pin', GHM_PLUGIN_URL.'public/js/ghm-public.js', array('jquery'), GHM_VERSION, true);
            wp_localize_script('ghm-public-pin','ghmPublic',array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ghm_public_nonce'),
            ));
        }
        ob_start();
        $hotel = get_option('ghm_hotel_name',get_bloginfo('name'));

        // Inline-printed REST URL — guaranteed fresh on every page render
        // (so even cached pages serve a valid endpoint).
        $rest_url = esc_url_raw( rest_url( 'ghm/v1/pin-login' ) );
        ?>
        <div class="ghm-public-wrap ghm-pin-wrap" style="max-width:400px;margin:60px auto;">
          <div class="ghm-pin-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:32px;box-shadow:0 4px 32px rgba(0,0,0,.08);text-align:center;font-family:'DM Sans',sans-serif;">
            <div style="font-size:48px;margin-bottom:12px;">🏨</div>
            <h2 style="font-family:'Playfair Display',serif;color:#1a1a2e;margin:0 0 4px;"><?php echo esc_html($hotel);?></h2>
            <p style="color:#6b7280;font-size:14px;margin:0 0 24px;">Staff Quick Login</p>
            <div id="ghm-pin-display" aria-live="polite" style="font-size:32px;letter-spacing:12px;color:#1a1a2e;background:#f3f4f6;border-radius:8px;padding:14px;margin-bottom:16px;min-height:60px;">
              ·  ·  ·  ·
            </div>
            <div id="ghm-pin-alert" role="alert" style="display:none;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px;"></div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;max-width:280px;margin:0 auto;">
              <?php for($i=1;$i<=9;$i++): ?>
              <button type="button" class="ghm-pin-key" data-val="<?php echo $i;?>" style="padding:16px;font-size:20px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;color:#1a1a2e;cursor:pointer;font-weight:600;font-family:inherit;"><?php echo $i;?></button>
              <?php endfor;?>
              <button type="button" class="ghm-pin-key" data-val="clear" aria-label="Backspace" style="padding:16px;font-size:14px;border-radius:10px;border:1px solid #e5e7eb;background:#f3f4f6;color:#374151;cursor:pointer;font-weight:600;font-family:inherit;">⌫</button>
              <button type="button" class="ghm-pin-key" data-val="0" style="padding:16px;font-size:20px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;color:#1a1a2e;cursor:pointer;font-weight:600;font-family:inherit;">0</button>
              <button type="button" class="ghm-pin-key ghm-pin-enter" data-val="enter" aria-label="Sign in" style="padding:16px;font-size:18px;border-radius:10px;border:none;background:linear-gradient(135deg,#c9a84c,#e8c97a);color:#1a1a2e;cursor:pointer;font-weight:700;font-family:inherit;">→</button>
            </div>
            <p style="font-size:12px;color:#9ca3af;margin:18px 0 0;">Enter your 4–8 digit PIN, then press →</p>
          </div>
        </div>
        <script>
        (function($){
          var pin = '';
          var busy = false;
          var $display = $('#ghm-pin-display');
          var $alert   = $('#ghm-pin-alert');
          var REST_URL = <?php echo wp_json_encode( $rest_url ); ?>;

          function updateDisplay(){
            $display.text(pin.length ? '●  '.repeat(pin.length).trim() : '·  ·  ·  ·');
          }

          function showError(msg){
            $alert
              .css({background:'#fef2f2',border:'1px solid #fecaca',color:'#991b1b'})
              .text(msg)
              .show();
          }

          function clearError(){ $alert.hide().empty(); }

          function handleResponse(res){
            if (res && res.success && res.redirect) {
              window.location.href = res.redirect;
              return true;
            }
            if (res && res.success && res.data && res.data.redirect) {
              // legacy admin-ajax shape
              window.location.href = res.data.redirect;
              return true;
            }
            return false;
          }

          function fallbackAdminAjax(){
            if (typeof ghmPublic === 'undefined' || !ghmPublic.ajax_url) {
              showError('Login is not configured on this page. Please contact admin.');
              busy = false;
              $('.ghm-pin-key').prop('disabled', false);
              return;
            }
            $.post(ghmPublic.ajax_url, {
              action: 'ghm_pin_login',
              nonce : ghmPublic.nonce,
              pin   : pin
            })
            .done(function(res){
              if (handleResponse(res)) return;
              showError((res && res.data && res.data.message) || 'Invalid PIN. Please try again.');
              pin = ''; updateDisplay();
            })
            .fail(function(){
              showError('Network error. Please try again.');
              pin = ''; updateDisplay();
            })
            .always(function(){
              busy = false;
              $('.ghm-pin-key').prop('disabled', false);
            });
          }

          function doLogin(){
            if (busy) return;
            if (pin.length < 4) {
              showError('Please enter at least 4 digits.');
              return;
            }
            clearError();
            busy = true;
            $('.ghm-pin-key').prop('disabled', true);

            // Try REST endpoint first (immune to stale page-cache nonces).
            $.ajax({
              url: REST_URL,
              method: 'POST',
              dataType: 'json',
              data: { pin: pin }
            })
            .done(function(res){
              if (handleResponse(res)) return;
              if (res && res.success === false) {
                showError(res.message || 'Invalid PIN. Please try again.');
                pin = ''; updateDisplay();
                busy = false;
                $('.ghm-pin-key').prop('disabled', false);
                return;
              }
              // Unknown shape — fall back
              fallbackAdminAjax();
            })
            .fail(function(){
              // REST blocked or 404 — fall back to admin-ajax
              fallbackAdminAjax();
            });
          }

          // Keypad — explicitly cancel any default form behaviour just in case
          // the shortcode is embedded inside a wrapping form (e.g. page builders).
          $(document).on('click', '.ghm-pin-key', function(e){
            e.preventDefault();
            e.stopPropagation();
            if (busy) return;
            var v = String($(this).data('val'));
            if (v === 'clear') {
              pin = pin.slice(0, -1);
              clearError();
              updateDisplay();
              return;
            }
            if (v === 'enter') {
              doLogin();
              return;
            }
            if (/^[0-9]$/.test(v) && pin.length < 8) {
              pin += v;
              clearError();
              updateDisplay();
            }
          });

          // Allow physical keyboard typing as well
          $(document).on('keydown', function(e){
            if (busy) return;
            if (e.key >= '0' && e.key <= '9' && pin.length < 8) {
              pin += e.key;
              clearError();
              updateDisplay();
            } else if (e.key === 'Backspace') {
              pin = pin.slice(0, -1);
              clearError();
              updateDisplay();
            } else if (e.key === 'Enter') {
              e.preventDefault();
              doLogin();
            } else if (e.key === 'Escape') {
              pin = '';
              clearError();
              updateDisplay();
            }
          });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }
}

add_action('plugins_loaded', array('GHM_PIN_Login','init'), 20);


/* ================================================================
   Staff Activity Report
================================================================ */
class GHM_Activity_Report {

    public static function get_report($args = array()) {
        global $wpdb;
        $from  = sanitize_text_field($args['from'] ?? date('Y-m-01'));
        $to    = sanitize_text_field($args['to']   ?? date('Y-m-t'));
        $limit = absint($args['limit'] ?? 100);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name, u.user_email,
             COUNT(*) OVER (PARTITION BY l.user_id) AS total_actions
             FROM {$wpdb->prefix}ghm_activity_log l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             WHERE l.created_at BETWEEN %s AND %s
             ORDER BY l.created_at DESC LIMIT %d",
            $from.' 00:00:00', $to.' 23:59:59', $limit
        ));
    }

    public static function get_summary_by_user($from = '', $to = '') {
        global $wpdb;
        $from = $from ?: date('Y-m-01');
        $to   = $to   ?: date('Y-m-t');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.user_id, u.display_name, COUNT(*) AS total_actions,
             SUM(CASE WHEN l.action LIKE '%booking%' THEN 1 ELSE 0 END) AS booking_actions,
             SUM(CASE WHEN l.action LIKE '%payment%' THEN 1 ELSE 0 END) AS payment_actions,
             MAX(l.created_at) AS last_active
             FROM {$wpdb->prefix}ghm_activity_log l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             WHERE l.created_at BETWEEN %s AND %s
             GROUP BY l.user_id ORDER BY total_actions DESC",
            $from.' 00:00:00', $to.' 23:59:59'
        ));
    }
}
