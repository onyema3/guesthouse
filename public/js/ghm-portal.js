/* GuestHouse Manager – Guest Portal JS v2.0 (Redirect Payment Flow) */
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
          // Use cache-busting redirect to bypass LiteSpeed page cache
          const url = new URL(window.location.href);
          url.searchParams.set('logged_in', Date.now());
          window.location.href = url.toString();
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
          .always(() => {
            // Use cache-busting redirect instead of reload() to bypass
            // LiteSpeed/server page cache that may serve the old dashboard
            const url = new URL(window.location.href);
            url.searchParams.set('logged_out', Date.now());
            window.location.href = url.toString();
          });
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
            this.showAlert('&#10003; ' + ((res.data ? res.data.message : '') || 'Request sent!'), 'success');
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
              this.showAlert('&#10003; ' + ((res.data ? res.data.message : '') || 'Review submitted!'), 'success');
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

    /* ─── Paystack Balance Payment (Redirect Flow) ───────────── */
    bindPaystackBalance() {
      $(document).on('click', '#ghm-portal-pay-btn', function(){
        const $btn      = $(this);
        const bookingId = $btn.data('booking');
        const amount    = parseFloat($btn.data('amount'));
        const email     = $btn.data('email');
        const name      = ($btn.data('name') || '').toString();
        const ref       = $btn.data('ref');

        $btn.prop('disabled', true).text('Connecting to Paystack…');

        // Call the server to initialize a Paystack transaction (redirect flow)
        const nonce = (window.ghmPublic && ghmPublic.nonce) ? ghmPublic.nonce : ghmPortal.nonce;
        $.ajax({
          url: ghmPortal.ajax_url,
          type: 'POST',
          dataType: 'json',
          timeout: 30000,
          data: {
            action    : 'ghm_paystack_init_balance',
            nonce     : nonce,
            booking_id: bookingId,
            amount    : amount,
            email     : email,
            name      : name,
            ref       : ref,
          }
        })
        .done(res => {
          if (res.success && res.data.authorization_url) {
            $btn.text('Redirecting to Paystack…');
            window.location.href = res.data.authorization_url;
          } else {
            Portal.showAlert((res.data ? res.data.message : '') || 'Could not initialise payment. Please try again.', 'error');
            $btn.prop('disabled', false).html('&#128179; Pay Balance with Paystack');
          }
        })
        .fail(() => {
          Portal.showAlert('Network error. Please try again.', 'error');
          $btn.prop('disabled', false).html('&#128179; Pay Balance with Paystack');
        });
      });
    },

    /* ─── Flutterwave Balance Payment (Redirect Flow) ────────── */
    bindFlutterwaveBalance() {
      $(document).on('click', '#ghm-portal-pay-flw-btn', function(){
        const $btn      = $(this);
        const bookingId = $btn.data('booking');
        const amount    = parseFloat($btn.data('amount'));
        const email     = $btn.data('email');
        const fullName  = ($btn.data('name') || '').toString();
        const phone     = ($btn.data('phone') || '').toString();
        const ref       = $btn.data('ref');

        $btn.prop('disabled', true).text('Connecting to Flutterwave…');

        const nonce = (window.ghmPublic && ghmPublic.nonce) ? ghmPublic.nonce : ghmPortal.nonce;
        $.ajax({
          url: ghmPortal.ajax_url,
          type: 'POST',
          dataType: 'json',
          timeout: 30000,
          data: {
            action    : 'ghm_flw_init_balance',
            nonce     : nonce,
            booking_id: bookingId,
            amount    : amount,
            email     : email,
            name      : fullName,
            phone     : phone,
            ref       : ref,
          }
        })
        .done(res => {
          if (res.success && res.data.payment_link) {
            $btn.text('Redirecting to Flutterwave…');
            window.location.href = res.data.payment_link;
          } else {
            Portal.showAlert((res.data ? res.data.message : '') || 'Could not initialise payment. Please try again.', 'error');
            $btn.prop('disabled', false).html('&#128179; Pay Balance with Flutterwave');
          }
        })
        .fail(() => {
          Portal.showAlert('Network error. Please try again.', 'error');
          $btn.prop('disabled', false).html('&#128179; Pay Balance with Flutterwave');
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
