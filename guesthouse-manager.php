<?php
/**
 * Plugin Name:  GuestHouse Manager
 * Plugin URI:   https://example.com/guesthouse-manager
 * Description:  Complete guest house PMS — rooms, workspaces, bookings, housekeeping, maintenance, CRM, Paystack, Flutterwave, WhatsApp, REST API, iCal, dynamic pricing, deposits, guest portal, forecasting, discount codes, waiting list, reviews, and more.
 * Version:      3.2.6
 * Author:       GuestHouse Manager
 * License:      GPL-2.0+
 * Text Domain:  guesthouse-manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'GHM_VERSION',     '3.2.6' );
define( 'GHM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'GHM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'GHM_PLUGIN_FILE', __FILE__ );

/* ── Core ────────────────────────────────────────────────────── */
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-install.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-post-types.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-rooms.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-workspaces.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-bookings.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-customers.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-staff.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-payments.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-reports.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-ajax.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-shortcodes.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-emails.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-paystack.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-staff-access.php';
require_once GHM_PLUGIN_DIR . 'includes/class-ghm-login-page.php';

/* ── Feature Modules ─────────────────────────────────────────── */
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-housekeeping.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-maintenance.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-discounts.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-waitlist.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-channels.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-invoice.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-whatsapp.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-rest-api.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-integrations.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-guest-portal.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-dynamic-pricing.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-deposits.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-flutterwave.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-scheduler.php';
require_once GHM_PLUGIN_DIR . 'includes/modules/class-ghm-utilities.php';

/* ── Admin ───────────────────────────────────────────────────── */
require_once GHM_PLUGIN_DIR . 'admin/class-ghm-admin.php';

/* ── Lifecycle hooks ─────────────────────────────────────────── */
register_activation_hook( __FILE__, array( 'GHM_Install', 'activate' ) );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'ghm_hourly_cron' );
    wp_clear_scheduled_hook( 'ghm_daily_cron' );
    wp_clear_scheduled_hook( 'ghm_whatsapp_reminders' );
    flush_rewrite_rules();
} );

/* ── Bootstrap ───────────────────────────────────────────────── */
function ghm_init() {
    GHM_Post_Types::init();
    GHM_Admin::init();
    GHM_Ajax::init();
    GHM_Shortcodes::init();
}
add_action( 'plugins_loaded', 'ghm_init' );
