<?php if ( ! defined( 'ABSPATH' ) ) exit;
$deposits = GHM_Deposits::get_all( array('limit'=>50) );
$summary  = GHM_Deposits::get_summary();
$sym      = get_option('ghm_currency_symbol','₦');
$methods  = GHM_Payments::get_payment_methods();
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-lock"></span> Security Deposits</h1>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-collect-deposit">
      <span class="dashicons dashicons-plus-alt"></span> Collect Deposit
    </button>
  </div>

  <!-- Summary cards -->
  <div class="ghm-stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
    <div class="ghm-stat-card accent-warning">
      <div class="stat-label">🔒 Currently Held</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym);?>"><?php echo number_format($summary['total_held'],2);?></div>
      <div class="room-meta" style="margin-top:4px;font-size:12px;color:var(--ghm-muted);"><?php echo $summary['count_held'];?> deposit<?php echo $summary['count_held']!==1?'s':'';?></div>
    </div>
    <div class="ghm-stat-card accent-green">
      <div class="stat-label">✓ Refunded</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym);?>"><?php echo number_format($summary['total_refunded'],2);?></div>
    </div>
    <div class="ghm-stat-card accent-danger">
      <div class="stat-label">⚠ Forfeited (income)</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym);?>"><?php echo number_format($summary['total_forfeited'],2);?></div>
    </div>
  </div>

  <div class="ghm-table-wrap">
    <table class="ghm-table">
      <thead><tr><th>Booking</th><th>Guest</th><th>Room</th><th>Amount</th><th>Method</th><th>Collected</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($deposits)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ghm-muted);">No deposits recorded.</td></tr>
        <?php else: foreach ($deposits as $d):
          $status_badge = array('held'=>'pending','refunded'=>'available','forfeited'=>'danger');
        ?>
        <tr>
          <td style="color:var(--ghm-gold);font-size:12px;"><?php echo esc_html($d->booking_ref);?></td>
          <td><?php echo esc_html($d->customer_name);?></td>
          <td><?php echo esc_html($d->room_name);?> <span class="room-meta"><?php echo esc_html($d->room_number);?></span></td>
          <td><strong><?php echo $sym.number_format($d->amount,2);?></strong></td>
          <td><?php echo $methods[$d->method] ?? ucfirst($d->method);?></td>
          <td><?php echo $d->collected_at ? date('M j, Y',strtotime($d->collected_at)) : '—';?></td>
          <td>
            <span class="ghm-badge <?php echo $d->status==='held'?'reserved':($d->status==='refunded'?'available':'cancelled');?>">
              <?php echo ucfirst($d->status);?>
              <?php if ($d->status==='refunded' && $d->refunded_at): echo ' '.date('M j',strtotime($d->refunded_at)); endif;?>
            </span>
          </td>
          <td>
            <?php if ($d->status === 'held'): ?>
            <div style="display:flex;gap:6px;">
              <button class="ghm-btn ghm-btn-success ghm-btn-sm ghm-refund-deposit" data-id="<?php echo $d->id;?>">
                ↩ Refund
              </button>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-forfeit-deposit" data-id="<?php echo $d->id;?>">
                ✗ Forfeit
              </button>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function($){
  $(document)
    .on('click','#ghm-btn-collect-deposit', function(){
      const body = `
        <div class="ghm-form-section">
          <div class="ghm-form-grid">
            <div class="ghm-form-field span-2">
              <label>Booking Reference *</label>
              <input type="text" name="booking_ref" required placeholder="GHM-XXXXXX-XXXXXXXX" style="text-transform:uppercase;">
            </div>
            <div class="ghm-form-field">
              <label>Deposit Amount (${ghmAdmin.currency_symbol}) *</label>
              <input type="number" name="amount" step="0.01" min="0.01" required placeholder="5000.00">
            </div>
            <div class="ghm-form-field">
              <label>Payment Method</label>
              <select name="method">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="mobile_money">Mobile Money</option>
              </select>
            </div>
            <div class="ghm-form-field span-2">
              <label>Transaction ID / Reference (optional)</label>
              <input type="text" name="transaction_id" placeholder="Optional">
            </div>
            <div class="ghm-form-field span-2">
              <label>Notes</label>
              <textarea name="notes" rows="2"></textarea>
            </div>
          </div>
        </div>`;
      GHM.openModal('Collect Security Deposit', body,
        '<button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>'+
        '<button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">Collect Deposit</button>');
      document.getElementById('ghm-btn-cancel').addEventListener('click',()=>GHM.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click',()=>{
        const data = GHM.collectForm();
        if (!data.booking_ref||!data.amount){ GHM.toast('Booking ref and amount required.','error'); return; }
        GHM.btnBusy($('#ghm-btn-submit'),'Saving');
        GHM.post('ghm_collect_deposit',data)
          .then(()=>{ GHM.toast('Deposit collected!','success'); GHM.closeModal(); setTimeout(()=>location.reload(),700); })
          .catch(e=>{ GHM.toast(e,'error'); });
      });
    })
    .on('click','.ghm-refund-deposit', function(){
      const id = $(this).data('id');
      if (!confirm('Refund this deposit to the guest?')) return;
      GHM.post('ghm_refund_deposit',{id}).then(()=>{ GHM.toast('Deposit refunded!','success'); setTimeout(()=>location.reload(),700); }).catch(e=>GHM.toast(e,'error'));
    })
    .on('click','.ghm-forfeit-deposit', function(){
      const id = $(this).data('id');
      const reason = prompt('Reason for forfeiting deposit:');
      if (!reason) return;
      GHM.post('ghm_forfeit_deposit',{id,reason}).then(()=>{ GHM.toast('Deposit forfeited and recorded as income.','success'); setTimeout(()=>location.reload(),700); }).catch(e=>GHM.toast(e,'error'));
    });
})(jQuery);
</script>
