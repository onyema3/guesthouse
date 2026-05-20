/* GuestHouse Manager – Public JS v1.3 */
(function($){
  'use strict';

  const GHMPublic = {

    init() {
      this.bindBookingForm();
      this.bindRoomCards();
    },

    /* ─── Booking Form ─────────────────────────────────────────── */
    bindBookingForm() {
      const $form = $('#ghm-public-booking-form');
      if (!$form.length) return;

      $form.on('change', '#ghm-pb-room, #ghm-pb-checkin, #ghm-pb-checkout, #ghm-pb-type',
        () => this.calcAmount());

      // Discount toggle
      $(document).on('click', '#ghm-discount-toggle', function(){
        $('#ghm-discount-fields').slideToggle(200);
      });

      // Apply discount
      $(document).on('click', '#ghm-discount-apply-btn', () => this.applyDiscount());
      $(document).on('keydown', '#ghm-discount-input', e => {
        if (e.key === 'Enter') { e.preventDefault(); this.applyDiscount(); }
      });

      $form.on('click', '.ghm-step-next', e => {
        const step = parseInt($(e.currentTarget).data('step'));
        if (!this.validateStep(step)) return;
        this.goToStep(step + 1);
      });

      $form.on('click', '.ghm-step-prev', e => {
        this.goToStep(parseInt($(e.currentTarget).data('step')) - 1);
      });

      $(document).on('click', '#ghm-confirm-btn', () => this.handleConfirm());

      // Payment option toggle — update button label
      $(document).on('change', 'input[name="payment_option"]', function(){
        const sym = $('#ghm-currency-symbol').val() || '';
        const finalAmount = parseFloat($('#ghm-pb-total-amount').val()) || 0;
        const amt = sym + finalAmount.toFixed(2);
        GHMPublic.updateConfirmBtn($(this).val(), amt);
        $('.ghm-pay-option').removeClass('ghm-pay-option--selected');
        $(this).closest('.ghm-pay-option').addClass('ghm-pay-option--selected');
      });
    },

    /* ─── Room Cards ───────────────────────────────────────────── */
    bindRoomCards() {
      $(document).on('click', '.ghm-book-room-btn', function(){
        const roomId = $(this).data('room-id');
        const $form  = $('#ghm-public-booking-form');
        if ($form.length) {
          $('#ghm-pb-room').val(roomId).trigger('change');
          $('html,body').animate({ scrollTop: $form.offset().top - 60 }, 400);
        }
      });
    },

    /* ─── Amount Calculation ───────────────────────────────────── */
    calcAmount() {
      const roomId  = $('#ghm-pb-room').val();
      const checkIn = $('#ghm-pb-checkin').val();
      const checkOut= $('#ghm-pb-checkout').val();
      const type    = $('#ghm-pb-type').val() || 'room';
      const sym     = $('#ghm-currency-symbol').val() || '';

      if (!roomId || !checkIn || !checkOut) {
        $('#ghm-pb-amount-preview').hide();
        return;
      }

      $.post(ghmPublic.ajax_url, {
        action      : 'ghm_calc_amount',
        nonce       : ghmPublic.nonce,
        room_id     : roomId,
        check_in    : checkIn,
        check_out   : checkOut,
        booking_type: type
      }, res => {
        if (res.success && parseFloat(res.data.amount) > 0) {
          const amt = parseFloat(res.data.amount).toFixed(2);
          $('#ghm-pb-amount-value').text(sym + amt);
          $('#ghm-pb-total-amount').val(amt);
          $('#ghm-pb-amount-preview').show();
          // Show discount section now that we have an amount
          $('#ghm-discount-section').show();
          // Reset any applied discount
          $('#ghm-discount-result').hide();
          document.getElementById('ghm-discount-id').value = '';
          document.getElementById('ghm-discount-amount-field').value = '0';
          const payOpt = $('input[name="payment_option"]:checked').val() || 'arrival';
          this.updateConfirmBtn(payOpt, sym + amt);
        }
      });
    },

    /* ─── Discount Code ───────────────────────────────────────── */
    applyDiscount() {
      const code   = document.getElementById('ghm-discount-input').value.trim().toUpperCase();
      const amount = parseFloat(document.getElementById('ghm-pb-total-amount').value) || 0;
      const roomId = document.getElementById('ghm-pb-room').value;
      const $res   = $('#ghm-discount-result');
      const sym    = $('#ghm-currency-symbol').val() || '';

      if (!code) { $res.removeClass('success').addClass('error').text('Please enter a discount code.').show(); return; }
      if (amount <= 0) { $res.removeClass('success').addClass('error').text('Please select a room and dates first.').show(); return; }

      $('#ghm-discount-apply-btn').prop('disabled',true).text('Checking…');

      $.post(ghmPublic.ajax_url, {
        action: 'ghm_validate_discount',
        nonce : ghmPublic.nonce,
        code, amount, room_id: roomId
      }, res => {
        $('#ghm-discount-apply-btn').prop('disabled',false).text('Apply');
        if (res.success) {
          const d = res.data;
          document.getElementById('ghm-discount-id').value            = d.discount_id;
          document.getElementById('ghm-discount-amount-field').value  = d.discount_amount;
          document.getElementById('ghm-pb-total-amount').value        = d.final_amount;
          // Update display
          const disc = parseFloat(d.discount_amount).toFixed(2);
          const final = parseFloat(d.final_amount).toFixed(2);
          $('#ghm-pb-amount-value').html(
            `<span style="text-decoration:line-through;opacity:.5;font-size:16px;">${sym}${parseFloat(amount).toFixed(2)}</span> ` +
            `<span style="color:#3ecf8e;">${sym}${final}</span>`
          );
          $res.removeClass('error').addClass('success')
            .html(`✓ Code <strong>${this._esc(d.code)}</strong> applied — you save <strong>${sym}${disc}</strong>!`).show();
          this.updateConfirmBtn($('input[name="payment_option"]:checked').val()||'arrival', sym+final);
        } else {
          document.getElementById('ghm-discount-id').value           = '';
          document.getElementById('ghm-discount-amount-field').value = '0';
          $res.removeClass('success').addClass('error').text('✗ ' + ((res.data ? res.data.message : '') || 'Invalid code')).show();
        }
      }).fail(() => {
        $('#ghm-discount-apply-btn').prop('disabled',false).text('Apply');
        $res.removeClass('success').addClass('error').text('✗ Network error. Please try again.').show();
      });
    },

    /* ─── Update Confirm Button ────────────────────────────────── */
    updateConfirmBtn(payOpt, amountText) {
      const $btn = $('#ghm-confirm-btn');
      if (!$btn.length) return;
      if (payOpt === 'paystack' && typeof ghmPaystack !== 'undefined' && ghmPaystack.enabled) {
        $btn.html(
          '<svg width="18" height="18" viewBox="0 0 32 32" style="flex-shrink:0;vertical-align:middle;margin-right:6px">' +
          '<rect width="32" height="32" rx="6" fill="rgba(255,255,255,.3)"/>' +
          '<path d="M7 16h18M7 10h12M13 22h12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>' +
          'Pay ' + amountText + ' with Paystack'
        ).css({ background:'linear-gradient(135deg,#00C3F7,#0099CC)', color:'#fff' });
      } else if (payOpt === 'flutterwave' && typeof ghmFlutterwave !== 'undefined' && ghmFlutterwave.enabled) {
        $btn.html(
          '🦋 Pay ' + amountText + ' with Flutterwave'
        ).css({ background:'linear-gradient(135deg,#FF6B00,#FF8C00)', color:'#fff' });
      } else {
        $btn.html('&#10003; Confirm Booking').css({
          background:'linear-gradient(135deg,#c9a84c,#e8c97a)', color:'#1a1a2e'
        });
      }
    },

    /* ─── Step Validation ──────────────────────────────────────── */
    /*
     * FIX: Previous version used $el.val() which returns '' for un-touched
     * datetime-local inputs on some browsers even when they have a default value
     * set via HTML, and also flagged hidden inputs erroneously.
     *
     * New logic:
     *  - Skip inputs that are hidden (type="hidden") — they are internal fields
     *  - For select: invalid if value === '' (placeholder option selected)
     *  - For text/email/tel/textarea: invalid if trimmed value is empty
     *  - For datetime-local: invalid if value is empty string
     *  - Checkbox: invalid if not checked
     *  - Never block on elements that have display:none on their parent step
     *    (shouldn't happen but guard anyway)
     */
    validateStep(step) {
      let valid    = true;
      let $first   = null;

      $(`.ghm-form-step-body[data-step="${step}"] [required]`).each(function(){
        const el   = this;
        const type = (el.type || '').toLowerCase();

        // Skip hidden inputs — these are internal (nonce, total_amount, etc.)
        if (type === 'hidden') return;

        // Skip if the element itself or its parent step is not visible
        if (!$(el).is(':visible')) return;

        let empty = false;

        if (type === 'checkbox') {
          empty = !el.checked;
        } else {
          // Works for select, text, email, tel, datetime-local, textarea
          const v = (el.value || '').trim();
          empty = (v === '' || v === '0');
        }

        if (empty) {
          $(el).css({ 'border-color':'#ef4444', 'outline':'2px solid #ef4444' });
          valid = false;
          if (!$first) $first = $(el);
        } else {
          $(el).css({ 'border-color':'', 'outline':'' });
        }
      });

      if (!valid) {
        this.showAlert('Please fill in all required fields.', 'error');
        if ($first) {
          $('html,body').animate({ scrollTop: $first.offset().top - 80 }, 300);
          setTimeout(() => $first.focus(), 350);
        }
      }
      return valid;
    },

    /* ─── Step Navigation ──────────────────────────────────────── */
    goToStep(step) {
      $('.ghm-form-step-body').hide();
      $(`.ghm-form-step-body[data-step="${step}"]`).show();
      $('.ghm-form-step').removeClass('active done');
      for (let i = 1; i < step; i++) $(`.ghm-form-step[data-step="${i}"]`).addClass('done');
      $(`.ghm-form-step[data-step="${step}"]`).addClass('active');

      // Populate summary when arriving at step 3
      if (step === 3) this.populateSummary();

      const $form = $('#ghm-public-booking-form');
      if ($form.length) $('html,body').animate({ scrollTop: $form.offset().top - 40 }, 300);
    },

    /* ─── Populate Step-3 Summary ──────────────────────────────── */
    populateSummary() {
      const sym    = $('#ghm-currency-symbol').val() || '';
      const room   = $('#ghm-pb-room option:selected').text().split('—')[0].trim();
      const ci     = $('#ghm-pb-checkin').val();
      const co     = $('#ghm-pb-checkout').val();
      const fn     = $('#ghm-pb-first-name').val() || '';
      const ln     = $('#ghm-pb-last-name').val()  || '';
      const email  = $('#ghm-pb-email').val()       || '';

      // Use the hidden total_amount field as the source of truth for the final amount
      const finalAmount = parseFloat($('#ghm-pb-total-amount').val()) || 0;
      const discountAmt = parseFloat($('#ghm-discount-amount-field').val()) || 0;
      const originalAmt = finalAmount + discountAmt;

      // Build display: show original struck-through + final if discount applied, otherwise just final
      let amountDisplay;
      let amountForBtn;
      if (discountAmt > 0) {
        amountDisplay = `<span style="text-decoration:line-through;opacity:.5;font-size:13px;">${this._esc(sym)}${originalAmt.toFixed(2)}</span> ` +
                        `<strong style="color:#3ecf8e;font-size:16px;">${this._esc(sym)}${finalAmount.toFixed(2)}</strong>`;
        amountForBtn = sym + finalAmount.toFixed(2);
      } else {
        amountDisplay = `<strong style="color:#c9a84c;font-size:16px;">${this._esc(sym)}${finalAmount.toFixed(2)}</strong>`;
        amountForBtn = sym + finalAmount.toFixed(2);
      }

      const row = (label, val) =>
        `<tr><td style="padding:7px 0;color:#9ca3af;width:130px;font-size:13px;">${label}</td>` +
        `<td style="padding:7px 0;font-weight:500;font-size:13px;">${val}</td></tr>`;

      $('#ghm-confirm-summary').html(
        `<table style="width:100%;border-collapse:collapse;">` +
        row('Room / Space', this._esc(room)) +
        row('Guest',        this._esc((fn + ' ' + ln).trim())) +
        row('Email',        this._esc(email)) +
        row('Check-In',     this._esc(ci)) +
        row('Check-Out',    this._esc(co)) +
        row('Total',        amountDisplay) +
        `</table>`
      );

      // Set initial button state
      const psEnabled = $('#ghm-paystack-enabled').val() === '1';
      const payOpt    = $('input[name="payment_option"]:checked').val()
                        || (psEnabled ? 'paystack' : 'arrival');
      this.updateConfirmBtn(payOpt, amountForBtn);
    },

    /* ─── Confirm Handler ──────────────────────────────────────── */
    handleConfirm() {
      const termsCb = document.getElementById('ghm-terms-cb');
      if (termsCb && !termsCb.checked) {
        this.showAlert('Please agree to the booking policy before continuing.', 'error');
        return;
      }

      const payOpt      = $('input[name="payment_option"]:checked').val() || 'arrival';
      const psEnabled   = $('#ghm-paystack-enabled').val() === '1';
      const psAvailable = typeof ghmPaystack !== 'undefined' && ghmPaystack.enabled;
      const flwAvailable= typeof ghmFlutterwave !== 'undefined' && ghmFlutterwave.enabled;

      if (payOpt === 'paystack' && psEnabled && psAvailable) {
        this.initiatePaystackPayment();
      } else if (payOpt === 'flutterwave' && flwAvailable) {
        this.initiateFlutterwavePayment();
      } else {
        this.submitArrivalBooking();
      }
    },

    /* ─── Paystack Flow ────────────────────────────────────────── */
    initiatePaystackPayment() {
      const $btn = $('#ghm-confirm-btn');
      const sym  = $('#ghm-currency-symbol').val() || '';

      // Fail fast if the Paystack inline.js SDK didn't load, or if a v2
      // script overwrote the global (v2 has no .setup method).
      if (typeof PaystackPop === 'undefined' || typeof PaystackPop.setup !== 'function') {
        this.showAlert('Paystack could not load. Please disable ad-blockers, refresh the page, and try again.', 'error');
        this.updateConfirmBtn('paystack', sym + parseFloat($('#ghm-pb-total-amount').val()||0).toFixed(2));
        return;
      }

      const formData = {};
      $('#ghm-public-booking-form').serializeArray().forEach(({name, value}) => {
        formData[name] = value;
      });

      if (!formData.room_id)    { this.showAlert('Please select a room.',             'error'); return; }
      if (!formData.check_in)   { this.showAlert('Please set a check-in date.',        'error'); return; }
      if (!formData.check_out)  { this.showAlert('Please set a check-out date.',       'error'); return; }
      if (!formData.first_name) { this.showAlert('Please enter your first name.',      'error'); return; }
      if (!formData.email)      { this.showAlert('Please enter your email address.',   'error'); return; }

      $btn.prop('disabled', true).html('<span class="ghm-pub-spinner"></span> Initialising Payment…');
      this.clearAlerts();

      $.ajax({
        url     : ghmPublic.ajax_url,
        type    : 'POST',
        dataType: 'json',
        timeout : 30000,
        data    : Object.assign({
          action: 'ghm_paystack_init',
          nonce : ghmPublic.nonce
        }, formData)
      })
      .done(res => {
        if (!res.success) {
          this.showAlert((res.data ? res.data.message : '') || 'Could not initialise payment. Please try again.', 'error');
          $btn.prop('disabled', false);
          this.updateConfirmBtn('paystack', sym + parseFloat(formData.total_amount||0).toFixed(2));
          return;
        }

        const d = res.data;
        $btn.html('<span class="ghm-pub-spinner"></span> Opening Paystack…');

        try {
        const handler = PaystackPop.setup({
          key      : ghmPaystack.public_key,
          email    : d.email,
          amount   : d.amount,
          currency : d.currency,
          ref      : d.reference,
          firstName: formData.first_name || '',
          lastName : formData.last_name  || '',
          phone    : formData.phone      || '',
          label    : d.hotel_name,
          metadata : {
            custom_fields: [
              { display_name:'Booking Ref', variable_name:'booking_ref', value: d.booking_ref },
              { display_name:'Room',        variable_name:'room',        value: d.meta.room   },
              { display_name:'Check-In',    variable_name:'check_in',    value: d.meta.check_in  },
              { display_name:'Check-Out',   variable_name:'check_out',   value: d.meta.check_out },
            ]
          },
          onClose: () => {
            $btn.prop('disabled', false);
            this.updateConfirmBtn('paystack', sym + parseFloat(formData.total_amount||0).toFixed(2));
            this.showAlert('Payment cancelled. Your booking is held for 30 minutes — try again when ready.', 'info');
          },
          callback: (response) => {
            $btn.html('<span class="ghm-pub-spinner"></span> Verifying Payment…').prop('disabled', true);
            $.post(ghmPublic.ajax_url, {
              action    : 'ghm_paystack_verify',
              nonce     : ghmPublic.nonce,
              reference : response.reference
            })
            .done(verRes => {
              if (verRes.success) {
                this.showPaystackSuccess(verRes.data, formData, sym);
              } else {
                this.showAlert(
                  (verRes.data ? verRes.data.message : '') ||
                  'Payment verification failed. Please contact us with reference: ' + response.reference,
                  'error'
                );
                $btn.prop('disabled', false);
                this.updateConfirmBtn('paystack', sym + parseFloat(formData.total_amount||0).toFixed(2));
              }
            })
            .fail(() => {
              this.showAlert('Could not verify payment. Contact us with reference: ' + response.reference, 'error');
              $btn.prop('disabled', false);
            });
          }
        });
        handler.openIframe();
        } catch(e) {
          console.error('Paystack error:', e);
          this.showAlert('Could not open payment window. Please disable ad-blockers and try again, or choose Pay on Arrival.', 'error');
          $btn.prop('disabled', false);
          this.updateConfirmBtn('paystack', sym + parseFloat(formData.total_amount||0).toFixed(2));
        }
      })
      .fail((jqXHR, textStatus) => {
        const msg = textStatus === 'timeout'
          ? 'Server is taking too long to respond. Please try again or choose Pay on Arrival.'
          : 'Network error. Please check your connection and try again.';
        this.showAlert(msg, 'error');
        $btn.prop('disabled', false);
        this.updateConfirmBtn('paystack', sym + parseFloat(formData.total_amount||0).toFixed(2));
      });
    },

    /* ─── Flutterwave Flow ──────────────────────────────────────── */
    initiateFlutterwavePayment() {
      const $btn    = $('#ghm-confirm-btn');
      const sym     = $('#ghm-currency-symbol').val() || '';

      // Fail fast if the Flutterwave SDK didn't load.
      if (typeof FlutterwaveCheckout === 'undefined') {
        this.showAlert('Flutterwave could not load. Please disable ad-blockers, refresh the page, and try again.', 'error');
        this.updateConfirmBtn('flutterwave', sym + parseFloat($('#ghm-pb-total-amount').val()||0).toFixed(2));
        return;
      }

      const formData = {};
      $('#ghm-public-booking-form').serializeArray().forEach(({name,value}) => { formData[name]=value; });

      if (!formData.room_id)    { this.showAlert('Please select a room.','error'); return; }
      if (!formData.check_in)   { this.showAlert('Please set check-in date.','error'); return; }
      if (!formData.check_out)  { this.showAlert('Please set check-out date.','error'); return; }
      if (!formData.email)      { this.showAlert('Please enter your email.','error'); return; }

      $btn.prop('disabled',true).html('<span class="ghm-pub-spinner"></span> Initialising…');
      this.clearAlerts();

      $.ajax({
        url     : ghmPublic.ajax_url,
        type    : 'POST',
        dataType: 'json',
        timeout : 30000,
        data    : Object.assign({action:'ghm_flw_init',nonce:ghmPublic.nonce}, formData)
      })
      .done(res => {
        if (!res.success) {
          this.showAlert((res.data ? res.data.message : '')||'Could not initialise payment.','error');
          $btn.prop('disabled',false);
          this.updateConfirmBtn('flutterwave', sym+parseFloat(formData.total_amount||0).toFixed(2));
          return;
        }
        const d = res.data;
        $btn.html('<span class="ghm-pub-spinner"></span> Opening Flutterwave…');

        try {
        FlutterwaveCheckout({
          public_key    : ghmFlutterwave.public_key,
          tx_ref        : d.tx_ref,
          amount        : d.amount,
          currency      : d.currency,
          payment_options: 'card,banktransfer,ussd,mobilemoney',
          customer      : { email:d.email, phone_number:d.phone||'', name:d.name },
          customizations: { title:d.hotel_name, description:d.description },
          meta          : { booking_ref:d.booking_ref },
          callback: (response) => {
            document.querySelectorAll('.flwpaymentmodal,.flwpaymentmodal-overlay').forEach(el=>el.remove());
            $btn.html('<span class="ghm-pub-spinner"></span> Verifying…').prop('disabled',true);
            $.post(ghmPublic.ajax_url,{
              action:'ghm_flw_verify', nonce:ghmPublic.nonce,
              tx_ref:response.tx_ref, transaction_id:response.transaction_id
            })
            .done(vr => {
              if (vr.success) { this.showFlwSuccess(vr.data, formData, sym); }
              else {
                this.showAlert((vr.data ? vr.data.message : '')||'Verification failed. Contact us with ref: '+response.tx_ref,'error');
                $btn.prop('disabled',false);
                this.updateConfirmBtn('flutterwave', sym+parseFloat(formData.total_amount||0).toFixed(2));
              }
            })
            .fail(()=>{ this.showAlert('Network error. Contact us with tx_ref: '+response.tx_ref,'error'); $btn.prop('disabled',false); });
          },
          onclose: () => {
            $btn.prop('disabled',false);
            this.updateConfirmBtn('flutterwave', sym+parseFloat(formData.total_amount||0).toFixed(2));
            this.showAlert('Payment cancelled. Your booking is held — try again when ready.','info');
          }
        });
        } catch(e) {
          console.error('Flutterwave error:', e);
          this.showAlert('Could not open Flutterwave payment window. Please disable ad-blockers and try again, or choose Pay on Arrival.', 'error');
          $btn.prop('disabled', false);
          this.updateConfirmBtn('flutterwave', sym+parseFloat(formData.total_amount||0).toFixed(2));
        }
      })
      .fail((jqXHR, textStatus) => {
        const msg = textStatus === 'timeout'
          ? 'Server is taking too long to respond. Please try again or choose Pay on Arrival.'
          : 'Network error. Please try again.';
        this.showAlert(msg,'error');
        $btn.prop('disabled',false);
        this.updateConfirmBtn('flutterwave', sym+parseFloat(formData.total_amount||0).toFixed(2));
      });
    },

    showFlwSuccess(data, formData, sym) {
      const roomName  = $('#ghm-pb-room option:selected').text().split('—')[0].trim();
      const guestName = ((formData.first_name||'')+' '+(formData.last_name||'')).trim();
      const amount    = parseFloat(formData.total_amount||data.amount||0).toFixed(2);
      const html = `
        <div class="ghm-confirmation-wrap">
          <div class="ghm-confirmation-icon" style="background:linear-gradient(135deg,#FF6B00,#FF8C00);">
            <svg width="40" height="40" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="rgba(255,255,255,.2)"/>
            <path d="M8 16l5 5 11-10" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
          </div>
          <h2>Payment Successful!</h2>
          <p>Thank you, <strong>${this._esc(guestName)}</strong>. Booking confirmed via Flutterwave.</p>
          <div class="ghm-conf-ref">${this._esc(data.booking_ref)}</div>
          <div class="ghm-conf-details">
            <div class="ghm-conf-row"><span class="conf-label">Room</span><span class="conf-value">${this._esc(roomName)}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Check-In</span><span class="conf-value">${this._esc(formData.check_in||'')}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Check-Out</span><span class="conf-value">${this._esc(formData.check_out||'')}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Amount Paid</span><span class="conf-value" style="color:#c9a84c;font-size:18px;">${sym}${amount}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Gateway</span><span class="conf-value" style="color:#FF6B00;font-weight:700;">✓ Flutterwave</span></div>
          </div>
          <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">Make another booking &#8594;</a>
        </div>`;
      $('#ghm-public-booking-form').slideUp(300, function(){ $(this).after(html); });
    },

    /* ─── Pay on Arrival ───────────────────────────────────────── */
    submitArrivalBooking() {
      const $btn  = $('#ghm-confirm-btn');
      const $form = $('#ghm-public-booking-form');
      $btn.prop('disabled', true).html('<span class="ghm-pub-spinner"></span> Confirming…');
      this.clearAlerts();

      const data = { action: 'ghm_public_booking', nonce: ghmPublic.nonce };
      $form.serializeArray().forEach(({name, value}) => data[name] = value);

      $.post(ghmPublic.ajax_url, data)
        .done(res => {
          if (res.success) {
            this.showArrivalSuccess(res.data, $form);
          } else {
            this.showAlert((res.data ? res.data.message : '') || 'Booking failed. Please try again.', 'error');
            $btn.prop('disabled', false).html('&#10003; Confirm Booking');
          }
        })
        .fail(() => {
          this.showAlert('Network error. Please try again.', 'error');
          $btn.prop('disabled', false).html('&#10003; Confirm Booking');
        });
    },

    /* ─── Success Screens ──────────────────────────────────────── */
    showPaystackSuccess(data, formData, sym) {
      const roomName  = $('#ghm-pb-room option:selected').text().split('—')[0].trim();
      const guestName = ((formData.first_name||'') + ' ' + (formData.last_name||'')).trim();
      const amount    = parseFloat(formData.total_amount || data.amount || 0).toFixed(2);

      const html = `
        <div class="ghm-confirmation-wrap">
          <div class="ghm-confirmation-icon ghm-confirmation-icon--paystack">
            <svg width="40" height="40" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="rgba(255,255,255,.2)"/>
            <path d="M7 16h18M7 10h12M13 22h12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
          </div>
          <h2>Payment Successful!</h2>
          <p>Thank you, <strong>${this._esc(guestName)}</strong>. Your booking is confirmed and paid.</p>
          <div class="ghm-conf-ref">${this._esc(data.booking_ref)}</div>
          <div class="ghm-conf-details">
            <div class="ghm-conf-row"><span class="conf-label">Room / Space</span><span class="conf-value">${this._esc(roomName)}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Check-In</span><span class="conf-value">${this._esc(formData.check_in||'')}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Check-Out</span><span class="conf-value">${this._esc(formData.check_out||'')}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Amount Paid</span><span class="conf-value" style="color:#c9a84c;font-size:18px;">${sym}${amount}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Payment</span><span class="conf-value" style="color:#3ecf8e;font-weight:700;">✓ Paid via Paystack</span></div>
          </div>
          <p style="font-size:13px;color:#9ca3af;">A confirmation email has been sent to <strong>${this._esc(formData.email||'')}</strong>.</p>
          <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">Make another booking &#8594;</a>
        </div>`;

      $('#ghm-public-booking-form').slideUp(300, function(){ $(this).after(html); });
    },

    showArrivalSuccess(data, $form) {
      const sym       = $('#ghm-currency-symbol').val() || '';
      const roomName  = $('#ghm-pb-room option:selected').text().split('—')[0].trim();
      const checkIn   = $('#ghm-pb-checkin').val();
      const checkOut  = $('#ghm-pb-checkout').val();
      const guestName = (($('#ghm-pb-first-name').val()||'') + ' ' + ($('#ghm-pb-last-name').val()||'')).trim();
      const amount    = parseFloat($('#ghm-pb-total-amount').val() || data.amount || 0).toFixed(2);

      const html = `
        <div class="ghm-confirmation-wrap">
          <div class="ghm-confirmation-icon">✓</div>
          <h2>Booking Confirmed!</h2>
          <p>Thank you, <strong>${this._esc(guestName)}</strong>. Your reservation is secured.</p>
          <div class="ghm-conf-ref">${this._esc(data.booking_ref)}</div>
          <div class="ghm-conf-details">
            <div class="ghm-conf-row"><span class="conf-label">Room / Space</span><span class="conf-value">${this._esc(roomName)}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Check-In</span><span class="conf-value">${this._esc(checkIn)}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Check-Out</span><span class="conf-value">${this._esc(checkOut)}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Total</span><span class="conf-value" style="color:#c9a84c;font-size:18px;">${sym}${amount}</span></div>
            <div class="ghm-conf-row"><span class="conf-label">Payment</span><span class="conf-value" style="color:#f59e0b;">Due on arrival</span></div>
          </div>
          <div class="ghm-alert info" style="margin-top:14px;text-align:left;">
            💳 Please bring payment when you check in and quote your booking reference at reception.
          </div>
          <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">Make another booking &#8594;</a>
        </div>`;

      $form.slideUp(300, function(){ $(this).after(html); });
    },

    /* ─── Alerts ───────────────────────────────────────────────── */
    showAlert(msg, type='info') {
      const icon = {success:'✓', error:'✗', info:'ℹ'}[type] || 'ℹ';
      $('#ghm-form-alerts').html(`<div class="ghm-alert ${type}">${icon} ${msg}</div>`);
      $('html,body').animate({ scrollTop: (($('#ghm-form-alerts').offset() ? $('#ghm-form-alerts').offset().top : 0)||0) - 20 }, 200);
    },
    clearAlerts() { $('#ghm-form-alerts').empty(); },

    _esc(s) {
      if (!s) return '';
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
  };

  window.GHMPublic = GHMPublic;
  $(document).ready(() => GHMPublic.init());

})(jQuery);
