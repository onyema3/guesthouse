<?php if ( ! defined( 'ABSPATH' ) ) exit;
$sym              = get_option( 'ghm_currency_symbol', '₦' );
$currency         = strtoupper( get_option( 'ghm_currency', 'NGN' ) );
$checkin_time     = get_option( 'ghm_checkin_time',  '14:00' );
$checkout_time    = get_option( 'ghm_checkout_time', '11:00' );
$today            = date( 'Y-m-d' );
$tomorrow         = date( 'Y-m-d', strtotime( '+1 day' ) );
$paystack_enabled = GHM_Paystack::is_enabled();
$pay_required     = get_option( 'ghm_paystack_payment_required', 'optional' );
$hotel_name       = get_option( 'ghm_hotel_name', get_bloginfo('name') );

$workspace_rooms = array(); // workspaces + halls (billed hourly/daily, not overnight)
$regular_rooms   = array(); // rooms, suites, apartments (billed per night)
foreach ( $rooms as $r ) {
    if ( in_array( $r->type, array('workspace','hall') ) ) $workspace_rooms[] = $r;
    else                                                    $regular_rooms[]   = $r;
}
?>
<div class="ghm-public-wrap">
  <div class="ghm-booking-form-wrap">
    <h2><?php echo esc_html( $hotel_name ); ?> &mdash; Reserve</h2>

    <div class="ghm-form-steps">
      <div class="ghm-form-step active" data-step="1">1. Dates &amp; Room</div>
      <div class="ghm-form-step" data-step="2">2. Your Details</div>
      <div class="ghm-form-step" data-step="3">3. Payment</div>
    </div>

    <div id="ghm-form-alerts"></div>

    <form id="ghm-public-booking-form" novalidate>
      <?php wp_nonce_field( 'ghm_public_nonce', 'nonce' ); ?>
      <input type="hidden" id="ghm-pb-total-amount"  name="total_amount"  value="0">
      <input type="hidden" id="ghm-currency-symbol"  value="<?php echo esc_attr($sym); ?>">
      <input type="hidden" id="ghm-currency-code"    value="<?php echo esc_attr($currency); ?>">
      <input type="hidden" id="ghm-paystack-enabled" value="<?php echo $paystack_enabled ? '1' : '0'; ?>">
      <input type="hidden" id="ghm-pay-required"     value="<?php echo esc_attr($pay_required); ?>">

      <!-- STEP 1 -->
      <div class="ghm-form-step-body" data-step="1">
        <div class="ghm-bform-grid">
          <div class="ghm-bform-field">
            <label>Booking Type</label>
            <select name="booking_type" id="ghm-pb-type">
              <option value="room">Room / Suite</option>
              <?php if ( ! empty($workspace_rooms) ): ?>
              <option value="workspace">Workspace / Conference</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="ghm-bform-field">
            <label>Room / Space *</label>
            <select name="room_id" id="ghm-pb-room" required>
              <option value="">— Choose —</option>
              <?php if ( ! empty($regular_rooms) ): ?>
              <optgroup label="Rooms &amp; Suites">
                <?php foreach ( $regular_rooms as $r ):
                  $rdp = GHM_Rooms::get_display_price($r); ?>
                <option value="<?php echo $r->id; ?>" data-type="<?php echo esc_attr($r->type); ?>">
                  <?php echo esc_html($r->name); ?> &mdash;
                  <?php echo $sym.number_format($rdp['price'],2).$rdp['unit']; ?>
                  (max <?php echo $r->capacity; ?> guests)
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
              <?php if ( ! empty($workspace_rooms) ): ?>
              <optgroup label="Workspaces &amp; Halls">
                <?php foreach ( $workspace_rooms as $r ):
                  $rdp = GHM_Rooms::get_display_price($r); ?>
                <option value="<?php echo $r->id; ?>" data-type="<?php echo esc_attr($r->type); ?>">
                  <?php echo esc_html($r->name); ?> &mdash;
                  <?php echo $sym.number_format($rdp['price'],2).$rdp['unit']; ?>
                  (cap. <?php echo $r->capacity; ?>)
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
            </select>
          </div>
          <div class="ghm-bform-field">
            <label>Check-In *</label>
            <input type="datetime-local" name="check_in" id="ghm-pb-checkin" value="<?php echo $today.'T'.$checkin_time; ?>" required>
          </div>
          <div class="ghm-bform-field">
            <label>Check-Out *</label>
            <input type="datetime-local" name="check_out" id="ghm-pb-checkout" value="<?php echo $tomorrow.'T'.$checkout_time; ?>" required>
          </div>
          <div class="ghm-bform-field">
            <label>Adults</label>
            <select name="adults"><?php for ($i=1;$i<=8;$i++) echo "<option value='$i'>$i adult".($i>1?'s':'')."</option>"; ?></select>
          </div>
          <div class="ghm-bform-field">
            <label>Children</label>
            <select name="children"><?php for ($i=0;$i<=6;$i++) echo "<option value='$i'>$i ".($i===1?'child':'children')."</option>"; ?></select>
          </div>
          <div class="ghm-bform-field span-2">
            <label>Special Requests</label>
            <textarea name="special_requests" rows="2" placeholder="Dietary requirements, room preferences…"></textarea>
          </div>
          <input type="hidden" name="source" value="direct_website">
        </div>

        <div id="ghm-pb-amount-preview" style="display:none;" class="ghm-amount-preview">
          <span class="label">Estimated Total</span>
          <span class="value" id="ghm-pb-amount-value"><?php echo $sym; ?>0.00</span>
        </div>

        <!-- Discount code -->
        <div class="ghm-discount-section" id="ghm-discount-section" style="display:none;">
          <div class="ghm-discount-label" id="ghm-discount-toggle">
            🏷️ Have a discount code?
          </div>
          <div id="ghm-discount-fields" style="display:none;">
            <div class="ghm-discount-input-row">
              <input type="text" id="ghm-discount-input" placeholder="Enter code e.g. SUMMER20" autocomplete="off">
              <input type="hidden" id="ghm-discount-id" name="discount_id" value="">
              <input type="hidden" id="ghm-discount-amount-field" name="discount_amount" value="0">
              <button type="button" class="ghm-bform-submit" id="ghm-discount-apply-btn"
                style="padding:10px 18px;font-size:13px;">Apply</button>
            </div>
            <div id="ghm-discount-result" class="ghm-discount-result"></div>
          </div>
        </div>

        <div style="margin-top:20px;">
          <button type="button" class="ghm-bform-submit ghm-step-next" data-step="1">
            Continue to Guest Details &#8594;
          </button>
        </div>
      </div>

      <!-- STEP 2 -->
      <div class="ghm-form-step-body" data-step="2" style="display:none;">
        <div class="ghm-bform-grid">
          <div class="ghm-bform-field"><label>First Name *</label><input type="text" name="first_name" id="ghm-pb-first-name" required placeholder="John"></div>
          <div class="ghm-bform-field"><label>Last Name *</label><input type="text" name="last_name" id="ghm-pb-last-name" required placeholder="Smith"></div>
          <div class="ghm-bform-field"><label>Email Address *</label><input type="email" name="email" id="ghm-pb-email" required placeholder="john@example.com"></div>
          <div class="ghm-bform-field"><label>Phone Number</label><input type="tel" name="phone" placeholder="+234 800 000 0000"></div>
          <div class="ghm-bform-field"><label>Country</label><input type="text" name="country" placeholder="Nigeria"></div>
          <div class="ghm-bform-field">
            <label>ID Type</label>
            <select name="id_type">
              <option value="">— Optional —</option>
              <option value="passport">Passport</option>
              <option value="national_id">National ID</option>
              <option value="drivers_license">Driver's License</option>
            </select>
          </div>
          <div class="ghm-bform-field span-2"><label>ID Number (optional)</label><input type="text" name="id_number" placeholder="Leave blank if not applicable"></div>
        </div>
        <div style="display:flex;gap:12px;margin-top:20px;">
          <button type="button" class="ghm-bform-submit ghm-step-prev" data-step="2" style="background:#f3f4f6;color:#374151;">&#8592; Back</button>
          <button type="button" class="ghm-bform-submit ghm-step-next" data-step="2">Review &amp; Payment &#8594;</button>
        </div>
      </div>

      <!-- STEP 3 -->
      <div class="ghm-form-step-body" data-step="3" style="display:none;">

        <div class="ghm-booking-summary-box">
          <h3 style="font-family:'Playfair Display',serif;color:#1a1a2e;margin:0 0 14px;font-size:17px;">Booking Summary</h3>
          <div id="ghm-confirm-summary"></div>
        </div>

        <?php
        $flw_enabled = class_exists('GHM_Flutterwave') && GHM_Flutterwave::is_enabled();
        $any_gateway = $paystack_enabled || $flw_enabled;
        if ( $any_gateway ): ?>
        <div class="ghm-payment-method-wrap">
          <p class="ghm-payment-method-label">How would you like to pay?</p>
          <div class="ghm-payment-options">

            <?php if ($paystack_enabled): ?>
            <label class="ghm-pay-option <?php echo ($pay_required==='required'&&!$flw_enabled)?'ghm-pay-option--selected':''; ?>" id="ghm-opt-paystack">
              <input type="radio" name="payment_option" value="paystack"
                <?php echo ($pay_required==='required'&&!$flw_enabled)?'checked':''; ?>>
              <span class="ghm-pay-icon">
                <svg width="20" height="20" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="#00C3F7"/><path d="M7 16h18M7 10h12M13 22h12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
              </span>
              <span class="ghm-pay-details">
                <strong>Pay via Paystack</strong>
                <small>Card, Bank Transfer, USSD, Mobile Money — instant confirmation</small>
              </span>
              <span class="ghm-pay-badge">✓ Instant</span>
            </label>
            <?php endif; ?>

            <?php if ($flw_enabled): ?>
            <label class="ghm-pay-option <?php echo $pay_required==='required'?'ghm-pay-option--selected':''; ?>" id="ghm-opt-flutterwave">
              <input type="radio" name="payment_option" value="flutterwave"
                <?php echo $pay_required==='required'?'checked':''; ?>>
              <span class="ghm-pay-icon" style="color:#FF6B00;font-weight:700;font-size:16px;">🦋</span>
              <span class="ghm-pay-details">
                <strong>Pay via Flutterwave</strong>
                <small>Card, Bank Transfer, USSD, Mobile Money — 30+ African currencies</small>
              </span>
              <span class="ghm-pay-badge">✓ Instant</span>
            </label>
            <?php endif; ?>

            <?php if ( $pay_required !== 'required' ): ?>
            <label class="ghm-pay-option" id="ghm-opt-arrival">
              <input type="radio" name="payment_option" value="arrival">
              <span class="ghm-pay-icon">🏨</span>
              <span class="ghm-pay-details">
                <strong>Pay on Arrival</strong>
                <small>Cash or card at the reception desk when you check in</small>
              </span>
            </label>
            <?php endif; ?>

          </div>
        </div>
        <?php else: ?>
        <div class="ghm-alert info" style="margin:0 0 20px;">
          🏨 <strong>Pay on Arrival</strong> &mdash; Payment is collected at reception during check-in.
        </div>
        <input type="hidden" name="payment_option" value="arrival">
        <?php endif; ?>

        <label class="ghm-terms-check">
          <input type="checkbox" id="ghm-terms-cb" required>
          I agree to the property&rsquo;s booking and cancellation policy.
        </label>

        <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;align-items:center;">
          <button type="button" class="ghm-bform-submit ghm-step-prev" data-step="3" style="background:#f3f4f6;color:#374151;">&#8592; Back</button>
          <button type="button" id="ghm-confirm-btn" class="ghm-bform-submit">
            &#10003; Confirm Booking
          </button>
        </div>

      </div>
    </form>
  </div>
</div>
