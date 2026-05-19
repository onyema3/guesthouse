/* GuestHouse Manager – Admin JS v1.1 */
(function($){
  'use strict';

  const GHM = {
    charts: {},

    init() {
      this.bindGlobal();
      this.initToast();
      this.initPage();
    },

    /* ===== GLOBAL BINDINGS ===== */
    bindGlobal() {
      // Close modal only on X button or Escape — never on bubble from inputs
      $(document).on('click', '.ghm-modal-close', function(e){
        e.preventDefault();
        e.stopPropagation();
        GHM.closeModal();
      });
      $(document).on('keydown', function(e){
        if (e.key === 'Escape') GHM.closeModal();
      });
      // Overlay background click — only if exact overlay div was the target
      $(document).on('click', '.ghm-modal-overlay', function(e){
        if (e.target === this) GHM.closeModal();
      });
    },

    /* ===== PAGE ROUTER ===== */
    initPage() {
      const page = new URLSearchParams(location.search).get('page') || '';
      const map  = {
        'ghm-dashboard' : 'initDashboard',
        'ghm-rooms'     : 'initRooms',
        'ghm-workspaces': 'initWorkspaces',
        'ghm-bookings'  : 'initBookings',
        'ghm-customers' : 'initCustomers',
        'ghm-payments'  : 'initPayments',
        'ghm-staff'     : 'initStaff',
        'ghm-reports'   : 'initReports',
      };
      if (map[page]) this[map[page]]();
    },

    /* ===== TOAST ===== */
    initToast() {
      if (!$('#ghm-toast-container').length) $('body').append('<div id="ghm-toast-container"></div>');
    },
    toast(msg, type='info', dur=4000) {
      const icon = {success:'✓', error:'✗', info:'ℹ'}[type] || 'ℹ';
      const $t = $('<div class="ghm-toast '+type+'">'+icon+' '+msg+'</div>');
      $('#ghm-toast-container').append($t);
      setTimeout(() => $t.fadeOut(300, () => $t.remove()), dur);
    },

    /* ===== MODAL ===== */
    openModal(title, body, footer) {
      this.closeModal();
      const $o = $(`
        <div class="ghm-modal-overlay">
          <div class="ghm-modal" id="ghm-active-modal">
            <div class="ghm-modal-header">
              <h2>${title}</h2>
              <button type="button" class="ghm-modal-close" title="Close">&times;</button>
            </div>
            <div class="ghm-modal-body">${body}</div>
            ${footer ? '<div class="ghm-modal-footer">'+footer+'</div>' : ''}
          </div>
        </div>`);

      // Critical: stop ALL events on the inner modal from reaching the overlay
      $o.find('#ghm-active-modal').on('click mousedown mouseup focus keydown keyup', function(e){
        e.stopPropagation();
      });

      $('body').append($o);
      // Focus first visible input
      setTimeout(()=>{ $o.find('input:visible:first').trigger('focus'); }, 100);
    },
    closeModal() { $('.ghm-modal-overlay').remove(); },

    /* ===== AJAX ===== */
    post(action, data) {
      return new Promise((resolve, reject) => {
        $.ajax({
          url  : ghmAdmin.ajax_url,
          type : 'POST',
          data : Object.assign({ action, nonce: ghmAdmin.nonce }, data),
          success(res) {
            if (res && res.success) resolve(res.data);
            else reject((res && res.data && res.data.message) ? res.data.message : 'Unknown error');
          },
          error(xhr) {
            reject('Server error: ' + xhr.status + ' ' + xhr.statusText);
          }
        });
      });
    },

    /* ===== HELPERS ===== */
    debounce(fn, ms=350) {
      let t; return (...a) => { clearTimeout(t); t = setTimeout(()=>fn(...a), ms); };
    },
    esc(s) {
      if (!s) return '';
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    },
    // Collect all named inputs/selects/textareas from the open modal
    collectForm() {
      const data = {};
      $('#ghm-active-modal').find('input, select, textarea').each(function(){
        const el   = this;
        const name = el.name;
        if (!name) return;
        if (el.type === 'checkbox') {
          if (el.checked) {
            if (!data[name]) data[name] = [];
            if (!Array.isArray(data[name])) data[name] = [data[name]];
            data[name].push(el.value);
          }
        } else {
          data[name] = el.value;
        }
      });
      return data;
    },
    btnBusy($btn, text) { $btn.prop('disabled', true).data('orig', $btn.text()).text(text+'…'); },
    btnReset($btn)       { $btn.prop('disabled', false).text($btn.data('orig') || 'Save'); },
    searchRows(tableId, q) {
      const lo = q.toLowerCase();
      $(tableId).find('tbody tr').each(function(){ $(this).toggle($(this).text().toLowerCase().includes(lo)); });
    },
    searchCards(selector, q) {
      const lo = q.toLowerCase();
      $(selector).each(function(){ $(this).toggle($(this).text().toLowerCase().includes(lo)); });
    },

    /* ================================================================
       DASHBOARD
    ================================================================ */
    initDashboard() {
      const raw = document.getElementById('ghm-chart-data');
      if (raw && raw.value) { try { this.renderBar('ghm-revenue-chart', JSON.parse(raw.value)); } catch(e){} }
      this.post('ghm_get_chart_data', {chart:'status'}).then(d => this.renderDonut('ghm-status-chart', d, 'status')).catch(()=>{});
    },

    renderBar(id, data) {
      const ctx = document.getElementById(id);
      if (!ctx || !data || !data.length) return;
      if (this.charts[id]) this.charts[id].destroy();
      this.charts[id] = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.map(d=>d.label),
          datasets: [
            { label:'Revenue', data:data.map(d=>+d.revenue||0), backgroundColor:'rgba(201,168,76,.6)', borderColor:'#c9a84c', borderWidth:2, borderRadius:5, yAxisID:'y' },
            { label:'Bookings', type:'line', data:data.map(d=>+d.bookings||0), borderColor:'#60a5fa', backgroundColor:'rgba(96,165,250,.1)', tension:0.4, pointBackgroundColor:'#60a5fa', yAxisID:'y1' }
          ]
        },
        options: {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ labels:{ color:'#e8eaf0', font:{family:'DM Sans'} } } },
          scales:{
            x:{ ticks:{color:'#7a7f96'}, grid:{color:'rgba(255,255,255,.05)'} },
            y:{ ticks:{color:'#7a7f96', callback:v=>(ghmAdmin.currency_symbol||'$')+Number(v).toLocaleString()}, grid:{color:'rgba(255,255,255,.05)'} },
            y1:{ position:'right', ticks:{color:'#60a5fa'}, grid:{display:false} }
          }
        }
      });
    },

    renderDonut(id, data, labelField) {
      const ctx = document.getElementById(id);
      if (!ctx || !data || !data.length) return;
      if (this.charts[id]) this.charts[id].destroy();
      const colors = ['#c9a84c','#3ecf8e','#60a5fa','#f59e0b','#ef4444','#a78bfa','#fb923c','#34d399'];
      this.charts[id] = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: data.map(d=>d[labelField]||'Unknown'),
          datasets:[{ data:data.map(d=>+d.count||0), backgroundColor:colors, borderWidth:0 }]
        },
        options:{
          responsive:true, maintainAspectRatio:false, cutout:'65%',
          plugins:{ legend:{ position:'bottom', labels:{ color:'#e8eaf0', padding:12, font:{family:'DM Sans'} } } }
        }
      });
    },

    /* ================================================================
       ROOMS
    ================================================================ */
    initRooms() {
      const self = this;
      $(document)
        .on('click', '#ghm-btn-add-room',  function(){ self.roomModal(0, 'room'); })
        .on('click', '.ghm-edit-room',      function(){ self.roomModal(+$(this).data('id'), 'room'); })
        .on('click', '.ghm-delete-room',    function(){ self.deleteRoom(+$(this).data('id')); })
        .on('input',  '#ghm-room-search',   self.debounce(function(){ self.searchCards('.ghm-room-card', this.value); }));
    },

    initWorkspaces() {
      const self = this;
      $(document)
        .on('click', '#ghm-btn-add-workspace', function(){ self.roomModal(0, 'workspace'); })
        .on('click', '.ghm-edit-workspace',     function(){ self.roomModal(+$(this).data('id'), 'workspace'); })
        .on('click', '.ghm-delete-workspace',   function(){ self.deleteRoom(+$(this).data('id')); });
    },

    roomModal(id, roomType) {
      const self  = this;
      const isWS  = (roomType === 'workspace');
      const label = isWS ? 'Workspace' : 'Room';

      const buildModal = (room) => {
        const amenityList = isWS
          ? ['WiFi','AC','Projector','Whiteboard','Printer','Phone','TV','Parking','Locker']
          : ['WiFi','AC','TV','Minibar','Safe','Bathtub','Jacuzzi','Balcony','Sea View','Kitchen','Parking','Desk'];
        const saved = (room && room.amenities) ? JSON.parse(room.amenities) : [];

        const amenityHTML = amenityList.map(a =>
          `<label class="ghm-amenity-check">
             <input type="checkbox" name="amenities" value="${self.esc(a)}" ${saved.includes(a)?'checked':''}> ${self.esc(a)}
           </label>`
        ).join('');

        const typeOpts = isWS
          ? [['workspace','Workspace'],['hall','Event Hall']]
          : [['room','Room'],['suite','Suite'],['apartment','Apartment']];
        const typeHTML = typeOpts.map(([v,l])=>`<option value="${v}" ${(room&&room.type===v)||(!room&&v===roomType)?'selected':''}>${l}</option>`).join('');

        const sym = self.esc(ghmAdmin.currency_symbol || '$');

        const body = `
          <div class="ghm-form-section">
            <div class="ghm-form-grid">
              <div class="ghm-form-field">
                <label>Name *</label>
                <input type="text" name="name" value="${self.esc(room&&room.name||'')}" placeholder="${isWS?'Conference Room A':'Deluxe Room 101'}" required>
              </div>
              <div class="ghm-form-field">
                <label>Type</label>
                <select name="type">${typeHTML}</select>
              </div>
              <div class="ghm-form-field">
                <label>Room / Space Number</label>
                <input type="text" name="room_number" value="${self.esc(room&&room.room_number||'')}">
              </div>
              <div class="ghm-form-field">
                <label>Floor</label>
                <input type="text" name="floor" value="${self.esc(room&&room.floor||'')}">
              </div>
              <div class="ghm-form-field">
                <label>Capacity (persons)</label>
                <input type="number" name="capacity" value="${room&&room.capacity||1}" min="1">
              </div>
              <div class="ghm-form-field">
                <label id="ghm-price-label">${isWS ? 'Price per Hour ('+sym+')' : ((room&&room.type==='hall') ? 'Price per Day ('+sym+')' : 'Price per Night ('+sym+')')}</label>
                <input type="number" id="ghm-price-input" name="${isWS?'price_hour':'price_night'}" step="0.01" min="0"
                  value="${isWS ? (room&&room.price_hour||0) : (room&&room.price_night||0)}">
              </div>
              <div class="ghm-form-field">
                <label>Status</label>
                <select name="status">
                  <option value="available"    ${(!room||room.status==='available')?'selected':''}>Available</option>
                  <option value="maintenance"  ${room&&room.status==='maintenance'?'selected':''}>Maintenance</option>
                  <option value="inactive"     ${room&&room.status==='inactive'?'selected':''}>Inactive</option>
                </select>
              </div>
              <div class="ghm-form-field">
                <label>${isWS?'Also set price/night (optional)':'Also set price/hour (optional)'}</label>
                <input type="number" name="${isWS?'price_night':'price_hour'}" step="0.01" min="0"
                  value="${isWS ? (room&&room.price_night||0) : (room&&room.price_hour||0)}">
              </div>
              <div class="ghm-form-field span-2">
                <label>Description</label>
                <textarea name="description" rows="2">${self.esc(room&&room.description||'')}</textarea>
              </div>
              <div class="ghm-form-field span-2">
                <label>Amenities</label>
                <div class="ghm-amenities-list">${amenityHTML}</div>
              </div>
            </div>
          </div>`;

        const foot = `
          <button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>
          <button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">
            ${id > 0 ? 'Update' : 'Add'} ${label}
          </button>`;

        self.openModal((id>0?'Edit ':'Add ')+label, body, foot);

        // Bind buttons fresh each time (modal is new DOM)
        document.getElementById('ghm-btn-cancel').addEventListener('click', ()=>self.closeModal());
        document.getElementById('ghm-btn-submit').addEventListener('click', ()=>self.saveRoom(id, roomType));

        // Dynamically update price label when type changes (for non-workspace modals)
        const typeEl = document.querySelector('#ghm-active-modal select[name="type"]');
        const lblEl  = document.getElementById('ghm-price-label');
        if (typeEl && lblEl && !isWS) {
          typeEl.addEventListener('change', function(){
            const s = ghmAdmin.currency_symbol || '$';
            if (this.value === 'workspace') {
              lblEl.textContent = 'Price per Hour (' + s + ')';
            } else if (this.value === 'hall') {
              lblEl.textContent = 'Price per Day (' + s + ')';
            } else {
              lblEl.textContent = 'Price per Night (' + s + ')';
            }
          });
        }
      };

      if (id > 0) {
        this.post('ghm_get_room', {id}).then(buildModal).catch(()=>buildModal(null));
      } else {
        buildModal(null);
      }
    },

    saveRoom(id, roomType) {
      const $btn = $('#ghm-btn-submit');
      const name = $('#ghm-active-modal input[name="name"]').val().trim();
      if (!name) {
        this.toast('Room name is required.', 'error');
        $('#ghm-active-modal input[name="name"]').focus();
        return;
      }
      const data = this.collectForm();
      data.id   = id;
      // Normalize amenities: collectForm gives array or undefined
      if (!data.amenities) data.amenities = [];
      if (!Array.isArray(data.amenities)) data.amenities = [data.amenities];

      this.btnBusy($btn, 'Saving');
      this.post('ghm_save_room', data)
        .then(() => { this.toast(id>0?'Room updated!':'Room created!', 'success'); this.closeModal(); setTimeout(()=>location.reload(), 800); })
        .catch(err => { this.toast(err||'Save failed.', 'error'); this.btnReset($btn); });
    },

    deleteRoom(id) {
      if (!confirm('Delete this room? This cannot be undone.')) return;
      this.post('ghm_delete_room', {id})
        .then(() => { this.toast('Room deleted.', 'success'); setTimeout(()=>location.reload(), 800); })
        .catch(err => this.toast(err||'Delete failed.', 'error'));
    },

    /* ================================================================
       BOOKINGS
    ================================================================ */
    initBookings() {
      const self = this;
      $(document)
        .on('click', '#ghm-btn-add-booking',      function(){ self.bookingModal(); })
        .on('click', '.ghm-checkin-btn',           function(){ self.bookingAction('ghm_checkin',        +$(this).data('id'), 'Checked in!'); })
        .on('click', '.ghm-checkout-btn',          function(){ self.bookingAction('ghm_checkout',       +$(this).data('id'), 'Checked out!'); })
        .on('click', '.ghm-cancel-btn',            function(){ self.bookingAction('ghm_cancel_booking', +$(this).data('id'), 'Booking cancelled.', true); })
        .on('click', '.ghm-record-payment-btn',    function(){ self.paymentModal(+$(this).data('id')); })
        .on('input',  '#ghm-booking-search',        self.debounce(function(){ self.searchRows('#ghm-bookings-table', this.value); }));
    },

    bookingAction(action, id, msg, needConfirm=false) {
      const doIt = () => this.post(action, {id}).then(()=>{ this.toast(msg,'success'); setTimeout(()=>location.reload(),800); }).catch(e=>this.toast(e,'error'));
      needConfirm ? (confirm('Are you sure?') && doIt()) : doIt();
    },

    bookingModal() {
      const self  = this;
      const rooms = window.ghmRooms || [];
      const now   = new Date(), tom = new Date(now); tom.setDate(tom.getDate()+1);
      const fmt   = d => d.toISOString().slice(0,16);

      const buildRoomOpts = (type) => {
        const filtered = rooms.filter(r => type==='workspace' ? r.type==='workspace' : r.type!=='workspace');
        if (!filtered.length) return '<option value="">No rooms available</option>';
        return '<option value="">— Select room —</option>' + filtered.map(r => {
          const sym2 = ghmAdmin.currency_symbol||'$';
          const price = r.type==='workspace'
            ? `${sym2}${(+r.price_hour||0).toFixed(2)}/hr`
            : r.type==='hall'
            ? `${sym2}${(+r.price_night||0).toFixed(2)}/day`
            : `${sym2}${(+r.price_night||0).toFixed(2)}/night`;
          return `<option value="${r.id}">${self.esc(r.name)} (${self.esc(r.room_number)}) — ${price}</option>`;
        }).join('');
      };

      const body = `
        <div class="ghm-form-section">
          <p class="ghm-form-section-title">Guest</p>
          <div class="ghm-form-grid">
            <div class="ghm-form-field span-2" style="position:relative;">
              <label>Customer * <small style="color:var(--ghm-muted);font-weight:400;">(type to search)</small></label>
              <input type="text" id="ghm-cs-search" autocomplete="off" placeholder="Search by name or email…">
              <input type="hidden" name="customer_id" id="ghm-cs-id">
              <div id="ghm-cs-drop" style="display:none;position:absolute;top:calc(100% - 4px);left:0;right:0;z-index:9999;
                background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:0 0 8px 8px;
                max-height:200px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.5);"></div>
            </div>
          </div>
        </div>
        <div class="ghm-form-section">
          <p class="ghm-form-section-title">Booking Details</p>
          <div class="ghm-form-grid">
            <div class="ghm-form-field">
              <label>Booking Type</label>
              <select name="booking_type" id="ghm-bk-type">
                <option value="room">Room / Suite</option>
                <option value="workspace">Workspace</option>
              </select>
            </div>
            <div class="ghm-form-field">
              <label>Room / Space *</label>
              <select name="room_id" id="ghm-bk-room">${buildRoomOpts('room')}</select>
            </div>
            <div class="ghm-form-field">
              <label>Check-In *</label>
              <input type="datetime-local" name="check_in" id="ghm-bk-ci" value="${fmt(now)}" required>
            </div>
            <div class="ghm-form-field">
              <label>Check-Out *</label>
              <input type="datetime-local" name="check_out" id="ghm-bk-co" value="${fmt(tom)}" required>
            </div>
            <div class="ghm-form-field">
              <label>Adults</label>
              <select name="adults">
                ${[1,2,3,4,5,6,7,8].map(n=>`<option value="${n}">${n} adult${n>1?'s':''}</option>`).join('')}
              </select>
            </div>
            <div class="ghm-form-field">
              <label>Children</label>
              <select name="children">
                ${[0,1,2,3,4,5].map(n=>`<option value="${n}">${n} child${n===1?'':'ren'}</option>`).join('')}
              </select>
            </div>
            <div class="ghm-form-field span-2">
              <label>Special Requests</label>
              <textarea name="special_requests" rows="2" placeholder="Dietary needs, room preferences…"></textarea>
            </div>
            <div class="ghm-form-field span-2">
              <label>Booking Source</label>
              <select name="source">
                <option value="direct_phone">Direct — Phone Call</option>
                <option value="walk_in">Walk-in</option>
                <option value="direct_website">Direct — Website</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="booking_com">Booking.com</option>
                <option value="airbnb">Airbnb</option>
                <option value="referral">Referral</option>
                <option value="corporate">Corporate / Company</option>
                <option value="travel_agent">Travel Agent</option>
                <option value="social_media">Social Media</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>
          <div id="ghm-bk-amt-row" style="display:none;background:var(--ghm-surface2);border:1px solid var(--ghm-border);
               border-radius:7px;padding:12px 16px;margin-top:14px;display:none;justify-content:space-between;align-items:center;">
            <span style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--ghm-muted);">Estimated Total</span>
            <span id="ghm-bk-amt-val" style="font-size:22px;font-weight:700;color:var(--ghm-gold);">—</span>
          </div>
          <input type="hidden" name="total_amount" id="ghm-bk-total" value="0">
        </div>`;

      const foot = `
        <button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>
        <button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">Create Booking</button>`;

      self.openModal('New Booking', body, foot);

      // Native event listeners — no jQuery namespace confusion
      document.getElementById('ghm-btn-cancel').addEventListener('click', ()=>self.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click', ()=>self.saveBooking());

      document.getElementById('ghm-bk-type').addEventListener('change', function(){
        document.getElementById('ghm-bk-room').innerHTML = buildRoomOpts(this.value);
        self.calcAmount();
      });

      ['ghm-bk-room','ghm-bk-ci','ghm-bk-co'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', ()=>self.calcAmount());
      });

      // Customer search
      let csTimer;
      const csInput = document.getElementById('ghm-cs-search');
      const csIdEl  = document.getElementById('ghm-cs-id');
      const csDrop  = document.getElementById('ghm-cs-drop');
      csInput.addEventListener('input', function(){
        clearTimeout(csTimer);
        const q = this.value.trim();
        const $drop = $(csDrop);
        // Clear previously selected customer when user edits the field
        if (csIdEl.value) csIdEl.value = '';
        if (q.length < 1) { $drop.hide().empty(); return; }
        csTimer = setTimeout(()=>{
          self.post('ghm_search_customers', {q}).then(results => {
            if (!results || !results.length) {
              $drop.html('<div style="padding:12px;color:var(--ghm-muted);font-size:13px;">No customers found. <a href="?page=ghm-customers" target="_blank" style="color:var(--ghm-gold);">Add one first.</a></div>').show();
              return;
            }
            const html = results.map(c =>
              `<div class="ghm-cs-opt" data-id="${c.id}" data-name="${self.esc(c.first_name+' '+c.last_name)}"
                style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--ghm-border);font-size:13px;"
                onmouseover="this.style.background='var(--ghm-surface)'" onmouseout="this.style.background=''">
                <strong>${self.esc(c.first_name+' '+c.last_name)}</strong>
                <small style="color:var(--ghm-muted);margin-left:8px;">${self.esc(c.email)}</small>
              </div>`
            ).join('');
            $drop.html(html).show();
          }).catch(()=>{});
        }, 300);
      });

      // Bind selection DIRECTLY to the dropdown (scoped) — the modal's
      // stopPropagation prevents document-level delegated handlers from firing.
      // Use mousedown so it fires before blur hides the dropdown.
      $(csDrop).on('mousedown', '.ghm-cs-opt', function(e){
        e.preventDefault();
        csIdEl.value      = $(this).data('id');
        csInput.value     = $(this).data('name');
        $(csDrop).hide();
      });

      // Hide dropdown when clicking elsewhere inside the modal
      $('#ghm-active-modal').on('mousedown.ghm-cs-out', function(e){
        if (!$(e.target).closest('#ghm-cs-search, #ghm-cs-drop').length) {
          $(csDrop).hide();
        }
      });
    },

    calcAmount() {
      const roomId  = $('#ghm-bk-room').val();
      const checkIn = $('#ghm-bk-ci').val();
      const checkOut= $('#ghm-bk-co').val();
      const type    = $('#ghm-bk-type').val() || 'room';
      if (!roomId || !checkIn || !checkOut) return;
      this.post('ghm_calc_amount', {room_id:roomId, check_in:checkIn, check_out:checkOut, booking_type:type})
        .then(d => {
          const sym = ghmAdmin.currency_symbol || '$';
          const amt = parseFloat(d.amount||0).toFixed(2);
          document.getElementById('ghm-bk-amt-val').textContent = sym + amt;
          document.getElementById('ghm-bk-total').value = amt;
          document.getElementById('ghm-bk-amt-row').style.display = 'flex';
        }).catch(()=>{});
    },

    saveBooking() {
      const $btn  = $('#ghm-btn-submit');
      const custId = document.getElementById('ghm-cs-id').value;
      const roomId = document.getElementById('ghm-bk-room').value;
      const ci     = document.getElementById('ghm-bk-ci').value;
      const co     = document.getElementById('ghm-bk-co').value;

      if (!custId) { this.toast('Please select a customer.', 'error'); document.getElementById('ghm-cs-search').focus(); return; }
      if (!roomId) { this.toast('Please select a room.', 'error'); return; }
      if (!ci)     { this.toast('Please set a check-in date/time.', 'error'); return; }
      if (!co)     { this.toast('Please set a check-out date/time.', 'error'); return; }
      if (co <= ci){ this.toast('Check-out must be after check-in.', 'error'); return; }

      const data = this.collectForm();
      data.customer_id = custId; // ensure hidden field is captured

      this.btnBusy($btn, 'Creating');
      this.post('ghm_save_booking', data)
        .then(() => { this.toast('Booking created!','success'); this.closeModal(); setTimeout(()=>location.reload(),800); })
        .catch(err => { this.toast(err||'Failed to create booking.','error'); this.btnReset($btn); });
    },

    /* ================================================================
       CUSTOMERS
    ================================================================ */
    initCustomers() {
      const self = this;
      $(document)
        .on('click', '#ghm-btn-add-customer', function(){ self.customerModal(0); })
        .on('click', '.ghm-edit-customer',    function(){ self.customerModal(+$(this).data('id')); })
        .on('click', '.ghm-delete-customer',  function(){
          const id = +$(this).data('id');
          if (!confirm('Delete this customer? This cannot be undone.')) return;
          self.post('ghm_delete_customer',{id}).then(()=>{ self.toast('Customer deleted.','success'); setTimeout(()=>location.reload(),800); }).catch(e=>self.toast(e,'error'));
        })
        .on('input', '#ghm-customer-search-main', self.debounce(function(){ self.searchRows('#ghm-customers-table', this.value); }));
    },

    customerModal(id) {
      const self = this;
      const build = (c) => {
        c = c || {};
        const v    = f => self.esc(c[f]||'');
        const sel  = (f,v2) => c[f]===v2 ? 'selected' : '';

        const body = `
          <div class="ghm-form-section">
            <div class="ghm-form-grid">
              <div class="ghm-form-field">
                <label>First Name *</label>
                <input type="text" name="first_name" value="${v('first_name')}" required placeholder="John">
              </div>
              <div class="ghm-form-field">
                <label>Last Name *</label>
                <input type="text" name="last_name" value="${v('last_name')}" required placeholder="Smith">
              </div>
              <div class="ghm-form-field">
                <label>Email *</label>
                <input type="email" name="email" value="${v('email')}" required placeholder="john@example.com">
              </div>
              <div class="ghm-form-field">
                <label>Phone</label>
                <input type="tel" name="phone" value="${v('phone')}" placeholder="+234 800 000 0000">
              </div>
              <div class="ghm-form-field">
                <label>Country</label>
                <input type="text" name="country" value="${v('country')}" placeholder="Nigeria">
              </div>
              <div class="ghm-form-field">
                <label>ID Type</label>
                <select name="id_type">
                  <option value="">— Optional —</option>
                  <option value="passport"        ${sel('id_type','passport')}>Passport</option>
                  <option value="national_id"     ${sel('id_type','national_id')}>National ID</option>
                  <option value="drivers_license" ${sel('id_type','drivers_license')}>Driver's License</option>
                </select>
              </div>
              <div class="ghm-form-field">
                <label>ID Number</label>
                <input type="text" name="id_number" value="${v('id_number')}">
              </div>
              <div class="ghm-form-field">
                <label>Status</label>
                <select name="status">
                  <option value="active"       ${!c.status||c.status==='active'?'selected':''}>Active</option>
                  <option value="blacklisted"  ${sel('status','blacklisted')}>Blacklisted</option>
                  <option value="inactive"     ${sel('status','inactive')}>Inactive</option>
                </select>
              </div>
              <div class="ghm-form-field span-2">
                <label>Address</label>
                <textarea name="address" rows="2">${v('address')}</textarea>
              </div>
              <div class="ghm-form-field span-2">
                <label>Notes</label>
                <textarea name="notes" rows="2">${v('notes')}</textarea>
              </div>
            </div>
          </div>`;

        const foot = `
          <button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>
          <button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">${id>0?'Update':'Add'} Customer</button>`;

        self.openModal(id>0?'Edit Customer':'Add Customer', body, foot);

        document.getElementById('ghm-btn-cancel').addEventListener('click', ()=>self.closeModal());
        document.getElementById('ghm-btn-submit').addEventListener('click', ()=>{
          const $btn = $('#ghm-btn-submit');
          const data = self.collectForm();
          data.id    = id;

          // Basic validation
          const required = ['first_name','last_name','email'];
          let ok = true;
          required.forEach(f => {
            const el = document.querySelector(`#ghm-active-modal [name="${f}"]`);
            if (!el || !el.value.trim()) { if(el) el.style.borderColor='#ef4444'; ok=false; }
            else if(el) el.style.borderColor='';
          });
          if (!ok) { self.toast('Please fill in all required fields.','error'); return; }

          self.btnBusy($btn,'Saving');
          self.post('ghm_save_customer', data)
            .then(()=>{ self.toast(id>0?'Customer updated!':'Customer added!','success'); self.closeModal(); setTimeout(()=>location.reload(),800); })
            .catch(err=>{ self.toast(err||'Save failed.','error'); self.btnReset($btn); });
        });
      };

      if (id > 0) {
        this.post('ghm_get_customer',{id}).then(build).catch(()=>build({}));
      } else {
        build({});
      }
    },

    /* ================================================================
       PAYMENTS
    ================================================================ */
    initPayments() {
      const self = this;
      $(document).on('click', '.ghm-record-payment-btn', function(){ self.paymentModal(+$(this).data('id')); });
    },

    paymentModal(bookingId) {
      const self = this;
      const sym  = this.esc(ghmAdmin.currency_symbol||'$');

      const body = `
        <div class="ghm-form-section">
          <div class="ghm-form-grid">
            <div class="ghm-form-field">
              <label>Amount (${sym}) *</label>
              <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="ghm-form-field">
              <label>Currency</label>
              <select name="currency">
                <option value="USD">USD</option><option value="EUR">EUR</option>
                <option value="GBP">GBP</option><option value="NGN">NGN</option>
                <option value="KES">KES</option><option value="GHS">GHS</option>
                <option value="ZAR">ZAR</option>
              </select>
            </div>
            <div class="ghm-form-field span-2">
              <label>Payment Method</label>
              <select name="method">
                <option value="cash">Cash</option>
                <option value="card">Credit / Debit Card</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="online">Online Payment</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="ghm-form-field span-2">
              <label>Transaction ID / Reference</label>
              <input type="text" name="transaction_id" placeholder="Optional — e.g. TXN-12345">
            </div>
            <div class="ghm-form-field span-2">
              <label>Notes</label>
              <textarea name="notes" rows="2" placeholder="Optional notes…"></textarea>
            </div>
          </div>
        </div>`;

      const foot = `
        <button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>
        <button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">Record Payment</button>`;

      self.openModal('Record Payment', body, foot);

      document.getElementById('ghm-btn-cancel').addEventListener('click', ()=>self.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click', ()=>{
        const $btn   = $('#ghm-btn-submit');
        const amount = document.querySelector('#ghm-active-modal [name="amount"]').value;
        if (!amount || +amount <= 0) {
          self.toast('Please enter a valid amount.','error');
          document.querySelector('#ghm-active-modal [name="amount"]').focus();
          return;
        }
        const data        = self.collectForm();
        data.booking_id   = bookingId;
        self.btnBusy($btn,'Recording');
        self.post('ghm_record_payment', data)
          .then(()=>{ self.toast('Payment recorded!','success'); self.closeModal(); setTimeout(()=>location.reload(),800); })
          .catch(err=>{ self.toast(err||'Failed.','error'); self.btnReset($btn); });
      });
    },

    /* ================================================================
       STAFF
    ================================================================ */
    initStaff() {
      const self = this;
      $(document)
        .on('click', '#ghm-btn-add-staff', function(){ self.staffModal(0); })
        .on('click', '.ghm-edit-staff',    function(){ self.staffModal(+$(this).data('id')); })
        .on('click', '.ghm-delete-staff',  function(){
          const id = +$(this).data('id');
          if (!confirm('Remove this staff member?')) return;
          self.post('ghm_delete_staff',{id}).then(()=>{ self.toast('Staff removed.','success'); setTimeout(()=>location.reload(),800); }).catch(e=>self.toast(e,'error'));
        });
    },

    staffModal(id) {
      const self  = this;
      const isNew = id === 0;

      const body = `
        <div class="ghm-form-section">
          <div class="ghm-form-grid">
            <div class="ghm-form-field">
              <label>First Name *</label>
              <input type="text" name="first_name" required placeholder="Jane">
            </div>
            <div class="ghm-form-field">
              <label>Last Name *</label>
              <input type="text" name="last_name" required placeholder="Doe">
            </div>
            <div class="ghm-form-field">
              <label>Email *</label>
              <input type="email" name="email" required placeholder="jane@hotel.com">
            </div>
            <div class="ghm-form-field">
              <label>Phone</label>
              <input type="tel" name="phone" placeholder="+234 800 000 0000">
            </div>
            ${isNew ? `
            <div class="ghm-form-field">
              <label>Username *</label>
              <input type="text" name="username" required placeholder="jane.doe" autocomplete="off">
            </div>
            <div class="ghm-form-field">
              <label>Password *</label>
              <input type="password" name="password" required placeholder="Min. 8 characters" autocomplete="new-password">
            </div>` : ''}
            <div class="ghm-form-field">
              <label>Position</label>
              <input type="text" name="position" placeholder="Front Desk, Housekeeping…">
            </div>
            <div class="ghm-form-field">
              <label>Department</label>
              <input type="text" name="department" placeholder="Reception, F&amp;B, Management…">
            </div>
            <div class="ghm-form-field">
              <label>Shift</label>
              <select name="shift">
                <option value="morning">Morning</option>
                <option value="afternoon">Afternoon</option>
                <option value="night">Night</option>
                <option value="flexible">Flexible</option>
              </select>
            </div>
            <div class="ghm-form-field">
              <label>Role</label>
              <select name="role">
                <option value="ghm_staff">Staff (limited access)</option>
                <option value="ghm_manager">Manager (full access)</option>
              </select>
            </div>
            <div class="ghm-form-field">
              <label>Hire Date</label>
              <input type="date" name="hire_date">
            </div>
          </div>
        </div>`;

      const foot = `
        <button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>
        <button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">${isNew?'Add':'Update'} Staff Member</button>`;

      self.openModal(isNew?'Add Staff Member':'Edit Staff Member', body, foot);

      document.getElementById('ghm-btn-cancel').addEventListener('click', ()=>self.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click', ()=>{
        const $btn = $('#ghm-btn-submit');
        const data = self.collectForm();
        data.id    = id;

        const reqFields = isNew ? ['first_name','last_name','email','username','password'] : ['first_name','last_name','email'];
        let ok = true;
        reqFields.forEach(f=>{
          const el = document.querySelector(`#ghm-active-modal [name="${f}"]`);
          if (!el || !el.value.trim()) { if(el) el.style.borderColor='#ef4444'; ok=false; }
          else if(el) el.style.borderColor='';
        });
        if (!ok) { self.toast('Please fill in all required fields.','error'); return; }

        self.btnBusy($btn,'Saving');
        self.post('ghm_save_staff', data)
          .then(()=>{ self.toast(isNew?'Staff member added!':'Staff updated!','success'); self.closeModal(); setTimeout(()=>location.reload(),800); })
          .catch(err=>{ self.toast(err||'Save failed.','error'); self.btnReset($btn); });
      });
    },

    /* ================================================================
       REPORTS
    ================================================================ */
    initReports() {
      const raw = document.getElementById('ghm-chart-data');
      if (raw && raw.value) { try { this.renderBar('ghm-revenue-chart', JSON.parse(raw.value)); } catch(e){} }
      this.post('ghm_get_chart_data',{chart:'status'}).then(d=>{ if(d&&d.length) this.renderDonut('ghm-status-chart',d,'status'); }).catch(()=>{});
      this.post('ghm_get_chart_data',{chart:'payment_methods'}).then(d=>{ if(d&&d.length) this.renderDonut('ghm-payment-chart',d,'method'); }).catch(()=>{});
    },

  }; // end GHM object

  window.GHM = GHM;
  $(document).ready(()=>GHM.init());

})(jQuery);
