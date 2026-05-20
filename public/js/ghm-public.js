/* GuestHouse Manager – Public JS v2.0 (Redirect Payment Flow) */
(function($){
  'use strict';

  const GHMPublic = {

    init() {
      this.bindBookingForm();
      this.bindRoomCards();
      this.handlePaymentReturn();
    },

    /* ─── Handle Payment Return (success/failed/cancelled) ───── */
    handlePaymentReturn() {
      const params = new URLSearchParams(window.location.search);
      const status = params.get('ghm_payment');
      if (!status) return;

      const ref     = params.get('ref') || '';
      const gateway = params.get('gateway') || '';
      const amount  = params.get('amount') || '';
      const sym     = $('#ghm-currency-symbol').val() || '';

      $('#ghm-public-booking-form').hide();

      let html = '';

      if (status === 'success') {
        const gwLabel = gateway === 'flutterwave' ? 'Flutterwave' : 'Paystack';
        const gwColor = gateway === 'flutterwave' ? '#FF6B00' : '#00C3F7';
        html = `
          <div class="ghm-confirmation-wrap">
            <div class="ghm-confirmation-icon" style="background:linear-gradient(135deg,${gwColor},${gwColor}cc);">
              <svg width="40" height="40" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="rgba(255,255,255,.2)"/>
              <path d="M8 16l5 5 11-10" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
            </div>
            <h2>Payment Successful!</h2>
            <p>Your booking is confirmed and paid via <strong>${gwLabel}</strong>.</p>
            <div class="ghm-conf-ref">${this._esc(ref)}</div>
            <div class="ghm-conf-details">
              ${amount ? `<div class="ghm-conf-row"><span class="conf-label">Amount Paid</span><span class="conf-value" style="color:#c9a84c;font-size:18px;">${sym}${parseFloat(amount).toFixed(2)}</span></div>` : ''}
              <div class="ghm-conf-row"><span class="conf-label">Payment</span><span class="conf-value" style="color:#3ecf8e;font-weight:700;">&#10003; Paid via ${gwLabel}</span></div>
            </div>
            <p style="font-size:13px;color:#9ca3af;">A confirmation email has been sent to your email address.</p>
            <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">Make another booking &#8594;</a>
          </div>`;
      } else if (status === 'failed') {
        html = `
          <div class="ghm-confirmation-wrap">
            <div class="ghm-confirmation-icon" style="background:#ef4444;">&#10007;</div>
            <h2>Payment Failed</h2>
            <p>Your payment could not be completed. No charge was made.</p>
            ${ref ? `<p style="font-size:13px;color:#9ca3af;">Reference: ${this._esc(ref)}</p>` : ''}
            <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">&#8592; Try again</a>
          </div>`;
      } else if (status === 'cancelled') {
        html = `
          <div class="ghm-confirmation-wrap">
            <div class="ghm-confirmation-icon" style="background:#f59e0b;">&#8617;</div>
            <h2>Payment Cancelled</h2>
            <p>You cancelled the payment. Your booking has not been confirmed.</p>
            <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">&#8592; Try again</a>
          </div>`;
      } else if (status === 'already_processed') {
        html = `
          <div class="ghm-confirmation-wrap">
            <div class="ghm-confirmation-icon" style="background:#3ecf8e;">&#10003;</div>
            <h2>Booking Already Confirmed</h2>
            <p>This payment was already processed. Check your email for the confirmation.</p>
            <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">Make another booking &#8594;</a>
          </div>`;
      }

      if (html) {
        const $wrap = $('.ghm-booking-form-wrap');
        if ($wrap.length) { $wrap.html(html); } else { $('#ghm-form-alerts').after(html); }
      }
    },

    /* ─── Booking Form ─────────────────────────────────────────── */
    bindBookingForm() {
      const $form = $('#ghm-public-booking-form');
      if (!$form.length) return;

      $form.on('change', '#ghm-pb-room, #ghm-pb-checkin, #ghm-pb-checkout, #ghm-pb-type',
        () => this.calcAmount());

      $(document).on('click', '#ghm-discount-toggle', function(){ $('#ghm-discount-fields').slideToggle(200); });
      $(document).on('click', '#ghm-discount-apply-btn', () => this.applyDiscount());
      $(document).on('keydown', '#ghm-discount-input', e => { if (e.key === 'Enter') { e.preventDefault(); this.applyDiscount(); } });

      $form.on('click', '.ghm-step-next', e => {
        const step = parseInt($(e.currentTarget).data('step'));
        if (!this.validateStep(step)) return;
        this.goToStep(step + 1);
      });
      $form.on('click', '.ghm-step-prev', e => { this.goToStep(parseInt($(e.currentTarget).data('step')) - 1); });

      $(document).on('click', '#ghm-confirm-btn', () => this.handleConfirm());

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

      if (!roomId || !checkIn || !checkOut) { $('#ghm-pb-amount-preview').hide(); return; }

      $.post(ghmPublic.ajax_url, {
        action: 'ghm_calc_amount', nonce: ghmPublic.nonce,
        room_id: roomId, check_in: checkIn, check_out: checkOut, booking_type: type
      }, res => {
        if (res.success && parseFloat(res.data.amount) > 0) {
          const amt = parseFloat(res.data.amount).toFixed(2);
          $('#ghm-pb-amount-value').text(sym + amt);
          $('#ghm-pb-total-amount').val(amt);
          $('#ghm-pb-amount-preview').show();
          $('#ghm-discount-section').show();
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
        action: 'ghm_validate_discount', nonce: ghmPublic.nonce,
        code, amount, room_id: roomId
      }, res => {
        $('#ghm-discount-apply-btn').prop('disabled',false).text('Apply');
        if (res.success) {
          const d = res.data;
          document.getElementById('ghm-discount-id').value            = d.discount_id;
          document.getElementById('ghm-discount-amount-field').value  = d.discount_amount;
          document.getElementById('ghm-pb-total-amount').value        = d.final_amount;
          const disc = parseFloat(d.discount_amount).toFixed(2);
          const fin  = parseFloat(d.final_amount).toFixed(2);
          $('#ghm-pb-amount-value').html(
            `<span style="text-decoration:line-through;opacity:.5;font-size:16px;">${sym}${parseFloat(amount).toFixed(2)}</span> ` +
            `<span style="color:#3ecf8e;">${sym}${fin}</span>`
          );
          $res.removeClass('error').addClass('success')
            .html(`&#10003; Code <strong>${this._esc(d.code)}</strong> applied — you save <strong>${sym}${disc}</strong>!`).show();
          this.updateConfirmBtn($('input[name="payment_option"]:checked').val()||'arrival', sym+fin);
        } else {
          document.getElementById('ghm-discount-id').value = '';
          document.getElementById('ghm-discount-amount-field').value = '0';
          $res.removeClass('success').addClass('error').text('&#10007; ' + ((res.data?res.data.message:'')||'Invalid code')).show();
        }
      }).fail(() => {
        $('#ghm-discount-apply-btn').prop('disabled',false).text('Apply');
        $res.removeClass('success').addClass('error').text('&#10007; Network error.').show();
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
        $btn.html('Pay ' + amountText + ' with Flutterwave')
          .css({ background:'linear-gradient(135deg,#FF6B00,#FF8C00)', color:'#fff' });
      } else {
        $btn.html('&#10003; Confirm Booking').css({ background:'linear-gradient(135deg,#c9a84c,#e8c97a)', color:'#1a1a2e' });
      }
    },

    /* ─── Step Validation ──────────────────────────────────────── */
    validateStep(step) {
      let valid = true, $first = null;
      $(`.ghm-form-step-body[data-step="${step}"] [required]`).each(function(){
        const el = this, type = (el.type||'').toLowerCase();
        if (type === 'hidden') return;
        if (!$(el).is(':visible')) return;
        let empty = type === 'checkbox' ? !el.checked : ((el.value||'').trim() === '' || (el.value||'').trim() === '0');
        if (empty) { $(el).css({'border-color':'#ef4444','outline':'2px solid #ef4444'}); valid=false; if(!$first)$first=$(el); }
        else { $(el).css({'border-color':'','outline':''}); }
      });
      if (!valid) { this.showAlert('Please fill in all required fields.','error'); if($first){$('html,body').animate({scrollTop:$first.offset().top-80},300);setTimeout(()=>$first.focus(),350);} }
      return valid;
    },

    /* ─── Step Navigation ──────────────────────────────────────── */
    goToStep(step) {
      $('.ghm-form-step-body').hide();
      $(`.ghm-form-step-body[data-step="${step}"]`).show();
      $('.ghm-form-step').removeClass('active done');
      for (let i=1;i<step;i++) $(`.ghm-form-step[data-step="${i}"]`).addClass('done');
      $(`.ghm-form-step[data-step="${step}"]`).addClass('active');
      if (step === 3) this.populateSummary();
      const $form = $('#ghm-public-booking-form');
      if ($form.length) $('html,body').animate({scrollTop:$form.offset().top-40},300);
    },

    /* ─── Populate Step-3 Summary ──────────────────────────────── */
    populateSummary() {
      const sym   = $('#ghm-currency-symbol').val()||'';
      const room  = $('#ghm-pb-room option:selected').text().split('—')[0].trim();
      const ci    = $('#ghm-pb-checkin').val();
      const co    = $('#ghm-pb-checkout').val();
      const fn    = $('#ghm-pb-first-name').val()||'';
      const ln    = $('#ghm-pb-last-name').val()||'';
      const email = $('#ghm-pb-email').val()||'';
      const finalAmount = parseFloat($('#ghm-pb-total-amount').val())||0;
      const discountAmt = parseFloat($('#ghm-discount-amount-field').val())||0;
      const originalAmt = finalAmount + discountAmt;

      let amountDisplay, amountForBtn;
      if (discountAmt > 0) {
        amountDisplay = `<span style="text-decoration:line-through;opacity:.5;font-size:13px;">${this._esc(sym)}${originalAmt.toFixed(2)}</span> <strong style="color:#3ecf8e;font-size:16px;">${this._esc(sym)}${finalAmount.toFixed(2)}</strong>`;
        amountForBtn = sym + finalAmount.toFixed(2);
      } else {
        amountDisplay = `<strong style="color:#c9a84c;font-size:16px;">${this._esc(sym)}${finalAmount.toFixed(2)}</strong>`;
        amountForBtn = sym + finalAmount.toFixed(2);
      }

      const row = (l,v) => `<tr><td style="padding:7px 0;color:#9ca3af;width:130px;font-size:13px;">${l}</td><td style="padding:7px 0;font-weight:500;font-size:13px;">${v}</td></tr>`;
      $('#ghm-confirm-summary').html(`<table style="width:100%;border-collapse:collapse;">${row('Room / Space',this._esc(room))}${row('Guest',this._esc((fn+' '+ln).trim()))}${row('Email',this._esc(email))}${row('Check-In',this._esc(ci))}${row('Check-Out',this._esc(co))}${row('Total',amountDisplay)}</table>`);

      const psEnabled = $('#ghm-paystack-enabled').val()==='1';
      const payOpt = $('input[name="payment_option"]:checked').val()||(psEnabled?'paystack':'arrival');
      this.updateConfirmBtn(payOpt, amountForBtn);
    },

    /* ─── Confirm Handler ──────────────────────────────────────── */
    handleConfirm() {
      const termsCb = document.getElementById('ghm-terms-cb');
      if (termsCb && !termsCb.checked) { this.showAlert('Please agree to the booking policy before continuing.','error'); return; }

      const payOpt = $('input[name="payment_option"]:checked').val()||'arrival';
      const psEnabled = $('#ghm-paystack-enabled').val()==='1';
      const psAvailable = typeof ghmPaystack!=='undefined' && ghmPaystack.enabled;
      const flwAvailable = typeof ghmFlutterwave!=='undefined' && ghmFlutterwave.enabled;

      if (payOpt==='paystack' && psEnabled && psAvailable) { this.initiatePaystackPayment(); }
      else if (payOpt==='flutterwave' && flwAvailable) { this.initiateFlutterwavePayment(); }
      else { this.submitArrivalBooking(); }
    },

    /* ─── Paystack Redirect Flow ───────────────────────────────── */
    initiatePaystackPayment() {
      const $btn = $('#ghm-confirm-btn');
      const sym  = $('#ghm-currency-symbol').val()||'';
      const formData = {};
      $('#ghm-public-booking-form').serializeArray().forEach(({name,value}) => { formData[name]=value; });

      if (!formData.room_id)   { this.showAlert('Please select a room.','error'); return; }
      if (!formData.check_in)  { this.showAlert('Please set a check-in date.','error'); return; }
      if (!formData.check_out) { this.showAlert('Please set a check-out date.','error'); return; }
      if (!formData.first_name){ this.showAlert('Please enter your first name.','error'); return; }
      if (!formData.email)     { this.showAlert('Please enter your email address.','error'); return; }

      $btn.prop('disabled',true).html('<span class="ghm-pub-spinner"></span> Connecting to Paystack…');
      this.clearAlerts();

      $.ajax({ url:ghmPublic.ajax_url, type:'POST', dataType:'json', timeout:30000,
        data: Object.assign({action:'ghm_paystack_init',nonce:ghmPublic.nonce}, formData)
      })
      .done(res => {
        if (!res.success) {
          this.showAlert((res.data?res.data.message:'')||'Could not initialise payment.','error');
          $btn.prop('disabled',false);
          this.updateConfirmBtn('paystack',sym+parseFloat(formData.total_amount||0).toFixed(2));
          return;
        }
        $btn.html('<span class="ghm-pub-spinner"></span> Redirecting to Paystack…');
        window.location.href = res.data.authorization_url;
      })
      .fail((jqXHR,textStatus) => {
        this.showAlert(textStatus==='timeout'?'Server took too long. Please try again.':'Network error.','error');
        $btn.prop('disabled',false);
        this.updateConfirmBtn('paystack',sym+parseFloat(formData.total_amount||0).toFixed(2));
      });
    },

    /* ─── Flutterwave Redirect Flow ────────────────────────────── */
    initiateFlutterwavePayment() {
      const $btn = $('#ghm-confirm-btn');
      const sym  = $('#ghm-currency-symbol').val()||'';
      const formData = {};
      $('#ghm-public-booking-form').serializeArray().forEach(({name,value}) => { formData[name]=value; });

      if (!formData.room_id)  { this.showAlert('Please select a room.','error'); return; }
      if (!formData.check_in) { this.showAlert('Please set check-in date.','error'); return; }
      if (!formData.check_out){ this.showAlert('Please set check-out date.','error'); return; }
      if (!formData.email)    { this.showAlert('Please enter your email.','error'); return; }

      $btn.prop('disabled',true).html('<span class="ghm-pub-spinner"></span> Connecting to Flutterwave…');
      this.clearAlerts();

      $.ajax({ url:ghmPublic.ajax_url, type:'POST', dataType:'json', timeout:30000,
        data: Object.assign({action:'ghm_flw_init',nonce:ghmPublic.nonce}, formData)
      })
      .done(res => {
        if (!res.success) {
          this.showAlert((res.data?res.data.message:'')||'Could not initialise payment.','error');
          $btn.prop('disabled',false);
          this.updateConfirmBtn('flutterwave',sym+parseFloat(formData.total_amount||0).toFixed(2));
          return;
        }
        $btn.html('<span class="ghm-pub-spinner"></span> Redirecting to Flutterwave…');
        window.location.href = res.data.payment_link;
      })
      .fail((jqXHR,textStatus) => {
        this.showAlert(textStatus==='timeout'?'Server took too long. Please try again.':'Network error.','error');
        $btn.prop('disabled',false);
        this.updateConfirmBtn('flutterwave',sym+parseFloat(formData.total_amount||0).toFixed(2));
      });
    },

    /* ─── Pay on Arrival ───────────────────────────────────────── */
    submitArrivalBooking() {
      const $btn  = $('#ghm-confirm-btn');
      const $form = $('#ghm-public-booking-form');
      $btn.prop('disabled',true).html('<span class="ghm-pub-spinner"></span> Confirming…');
      this.clearAlerts();
      const data = {action:'ghm_public_booking',nonce:ghmPublic.nonce};
      $form.serializeArray().forEach(({name,value}) => data[name]=value);
      $.post(ghmPublic.ajax_url, data)
        .done(res => {
          if (res.success) { this.showArrivalSuccess(res.data,$form); }
          else { this.showAlert((res.data?res.data.message:'')||'Booking failed.','error'); $btn.prop('disabled',false).html('&#10003; Confirm Booking'); }
        })
        .fail(() => { this.showAlert('Network error.','error'); $btn.prop('disabled',false).html('&#10003; Confirm Booking'); });
    },

    showArrivalSuccess(data, $form) {
      const sym       = $('#ghm-currency-symbol').val()||'';
      const roomName  = $('#ghm-pb-room option:selected').text().split('—')[0].trim();
      const checkIn   = $('#ghm-pb-checkin').val();
      const checkOut  = $('#ghm-pb-checkout').val();
      const guestName = (($('#ghm-pb-first-name').val()||'')+' '+($('#ghm-pb-last-name').val()||'')).trim();
      const amount    = parseFloat($('#ghm-pb-total-amount').val()||data.amount||0).toFixed(2);
      const html = `
        <div class="ghm-confirmation-wrap">
          <div class="ghm-confirmation-icon">&#10003;</div>
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
          <div class="ghm-alert info" style="margin-top:14px;text-align:left;">Please bring payment when you check in and quote your booking reference at reception.</div>
          <a href="${location.pathname}" style="display:inline-block;margin-top:16px;color:#c9a84c;font-size:13px;">Make another booking &#8594;</a>
        </div>`;
      $form.slideUp(300, function(){ $(this).after(html); });
    },

    /* ─── Alerts ───────────────────────────────────────────────── */
    showAlert(msg, type='info') {
      const icon = {success:'&#10003;',error:'&#10007;',info:'&#8505;'}[type]||'&#8505;';
      $('#ghm-form-alerts').html(`<div class="ghm-alert ${type}">${icon} ${msg}</div>`);
      $('html,body').animate({scrollTop:(($('#ghm-form-alerts').offset()?$('#ghm-form-alerts').offset().top:0)||0)-20},200);
    },
    clearAlerts() { $('#ghm-form-alerts').empty(); },

    _esc(s) {
      if (!s) return '';
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
  };

  window.GHMPublic = GHMPublic;
  $(document).ready(() => GHMPublic.init());

})(jQuery);
