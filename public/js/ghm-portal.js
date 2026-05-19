/* GuestHouse Manager – Guest Portal JS */
(function($){
  'use strict';

  const Portal = {

    init() {
      this.bindLogin();
      this.bindLogout();
      this.bindTabs();
      this.bindStars();
      this.bindServiceForm();
      this.bindReviewForm();
      this.bindPaystackBalance();
      this.bindFlutterwaveBalance();
    },

    /* ─── Login ──────────────────────────────────────────────── */
    bindLogin() {
      const $form = $('#ghm-portal-login-form');
      if (!$form.length) return;

      $form.on('submit', e => {
        e.preventDefault();
        this.login();
      });
    },

    login() {
      const ref   = ($('#ghm-portal-ref').val()   || '').trim().toUpperCase();
      const email = ($('#ghm-portal-email').val() || '').trim();

      if (!ref)   { this.showAlert('Please enter your booking reference.', 'error'); return; }
      if (!email) { this.showAlert('Please enter your email address.',     'error'); return; }

      const $btn = $('#ghm-portal-login-btn');
      $btn.prop('disabled', true).text('Verifying…');
      this.clearAlert();

      $.post(ghmPortal.ajax_url, {
        action      : 'ghm_portal_login',
        nonce       : ghmPortal.nonce,
        booking_ref : ref,
        email       : email,
      })
      .done(res => {
        if (res.success) {
          // Reload page to show dashboard
          window.location.reload();
        } else {
          this.showAlert((res.data ? res.data.message : '') || 'Login failed. Please check your details.', 'error');
          $btn.prop('disabled', false).text('Access My Booking');
        }
      })
      .fail(() => {
        this.showAlert('Network error. Please try again.', 'error');
        $btn.prop('disabled', false).text('Access My Booking');
      });
    },

    /* ─── Logout ─────────────────────────────────────────────── */
    bindLogout() {
      $(document).on('click', '#ghm-portal-logout-btn', () => {
        $.post(ghmPortal.ajax_url, { action:'ghm_portal_logout', nonce:ghmPortal.nonce })
          .always(() => window.location.reload());
      });
    },

    /* ─── Tabs ───────────────────────────────────────────────── */
    bindTabs() {
      $(document).on('click', '.ghm-portal-tab', function(){
        const tab = $(this).data('tab');
        $('.ghm-portal-tab').removeClass('active');
        $('.ghm-portal-tab-content').removeClass('active');
        $(this).addClass('active');
        $(`#ghm-tab-${tab}`).addClass('active');
      });
    },

    /* ─── Star Ratings ───────────────────────────────────────── */
    bindStars() {
      $(document).on('mouseenter', '.ghm-portal-star-picker .ghm-star', function(){
        const val     = +$(this).data('value');
        const $picker = $(this).closest('.ghm-portal-star-picker');
        $picker.find('.ghm-star').each(function(){
          $(this).css('color', +$(this).data('value') <= val ? '#f59e0b' : '#e5e7eb');
        });
      })
      .on('mouseleave', '.ghm-portal-star-picker', function(){
        const $picker   = $(this);
        const inputName = $picker.data('name');
        const current   = parseInt($(`input[name="${inputName}"]`).val()) || 0;
        $picker.find('.ghm-star').each(function(){
          $(this).css('color', +$(this).data('value') <= current ? '#f59e0b' : '#e5e7eb');
        });
      })
      .on('click', '.ghm-portal-star-picker .ghm-star', function(){
        const val       = +$(this).data('value');
        const $picker   = $(this).closest('.ghm-portal-star-picker');
        const inputName = $picker.data('name');
        $(`input[name="${inputName}"]`).val(val);
        $picker.find('.ghm-star').each(function(){
          $(this).css('color', +$(this).data('value') <= val ? '#f59e0b' : '#e5e7eb');
        });
      });

      // Set all star pickers to default value of 5
      $('.ghm-portal-star-picker').each(function(){
        $(this).find('.ghm-star').css('color', '#f59e0b');
      });
    },

    /* ─── Service Request ────────────────────────────────────── */
    bindServiceForm() {
      $(document).on('click', '#ghm-service-submit-btn', () => {
        const type    = $('input[name="service_type"]:checked').val();
        const message = $('#ghm-service-message').val().trim();

        if (!type) { this.showAlert('Please select a service type.', 'error'); return; }

        const $btn = $('#ghm-service-submit-btn');
        $btn.prop('disabled', true).text('Sending…');
        this.clearAlert();

        $.post(ghmPortal.ajax_url, {
          action : 'ghm_portal_service',
          nonce  : ghmPortal.nonce,
          type   : type,
          message: message,
        })
        .done(res => {
          $btn.prop('disabled', false).text('Send Request to Front Desk');
          if (res.success) {
            this.showAlert('✓ ' + ((res.data ? res.data.message : '') || 'Request sent!'), 'success');
            $('#ghm-service-message').val('');
            $('input[name="service_type"]').prop('checked', false);
            setTimeout(() => window.location.reload(), 1500);
          } else {
            this.showAlert((res.data ? res.data.message : '') || 'Failed to send.', 'error');
          }
        })
        .fail(() => {
          $btn.prop('disabled', false).text('Send Request to Front Desk');
          this.showAlert('Network error. Please try again.', 'error');
        });
      });
    },

    /* ─── Review Form ────────────────────────────────────────── */
    bindReviewForm() {
      $(document).on('click', '#ghm-review-submit-btn', () => {
        const rating = parseInt($('input[name="rating"]').val()) || 0;
        if (!rating) { this.showAlert('Please select an overall rating.', 'error'); return; }

        const data = {
          action      : 'ghm_portal_review',
          nonce       : ghmPortal.nonce,
          rating      : rating,
          cleanliness : $('input[name="cleanliness"]').val() || 5,
          service     : $('input[name="service"]').val()     || 5,
          comfort     : $('input[name="comfort"]').val()     || 5,
          value       : $('input[name="value"]').val()       || 5,
          title       : $('input[name="title"]').val(),
          comment     : $('textarea[name="comment"]').val(),
        };

        const $btn = $('#ghm-review-submit-btn');
        $btn.prop('disabled', true).text('Submitting…');
        this.clearAlert();

        $.post(ghmPortal.ajax_url, data)
          .done(res => {
            if (res.success) {
              this.showAlert('✓ ' + ((res.data ? res.data.message : '') || 'Review submitted!'), 'success');
              setTimeout(() => window.location.reload(), 1500);
            } else {
              $btn.prop('disabled', false).text('Submit Review');
              this.showAlert((res.data ? res.data.message : '') || 'Failed to submit.', 'error');
            }
          })
          .fail(() => {
            $btn.prop('disabled', false).text('Submit Review');
            this.showAlert('Network error. Please try again.', 'error');
          });
      });
    },

    /* ─── Paystack Balance Payment ───────────────────────────── */
    bindPaystackBalance() {
      $(document).on('click', '#ghm-portal-pay-btn', function(){
        if (typeof PaystackPop === 'undefined') {
          alert('Paystack not loaded. Please refresh and try again.');
          return;
        }

        const $btn       = $(this);
        const bookingId  = $btn.data('booking');
        const amount     = parseFloat($btn.data('amount'));
        const email      = $btn.data('email');
        const name       = $btn.data('name').split(' ');
        const ref        = $btn.data('ref');
        const currency   = (ghmPortal.currency || '₦');

        // Get Paystack config from page (must be localised)
        if (!window.ghmPaystack || !ghmPaystack.public_key) {
          alert('Payment not configured. Please contact reception.');
          return;
        }

        $btn.prop('disabled', true).text('Opening payment…');

        const handler = PaystackPop.setup({
          key      : ghmPaystack.public_key,
          email    : email,
          amount   : Math.round(amount * 100),
          currency : ghmPaystack.currency || 'NGN',
          ref      : 'PORTAL-' + ref + '-' + Date.now(),
          firstname: name[0] || '',
          lastname : name[1] || '',
          metadata : { custom_fields:[{ display_name:'Booking', variable_name:'booking_ref', value: ref }] },
          onClose  : () => { $btn.prop('disabled', false).html('💳 Pay Balance with Paystack'); },
          callback : (response) => {
            $btn.text('Verifying…');
            $.post(ghmPortal.ajax_url, {
              action    : 'ghm_paystack_verify',
              nonce     : ghmPortal.nonce,
              reference : response.reference,
              booking_id: bookingId,
            })
            .done(res => {
              if (res.success) {
                Portal.showAlert('✓ Payment successful! Your booking is confirmed.', 'success');
                setTimeout(() => window.location.reload(), 1500);
              } else {
                Portal.showAlert((res.data ? res.data.message : '') || 'Verification failed. Contact reception with ref: ' + response.reference, 'error');
                $btn.prop('disabled', false).html('💳 Pay Balance with Paystack');
              }
            })
            .fail(() => {
              Portal.showAlert('Could not verify payment. Contact reception with ref: ' + response.reference, 'error');
              $btn.prop('disabled', false).html('💳 Pay Balance with Paystack');
            });
          }
        });

        handler.openIframe();
      });
    },

    /* ─── Flutterwave Balance Payment ────────────────────────── */
    bindFlutterwaveBalance() {
      $(document).on('click', '#ghm-portal-pay-flw-btn', function(){
        if (typeof FlutterwaveCheckout === 'undefined') {
          alert('Flutterwave not loaded. Please refresh and try again.');
          return;
        }
        if (!window.ghmFlutterwave || !ghmFlutterwave.public_key) {
          alert('Flutterwave not configured. Please contact reception.');
          return;
        }

        const $btn      = $(this);
        const bookingId = $btn.data('booking');
        const amount    = parseFloat($btn.data('amount'));
        const email     = $btn.data('email');
        const fullName  = ($btn.data('name') || '').toString();
        const phone     = ($btn.data('phone') || '').toString();
        const ref       = $btn.data('ref');
        const origLabel = $btn.html();
        const tx_ref    = 'PORTAL-FLW-' + ref + '-' + Date.now();

        $btn.prop('disabled', true).text('Opening payment…');

        FlutterwaveCheckout({
          public_key: ghmFlutterwave.public_key,
          tx_ref    : tx_ref,
          amount    : amount,
          currency  : ghmFlutterwave.currency || 'NGN',
          payment_options: 'card,banktransfer,ussd,mobilemoneyghana,mobilemoneyrwanda,mobilemoneyzambia,mpesa',
          customer: { email: email, name: fullName, phone_number: phone },
          customizations: {
            title      : 'Booking Balance Payment',
            description: 'Balance for booking ' + ref,
          },
          meta: { booking_ref: ref, booking_id: bookingId },
          onclose: function(){
            $btn.prop('disabled', false).html(origLabel);
          },
          callback: function(response){
            $btn.text('Verifying…');
            const verifyNonce = (window.ghmPublic && ghmPublic.nonce) ? ghmPublic.nonce : ghmPortal.nonce;
            $.post(ghmPortal.ajax_url, {
              action        : 'ghm_flw_verify_balance',
              nonce         : verifyNonce,
              tx_ref        : response.tx_ref || tx_ref,
              transaction_id: response.transaction_id || '',
              booking_id    : bookingId,
            })
            .done(res => {
              if (res.success) {
                Portal.showAlert('✓ Payment successful! Your booking is confirmed.', 'success');
                setTimeout(() => window.location.reload(), 1500);
              } else {
                Portal.showAlert((res.data ? res.data.message : '') || 'Verification failed. Contact reception with ref: ' + tx_ref, 'error');
                $btn.prop('disabled', false).html(origLabel);
              }
            })
            .fail(() => {
              Portal.showAlert('Could not verify payment. Contact reception with ref: ' + tx_ref, 'error');
              $btn.prop('disabled', false).html(origLabel);
            });
          }
        });
      });
    },

    /* ─── Alert Helpers ──────────────────────────────────────── */
    showAlert(msg, type = 'info') {
      const $alert = $('#ghm-portal-alert');
      $alert.removeClass('success error info').addClass(type).html(msg).show();
      $('html,body').animate({ scrollTop: $alert.offset().top - 20 }, 200);
    },

    clearAlert() {
      $('#ghm-portal-alert').hide().html('');
    },
  };

  $(document).ready(() => Portal.init());

})(jQuery);
