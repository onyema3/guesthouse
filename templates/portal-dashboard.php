<?php if ( ! defined( 'ABSPATH' ) ) exit;
$sym          = get_option('ghm_currency_symbol','₦');
$hotel        = get_option('ghm_hotel_name', get_bloginfo('name'));
$checkin_time = get_option('ghm_checkin_time','14:00');
$checkout_time= get_option('ghm_checkout_time','11:00');
$balance      = max(0, (float)$booking->total_amount - (float)$booking->paid_amount);
$nights       = max(1,(int)(new DateTime($booking->check_in))->diff(new DateTime($booking->check_out))->days);
$service_types= GHM_Guest_Portal::get_service_types();

$status_labels= array(
    'booked'     =>'Booked — Awaiting Payment',
    'confirmed'  =>'Confirmed ✓',
    'checked_in' =>'Checked In 🏨',
    'checked_out'=>'Checked Out',
    'cancelled'  =>'Cancelled',
);
$status_colors= array(
    'booked'     =>'#a78bfa',
    'confirmed'  =>'#3ecf8e',
    'checked_in' =>'#60a5fa',
    'checked_out'=>'#c9a84c',
    'cancelled'  =>'#ef4444',
);
$sc = $status_colors[$booking->status] ?? '#6b7280';
$sl = $status_labels[$booking->status] ?? ucfirst($booking->status);

// Determine which sections are visible
// Service requests available for any active (non-cancelled, non-checked-out) booking
$can_service = ! in_array( $booking->status, array( 'cancelled', 'checked_out' ), true );
$can_review  = $booking->status === 'checked_out' && !$review;
$reviewed    = !empty($review);
?>
<div class="ghm-portal-wrap" id="ghm-portal">

  <!-- Header -->
  <div class="ghm-portal-header">
    <div>
      <div class="ghm-portal-hotel"><?php echo esc_html($hotel); ?></div>
      <div class="ghm-portal-welcome">Welcome back, <?php echo esc_html($customer->first_name); ?>!</div>
    </div>
    <button class="ghm-portal-btn ghm-portal-btn-ghost" id="ghm-portal-logout-btn">
      Sign Out
    </button>
  </div>

  <!-- Booking status banner -->
  <div class="ghm-portal-status-banner" style="border-left-color:<?php echo $sc;?>;">
    <div class="ghm-portal-status-dot" style="background:<?php echo $sc;?>;"></div>
    <div>
      <div class="ghm-portal-status-label"><?php echo esc_html($sl); ?></div>
      <div class="ghm-portal-ref"><?php echo esc_html($booking->booking_ref); ?></div>
    </div>
    <?php if ($balance > 0 && $booking->status !== 'cancelled'): ?>
    <div class="ghm-portal-balance-alert">
      Balance due: <strong><?php echo $sym.number_format($balance,2); ?></strong>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tab navigation -->
  <div class="ghm-portal-tabs" id="ghm-portal-tabs">
    <button class="ghm-portal-tab active" data-tab="booking">📋 Booking</button>
    <button class="ghm-portal-tab" data-tab="payments">💳 Payments</button>
    <?php if ($can_service): ?>
    <button class="ghm-portal-tab" data-tab="services">🔔 Request Service</button>
    <?php endif; ?>
    <?php if ($can_review || $reviewed): ?>
    <button class="ghm-portal-tab" data-tab="review">⭐ Review</button>
    <?php endif; ?>
  </div>

  <div id="ghm-portal-alert" class="ghm-portal-alert" style="display:none;margin:0 0 16px;"></div>

  <!-- ══════════ TAB: BOOKING ══════════ -->
  <div class="ghm-portal-tab-content active" id="ghm-tab-booking">

    <div class="ghm-portal-card-grid">
      <!-- Booking details -->
      <div class="ghm-portal-card">
        <h3 class="ghm-portal-card-title">🛏️ Room Details</h3>
        <ul class="ghm-portal-info-list">
          <li><span>Room</span><strong><?php echo esc_html($booking->room_name); ?></strong></li>
          <li><span>Room Number</span><strong><?php echo esc_html($booking->room_number); ?></strong></li>
          <li><span>Type</span><strong><?php echo esc_html(ucfirst($booking->room_type)); ?></strong></li>
          <li><span>Guests</span><strong><?php echo $booking->adults; ?> adult<?php echo $booking->adults>1?'s':''; ?>
            <?php if($booking->children>0) echo ', '.$booking->children.' child'.($booking->children>1?'ren':''); ?>
          </strong></li>
        </ul>
      </div>

      <!-- Dates -->
      <div class="ghm-portal-card">
        <h3 class="ghm-portal-card-title">📅 Stay Dates</h3>
        <div class="ghm-portal-dates-grid">
          <div class="ghm-portal-date-box ghm-portal-date-in">
            <div class="ghm-portal-date-label">Check-In</div>
            <div class="ghm-portal-date-day"><?php echo date('j', strtotime($booking->check_in)); ?></div>
            <div class="ghm-portal-date-month"><?php echo date('M Y', strtotime($booking->check_in)); ?></div>
            <div class="ghm-portal-date-time">From <?php echo $checkin_time; ?></div>
          </div>
          <div class="ghm-portal-nights">
            <div><?php echo $nights; ?></div>
            <div>night<?php echo $nights>1?'s':''; ?></div>
          </div>
          <div class="ghm-portal-date-box ghm-portal-date-out">
            <div class="ghm-portal-date-label">Check-Out</div>
            <div class="ghm-portal-date-day"><?php echo date('j', strtotime($booking->check_out)); ?></div>
            <div class="ghm-portal-date-month"><?php echo date('M Y', strtotime($booking->check_out)); ?></div>
            <div class="ghm-portal-date-time">By <?php echo $checkout_time; ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Financial summary -->
    <div class="ghm-portal-card" style="margin-top:16px;">
      <h3 class="ghm-portal-card-title">💰 Financial Summary</h3>
      <div class="ghm-portal-finance-row">
        <span>Room Total</span>
        <span><?php echo $sym.number_format($booking->total_amount,2); ?></span>
      </div>
      <div class="ghm-portal-finance-row">
        <span>Amount Paid</span>
        <span style="color:#3ecf8e;"><?php echo $sym.number_format($booking->paid_amount,2); ?></span>
      </div>
      <?php if ($balance > 0): ?>
      <div class="ghm-portal-finance-row ghm-portal-finance-balance">
        <span>Balance Due</span>
        <span><?php echo $sym.number_format($balance,2); ?></span>
      </div>
      <?php else: ?>
      <div class="ghm-portal-finance-row" style="color:#3ecf8e;">
        <span>✓ Fully Paid</span><span></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($booking->special_requests)): ?>
    <div class="ghm-portal-card" style="margin-top:16px;">
      <h3 class="ghm-portal-card-title">📝 Special Requests</h3>
      <p style="font-size:14px;color:#374151;line-height:1.6;"><?php echo nl2br(esc_html($booking->special_requests)); ?></p>
    </div>
    <?php endif; ?>

    <!-- Invoice download -->
    <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
      <a href="<?php echo esc_url(GHM_Invoice::get_url($booking->id)); ?>" target="_blank"
         class="ghm-portal-btn ghm-portal-btn-outline">
        📄 Download Invoice
      </a>
      <?php if ($balance > 0 && class_exists('GHM_Paystack') && GHM_Paystack::is_enabled() && $booking->status !== 'cancelled'): ?>
      <button class="ghm-portal-btn ghm-portal-btn-paystack" id="ghm-portal-pay-btn"
              data-booking="<?php echo $booking->id; ?>"
              data-amount="<?php echo $balance; ?>"
              data-email="<?php echo esc_attr($customer->email); ?>"
              data-name="<?php echo esc_attr($customer->first_name.' '.$customer->last_name); ?>"
              data-ref="<?php echo esc_attr($booking->booking_ref); ?>">
        💳 Pay Balance <?php echo $sym.number_format($balance,2); ?> with Paystack
      </button>
      <?php endif; ?>
      <?php if ($balance > 0 && class_exists('GHM_Flutterwave') && GHM_Flutterwave::is_enabled() && $booking->status !== 'cancelled'): ?>
      <button class="ghm-portal-btn ghm-portal-btn-primary" id="ghm-portal-pay-flw-btn"
              data-booking="<?php echo $booking->id; ?>"
              data-amount="<?php echo $balance; ?>"
              data-email="<?php echo esc_attr($customer->email); ?>"
              data-name="<?php echo esc_attr($customer->first_name.' '.$customer->last_name); ?>"
              data-phone="<?php echo esc_attr($customer->phone ?? ''); ?>"
              data-ref="<?php echo esc_attr($booking->booking_ref); ?>">
        💳 Pay Balance <?php echo $sym.number_format($balance,2); ?> with Flutterwave
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════ TAB: PAYMENTS ══════════ -->
  <div class="ghm-portal-tab-content" id="ghm-tab-payments">
    <div class="ghm-portal-card">
      <h3 class="ghm-portal-card-title">💳 Payment History</h3>
      <?php if (empty($payments)): ?>
      <p style="color:#9ca3af;font-size:14px;padding:20px 0;">No payments recorded yet.</p>
      <?php else: ?>
      <div class="ghm-portal-payment-list">
        <?php foreach ($payments as $p): ?>
        <div class="ghm-portal-payment-row">
          <div class="ghm-portal-payment-icon">
            <?php
            $icons = array('cash'=>'💵','card'=>'💳','bank_transfer'=>'🏦','mobile_money'=>'📱','online'=>'🌐','other'=>'💰');
            echo $icons[$p->method] ?? '💰';
            ?>
          </div>
          <div class="ghm-portal-payment-details">
            <div class="ghm-portal-payment-method"><?php echo ucwords(str_replace('_',' ',$p->method)); ?></div>
            <div class="ghm-portal-payment-date"><?php echo date('F j, Y \a\t g:i A', strtotime($p->created_at)); ?></div>
            <?php if ($p->transaction_id): ?>
            <div class="ghm-portal-payment-ref">Ref: <?php echo esc_html($p->transaction_id); ?></div>
            <?php endif; ?>
          </div>
          <div class="ghm-portal-payment-amount"><?php echo $sym.number_format($p->amount,2); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════ TAB: SERVICES ══════════ -->
  <?php if ($can_service): ?>
  <div class="ghm-portal-tab-content" id="ghm-tab-services">
    <div class="ghm-portal-card">
      <h3 class="ghm-portal-card-title">🔔 Request a Service</h3>
      <p style="font-size:14px;color:#6b7280;margin-bottom:16px;">
        <?php if ( $booking->status === 'checked_in' ): ?>
          Our team will attend to your request as soon as possible.
        <?php else: ?>
          Send pre-arrival requests like airport pickup, early check-in, extra bed, or any special arrangement. Our team will follow up before your stay.
        <?php endif; ?>
      </p>

      <form id="ghm-service-form">
        <div class="ghm-portal-field">
          <label>Service Type</label>
          <div class="ghm-portal-service-grid">
            <?php foreach ($service_types as $key => $label): ?>
            <label class="ghm-portal-service-option">
              <input type="radio" name="service_type" value="<?php echo esc_attr($key); ?>"
                     <?php echo $key === 'housekeeping' ? 'checked' : ''; ?>>
              <span><?php echo $label; ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="ghm-portal-field" style="margin-top:16px;">
          <label>Additional Message (optional)</label>
          <textarea id="ghm-service-message" rows="3"
            placeholder="Any specific details about your request…"
            style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;resize:vertical;"></textarea>
        </div>
        <button type="button" class="ghm-portal-btn ghm-portal-btn-primary" id="ghm-service-submit-btn" style="margin-top:12px;">
          Send Request to Front Desk
        </button>
      </form>
    </div>

    <!-- Past service requests -->
    <?php if (!empty($service_requests)): ?>
    <div class="ghm-portal-card" style="margin-top:16px;">
      <h3 class="ghm-portal-card-title">📋 Your Requests</h3>
      <?php foreach ($service_requests as $sr):
        $sr_status_color = $sr->status==='resolved' ? '#3ecf8e' : ($sr->status==='in_progress' ? '#f59e0b' : '#9ca3af');
        $sr_label_full   = $service_types[$sr->type] ?? ucfirst($sr->type);
        // Extract leading emoji (the labels start with one) for the icon column
        $sr_icon         = function_exists('mb_substr') ? trim(mb_substr($sr_label_full, 0, 2)) : '💬';
      ?>
      <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #f3f4f6;">
        <span style="font-size:20px;"><?php echo esc_html($sr_icon ?: '💬'); ?></span>
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:600;color:#1a1a2e;"><?php echo esc_html($sr_label_full); ?></div>
          <?php if ($sr->message): ?><div style="font-size:13px;color:#6b7280;margin-top:2px;"><?php echo esc_html($sr->message); ?></div><?php endif; ?>
          <div style="font-size:11px;color:#9ca3af;margin-top:4px;"><?php echo date('M j, g:i A', strtotime($sr->created_at)); ?></div>
        </div>
        <span style="font-size:11px;font-weight:700;color:<?php echo $sr_status_color;?>;text-transform:uppercase;letter-spacing:.5px;">
          <?php echo ucfirst($sr->status); ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ══════════ TAB: REVIEW ══════════ -->
  <?php if ($can_review || $reviewed): ?>
  <div class="ghm-portal-tab-content" id="ghm-tab-review">
    <?php if ($reviewed): ?>
    <div class="ghm-portal-card ghm-portal-review-submitted">
      <div style="font-size:48px;text-align:center;margin-bottom:12px;">⭐</div>
      <h3 style="text-align:center;font-family:'Playfair Display',serif;font-size:20px;color:#1a1a2e;">Thank you for your review!</h3>
      <p style="text-align:center;color:#6b7280;font-size:14px;">Your feedback has been submitted and will be published after review.</p>
      <div class="ghm-portal-stars-display" style="justify-content:center;margin-top:12px;">
        <?php for($i=1;$i<=5;$i++) echo '<span style="font-size:24px;color:'.($i<=$review->rating?'#f59e0b':'#e5e7eb').';">★</span>'; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="ghm-portal-card">
      <h3 class="ghm-portal-card-title">⭐ How was your stay?</h3>
      <p style="font-size:14px;color:#6b7280;margin-bottom:20px;">Your feedback helps us improve and helps other guests.</p>

      <form id="ghm-review-form">
        <!-- Overall rating -->
        <div class="ghm-portal-field">
          <label>Overall Rating *</label>
          <div class="ghm-portal-star-picker" data-name="rating">
            <?php for($i=1;$i<=5;$i++): ?>
            <span class="ghm-star" data-value="<?php echo $i; ?>" style="font-size:32px;cursor:pointer;color:#e5e7eb;">★</span>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="ghm-rating-val" value="5">
        </div>

        <!-- Category ratings -->
        <div class="ghm-portal-rating-grid">
          <?php foreach (array('cleanliness'=>'🧹 Cleanliness','service'=>'😊 Service','comfort'=>'🛏️ Comfort','value'=>'💰 Value') as $key=>$label): ?>
          <div class="ghm-portal-cat-rating">
            <div class="ghm-portal-cat-label"><?php echo $label; ?></div>
            <div class="ghm-portal-star-picker ghm-portal-star-sm" data-name="<?php echo $key; ?>">
              <?php for($i=1;$i<=5;$i++): ?>
              <span class="ghm-star" data-value="<?php echo $i; ?>" style="font-size:22px;cursor:pointer;color:#e5e7eb;">★</span>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="<?php echo $key; ?>" value="5">
          </div>
          <?php endforeach; ?>
        </div>

        <div class="ghm-portal-field" style="margin-top:16px;">
          <label>Review Title</label>
          <input type="text" name="title" placeholder="e.g. Wonderful stay, highly recommend!" maxlength="200">
        </div>

        <div class="ghm-portal-field">
          <label>Your Review</label>
          <textarea name="comment" rows="4" placeholder="Tell us about your experience…"></textarea>
        </div>

        <button type="button" class="ghm-portal-btn ghm-portal-btn-primary" id="ghm-review-submit-btn" style="margin-top:12px;">
          Submit Review
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /ghm-portal-wrap -->

<?php
// Paystack & Flutterwave SDKs are enqueued via wp_enqueue_script in
// GHM_Guest_Portal::enqueue_portal_assets() / GHM_Paystack::maybe_enqueue() /
// GHM_Flutterwave::maybe_enqueue(). Do NOT add hardcoded <script> tags here —
// previous v2/inline.js tag was overwriting the v1 PaystackPop global and
// breaking the v1 API used by ghm-public.js and ghm-portal.js.
?>
