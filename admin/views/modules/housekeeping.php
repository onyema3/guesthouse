<?php if ( ! defined( 'ABSPATH' ) ) exit;
$statuses   = GHM_Housekeeping::get_statuses();
$priorities = GHM_Housekeeping::get_priorities();
$all_rooms  = GHM_Rooms::get_rooms( array('limit'=>200) );
$hk_records = GHM_Housekeeping::get_all();
$hk_map     = array();
foreach ( $hk_records as $h ) $hk_map[$h->room_id] = $h;
$staff_list = GHM_Staff::get_staff( array('limit'=>50) );
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-yes"></span> Housekeeping Board</h1>
    <div style="display:flex;gap:8px;align-items:center;">
      <span style="font-size:12px;color:var(--ghm-muted);">Live status — auto-refreshes every 60s</span>
      <button class="ghm-btn ghm-btn-outline ghm-btn-sm" onclick="location.reload()">↻ Refresh</button>
    </div>
  </div>

  <!-- Status summary bar -->
  <div class="ghm-stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px;">
    <?php foreach ( $statuses as $key => $s ):
      $count = count( array_filter($hk_records, function($h){return $h->status === $key;}) );
    ?>
    <div class="ghm-stat-card" style="border-top-color:<?php echo $s['color']; ?>;">
      <div class="stat-label"><?php echo $s['icon']; ?> <?php echo $s['label']; ?></div>
      <div class="stat-value" style="color:<?php echo $s['color']; ?>;font-size:28px;"><?php echo $count; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Board columns -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
    <?php foreach ( $all_rooms as $room ):
      $hk     = $hk_map[$room->id] ?? null;
      $status = $hk ? $hk->status : 'clean';
      $s_info = $statuses[$status] ?? $statuses['clean'];
      $prio   = $hk ? $hk->priority : 'normal';
    ?>
    <div class="ghm-room-card" style="border-left:4px solid <?php echo $s_info['color']; ?>;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
        <div>
          <div class="card-number"><?php echo esc_html($room->room_number); ?> · Floor <?php echo esc_html($room->floor); ?></div>
          <div class="card-name"><?php echo esc_html($room->name); ?></div>
        </div>
        <span class="ghm-badge" style="background:<?php echo $s_info['color']; ?>22;color:<?php echo $s_info['color']; ?>;">
          <?php echo $s_info['icon']; ?> <?php echo $s_info['label']; ?>
        </span>
      </div>

      <?php if ($hk && $hk->assigned_name): ?>
      <div style="font-size:12px;color:var(--ghm-muted);margin-bottom:8px;">
        👤 <?php echo esc_html($hk->assigned_name); ?>
        <?php if ($prio === 'high' || $prio === 'urgent'): ?>
        <span style="color:var(--ghm-warning);font-weight:600;margin-left:6px;">⚡ <?php echo ucfirst($prio); ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($hk && $hk->notes): ?>
      <div style="font-size:12px;color:var(--ghm-muted);margin-bottom:10px;font-style:italic;">
        "<?php echo esc_html(substr($hk->notes,0,60)); ?><?php echo strlen($hk->notes)>60?'…':''; ?>"
      </div>
      <?php endif; ?>

      <!-- Quick status update buttons -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php foreach ( $statuses as $sk => $si ):
          $active = $status === $sk;
        ?>
        <button class="ghm-btn ghm-btn-sm ghm-hk-status-btn"
          style="<?php echo $active ? "background:{$si['color']};color:#fff;" : "background:var(--ghm-surface2);color:var(--ghm-muted);"; ?>"
          data-room="<?php echo $room->id; ?>"
          data-status="<?php echo $sk; ?>">
          <?php echo $si['icon']; ?> <?php echo $si['label']; ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Assign staff -->
      <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
        <select class="ghm-hk-assign-select"
          data-room="<?php echo $room->id; ?>"
          style="flex:1;background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:6px;padding:5px 8px;color:var(--ghm-text);font-size:12px;">
          <option value="">Assign staff…</option>
          <?php foreach ($staff_list as $st): ?>
          <option value="<?php echo $st->wp_user_id; ?>"
            <?php selected($hk?$hk->assigned_to:0, $st->wp_user_id); ?>>
            <?php echo esc_html($st->display_name); ?>
          </option>
          <?php endforeach; ?>
        </select>
        <select class="ghm-hk-priority-select"
          data-room="<?php echo $room->id; ?>"
          style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:6px;padding:5px 8px;color:var(--ghm-text);font-size:12px;">
          <?php foreach ($priorities as $pk => $pl): ?>
          <option value="<?php echo $pk; ?>" <?php selected($prio,$pk); ?>><?php echo $pl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function($){
  function hkUpdate(roomId, data) {
    $.post(ghmAdmin.ajax_url, Object.assign({action:'ghm_hk_update', nonce:ghmAdmin.nonce, room_id:roomId}, data))
      .done(res => {
        if(res.success) GHM.toast('Updated!','success');
        else GHM.toast(res.data?.message||'Error','error');
      });
  }
  $(document)
    .on('click','.ghm-hk-status-btn', function(){
      hkUpdate($(this).data('room'), {status:$(this).data('status')});
      setTimeout(()=>location.reload(),600);
    })
    .on('change','.ghm-hk-assign-select', function(){
      hkUpdate($(this).data('room'), {assigned_to:$(this).val()});
    })
    .on('change','.ghm-hk-priority-select', function(){
      hkUpdate($(this).data('room'), {priority:$(this).val()});
    });
  // Auto-refresh every 60 seconds
  setTimeout(()=>location.reload(), 60000);
})(jQuery);
</script>
