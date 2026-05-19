<?php if ( ! defined( 'ABSPATH' ) ) exit;
$discounts = GHM_Discounts::get_all();
$sym       = get_option('ghm_currency_symbol','₦');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-tag"></span> Discount Codes</h1>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-discount">
      <span class="dashicons dashicons-plus-alt"></span> Create Code
    </button>
  </div>

  <div class="ghm-table-wrap">
    <table class="ghm-table">
      <thead><tr><th>Code</th><th>Type / Value</th><th>Min Amount</th><th>Uses</th><th>Valid From</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($discounts)):?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ghm-muted);">No discount codes yet.</td></tr>
        <?php else: foreach($discounts as $d):?>
        <tr>
          <td><code style="background:rgba(201,168,76,.12);color:var(--ghm-gold);padding:3px 10px;border-radius:5px;font-size:13px;letter-spacing:1px;"><?php echo esc_html($d->code);?></code></td>
          <td><?php echo $d->type==='percent' ? $d->value.'%' : $sym.number_format($d->value,2).' off';?></td>
          <td><?php echo $d->min_amount>0 ? $sym.number_format($d->min_amount,2) : '—';?></td>
          <td><?php echo $d->used_count; if($d->max_uses) echo '/'.$d->max_uses;?></td>
          <td><?php echo $d->valid_from  ? date('M j, Y',strtotime($d->valid_from))  : '—';?></td>
          <td><?php echo $d->valid_until ? date('M j, Y',strtotime($d->valid_until)) : '—';?></td>
          <td><span class="ghm-badge <?php echo $d->status==='active'?'available':'cancelled';?>"><?php echo ucfirst($d->status);?></span></td>
          <td>
            <div style="display:flex;gap:6px;">
              <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-disc-toggle" data-id="<?php echo $d->id;?>" data-status="<?php echo $d->status;?>">
                <?php echo $d->status==='active'?'Deactivate':'Activate';?>
              </button>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-disc-delete" data-id="<?php echo $d->id;?>"><span class="dashicons dashicons-trash"></span></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function($){
  $(document)
    .on('click','#ghm-btn-add-discount',function(){
      const body=`
        <div class="ghm-form-section">
          <div class="ghm-form-grid">
            <div class="ghm-form-field"><label>Code * (auto-uppercase)</label><input type="text" name="code" required placeholder="SUMMER20" style="text-transform:uppercase;"></div>
            <div class="ghm-form-field"><label>Type</label>
              <select name="type"><option value="percent">Percentage (%)</option><option value="fixed">Fixed Amount</option></select>
            </div>
            <div class="ghm-form-field"><label>Value *</label><input type="number" name="value" step="0.01" min="0" required placeholder="20"></div>
            <div class="ghm-form-field"><label>Minimum Booking Amount</label><input type="number" name="min_amount" step="0.01" min="0" placeholder="0"></div>
            <div class="ghm-form-field"><label>Max Uses (blank = unlimited)</label><input type="number" name="max_uses" min="1" placeholder="Unlimited"></div>
            <div class="ghm-form-field"><label>Valid From</label><input type="date" name="valid_from"></div>
            <div class="ghm-form-field"><label>Valid Until</label><input type="date" name="valid_until"></div>
            <div class="ghm-form-field span-2"><label>Description / Notes</label><input type="text" name="description" placeholder="e.g. Summer promotion 2025"></div>
          </div>
        </div>`;
      GHM.openModal('Create Discount Code',body,
        '<button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>'+
        '<button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">Create Code</button>');
      document.getElementById('ghm-btn-cancel').addEventListener('click',()=>GHM.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click',()=>{
        const data=GHM.collectForm();
        if(!data.code||!data.value){GHM.toast('Code and value are required.','error');return;}
        GHM.btnBusy($('#ghm-btn-submit'),'Creating');
        GHM.post('ghm_save_discount',data).then(()=>{GHM.toast('Code created!','success');GHM.closeModal();setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
      });
    })
    .on('click','.ghm-disc-toggle',function(){
      const id=$(this).data('id'), st=$(this).data('status');
      GHM.post('ghm_save_discount',{id,status:st==='active'?'inactive':'active'}).then(()=>{GHM.toast('Updated.','success');setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
    })
    .on('click','.ghm-disc-delete',function(){
      if(!confirm('Delete this discount code?')) return;
      GHM.post('ghm_delete_discount',{id:$(this).data('id')}).then(()=>{GHM.toast('Deleted.','success');setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
    });
})(jQuery);
</script>
