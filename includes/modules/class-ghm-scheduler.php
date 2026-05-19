<?php
/**
 * Automated Email & Notification Scheduler
 * - Pre-arrival email (24–48 hours before check-in)
 * - Post-stay review request (24 hours after checkout)
 * - Daily digest to admin
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Scheduler {

    public static function init() {
        // Register cron schedules
        add_filter('cron_schedules', array(__CLASS__,'add_cron_intervals'));

        // Schedule cron events on activation
        add_action('ghm_hourly_cron',   array(__CLASS__,'run_hourly'));
        add_action('ghm_daily_cron',    array(__CLASS__,'run_daily'));

        // Register cron hooks
        if (!wp_next_scheduled('ghm_hourly_cron')) {
            wp_schedule_event(time(), 'hourly', 'ghm_hourly_cron');
        }
        if (!wp_next_scheduled('ghm_daily_cron')) {
            wp_schedule_event(strtotime('08:00:00'), 'daily', 'ghm_daily_cron');
        }
    }

    public static function add_cron_intervals($schedules) {
        $schedules['every_6_hours'] = array('interval'=>21600,'display'=>'Every 6 Hours');
        return $schedules;
    }

    /* ══ Hourly runner ════════════════════════════════════════════ */
    public static function run_hourly() {
        self::send_pre_arrival_emails();
    }

    /* ══ Daily runner ═════════════════════════════════════════════ */
    public static function run_daily() {
        self::send_post_stay_review_requests();
        self::send_admin_daily_digest();
        self::cleanup_pending_bookings();
    }

    /* ── Pre-arrival email (sent ~24 hours before check-in) ────── */
    public static function send_pre_arrival_emails() {
        if (!get_option('ghm_email_notify',1)) return;
        global $wpdb;

        $window_start = date('Y-m-d H:i:s', strtotime('+22 hours'));
        $window_end   = date('Y-m-d H:i:s', strtotime('+26 hours'));

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.email, c.phone
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
             WHERE b.check_in BETWEEN %s AND %s
             AND b.status IN ('booked','confirmed')
             AND b.pre_arrival_sent IS NULL
             AND c.email IS NOT NULL",
            $window_start, $window_end
        ));

        foreach ($bookings as $b) {
            self::send_pre_arrival($b);
            // Mark as sent
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ghm_bookings SET notes = CONCAT(IFNULL(notes,''), %s) WHERE id = %d",
                ' [pre_arrival_sent:'.date('Y-m-d H:i:s').']', $b->id
            ));
        }
    }

    private static function send_pre_arrival($b) {
        $hotel      = get_option('ghm_hotel_name',get_bloginfo('name'));
        $sym        = get_option('ghm_currency_symbol','₦');
        $ci_time    = get_option('ghm_checkin_time','14:00');
        $address    = get_option('ghm_hotel_address','');
        $phone      = get_option('ghm_hotel_phone','');
        $balance    = max(0,(float)$b->total_amount-(float)$b->paid_amount);

        $subject = "[$hotel] Your check-in is tomorrow — {$b->booking_ref}";
        $content = "
            <p>Dear {$b->first_name},</p>
            <p>We're looking forward to welcoming you to <strong>{$hotel}</strong> tomorrow!</p>
            <table style='width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;'>
              <tr style='background:#f9fafb;'><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Booking Ref</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#c9a84c;'>{$b->booking_ref}</td></tr>
              <tr><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Room</td><td style='padding:10px 12px;border:1px solid #e5e7eb;'>{$b->room_name} ({$b->room_number})</td></tr>
              <tr style='background:#f9fafb;'><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Check-In</td><td style='padding:10px 12px;border:1px solid #e5e7eb;'>".date_i18n('l, F j, Y',strtotime($b->check_in))." from {$ci_time}</td></tr>
              <tr><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Check-Out</td><td style='padding:10px 12px;border:1px solid #e5e7eb;'>".date_i18n('l, F j, Y',strtotime($b->check_out))."</td></tr>
              ".($balance>0?"<tr style='background:#fff5f5;'><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Balance Due</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#ef4444;'>{$sym}".number_format($balance,2)."</td></tr>":'')."
            </table>
            ".($address?"<p><strong>📍 Address:</strong> {$address}</p>":'')."
            ".($phone?"<p><strong>📞 Contact:</strong> {$phone}</p>":'')."
            <p>Please bring a valid ID for check-in. If you need any assistance, don't hesitate to contact us.</p>
            <p>We look forward to seeing you!</p>
        ";

        self::send_email($b->email, $subject, $content);

        // WhatsApp reminder
        if (class_exists('GHM_WhatsApp') && GHM_WhatsApp::is_enabled() && !empty($b->phone)) {
            $msg = "⏰ *{$hotel}* — Check-in Tomorrow!\n\n"
                 . "Hi {$b->first_name}! Your check-in is tomorrow.\n"
                 . "🛏️ {$b->room_name}\n"
                 . "🕒 From: {$ci_time}\n"
                 . "📋 Ref: {$b->booking_ref}\n"
                 . ($balance > 0 ? "💰 Balance due: {$sym}".number_format($balance,2)."\n" : "")
                 . "\nSee you soon! 🙏";
            GHM_WhatsApp::send($b->phone, $msg);
        }
    }

    /* ── Post-stay review request (24 hours after checkout) ─────── */
    public static function send_post_stay_review_requests() {
        if (!get_option('ghm_email_notify',1)) return;
        global $wpdb;

        $window_start = date('Y-m-d H:i:s', strtotime('-26 hours'));
        $window_end   = date('Y-m-d H:i:s', strtotime('-22 hours'));

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.email, c.phone
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
             LEFT JOIN {$wpdb->prefix}ghm_reviews rv ON rv.booking_id = b.id
             WHERE b.check_out BETWEEN %s AND %s
             AND b.status = 'checked_out'
             AND rv.id IS NULL
             AND c.email IS NOT NULL",
            $window_start, $window_end
        ));

        foreach ($bookings as $b) {
            self::send_review_request($b);
        }
    }

    private static function send_review_request($b) {
        $hotel   = get_option('ghm_hotel_name',get_bloginfo('name'));
        $portal  = home_url('/guest-portal/?ref='.urlencode($b->booking_ref));
        $subject = "[$hotel] How was your stay? Share your feedback 🌟";
        $content = "
            <p>Dear {$b->first_name},</p>
            <p>Thank you for staying with us at <strong>{$hotel}</strong>. We hope you had a wonderful experience!</p>
            <p>Your feedback means the world to us and helps us serve future guests better. It takes just 60 seconds:</p>
            <div style='text-align:center;margin:24px 0;'>
              <a href='{$portal}' style='background:linear-gradient(135deg,#c9a84c,#e8c97a);color:#1a1a2e;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;'>
                ⭐ Write a Review
              </a>
            </div>
            <p>Simply log in with your booking reference <strong>{$b->booking_ref}</strong> and email address.</p>
            <p>We look forward to welcoming you again!</p>
        ";
        self::send_email($b->email, $subject, $content);

        // WhatsApp review request
        if (class_exists('GHM_WhatsApp') && GHM_WhatsApp::is_enabled() && !empty($b->phone)) {
            $msg = "⭐ *{$hotel}* — How was your stay?\n\n"
                 . "Hi {$b->first_name}! We hope you enjoyed your stay.\n\n"
                 . "We'd love your feedback — it only takes a minute:\n"
                 . $portal . "\n\n"
                 . "Use booking ref: *{$b->booking_ref}*\n"
                 . "Thank you! 🙏";
            GHM_WhatsApp::send($b->phone, $msg);
        }
    }

    /* ── Admin daily digest ─────────────────────────────────────── */
    public static function send_admin_daily_digest() {
        if (!get_option('ghm_email_digest',1)) return;
        $admin_email = get_option('ghm_admin_email',get_option('admin_email'));
        if (!$admin_email) return;

        global $wpdb;
        $today    = date('Y-m-d');
        $hotel    = get_option('ghm_hotel_name',get_bloginfo('name'));
        $sym      = get_option('ghm_currency_symbol','₦');

        $checkins  = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE DATE(check_in)=%s AND status NOT IN('cancelled')",$today));
        $checkouts = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE DATE(check_out)=%s AND status NOT IN('cancelled')",$today));
        $revenue   = (float)$wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$wpdb->prefix}ghm_payments WHERE DATE(created_at)=%s AND status='completed'",$today));
        $pending   = (float)$wpdb->get_var("SELECT SUM(total_amount-paid_amount) FROM {$wpdb->prefix}ghm_bookings WHERE payment_status IN('unpaid','partial') AND status NOT IN('cancelled')");
        $maint     = GHM_Maintenance::count_open();

        $subject = "[$hotel] Daily Digest — ".date_i18n('F j, Y');
        $content = "
            <p>Good morning! Here is your property summary for today.</p>
            <table style='width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;'>
              <tr style='background:#1a1a2e;color:#fff;'><th colspan='2' style='padding:12px;text-align:left;'>".date_i18n('l, F j, Y')."</th></tr>
              <tr><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Check-ins Today</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;'>{$checkins}</td></tr>
              <tr style='background:#f9fafb;'><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Check-outs Today</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;'>{$checkouts}</td></tr>
              <tr><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Revenue Today</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#c9a84c;'>{$sym}".number_format($revenue,2)."</td></tr>
              <tr style='background:#f9fafb;'><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Outstanding Balance</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#ef4444;'>{$sym}".number_format($pending,2)."</td></tr>
              ".($maint>0?"<tr><td style='padding:10px 12px;color:#6b7280;border:1px solid #e5e7eb;'>Open Maintenance Issues</td><td style='padding:10px 12px;font-weight:700;border:1px solid #e5e7eb;color:#f59e0b;'>{$maint}</td></tr>":'')."
            </table>
            <p><a href='".admin_url('admin.php?page=ghm-dashboard')."' style='color:#c9a84c;'>Open Dashboard →</a></p>
        ";
        self::send_email($admin_email, $subject, $content);
    }

    /* ── Cleanup stale pending bookings (older than 30 mins) ────── */
    public static function cleanup_pending_bookings() {
        global $wpdb;
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $stale  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_bookings WHERE status='pending' AND created_at < %s",
            $cutoff
        ));
        foreach ($stale as $b) {
            GHM_Bookings::cancel_booking($b->id);
        }
    }

    /* ── Email helper ────────────────────────────────────────────── */
    private static function send_email($to, $subject, $content) {
        $hotel = get_option('ghm_hotel_name',get_bloginfo('name'));
        $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:DM Sans,Arial,sans-serif;">
          <div style="max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
            <div style="background:linear-gradient(135deg,#1a1a2e,#2a3055);padding:24px 32px;">
              <h1 style="color:#e8c97a;font-family:Georgia,serif;font-size:20px;margin:0;">'.esc_html($hotel).'</h1>
            </div>
            <div style="padding:28px 32px;color:#374151;font-size:14px;line-height:1.6;">'.$content.'</div>
            <div style="background:#f9fafb;padding:14px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">'.esc_html($hotel).' &mdash; '.esc_html(home_url()).'</div>
          </div></body></html>';

        wp_mail($to, $subject, $body, array(
            'Content-Type: text/html; charset=UTF-8',
            'From: '.get_option('ghm_hotel_name','GuestHouse').' <'.get_option('ghm_admin_email',get_option('admin_email')).'>',
        ));
    }
}

add_action('plugins_loaded', array('GHM_Scheduler','init'), 20);
