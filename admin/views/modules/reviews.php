<?php if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$filter  = sanitize_key($_GET['status'] ?? 'pending');
$reviews = $wpdb->get_results($wpdb->prepare(
    "SELECT rv.*, b.booking_ref, CONCAT(c.first_name,' ',c.last_name) AS guest_name,
     r.name AS room_name FROM {$wpdb->prefix}ghm_reviews rv
     LEFT JOIN {$wpdb->prefix}ghm_bookings b  ON b.id  = rv.booking_id
     LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id  = rv.customer_id
     LEFT JOIN {$wpdb->prefix}ghm_rooms r     ON r.id  = b.room_id
     WHERE rv.status = %s ORDER BY rv.created_at DESC LIMIT 50", $filter
));
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-star-filled"></span> Guest Reviews</h1>
  </div>
  <div class="ghm-filter-tabs" style="margin-bottom:20px;">
    <a href="?page=ghm-reviews&status=pending"  class="<?php echo $filter==='pending'?'active':'';?>">Pending</a>
    <a href="?page=ghm-reviews&status=approved" class="<?php echo $filter==='approved'?'active':'';?>">Approved</a>
  </div>
  <?php if (empty($reviews)): ?>
  <div class="ghm-empty"><span class="dashicons dashicons-star-filled"></span><p>No <?php echo $filter;?> reviews.</p></div>
  <?php else: foreach ($reviews as $rv): ?>
  <div class="ghm-form-section" style="margin-bottom:14px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-size:15px;font-weight:600;color:var(--ghm-text);"><?php echo esc_html($rv->title ?: 'Untitled Review');?></div>
        <div style="font-size:12px;color:var(--ghm-muted);margin-top:2px;">
          <?php echo esc_html($rv->guest_name);?> &middot;
          <?php echo esc_html($rv->room_name);?> &middot;
          <?php echo date('M j, Y',strtotime($rv->created_at));?>
          <span style="color:var(--ghm-gold);margin-left:8px;"><?php echo str_repeat('★',$rv->rating).str_repeat('☆',5-$rv->rating);?></span>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <?php if ($rv->status==='pending'): ?>
        <button class="ghm-btn ghm-btn-success ghm-btn-sm ghm-approve-review" data-id="<?php echo $rv->id;?>">✓ Approve</button>
        <?php endif;?>
        <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-delete-review" data-id="<?php echo $rv->id;?>"><span class="dashicons dashicons-trash"></span></button>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0;">
      <?php foreach(['cleanliness'=>'🧹','service'=>'😊','comfort'=>'🛏️','value'=>'💰'] as $key=>$icon): if($rv->$key): ?>
      <div style="background:var(--ghm-surface2);border-radius:6px;padding:8px;text-align:center;font-size:12px;">
        <div><?php echo $icon;?></div>
        <div style="color:var(--ghm-muted);margin-top:2px;"><?php echo ucfirst($key);?></div>
        <div style="color:var(--ghm-gold);font-weight:700;"><?php echo $rv->$key;?>/5</div>
      </div>
      <?php endif; endforeach;?>
    </div>
    <?php if ($rv->comment): ?>
    <p style="font-size:14px;color:var(--ghm-text);line-height:1.6;border-top:1px solid var(--ghm-border);padding-top:10px;margin:0;">
      &ldquo;<?php echo nl2br(esc_html($rv->comment));?>&rdquo;
    </p>
    <?php endif;?>
  </div>
  <?php endforeach; endif;?>
</div>
<script>
(function($){
  $(document)
    .on('click','.ghm-approve-review',function(){
      GHM.post('ghm_approve_review',{id:$(this).data('id')}).then(()=>{GHM.toast('Review approved!','success');setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
    })
    .on('click','.ghm-delete-review',function(){
      if(!confirm('Delete this review?')) return;
      GHM.post('ghm_delete_review',{id:$(this).data('id')}).then(()=>{GHM.toast('Deleted.','success');setTimeout(()=>location.reload(),700);}).catch(e=>GHM.toast(e,'error'));
    });
})(jQuery);
</script>
