<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-laptop"></span> Workspaces</h1>
    <?php if ( current_user_can('ghm_manage_rooms') ): ?>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-workspace">
      <span class="dashicons dashicons-plus-alt"></span> Add Workspace
    </button>
    <?php endif; ?>
  </div>

  <?php if ( empty($workspaces) ): ?>
  <div class="ghm-empty">
    <span class="dashicons dashicons-laptop"></span>
    <p>No workspaces found.</p>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-workspace">Add Your First Workspace</button>
  </div>
  <?php else: ?>
  <div class="ghm-cards-grid">
    <?php
    $statuses = GHM_Rooms::get_room_statuses();
    foreach ($workspaces as $ws):
      $amenities = json_decode($ws->amenities ?: '[]', true);
    ?>
    <div class="ghm-room-card">
      <span class="card-badge ghm-badge <?php echo esc_attr($ws->status); ?>"><?php echo $statuses[$ws->status] ?? $ws->status; ?></span>
      <div class="card-number"><?php echo strtoupper(esc_html($ws->type)); ?> · <?php echo esc_html($ws->room_number); ?></div>
      <div class="card-name"><?php echo esc_html($ws->name); ?></div>
      <div class="card-meta">
        <span><span class="dashicons dashicons-groups" style="font-size:14px;"></span><?php echo $ws->capacity; ?> persons</span>
        <span><span class="dashicons dashicons-clock" style="font-size:14px;"></span>per hour</span>
      </div>
      <?php $dp = GHM_Rooms::get_display_price($ws); ?>
      <div class="card-price">
        <?php echo get_option('ghm_currency_symbol','₦'); ?><?php echo number_format($dp['price'],2); ?>
        <small><?php echo esc_html($dp['unit']); ?></small>
      </div>
      <?php if (!empty($amenities)): ?>
      <div class="card-amenities">
        <?php foreach (array_slice($amenities,0,4) as $a): ?><span><?php echo esc_html($a); ?></span><?php endforeach; ?>
        <?php if(count($amenities)>4): ?><span>+<?php echo count($amenities)-4; ?> more</span><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="card-actions">
        <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-edit-workspace" data-id="<?php echo $ws->id; ?>">
          <span class="dashicons dashicons-edit"></span> Edit
        </button>
        <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-delete-workspace" data-id="<?php echo $ws->id; ?>">
          <span class="dashicons dashicons-trash"></span>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
