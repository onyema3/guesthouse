<?php if ( ! defined( 'ABSPATH' ) ) exit;
$statuses   = GHM_Maintenance::get_statuses();
$priorities = GHM_Maintenance::get_priorities();
$categories = GHM_Maintenance::get_categories();
$rooms      = GHM_Rooms::get_rooms(array('limit'=>200));
$requests   = GHM_Maintenance::get_requests(array(
    'status' => sanitize_key($_GET['status'] ?? ''),
    'limit'  => 50,
));
$priority_colors = array('urgent'=>'#ef4444','high'=>'#f59e0b','normal'=>'#60a5fa','low'=>'#7a7f96');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-admin-tools"></span> Maintenance Requests</h1>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-maintenance">
      <span class="dashicons dashicons-plus-alt"></span> Log Issue
    </button>
  </div>

  <div class="ghm-toolbar">
    <div class="ghm-filter-tabs">
      <a href="?page=ghm-maintenance" class="<?php echo empty($_GET['status'])?'active':''; ?>">All</a>
      <?php foreach ($statuses as $k=>$v): ?>
      <a href="?page=ghm-maintenance&status=<?php echo $k;?>" class="<?php echo ($_GET['status']??'')===$k?'active':''; ?>"><?php echo $v;?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div class="ghm-empty"><span class="dashicons dashicons-admin-tools"></span><p>No maintenance requests found.</p></div>
  <?php else: ?>
  <div class="ghm-table-wrap">
    <table class="ghm-table" id="ghm-maint-table">
      <thead><tr><th>Priority</th><th>Room</th><th>Issue</th><th>Category</th><th>Assigned To</th><th>Status</th><th>Reported</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($requests as $r):
          $pcolor = $priority_colors[$r->priority] ?? '#7a7f96';
        ?>
        <tr>
          <td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $pcolor;?>;margin-right:6px;"></span><?php echo esc_html(ucfirst($r->priority));?></td>
          <td><div class="room-name"><?php echo esc_html($r->room_name);?></div><div class="room-meta"><?php echo esc_html($r->room_number);?></div></td>
          <td><strong><?php echo esc_html($r->title);?></strong><?php if($r->description):?><div class="room-meta"><?php echo esc_html(substr($r->description,0,60));?></div><?php endif;?></td>
          <td><?php echo $r->category ? ucfirst($r->category) : '—';?></td>
          <td><?php echo $r->assigned_name ? esc_html($r->assigned_name) : '<span style="color:var(--ghm-muted)">Unassigned</span>';?></td>
          <td><span class="ghm-badge <?php echo $r->status==='resolved'?'available':($r->status==='in_progress'?'checked_in':'pending');?>"><?php echo $statuses[$r->status]??$r->status;?></span></td>
          <td><?php echo date('M j, Y', strtotime($r->created_at));?></td>
          <td>
            <div style="display:flex;gap:6px;">
              <?php if($r->status!=='resolved'):?>
              <button class="ghm-btn ghm-btn-success ghm-btn-sm ghm-maint-resolve" data-id="<?php echo $r->id;?>">✓ Resolve</button>
              <?php endif;?>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-maint-delete" data-id="<?php echo $r->id;?>"><span class="dashicons dashicons-trash"></span></button>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div>

<script>
(function($){
  $(document)
    .on('click','#ghm-btn-add-maintenance',function(){
      const roomOpts = <?php
        $opts = array();
        foreach ($rooms as $r) {
            $opts[] = array('id' => $r->id, 'name' => $r->name.' ('.$r->room_number.')');
        }
        echo wp_json_encode($opts);
      ?>;
      const cats     = <?php echo json_encode($categories);?>;
      const body = `
        <div class="ghm-form-section">
          <div class="ghm-form-grid">
            <div class="ghm-form-field span-2"><label>Issue Title *</label><input type="text" name="title" required placeholder="e.g. Broken AC in Room 101"></div>
            <div class="ghm-form-field"><label>Room *</label>
              <select name="room_id" required>
                <option value="">— Select —</option>
                ${roomOpts.map(r=>`<option value="${r.id}">${GHM._esc?GHM._esc(r.name):r.name}</option>`).join('')}
              </select>
            </div>
            <div class="ghm-form-field"><label>Category</label>
              <select name="category"><option value="">— Select —</option>${cats.map(c=>`<option value="${c}">${c.charAt(0).toUpperCase()+c.slice(1)}</option>`).join('')}</select>
            </div>
            <div class="ghm-form-field"><label>Priority</label>
              <select name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select>
            </div>
            <div class="ghm-form-field"><label>Status</label>
              <select name="status"><option value="open">Open</option><option value="in_progress">In Progress</option></select>
            </div>
            <div class="ghm-form-field span-2"><label>Description</label><textarea name="description" rows="3" placeholder="Describe the issue…"></textarea></div>
          </div>
        </div>`;
      GHM.openModal('Log Maintenance Issue', body,
        '<button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>' +
        '<button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">Log Issue</button>');
      document.getElementById('ghm-btn-cancel').addEventListener('click',()=>GHM.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click',()=>{
        const data = GHM.collectForm();
        if(!data.title||!data.room_id){GHM.toast('Title and room are required.','error');return;}
        GHM.btnBusy($('#ghm-btn-submit'),'Saving');
        GHM.post('ghm_save_maintenance',data).then(()=>{GHM.toast('Issue logged.','success');GHM.closeModal();setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
      });
    })
    .on('click','.ghm-maint-resolve',function(){
      if(!confirm('Mark as resolved?')) return;
      GHM.post('ghm_save_maintenance',{id:$(this).data('id'),status:'resolved'}).then(()=>{GHM.toast('Resolved!','success');setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
    })
    .on('click','.ghm-maint-delete',function(){
      if(!confirm('Delete this request?')) return;
      GHM.post('ghm_delete_maintenance',{id:$(this).data('id')}).then(()=>{GHM.toast('Deleted.','success');setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
    });
})(jQuery);
</script>
