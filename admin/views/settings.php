<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( isset( $_POST['ghm_save_settings'] ) && check_admin_referer( 'ghm_settings' ) ) {
    // Determine which tab is being saved — only update fields belonging to that tab
    // so saving one tab doesn't wipe fields from other tabs (which aren't in $_POST).
    $saved_tab = sanitize_key( $_POST['ghm_active_tab'] ?? 'general' );

    // Map of tab => array of [text_fields, checkbox_fields]
    $tab_fields = array(
        'general' => array(
            'text' => array(
                'ghm_hotel_name','ghm_hotel_address','ghm_hotel_phone','ghm_admin_email',
                'ghm_currency','ghm_currency_symbol','ghm_checkin_time','ghm_checkout_time',
            ),
            'checkbox' => array( 'ghm_email_notify','ghm_email_digest' ),
        ),
        'payments' => array(
            'text' => array(
                'ghm_paystack_test_public_key','ghm_paystack_test_secret_key',
                'ghm_paystack_live_public_key','ghm_paystack_live_secret_key',
                'ghm_paystack_payment_required',
                'ghm_flw_test_public_key','ghm_flw_test_secret_key',
                'ghm_flw_live_public_key','ghm_flw_live_secret_key',
            ),
            'checkbox' => array(
                'ghm_paystack_enabled','ghm_paystack_test_mode',
                'ghm_flw_enabled','ghm_flw_test_mode',
            ),
        ),
        'whatsapp' => array(
            'text' => array(
                'ghm_wa_provider','ghm_wa_admin_phone','ghm_wa_country_code',
                'ghm_wa_twilio_sid','ghm_wa_twilio_token','ghm_wa_twilio_from',
                'ghm_wa_meta_token','ghm_wa_meta_phone_id',
                'ghm_wa_ultramsg_instance','ghm_wa_ultramsg_token',
            ),
            'checkbox' => array( 'ghm_wa_enabled' ),
        ),
        'calendar' => array(
            'text' => array(
                'ghm_gcal_client_id','ghm_gcal_client_secret','ghm_gcal_calendar_id',
            ),
            'checkbox' => array( 'ghm_gcal_enabled' ),
        ),
        'tax' => array(
            'text' => array( 'ghm_tax_label','ghm_tax_rate' ),
            'checkbox' => array( 'ghm_tax_inclusive' ),
        ),
        'api' => array(
            'text' => array( 'ghm_api_key' ),
            'checkbox' => array(),
        ),
        'shortcodes' => array( 'text' => array(), 'checkbox' => array() ),
    );

    if ( isset( $tab_fields[ $saved_tab ] ) ) {
        foreach ( $tab_fields[ $saved_tab ]['text'] as $f ) {
            if ( array_key_exists( $f, $_POST ) ) {
                update_option( $f, sanitize_text_field( $_POST[ $f ] ) );
            }
        }
        foreach ( $tab_fields[ $saved_tab ]['checkbox'] as $c ) {
            update_option( $c, isset( $_POST[ $c ] ) ? 1 : 0 );
        }
    }

    add_settings_error( 'ghm_settings', 'saved', 'Settings saved successfully.', 'success' );
}
$sym        = get_option('ghm_currency_symbol','₦');
$active_tab = sanitize_key($_GET['tab'] ?? 'general');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-admin-settings"></span> Settings</h1>
  </div>
  <?php settings_errors('ghm_settings'); ?>

  <div class="ghm-settings-tabs">
    <?php foreach(array('general'=>'🏠 General','payments'=>'💳 Payments','whatsapp'=>'💬 WhatsApp','calendar'=>'📅 Calendar','tax'=>'🧾 Tax','api'=>'🔌 API','shortcodes'=>'📋 Shortcodes') as $slug=>$label): ?>
    <a href="?page=ghm-settings&tab=<?php echo $slug;?>" class="ghm-stab <?php echo $active_tab===$slug?'active':'';?>"><?php echo $label;?></a>
    <?php endforeach;?>
  </div>

  <form method="post" action="">
    <?php wp_nonce_field('ghm_settings'); ?>
    <input type="hidden" name="ghm_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

    <?php if ($active_tab==='general'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">Property Information</p>
      <div class="ghm-form-grid">
        <div class="ghm-form-field span-2"><label>Hotel / Guest House Name</label><input type="text" name="ghm_hotel_name" value="<?php echo esc_attr(get_option('ghm_hotel_name',get_bloginfo('name')));?>"></div>
        <div class="ghm-form-field span-2"><label>Address</label><input type="text" name="ghm_hotel_address" value="<?php echo esc_attr(get_option('ghm_hotel_address',''));?>" placeholder="123 Main Street, Lagos"></div>
        <div class="ghm-form-field"><label>Phone</label><input type="text" name="ghm_hotel_phone" value="<?php echo esc_attr(get_option('ghm_hotel_phone',''));?>"></div>
        <div class="ghm-form-field"><label>Admin Email</label><input type="email" name="ghm_admin_email" value="<?php echo esc_attr(get_option('ghm_admin_email',get_option('admin_email')));?>"></div>
        <div class="ghm-form-field"><label>Currency Code</label>
          <select name="ghm_currency"><?php $curr=get_option('ghm_currency','NGN'); foreach(array('NGN'=>'NGN – Naira','USD'=>'USD – Dollar','EUR'=>'EUR – Euro','GBP'=>'GBP – Pound','KES'=>'KES – Shilling','GHS'=>'GHS – Cedi','ZAR'=>'ZAR – Rand') as $c=>$l): ?><option value="<?php echo $c;?>" <?php selected($curr,$c);?>><?php echo $l;?></option><?php endforeach;?></select></div>
        <div class="ghm-form-field"><label>Currency Symbol</label><input type="text" name="ghm_currency_symbol" value="<?php echo esc_attr(get_option('ghm_currency_symbol','₦'));?>"></div>
        <div class="ghm-form-field"><label>Default Check-In Time</label><input type="time" name="ghm_checkin_time" value="<?php echo esc_attr(get_option('ghm_checkin_time','14:00'));?>"></div>
        <div class="ghm-form-field"><label>Default Check-Out Time</label><input type="time" name="ghm_checkout_time" value="<?php echo esc_attr(get_option('ghm_checkout_time','11:00'));?>"></div>
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_email_notify" value="1" <?php checked(get_option('ghm_email_notify',1),1);?>> Send email notifications on new bookings</label></div>
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_email_digest" value="1" <?php checked(get_option('ghm_email_digest',1),1);?>> Send daily digest email to admin at 8am</label></div>
      </div>
    </div>

    <?php elseif ($active_tab==='payments'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">💳 Paystack</p>
      <div class="ghm-form-grid">
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_paystack_enabled" value="1" <?php checked(get_option('ghm_paystack_enabled',0),1);?>> Enable Paystack online payments</label></div>
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_paystack_test_mode" value="1" <?php checked(get_option('ghm_paystack_test_mode',1),1);?>> Test Mode <small style="color:var(--ghm-warning);">(no real charges)</small></label></div>
        <div class="ghm-form-field"><label>Test Public Key</label><input type="text" name="ghm_paystack_test_public_key" value="<?php echo esc_attr(get_option('ghm_paystack_test_public_key',''));?>" placeholder="pk_test_…"></div>
        <div class="ghm-form-field"><label>Test Secret Key</label><input type="password" name="ghm_paystack_test_secret_key" value="<?php echo esc_attr(get_option('ghm_paystack_test_secret_key',''));?>" placeholder="sk_test_…"></div>
        <div class="ghm-form-field"><label>Live Public Key</label><input type="text" name="ghm_paystack_live_public_key" value="<?php echo esc_attr(get_option('ghm_paystack_live_public_key',''));?>" placeholder="pk_live_…"></div>
        <div class="ghm-form-field"><label>Live Secret Key</label><input type="password" name="ghm_paystack_live_secret_key" value="<?php echo esc_attr(get_option('ghm_paystack_live_secret_key',''));?>" placeholder="sk_live_…"></div>
        <div class="ghm-form-field span-2"><label>Payment Requirement</label>
          <select name="ghm_paystack_payment_required">
            <option value="optional" <?php selected(get_option('ghm_paystack_payment_required','optional'),'optional');?>>Optional — guest chooses Pay Now or Arrival</option>
            <option value="required" <?php selected(get_option('ghm_paystack_payment_required','optional'),'required');?>>Required — must pay online</option>
          </select></div>
      </div>
      <div style="margin-top:14px;background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:12px 16px;">
        <div style="font-size:11px;color:var(--ghm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;">Webhook URL (add in Paystack Dashboard → Settings → Webhooks)</div>
        <div style="display:flex;gap:10px;align-items:center;"><code style="flex:1;font-size:12px;color:var(--ghm-gold);word-break:break-all;"><?php echo esc_html(GHM_Paystack::webhook_url());?></code>
        <button type="button" class="ghm-btn ghm-btn-outline ghm-btn-sm" onclick="navigator.clipboard.writeText('<?php echo esc_js(GHM_Paystack::webhook_url());?>');this.textContent='Copied!'">Copy</button></div>
      </div>
    </div>

    <!-- Flutterwave section -->
    <div class="ghm-form-section" style="max-width:820px;margin-top:20px;">
      <p class="ghm-form-section-title">🦋 Flutterwave Online Payments</p>
      <div style="background:rgba(255,107,0,.06);border:1px solid rgba(255,107,0,.2);border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px;">
        Flutterwave covers Nigeria, Ghana, Kenya, South Africa, Uganda, Tanzania and 30+ African countries.
        Get keys from <a href="https://dashboard.flutterwave.com/settings/apis" target="_blank" style="color:var(--ghm-gold);">Flutterwave Dashboard → Settings → APIs</a>.
      </div>
      <div class="ghm-form-grid">
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;">
          <input type="checkbox" name="ghm_flw_enabled" value="1" <?php checked(get_option('ghm_flw_enabled',0),1);?>> Enable Flutterwave</label></div>
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;">
          <input type="checkbox" name="ghm_flw_test_mode" value="1" <?php checked(get_option('ghm_flw_test_mode',1),1);?>> Test Mode</label></div>
        <div class="ghm-form-field"><label>Test Public Key</label><input type="text" name="ghm_flw_test_public_key" value="<?php echo esc_attr(get_option('ghm_flw_test_public_key',''));?>" placeholder="FLWPUBK_TEST-…"></div>
        <div class="ghm-form-field"><label>Test Secret Key</label><input type="password" name="ghm_flw_test_secret_key" value="<?php echo esc_attr(get_option('ghm_flw_test_secret_key',''));?>" placeholder="FLWSECK_TEST-…"></div>
        <div class="ghm-form-field"><label>Live Public Key</label><input type="text" name="ghm_flw_live_public_key" value="<?php echo esc_attr(get_option('ghm_flw_live_public_key',''));?>" placeholder="FLWPUBK-…"></div>
        <div class="ghm-form-field"><label>Live Secret Key</label><input type="password" name="ghm_flw_live_secret_key" value="<?php echo esc_attr(get_option('ghm_flw_live_secret_key',''));?>" placeholder="FLWSECK-…"></div>
      </div>
      <?php if(class_exists('GHM_Flutterwave')): ?>
      <div style="margin-top:12px;background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:12px 16px;">
        <div style="font-size:11px;color:var(--ghm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;">Webhook URL</div>
        <div style="display:flex;gap:10px;align-items:center;">
          <code style="flex:1;font-size:12px;color:var(--ghm-gold);word-break:break-all;"><?php echo esc_html(GHM_Flutterwave::webhook_url());?></code>
          <button type="button" class="ghm-btn ghm-btn-outline ghm-btn-sm" onclick="navigator.clipboard.writeText('<?php echo esc_js(GHM_Flutterwave::webhook_url());?>');this.textContent='Copied!'">Copy</button>
        </div>
      </div>
      <?php endif;?>
    </div>

    <?php elseif ($active_tab==='whatsapp'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">💬 WhatsApp Notifications</p>
      <div style="background:rgba(62,207,142,.08);border:1px solid rgba(62,207,142,.2);border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:18px;">
        Sends guests WhatsApp messages for bookings, payments, check-in reminders, and post-stay thank-yous. Supports <strong>Twilio</strong>, <strong>WhatsApp Cloud API (Meta)</strong>, and <strong>UltraMsg</strong>.
      </div>
      <div class="ghm-form-grid">
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_wa_enabled" value="1" <?php checked(get_option('ghm_wa_enabled',0),1);?>> Enable WhatsApp notifications</label></div>
        <div class="ghm-form-field"><label>Provider</label>
          <select name="ghm_wa_provider">
            <option value="" <?php selected(get_option('ghm_wa_provider',''),'');?>>— Select —</option>
            <option value="twilio"   <?php selected(get_option('ghm_wa_provider',''),'twilio');?>>Twilio WhatsApp</option>
            <option value="meta"     <?php selected(get_option('ghm_wa_provider',''),'meta');?>>WhatsApp Cloud API (Meta)</option>
            <option value="ultramsg" <?php selected(get_option('ghm_wa_provider',''),'ultramsg');?>>UltraMsg</option>
          </select></div>
        <div class="ghm-form-field"><label>Admin WhatsApp Number</label><input type="text" name="ghm_wa_admin_phone" value="<?php echo esc_attr(get_option('ghm_wa_admin_phone',''));?>" placeholder="+2348012345678"></div>
        <div class="ghm-form-field"><label>Default Country Code</label><input type="text" name="ghm_wa_country_code" value="<?php echo esc_attr(get_option('ghm_wa_country_code','234'));?>" placeholder="234"></div>
        <div class="ghm-form-field"><label>Twilio SID</label><input type="text" name="ghm_wa_twilio_sid" value="<?php echo esc_attr(get_option('ghm_wa_twilio_sid',''));?>" placeholder="ACxxxxx"></div>
        <div class="ghm-form-field"><label>Twilio Auth Token</label><input type="password" name="ghm_wa_twilio_token" value="<?php echo esc_attr(get_option('ghm_wa_twilio_token',''));?>"></div>
        <div class="ghm-form-field span-2"><label>Twilio From Number</label><input type="text" name="ghm_wa_twilio_from" value="<?php echo esc_attr(get_option('ghm_wa_twilio_from','whatsapp:+14155238886'));?>"></div>
        <div class="ghm-form-field"><label>Meta Access Token</label><input type="password" name="ghm_wa_meta_token" value="<?php echo esc_attr(get_option('ghm_wa_meta_token',''));?>"></div>
        <div class="ghm-form-field"><label>Meta Phone Number ID</label><input type="text" name="ghm_wa_meta_phone_id" value="<?php echo esc_attr(get_option('ghm_wa_meta_phone_id',''));?>"></div>
        <div class="ghm-form-field"><label>UltraMsg Instance</label><input type="text" name="ghm_wa_ultramsg_instance" value="<?php echo esc_attr(get_option('ghm_wa_ultramsg_instance',''));?>"></div>
        <div class="ghm-form-field"><label>UltraMsg Token</label><input type="password" name="ghm_wa_ultramsg_token" value="<?php echo esc_attr(get_option('ghm_wa_ultramsg_token',''));?>"></div>
      </div>
    </div>

    <?php elseif ($active_tab==='calendar'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">📅 Google Calendar Sync</p>
      <div class="ghm-form-grid">
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_gcal_enabled" value="1" <?php checked(get_option('ghm_gcal_enabled',0),1);?>> Enable Google Calendar sync</label></div>
        <div class="ghm-form-field"><label>OAuth Client ID</label><input type="text" name="ghm_gcal_client_id" value="<?php echo esc_attr(get_option('ghm_gcal_client_id',''));?>" placeholder="xxxxxx.apps.googleusercontent.com"></div>
        <div class="ghm-form-field"><label>OAuth Client Secret</label><input type="password" name="ghm_gcal_client_secret" value="<?php echo esc_attr(get_option('ghm_gcal_client_secret',''));?>"></div>
        <div class="ghm-form-field span-2"><label>Calendar ID</label><input type="text" name="ghm_gcal_calendar_id" value="<?php echo esc_attr(get_option('ghm_gcal_calendar_id','primary'));?>"></div>
      </div>
      <?php if(get_option('ghm_gcal_access_token','')): ?>
      <div class="ghm-notice success" style="margin-top:14px;">✓ Google Calendar connected.</div>
      <?php else: $auth_url = get_option('ghm_gcal_client_id') ? GHM_GoogleCalendar::get_auth_url() : '#'; ?>
      <div style="margin-top:14px;"><a href="<?php echo esc_url($auth_url);?>" class="ghm-btn ghm-btn-primary" <?php echo !get_option('ghm_gcal_client_id')?'style="opacity:.5;pointer-events:none;"':'';?>>🔗 Connect Google Calendar</a></div>
      <?php endif;?>
      <div style="margin-top:20px;"><p style="font-size:13px;color:var(--ghm-muted);margin-bottom:10px;font-weight:600;">iCal Feeds (for Booking.com, Airbnb, etc.)</p>
        <?php foreach(GHM_Rooms::get_rooms(array('limit'=>20)) as $r): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <span style="font-size:12px;min-width:160px;color:var(--ghm-muted);"><?php echo esc_html($r->name);?></span>
          <code style="font-size:11px;color:var(--ghm-gold);flex:1;word-break:break-all;"><?php echo esc_html(rest_url('ghm/v1/ical/'.$r->id));?></code>
          <button type="button" class="ghm-btn ghm-btn-outline ghm-btn-sm" onclick="navigator.clipboard.writeText('<?php echo esc_js(rest_url('ghm/v1/ical/'.$r->id));?>');this.textContent='Copied!'">Copy</button>
        </div>
        <?php endforeach;?>
      </div>
    </div>

    <?php elseif ($active_tab==='tax'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">🧾 Tax Configuration</p>
      <div class="ghm-form-grid">
        <div class="ghm-form-field"><label>Tax Label (VAT, GST, Service Charge…)</label><input type="text" name="ghm_tax_label" value="<?php echo esc_attr(get_option('ghm_tax_label','VAT'));?>" placeholder="VAT"></div>
        <div class="ghm-form-field"><label>Tax Rate (%)</label><input type="number" name="ghm_tax_rate" step="0.01" min="0" max="100" value="<?php echo esc_attr(get_option('ghm_tax_rate',0));?>" placeholder="7.5"></div>
        <div class="ghm-form-field span-2"><label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="ghm_tax_inclusive" value="1" <?php checked(get_option('ghm_tax_inclusive',0),1);?>> Tax-inclusive pricing (prices already include tax)</label></div>
      </div>
      <?php if(get_option('ghm_tax_rate',0)>0): $t=GHM_Tax::calculate(50000);?>
      <div style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:14px 16px;margin-top:12px;font-size:13px;color:var(--ghm-text);">
        Preview: <?php echo $sym;?>50,000 booking → <?php echo get_option('ghm_tax_label','VAT');?> <?php echo $sym;?><?php echo number_format($t['tax'],2);?> → Total <?php echo $sym;?><?php echo number_format($t['total'],2);?>
      </div>
      <?php endif;?>
    </div>

    <?php elseif ($active_tab==='api'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">🔌 REST API</p>
      <p style="font-size:13px;color:var(--ghm-muted);margin-bottom:14px;">Base URL: <code style="color:var(--ghm-gold);"><?php echo esc_html(rest_url('ghm/v1/'));?></code></p>
      <div class="ghm-form-grid">
        <div class="ghm-form-field span-2"><label>API Key (send as <code>X-GHM-API-Key</code> header)</label>
          <div style="display:flex;gap:8px;">
            <input type="text" name="ghm_api_key" id="ghm-api-key-field" value="<?php echo esc_attr(get_option('ghm_api_key',''));?>" style="flex:1;font-family:monospace;font-size:13px;">
            <button type="button" class="ghm-btn ghm-btn-outline" onclick="document.getElementById('ghm-api-key-field').value='ghm_'+Math.random().toString(36).substr(2,32);">Generate</button>
          </div>
        </div>
      </div>
      <div style="margin-top:16px;">
        <?php foreach(array('GET /rooms'=>'List rooms','GET /rooms/available'=>'Available rooms','GET /bookings'=>'List bookings','POST /bookings'=>'Create booking','PATCH /bookings/{id}'=>'Update booking','GET /customers'=>'List customers','POST /payments'=>'Record payment','GET /reports/summary'=>'Dashboard stats','GET /housekeeping'=>'Housekeeping board','GET /ical/{room_id}'=>'iCal feed') as $ep=>$desc):?>
        <div style="display:flex;align-items:center;gap:12px;padding:7px 12px;border-bottom:1px solid var(--ghm-border);font-size:12px;">
          <code style="color:var(--ghm-gold);flex-shrink:0;min-width:240px;"><?php echo esc_html($ep);?></code>
          <span style="color:var(--ghm-muted);"><?php echo $desc;?></span>
        </div>
        <?php endforeach;?>
      </div>
    </div>

    <?php elseif ($active_tab==='shortcodes'): ?>
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">📋 Shortcodes</p>
      <div style="background:rgba(62,207,142,.08);border:1px solid rgba(62,207,142,.2);border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:13px;">
      <strong style="color:var(--ghm-success);">Guest Portal Setup:</strong>
      Create a new page called "Guest Portal", paste <code style="background:rgba(201,168,76,.1);color:var(--ghm-gold);padding:2px 8px;border-radius:4px;">[ghm_guest_portal]</code> as the only content, and publish it.
      Guests log in with their <strong>Booking Reference</strong> (e.g. GHM-A1B2C3-20250501) and their <strong>email address</strong>.
    </div>
    <?php foreach(array('[ghm_booking_form]'=>'Full booking form with Paystack + Flutterwave','[ghm_booking_form type="workspace"]'=>'Workspace booking form','[ghm_rooms_list]'=>'Available rooms grid','[ghm_rooms_list type="workspace"]'=>'Workspaces grid','[ghm_booking_confirmation]'=>'Booking lookup / confirmation','[ghm_guest_portal]'=>'Guest self-service portal (view booking, pay balance, request services, leave review)','[ghm_waitlist_form]'=>'Waiting list signup','[ghm_waitlist_form room_id="5"]'=>'Waiting list for a specific room','[ghm_reviews]'=>'Approved guest reviews wall (attrs: limit, min_rating, layout)','[ghm_reviews limit="6" min_rating="4"]'=>'Top reviews only (4★+, max 6)','[ghm_pin_login]'=>'Staff PIN quick login pad for shared terminals') as $code=>$desc):?>
      <div style="display:flex;align-items:center;gap:12px;background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:10px 14px;margin-bottom:8px;">
        <code style="background:rgba(201,168,76,.1);color:var(--ghm-gold);padding:4px 10px;border-radius:5px;font-size:12px;flex-shrink:0;"><?php echo esc_html($code);?></code>
        <span style="font-size:13px;color:var(--ghm-muted);flex:1;"><?php echo $desc;?></span>
        <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($code);?>');this.textContent='Copied!';" class="ghm-btn ghm-btn-outline ghm-btn-sm">Copy</button>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>

    <input type="hidden" name="ghm_save_settings" value="1">
    <button type="submit" class="ghm-btn ghm-btn-primary" style="padding:12px 32px;margin-top:8px;"><span class="dashicons dashicons-yes"></span> Save Settings</button>
  </form>
</div>

<style>
.ghm-settings-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:24px;background:var(--ghm-surface);border:1px solid var(--ghm-border);border-radius:10px;padding:6px;}
.ghm-stab{padding:7px 14px;border-radius:7px;font-size:13px;color:var(--ghm-muted);text-decoration:none;transition:all .2s;font-weight:500;white-space:nowrap;}
.ghm-stab:hover{background:var(--ghm-surface2);color:var(--ghm-text);}
.ghm-stab.active{background:linear-gradient(135deg,var(--ghm-gold),var(--ghm-gold-light));color:#0f1117;font-weight:700;}
</style>
