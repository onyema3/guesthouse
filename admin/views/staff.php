<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-businessperson"></span> Staff Management</h1>
    <?php if ( current_user_can('ghm_manage_staff') ): ?>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-staff">
      <span class="dashicons dashicons-plus-alt"></span> Add Staff Member
    </button>
    <?php endif; ?>
  </div>

  <?php if ( empty($staff) ): ?>
  <div class="ghm-empty">
    <span class="dashicons dashicons-businessperson"></span>
    <p>No staff members found.</p>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-staff">Add Staff Member</button>
  </div>
  <?php else: ?>
  <div class="ghm-cards-grid">
    <?php foreach ($staff as $member):
      $member_pin_set = $member->wp_user_id ? (bool) get_user_meta( $member->wp_user_id, 'ghm_pin', true ) : false;
    ?>
    <div class="ghm-room-card">
      <span class="card-badge ghm-badge <?php echo esc_attr($member->status); ?>"><?php echo ucfirst($member->status); ?></span>
      <div class="ghm-profile-avatar" style="width:48px;height:48px;font-size:18px;margin:0 0 10px;text-align:center;display:flex;align-items:center;justify-content:center;background:var(--ghm-surface2);border:2px solid var(--ghm-gold);border-radius:50%;">
        <?php echo strtoupper(substr($member->display_name,0,2)); ?>
      </div>
      <div class="card-name"><?php echo esc_html($member->display_name); ?></div>
      <div class="card-number"><?php echo esc_html($member->position ?: 'Staff'); ?> · <?php echo esc_html($member->department ?: ''); ?></div>
      <ul class="ghm-info-list" style="margin:10px 0;">
        <li><span class="label">Email</span><span class="value" style="font-size:12px;"><?php echo esc_html($member->user_email); ?></span></li>
        <?php if ($member->phone): ?>
        <li><span class="label">Phone</span><span class="value"><?php echo esc_html($member->phone); ?></span></li>
        <?php endif; ?>
        <?php if ($member->shift): ?>
        <li><span class="label">Shift</span><span class="value"><?php echo ucfirst($member->shift); ?></span></li>
        <?php endif; ?>
        <?php if ($member->hire_date): ?>
        <li><span class="label">Hired</span><span class="value"><?php echo date('M Y',strtotime($member->hire_date)); ?></span></li>
        <?php endif; ?>
        <li>
          <span class="label">Front-desk PIN</span>
          <span class="value">
            <?php if ($member_pin_set): ?>
              <span style="color:var(--ghm-success);font-size:12px;">✓ Set</span>
            <?php else: ?>
              <span style="color:var(--ghm-muted);font-size:12px;">Not set</span>
            <?php endif; ?>
          </span>
        </li>
      </ul>
      <div class="card-actions">
        <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-edit-staff" data-id="<?php echo $member->id; ?>">
          <span class="dashicons dashicons-edit"></span> Edit
        </button>
        <?php if ( current_user_can('manage_options') && $member->wp_user_id ): ?>
        <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-set-pin"
                data-user="<?php echo (int) $member->wp_user_id; ?>"
                data-name="<?php echo esc_attr( $member->display_name ); ?>"
                title="<?php echo $member_pin_set ? 'Change PIN' : 'Set PIN'; ?>">
          <span class="dashicons dashicons-lock"></span>
          <?php echo $member_pin_set ? 'Change PIN' : 'Set PIN'; ?>
        </button>
        <?php if ( $member_pin_set ): ?>
        <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-clear-pin"
                data-user="<?php echo (int) $member->wp_user_id; ?>"
                title="Clear PIN">
          Clear
        </button>
        <?php endif; ?>
        <?php endif; ?>
        <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-delete-staff" data-id="<?php echo $member->id; ?>">
          <span class="dashicons dashicons-trash"></span>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ( current_user_can( 'manage_options' ) ): ?>
  <div class="ghm-form-section" style="max-width:820px;margin-top:24px;">
    <p class="ghm-form-section-title">⌨️ Front-Desk PIN Login</p>
    <p style="font-size:13px;color:var(--ghm-muted);margin-bottom:8px;">
      Set 4–8 digit PINs so staff can sign in quickly at a shared front-desk terminal using the
      <code>[ghm_pin_login]</code> shortcode — no need to type the full WordPress password.
    </p>
    <p style="font-size:12px;color:var(--ghm-muted);margin:0;">
      Use the <strong>Set PIN</strong> button on any staff card above. Each staff member can also
      change their own PIN under <a href="?page=ghm-permissions" style="color:var(--ghm-gold);">Permissions</a>.
    </p>
  </div>
  <?php endif; ?>
</div>

<script>
(function($){
  $(document)
    .on('click', '.ghm-set-pin', function(){
      const userId = $(this).data('user');
      const name   = $(this).data('name') || 'this user';
      const body = `
        <div class="ghm-form-section">
          <p style="font-size:13px;color:var(--ghm-muted);margin-bottom:12px;">
            Set a 4–8 digit PIN for <strong>${GHM.esc(name)}</strong>.
            They can use it on the <code>[ghm_pin_login]</code> page.
          </p>
          <div class="ghm-form-grid" style="max-width:300px;">
            <div class="ghm-form-field">
              <label>New PIN (4–8 digits)</label>
              <input type="password" id="ghm-new-pin" inputmode="numeric" pattern="[0-9]{4,8}"
                maxlength="8" placeholder="e.g. 1234"
                style="letter-spacing:6px;font-size:20px;font-family:monospace;">
            </div>
          </div>
        </div>`;
      const foot = '<button type="button" class="ghm-btn ghm-btn-outline" id="ghm-btn-cancel">Cancel</button>' +
                   '<button type="button" class="ghm-btn ghm-btn-primary" id="ghm-btn-submit">Save PIN</button>';
      GHM.openModal('Set Front-Desk PIN', body, foot);
      document.getElementById('ghm-btn-cancel').addEventListener('click', () => GHM.closeModal());
      document.getElementById('ghm-btn-submit').addEventListener('click', () => {
        const pin = (document.getElementById('ghm-new-pin').value || '').replace(/\D/g, '');
        if (pin.length < 4 || pin.length > 8) {
          GHM.toast('PIN must be 4 to 8 digits.', 'error');
          return;
        }
        GHM.post('ghm_set_staff_pin', { user_id: userId, pin: pin })
          .then(() => { GHM.toast('PIN saved.', 'success'); GHM.closeModal(); setTimeout(() => location.reload(), 700); })
          .catch(e => GHM.toast(e || 'Failed to save PIN.', 'error'));
      });
    })
    .on('click', '.ghm-clear-pin', function(){
      if (!confirm('Clear PIN for this staff member?')) return;
      const userId = $(this).data('user');
      GHM.post('ghm_clear_staff_pin', { user_id: userId })
        .then(() => { GHM.toast('PIN cleared.', 'success'); setTimeout(() => location.reload(), 700); })
        .catch(e => GHM.toast(e || 'Failed to clear PIN.', 'error'));
    });
})(jQuery);
</script>
