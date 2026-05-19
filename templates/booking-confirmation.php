<?php if ( ! defined( 'ABSPATH' ) ) exit;
$sym = get_option( 'ghm_currency_symbol', '$' );
?>
<div class="ghm-public-wrap">
  <?php if ( $booking ): ?>
  <div class="ghm-confirmation-wrap">
    <div class="ghm-confirmation-icon">✓</div>
    <h2>Booking Confirmed</h2>
    <p>Your reservation is confirmed. Please keep your booking reference handy.</p>

    <div class="ghm-conf-ref"><?php echo esc_html($booking->booking_ref); ?></div>

    <div class="ghm-conf-details">
      <div class="ghm-conf-row">
        <span class="conf-label">Guest</span>
        <span class="conf-value"><?php echo esc_html($booking->customer_name); ?></span>
      </div>
      <div class="ghm-conf-row">
        <span class="conf-label">Room / Space</span>
        <span class="conf-value"><?php echo esc_html($booking->room_name); ?> (<?php echo esc_html($booking->room_number); ?>)</span>
      </div>
      <div class="ghm-conf-row">
        <span class="conf-label">Check-In</span>
        <span class="conf-value"><?php echo date_i18n('l, F j, Y \a\t g:i A', strtotime($booking->check_in)); ?></span>
      </div>
      <div class="ghm-conf-row">
        <span class="conf-label">Check-Out</span>
        <span class="conf-value"><?php echo date_i18n('l, F j, Y \a\t g:i A', strtotime($booking->check_out)); ?></span>
      </div>
      <div class="ghm-conf-row">
        <span class="conf-label">Status</span>
        <span class="conf-value">
          <?php
          $status_colors = ["booked"=>"background:#f5f3ff;color:#6d28d9","confirmed"=>"background:#f0fdf4;color:#166534","checked_in"=>"background:#eff6ff;color:#1e40af","checked_out"=>"background:#fffbeb;color:#92400e","cancelled"=>"background:#fef2f2;color:#991b1b"];
          $sc  = $status_colors[$booking->status] ?? "background:#f3f4f6;color:#374151";
          $slb = $booking->status === "booked" ? "Booked — Payment Pending" : ucfirst(str_replace("_"," ",$booking->status));
          ?>
          <span style="<?php echo $sc; ?>;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">
            <?php echo esc_html($slb); ?>
          </span>
        </span>
      </div>
      <div class="ghm-conf-row">
        <span class="conf-label">Total Amount</span>
        <span class="conf-value" style="color:#c9a84c;font-size:18px;"><?php echo $sym.number_format($booking->total_amount, 2); ?></span>
      </div>
      <div class="ghm-conf-row">
        <span class="conf-label">Payment Status</span>
        <span class="conf-value"><?php echo ucfirst($booking->payment_status); ?></span>
      </div>
    </div>

    <div class="ghm-alert info">
      📞 Need to modify or cancel your booking? Contact us and quote reference <strong><?php echo esc_html($booking->booking_ref); ?></strong>.
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:16px;">
      <a href="<?php echo esc_url(GHM_Invoice::get_url($booking->id)); ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#c9a84c,#e8c97a);color:#1a1a2e;padding:10px 20px;border-radius:8px;font-weight:700;text-decoration:none;font-size:14px;">
        📄 Download Invoice
      </a>
      <a href="<?php echo home_url(); ?>"
         style="display:inline-flex;align-items:center;gap:6px;color:#c9a84c;font-size:14px;text-decoration:none;">
        &#8592; Back to Home
      </a>
    </div>
  </div>

  <?php elseif ( isset($_GET['ref']) ): ?>
  <div class="ghm-alert error" style="max-width:480px;margin:40px auto;">
    ✗ Booking reference not found. Please double-check the reference number and try again.
  </div>

  <?php else: ?>
  <!-- Search for booking by reference -->
  <div style="max-width:480px;margin:40px auto;text-align:center;">
    <h2 style="font-family:'Playfair Display',serif;font-size:24px;color:#1a1a2e;margin-bottom:8px;">Find Your Booking</h2>
    <p style="color:#6b7280;margin-bottom:24px;">Enter your booking reference number to view your reservation details.</p>
    <form method="GET" style="display:flex;gap:10px;">
      <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
      <input type="text" name="ref" placeholder="e.g. GHM-ABC123-20250501"
             style="flex:1;border:1.5px solid #e5e7eb;border-radius:8px;padding:11px 14px;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;"
             onfocus="this.style.borderColor='#c9a84c'" onblur="this.style.borderColor='#e5e7eb'">
      <button type="submit"
              style="background:linear-gradient(135deg,#c9a84c,#e8c97a);color:#1a1a2e;font-weight:700;border:none;border-radius:8px;padding:11px 20px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;">
        Search
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>
