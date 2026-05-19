<?php if ( ! defined( 'ABSPATH' ) ) exit;
$sym     = get_option('ghm_currency_symbol','₦');
$methods = GHM_Payments::get_payment_methods();

// Handle CSV export (direct download — no AJAX needed)
if ( isset($_GET['ghm_export']) && current_user_can('ghm_manage_payments') && check_admin_referer('ghm_export') ) {
    $type = sanitize_key($_GET['ghm_export']);
    $from = sanitize_text_field($_GET['from'] ?? '');
    $to   = sanitize_text_field($_GET['to']   ?? '');
    if ( $type === 'payments' ) {
        GHM_Export::payments_csv( $from, $to );
    } elseif ( $type === 'bookings' ) {
        GHM_Export::bookings_csv( array('status' => sanitize_key($_GET['status'] ?? '')) );
    }
    exit;
}

$today_rev = GHM_Payments::get_total_revenue( date('Y-m-d'), date('Y-m-d') );
$month_rev = GHM_Payments::get_total_revenue( date('Y-m-01'), date('Y-m-t') );

// Build export URLs cleanly
$export_payments_url = wp_nonce_url(
    admin_url('admin.php?page=ghm-payments&ghm_export=payments&from=' . date('Y-m-01') . '&to=' . date('Y-m-t')),
    'ghm_export'
);
$export_bookings_url = wp_nonce_url(
    admin_url('admin.php?page=ghm-payments&ghm_export=bookings'),
    'ghm_export'
);
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-money-alt"></span> Payments &amp; Checkout</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="<?php echo esc_url($export_payments_url); ?>"
         class="ghm-btn ghm-btn-outline ghm-btn-sm">
        ⬇ Export Payments CSV
      </a>
      <a href="<?php echo esc_url($export_bookings_url); ?>"
         class="ghm-btn ghm-btn-outline ghm-btn-sm">
        ⬇ Export Bookings CSV
      </a>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="ghm-stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
    <div class="ghm-stat-card">
      <div class="stat-label">Revenue Today</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym); ?>"><?php echo number_format($today_rev,2); ?></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Revenue This Month</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym); ?>"><?php echo number_format($month_rev,2); ?></div>
    </div>
    <div class="ghm-stat-card accent-danger">
      <div class="stat-label">Outstanding Balance</div>
      <?php global $wpdb;
      $outstanding = (float)$wpdb->get_var("SELECT SUM(total_amount-paid_amount) FROM {$wpdb->prefix}ghm_bookings WHERE payment_status IN ('unpaid','partial') AND status NOT IN ('cancelled')");
      ?>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym); ?>"><?php echo number_format($outstanding,2); ?></div>
    </div>
  </div>

  <!-- Export with date range filter -->
  <div class="ghm-form-section" style="margin-bottom:20px;">
    <p class="ghm-form-section-title">📊 Export Reports</p>
    <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <?php wp_nonce_field('ghm_export'); ?>
      <input type="hidden" name="page" value="ghm-payments">
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--ghm-muted);">From</label>
        <input type="date" name="from" value="<?php echo esc_attr(date('Y-m-01')); ?>"
          style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:8px 12px;color:var(--ghm-text);font-size:13px;">
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--ghm-muted);">To</label>
        <input type="date" name="to" value="<?php echo esc_attr(date('Y-m-t')); ?>"
          style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:8px 12px;color:var(--ghm-text);font-size:13px;">
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--ghm-muted);">Format</label>
        <select name="ghm_export_format" id="ghm-export-format"
          style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:8px 12px;color:var(--ghm-text);font-size:13px;">
          <option value="csv">CSV (Excel compatible)</option>
          <option value="print">Print / PDF</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;padding-bottom:0;">
        <button type="submit" name="ghm_export" value="payments" class="ghm-btn ghm-btn-primary">
          💳 Export Payments
        </button>
        <button type="submit" name="ghm_export" value="bookings" class="ghm-btn ghm-btn-outline">
          📅 Export Bookings
        </button>
        <button type="button" class="ghm-btn ghm-btn-outline" onclick="window.print()">
          🖨 Print
        </button>
      </div>
    </form>
  </div>

  <!-- Payment records -->
  <div class="ghm-table-wrap">
    <table class="ghm-table" id="ghm-payments-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Booking Ref</th>
          <th>Customer</th>
          <th>Method</th>
          <th>Amount</th>
          <th>Transaction ID</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--ghm-muted);">No payments recorded yet.</td></tr>
        <?php else: foreach ($payments as $p): ?>
        <tr>
          <td><?php echo date('M j, Y H:i', strtotime($p->created_at)); ?></td>
          <td style="color:var(--ghm-gold);font-size:12px;font-family:monospace;"><?php echo esc_html($p->booking_ref); ?></td>
          <td><?php echo esc_html($p->customer_name); ?></td>
          <td><span class="ghm-badge checked_out"><?php echo $methods[$p->method] ?? ucfirst($p->method); ?></span></td>
          <td><strong><?php echo $sym.number_format($p->amount,2); ?></strong></td>
          <td style="font-size:12px;color:var(--ghm-muted);"><?php echo esc_html($p->transaction_id ?: '—'); ?></td>
          <td><span class="ghm-badge paid"><?php echo ucfirst($p->status); ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pending payments section -->
  <?php
  global $wpdb;
  $unpaid = $wpdb->get_results(
    "SELECT b.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name, r.name AS room_name
     FROM {$wpdb->prefix}ghm_bookings b
     LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
     LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = b.room_id
     WHERE b.payment_status IN ('unpaid','partial') AND b.status NOT IN ('cancelled')
     ORDER BY b.check_in ASC LIMIT 20"
  );
  if (!empty($unpaid)):
  ?>
  <div style="margin-top:28px;">
    <h3 style="font-family:'Playfair Display',serif;color:var(--ghm-gold);font-size:16px;margin-bottom:14px;">
      <span class="dashicons dashicons-warning" style="color:var(--ghm-warning);"></span> Pending Payments
    </h3>
    <div class="ghm-table-wrap">
      <table class="ghm-table">
        <thead>
          <tr><th>Booking Ref</th><th>Guest</th><th>Room</th><th>Check-In</th><th>Total</th><th>Paid</th><th>Balance</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($unpaid as $b): $balance = $b->total_amount - $b->paid_amount; ?>
          <tr>
            <td style="color:var(--ghm-gold);font-size:12px;font-family:monospace;"><?php echo esc_html($b->booking_ref); ?></td>
            <td><?php echo esc_html($b->customer_name); ?></td>
            <td><?php echo esc_html($b->room_name); ?></td>
            <td><?php echo date('M j, Y', strtotime($b->check_in)); ?></td>
            <td><?php echo $sym.number_format($b->total_amount,2); ?></td>
            <td style="color:var(--ghm-success);"><?php echo $sym.number_format($b->paid_amount,2); ?></td>
            <td style="color:var(--ghm-danger);font-weight:600;"><?php echo $sym.number_format($balance,2); ?></td>
            <td>
              <button class="ghm-btn ghm-btn-primary ghm-btn-sm ghm-record-payment-btn"
                      data-id="<?php echo $b->id; ?>">
                <span class="dashicons dashicons-money-alt" style="font-size:13px;margin-top:2px;"></span>
                Record Payment
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
@media print {
  #adminmenumain, #wpadminbar, #wpfooter, .ghm-page-header div, .ghm-form-section { display:none !important; }
  #ghm-wrap { background:#fff !important; color:#000 !important; }
  .ghm-table-wrap, .ghm-table { border:1px solid #ccc !important; }
  .ghm-table thead { background:#1a1a2e !important; -webkit-print-color-adjust:exact; }
  body { font-size:12px; }
}
</style>
