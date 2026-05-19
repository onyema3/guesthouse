<?php if ( ! defined( 'ABSPATH' ) ) exit;
$from    = sanitize_text_field($_GET['from'] ?? date('Y-m-01'));
$to      = sanitize_text_field($_GET['to']   ?? date('Y-m-t'));
$summary = GHM_Activity_Report::get_summary_by_user($from, $to);
$log     = GHM_Activity_Report::get_report(array('from'=>$from,'to'=>$to,'limit'=>100));

$action_icons = array(
    'created_booking'=>'📅','updated_booking'=>'✏️','cancelled_booking'=>'❌',
    'checked_in_booking'=>'🏨','checked_out_booking'=>'👋','auto_confirmed_booking'=>'✅',
    'created_room'=>'🛏️','updated_room'=>'✏️','deleted_room'=>'🗑️',
    'collected_deposit'=>'🔒','refunded_deposit'=>'↩️','forfeited_deposit'=>'⚠️',
    'created_customer'=>'👤','updated_customer'=>'✏️',
    'whatsapp_sent'=>'💬','payment_recorded'=>'💳',
);
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-list-view"></span> Staff Activity Report</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="page" value="ghm-activity">
        <input type="date" name="from" value="<?php echo esc_attr($from);?>"
          style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:7px 10px;color:var(--ghm-text);font-size:13px;">
        <span style="color:var(--ghm-muted);">to</span>
        <input type="date" name="to" value="<?php echo esc_attr($to);?>"
          style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:7px;padding:7px 10px;color:var(--ghm-text);font-size:13px;">
        <button type="submit" class="ghm-btn ghm-btn-outline ghm-btn-sm">Filter</button>
      </form>
      <?php
      $act_csv   = wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=activity&from='.urlencode($from).'&to='.urlencode($to)),'ghm_export');
      $act_print = wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=activity&ghm_export_format=print&from='.urlencode($from).'&to='.urlencode($to)),'ghm_export');
      ?>
      <a href="<?php echo esc_url($act_csv);?>" class="ghm-btn ghm-btn-outline ghm-btn-sm">⬇ Export CSV</a>
      <a href="<?php echo esc_url($act_print);?>" target="_blank" class="ghm-btn ghm-btn-outline ghm-btn-sm">🖨 Print Report</a>
    </div>
  </div>

  <!-- Per-user summary -->
  <?php if (!empty($summary)): ?>
  <div class="ghm-form-section" style="margin-bottom:20px;">
    <p class="ghm-form-section-title">Staff Performance Summary</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
      <?php foreach ($summary as $s): ?>
      <div style="background:var(--ghm-surface2);border:1px solid var(--ghm-border);border-radius:8px;padding:14px 16px;">
        <div style="font-size:14px;font-weight:600;color:var(--ghm-text);margin-bottom:8px;">
          <?php echo esc_html($s->display_name ?? 'System');?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
          <span style="color:var(--ghm-muted);">Total Actions</span>
          <span style="color:var(--ghm-gold);font-weight:700;"><?php echo $s->total_actions;?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
          <span style="color:var(--ghm-muted);">Bookings</span>
          <span><?php echo $s->booking_actions;?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;">
          <span style="color:var(--ghm-muted);">Payments</span>
          <span><?php echo $s->payment_actions;?></span>
        </div>
        <div style="font-size:11px;color:var(--ghm-muted);">Last active: <?php echo $s->last_active ? human_time_diff(strtotime($s->last_active)).' ago' : '—';?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Detailed log -->
  <div class="ghm-table-wrap">
    <table class="ghm-table">
      <thead><tr><th>Time</th><th>Staff Member</th><th>Action</th><th>Object</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (empty($log)): ?>
        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--ghm-muted);">No activity in this period.</td></tr>
        <?php else: foreach ($log as $entry):
          $icon = $action_icons[$entry->action] ?? '📝';
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:12px;color:var(--ghm-muted);">
            <?php echo date('M j, H:i',strtotime($entry->created_at));?>
          </td>
          <td><?php echo esc_html($entry->display_name ?? 'System');?></td>
          <td>
            <span style="margin-right:6px;"><?php echo $icon;?></span>
            <?php echo esc_html(ucwords(str_replace('_',' ',$entry->action)));?>
          </td>
          <td>
            <?php if ($entry->object_type && $entry->object_id): ?>
            <span style="font-size:12px;color:var(--ghm-muted);"><?php echo ucfirst($entry->object_type);?> #<?php echo $entry->object_id;?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:11px;color:var(--ghm-muted);"><?php echo esc_html($entry->ip_address ?? '');?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
