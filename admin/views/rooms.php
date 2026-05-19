<?php if ( ! defined( 'ABSPATH' ) ) exit;
$types    = GHM_Rooms::get_room_types();
$statuses = GHM_Rooms::get_room_statuses();
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-admin-home"></span> Rooms</h1>
    <?php if ( current_user_can('ghm_manage_rooms') ): ?>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-room">
      <span class="dashicons dashicons-plus-alt"></span> Add Room
    </button>
    <?php endif; ?>
  </div>

  <div class="ghm-toolbar">
    <div class="ghm-search-box">
      <span class="dashicons dashicons-search"></span>
      <input type="text" id="ghm-room-search" placeholder="Search rooms…">
    </div>
    <div class="ghm-filter-tabs">
      <a href="?page=ghm-rooms" class="<?php echo empty($_GET['status'])?'active':''; ?>">All</a>
      <?php foreach ($statuses as $key => $label): ?>
      <a href="?page=ghm-rooms&status=<?php echo $key; ?>" class="<?php echo ($_GET['status']??'')===$key?'active':''; ?>"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ( empty($rooms) ): ?>
  <div class="ghm-empty">
    <span class="dashicons dashicons-admin-home"></span>
    <p>No rooms found.</p>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-room">Add Your First Room</button>
  </div>
  <?php else: ?>
  <div class="ghm-cards-grid" id="ghm-rooms-grid">
    <?php foreach ($rooms as $room):
      $amenities = json_decode($room->amenities ?: '[]', true);
    ?>
    <div class="ghm-room-card">
      <span class="card-badge ghm-badge <?php echo esc_attr($room->status); ?>"><?php echo $statuses[$room->status] ?? $room->status; ?></span>
      <div class="card-number"><?php echo esc_html($room->type); ?> · <?php echo esc_html($room->room_number); ?> · Floor <?php echo esc_html($room->floor); ?></div>
      <div class="card-name"><?php echo esc_html($room->name); ?></div>
      <div class="card-meta">
        <span><span class="dashicons dashicons-groups" style="font-size:14px;"></span><?php echo $room->capacity; ?> guests</span>
        <?php if ($room->price_night > 0): ?>
        <span><span class="dashicons dashicons-calendar" style="font-size:14px;"></span>per night</span>
        <?php endif; ?>
      </div>
      <?php $dp = GHM_Rooms::get_display_price($room); ?>
      <div class="card-price">
        <?php echo get_option('ghm_currency_symbol','₦'); ?><?php echo number_format($dp['price'],2); ?>
        <small><?php echo esc_html($dp['unit']); ?></small>
      </div>
      <?php if (!empty($amenities)): ?>
      <div class="card-amenities">
        <?php foreach (array_slice($amenities,0,4) as $a): ?>
        <span><?php echo esc_html($a); ?></span>
        <?php endforeach;
        if(count($amenities)>4): ?><span>+<?php echo count($amenities)-4; ?> more</span><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="card-actions">
        <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-edit-room" data-id="<?php echo $room->id; ?>">
          <span class="dashicons dashicons-edit"></span> Edit
        </button>
        <a href="?page=ghm-bookings&room_id=<?php echo $room->id; ?>" class="ghm-btn ghm-btn-success ghm-btn-sm">
          <span class="dashicons dashicons-calendar-alt"></span> Bookings
        </a>
        <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-delete-room" data-id="<?php echo $room->id; ?>">
          <span class="dashicons dashicons-trash"></span>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
