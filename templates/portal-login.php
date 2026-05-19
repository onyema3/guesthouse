<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ghm-portal-wrap" id="ghm-portal">
  <div class="ghm-portal-login-card">
    <div class="ghm-portal-logo">
      <span>🏨</span>
      <h1><?php echo esc_html( get_option('ghm_hotel_name', get_bloginfo('name')) ); ?></h1>
      <p>Guest Portal — View your reservation</p>
    </div>

    <div id="ghm-portal-alert" class="ghm-portal-alert" style="display:none;"></div>

    <form id="ghm-portal-login-form" novalidate>
      <div class="ghm-portal-field">
        <label>Booking Reference</label>
        <input type="text" id="ghm-portal-ref" placeholder="e.g. GHM-A1B2C3-20250501"
               autocomplete="off" style="text-transform:uppercase;letter-spacing:1px;">
      </div>
      <div class="ghm-portal-field">
        <label>Email Address</label>
        <input type="email" id="ghm-portal-email" placeholder="The email used when booking">
      </div>
      <button type="submit" class="ghm-portal-btn ghm-portal-btn-primary" id="ghm-portal-login-btn">
        Access My Booking
      </button>
    </form>

    <p class="ghm-portal-hint">
      Your booking reference was sent to your email or WhatsApp when you reserved.
      Need help? Contact reception.
    </p>
  </div>
</div>
