<?php if ( ! defined( 'ABSPATH' ) ) exit;
$waitlist = GHM_Waitlist::get_all();
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-clock"></span> Waiting List</h1>
  </div>
  <div class="ghm-notice info" style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgba(96,165,250,.08);border-left:3px solid var(--ghm-info);border-radius:7px;margin-bottom:18px;font-size:13px;color:var(--ghm-text);">
    ℹ️ Guests are automatically notified via WhatsApp &amp; email when a room they are waiting for becomes available through a cancellation.
  </div>
  <div class="ghm-table-wrap">
    <table class="ghm-table">
      <thead><tr><th>Guest</th><th>Email</th><th>Phone</th><th>Room</th><th>Dates</th><th>Adults</th><th>Notified</th><th>Joined</th></tr></thead>
      <tbody>
        <?php if(empty($waitlist)):?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ghm-muted);">No guests on the waiting list.</td></tr>
        <?php else: foreach($waitlist as $w):?>
        <tr>
          <td><strong><?php echo esc_html($w->first_name.' '.$w->last_name);?></strong></td>
          <td><?php echo esc_html($w->email);?></td>
          <td><?php echo esc_html($w->phone?:'—');?></td>
          <td><?php echo esc_html($w->room_name);?> <small style="color:var(--ghm-muted)">(<?php echo esc_html($w->room_number);?>)</small></td>
          <td><?php echo date('M j',strtotime($w->check_in)).' → '.date('M j, Y',strtotime($w->check_out));?></td>
          <td><?php echo $w->adults;?></td>
          <td><?php echo $w->notified_at ? '<span class="ghm-badge available">✓ Notified '.date('M j',strtotime($w->notified_at)).'</span>' : '<span class="ghm-badge unpaid">Waiting</span>';?></td>
          <td><?php echo date('M j, Y',strtotime($w->created_at));?></td>
        </tr>
        <?php endforeach;endif;?>
      </tbody>
    </table>
  </div>
</div>
