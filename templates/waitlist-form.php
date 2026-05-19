<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ghm-public-wrap">
  <div class="ghm-waitlist-wrap">
    <?php if ( $success ): ?>
      <div style="text-align:center;padding:20px 0;">
        <div style="font-size:48px;margin-bottom:12px;">⏰</div>
        <h3 style="font-family:'Playfair Display',serif;font-size:22px;color:#1a1a2e;margin-bottom:8px;">You're on the Waitlist!</h3>
        <p style="color:#6b7280;font-size:14px;">We'll notify you immediately via WhatsApp and email if this room becomes available for your dates.</p>
      </div>
    <?php else: ?>
      <h3><?php echo $room ? esc_html($room->name).' — ' : ''; ?>Join Waiting List</h3>
      <p class="subtitle">This room is currently unavailable. Leave your details and we'll contact you first if it opens up.</p>
      <form method="post">
        <?php wp_nonce_field('ghm_waitlist','ghm_waitlist_nonce'); ?>
        <div class="ghm-bform-grid">
          <div class="ghm-bform-field">
            <label>First Name *</label>
            <input type="text" name="first_name" required placeholder="John">
          </div>
          <div class="ghm-bform-field">
            <label>Last Name *</label>
            <input type="text" name="last_name" required placeholder="Smith">
          </div>
          <div class="ghm-bform-field">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="john@example.com">
          </div>
          <div class="ghm-bform-field">
            <label>Phone (for WhatsApp alerts)</label>
            <input type="tel" name="phone" placeholder="+234 800 000 0000">
          </div>
          <div class="ghm-bform-field">
            <label>Desired Check-In *</label>
            <input type="date" name="check_in" required min="<?php echo date('Y-m-d');?>">
          </div>
          <div class="ghm-bform-field">
            <label>Desired Check-Out *</label>
            <input type="date" name="check_out" required min="<?php echo date('Y-m-d',strtotime('+1 day'));?>">
          </div>
          <div class="ghm-bform-field">
            <label>Adults</label>
            <select name="adults">
              <?php for ($i=1;$i<=8;$i++) echo "<option value='$i'>$i adult".($i>1?'s':'')."</option>"; ?>
            </select>
          </div>
          <?php if (!$room_id): ?>
          <div class="ghm-bform-field">
            <label>Preferred Room</label>
            <select name="room_id">
              <option value="0">Any Available Room</option>
              <?php foreach(GHM_Rooms::get_rooms(array('limit'=>50)) as $r): ?>
              <option value="<?php echo $r->id;?>"><?php echo esc_html($r->name);?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
          <input type="hidden" name="room_id" value="<?php echo $room_id;?>">
          <?php endif; ?>
        </div>
        <button type="submit" name="ghm_waitlist_submit" class="ghm-bform-submit" style="margin-top:20px;width:100%;justify-content:center;">
          ⏰ Join Waiting List — Notify Me When Available
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
