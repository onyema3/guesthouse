<?php if ( ! defined( 'ABSPATH' ) ) exit;
$rules = GHM_Dynamic_Pricing::get_rules();
$rooms = GHM_Rooms::get_rooms( array('limit'=>200) );
$sym   = get_option('ghm_currency_symbol','₦');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-tag"></span> Dynamic Pricing Rules</h1>
    <div style="display:flex;gap:10px;align-items:center;">
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ghm-text);">
        <input type="checkbox" id="ghm-dp-toggle"
          <?php checked(get_option('ghm_dynamic_pricing_enabled',0),1); ?>
          onchange="jQuery.post(ghmAdmin.ajax_url,{action:'ghm_toggle_dynamic_pricing',nonce:ghmAdmin.nonce,val:this.checked?1:0},function(r){GHM.toast(r.success?'Saved!':'Error','info');});">
        Enable Dynamic Pricing
      </label>
      <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-rule">
        <span class="dashicons dashicons-plus-alt"></span> Add Rule
      </button>
    </div>
  </div>

  <div class="ghm-notice info" style="padding:12px 16px;background:rgba(96,165,250,.08);border-left:3px solid var(--ghm-info);border-radius:7px;margin-bottom:20px;font-size:13px;">
    ℹ️ Rules are applied on top of the base room price. Higher priority rules take precedence. Percentage rules stack multiplicatively.
  </div>

  <?php if (empty($rules)): ?>
  <div class="ghm-empty">
    <span class="dashicons dashicons-tag"></span>
    <p>No pricing rules yet. Add rules for weekends, holidays, or peak seasons.</p>
  </div>
  <?php else: ?>
  <div class="ghm-table-wrap">
    <table class="ghm-table">
      <thead><tr><th>Name</th><th>When</th><th>Rooms</th><th>Adjustment</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rules as $rule):
          $days_map = array('1'=>'Mon','2'=>'Tue','3'=>'Wed','4'=>'Thu','5'=>'Fri','6'=>'Sat','7'=>'Sun');
          $dow_label = '';
          if ($rule->days_of_week) {
              $days = array_map(function($d){return $days_map[$d]??$d;}, explode(',',$rule->days_of_week));
              $dow_label = implode(', ',$days);
          }
        ?>
        <tr>
          <td><strong><?php echo esc_html($rule->name);?></strong></td>
          <td>
            <?php if ($rule->date_from || $rule->date_to): ?>
            <div><?php echo esc_html($rule->date_from??'Any');?> → <?php echo esc_html($rule->date_to??'Any');?></div>
            <?php endif; ?>
            <?php if ($dow_label): ?>
            <div class="room-meta"><?php echo esc_html($dow_label);?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php
            if ($rule->room_ids) {
                $ids   = json_decode($rule->room_ids,true);
                $names = array_filter(array_map(function($id){ $r=GHM_Rooms::get_room($id); return $r?$r->name:null; },(array)$ids));
                echo esc_html(implode(', ',array_slice($names,0,2)));
                if (count($names)>2) echo ' +'.( count($names)-2 ).' more';
            } elseif ($rule->room_types) {
                echo esc_html(implode(', ',json_decode($rule->room_types,true)));
            } else {
                echo '<span style="color:var(--ghm-muted)">All rooms</span>';
            }
            ?>
          </td>
          <td>
            <?php $sign = $rule->adjustment >= 0 ? '+' : ''; ?>
            <strong style="color:<?php echo $rule->adjustment>=0?'var(--ghm-success)':'var(--ghm-danger)';?>">
              <?php echo $sign.($rule->adj_type==='percent' ? $rule->adjustment.'%' : $sym.number_format(abs($rule->adjustment),2));?>
            </strong>
          </td>
          <td><?php echo $rule->priority;?></td>
          <td><span class="ghm-badge <?php echo $rule->status==='active'?'available':'cancelled';?>"><?php echo ucfirst($rule->status);?></span></td>
          <td>
            <div style="display:flex;gap:6px;">
              <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-edit-rule" data-id="<?php echo $rule->id;?>"
                data-rule='<?php echo esc_attr(json_encode($rule));?>'>Edit</button>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-delete-rule" data-id="<?php echo $rule->id;?>">
                <span class="dashicons dashicons-trash"></span>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function($){
  const roomOpts = <?php
    $opts = array();
    foreach ($rooms as $r) {
        $opts[] = array('id' => $r->id, 'name' => $r->name.' ('.$r->room_number.')');
    }
    echo wp_json_encode($opts);
  ?>;
  const typeOpts = ['room','suite','apartment','workspace','hall'];

  function openModal(rule) {
    const r   = rule || {};
    const ids = r.room_ids ? JSON.parse(r.room_ids) : [];
    const types = r.room_types ? JSON.parse(r.room_types) : [];
    const days  = r.days_of_week ? r.days_of_week.split(',') : [];

    const body = `
      <div class="ghm-form-section">
        <div class="ghm-form-grid">
          <div class="ghm-form-field span-2"><label>Rule Name *</label>
            <input type="text" name="name" value="${GHM._esc?GHM._esc(r.name||''):(r.name||'')}" required placeholder="e.g. Weekend Surcharge, Peak Season, Public Holiday"></div>
          <div class="ghm-form-field"><label>Date From</label><input type="date" name="date_from" value="${r.date_from||''}"></div>
          <div class="ghm-form-field"><label>Date To</label><input type="date" name="date_to" value="${r.date_to||''}"></div>
          <div class="ghm-form-field span-2"><label>Days of Week (leave blank = all days)</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
              ${['1:Mon','2:Tue','3:Wed','4:Thu','5:Fri','6:Sat','7:Sun'].map(d=>{
                const [v,l]=d.split(':');
                return `<label class="ghm-amenity-check"><input type="checkbox" name="days_of_week[]" value="${v}" ${days.includes(v)?'checked':''}> ${l}</label>`;
              }).join('')}
            </div>
          </div>
          <div class="ghm-form-field"><label>Adjustment Type</label>
            <select name="adj_type">
              <option value="percent" ${r.adj_type==='percent'||!r.adj_type?'selected':''}>Percentage (%)</option>
              <option value="fixed"   ${r.adj_type==='fixed'?'selected':''}>Fixed Amount (${ghmAdmin.currency_symbol})</option>
            </select></div>
          <div class="ghm-form-field"><label>Adjustment Value (use negative for discount)</label>
            <input type="number" name="adjustment" step="0.01" value="${r.adjustment||0}" placeholder="e.g. 20 for +20% or -10 for -10%"></div>
          <div class="ghm-form-field"><label>Apply to Room Types</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
              ${typeOpts.map(t=>`<label class="ghm-amenity-check"><input type="checkbox" name="room_types[]" value="${t}" ${types.includes(t)?'checked':''}> ${t.charAt(0).toUpperCase()+t.slice(1)}</label>`).join('')}
            </div>
          </div>
          <div class="ghm-form-field"><label>Priority (higher = applied first)</label>
            <input type="number" name="priority" value="${r.priority||0}" min="0"></div>
          <div class="ghm-form-field"><label>Status</label>
            <select name="status">
              <option value="active"   ${r.status==='active'||!r.status?'selected':''}>Active</option>
              <option value="inactive" ${r.status==='inactive'?'selected':''}>Inactive</option>
            </select></div>
        </div>
      </div>`;

    GHM.openModal((r.id?'Edit':'Add')+' Pricing Rule', body,
      '<button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>'+
      '<button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">'+(r.id?'Update':'Create')+'</button>');

    document.getElementById('ghm-btn-cancel').addEventListener('click',()=>GHM.closeModal());
    document.getElementById('ghm-btn-submit').addEventListener('click',()=>{
      const data = GHM.collectForm();
      data.id = r.id || 0;
      if (!data.name) { GHM.toast('Rule name is required.','error'); return; }
      GHM.btnBusy($('#ghm-btn-submit'),'Saving');
      GHM.post('ghm_save_pricing_rule',data)
        .then(()=>{ GHM.toast('Rule saved!','success'); GHM.closeModal(); setTimeout(()=>location.reload(),700); })
        .catch(e=>GHM.toast(e,'error'));
    });
  }

  $(document)
    .on('click','#ghm-btn-add-rule', ()=>openModal(null))
    .on('click','.ghm-edit-rule',   function(){ openModal(JSON.parse($(this).attr('data-rule'))); })
    .on('click','.ghm-delete-rule', function(){
      if (!confirm('Delete this rule?')) return;
      GHM.post('ghm_delete_pricing_rule',{id:$(this).data('id')})
        .then(()=>{ GHM.toast('Deleted.','success'); setTimeout(()=>location.reload(),700); }).catch(e=>GHM.toast(e,'error'));
    });
})(jQuery);
</script>
