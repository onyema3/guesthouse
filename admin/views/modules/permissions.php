<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( isset($_POST['ghm_save_permissions']) && check_admin_referer('ghm_permissions') ) {
    foreach ( GHM_Permissions::get_ghm_roles() as $role_slug ) {
        $caps = (array)($_POST['caps'][$role_slug] ?? array());
        GHM_Permissions::save_permissions($role_slug, $caps);
    }
    // Save PIN for current user if submitted
    if ( !empty($_POST['ghm_my_pin']) && strlen($_POST['ghm_my_pin']) >= 4 ) {
        GHM_PIN_Login::set_pin(get_current_user_id(), sanitize_text_field($_POST['ghm_my_pin']));
        add_settings_error('ghm_permissions','pin_saved','PIN updated successfully.','success');
    }
    add_settings_error('ghm_permissions','saved','Permissions saved.','success');
}
$caps  = GHM_Permissions::get_capabilities();
$roles = GHM_Permissions::get_ghm_roles();
$role_labels = array('ghm_staff'=>'GHM Staff (front desk)','ghm_manager'=>'GHM Manager (senior)');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-admin-users"></span> Role Permissions</h1>
  </div>
  <?php settings_errors('ghm_permissions'); ?>

  <form method="post" action="">
    <?php wp_nonce_field('ghm_permissions'); ?>

    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">Role Capabilities</p>
      <p style="font-size:13px;color:var(--ghm-muted);margin-bottom:16px;">
        Administrators always have all capabilities. Changes here affect GHM Staff and GHM Manager roles.
      </p>
      <table class="ghm-table" style="min-width:auto;">
        <thead>
          <tr>
            <th style="min-width:280px;">Capability</th>
            <?php foreach ($roles as $role): ?>
            <th style="text-align:center;"><?php echo $role_labels[$role] ?? $role;?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($caps as $cap => $label):?>
          <tr>
            <td>
              <div style="font-size:13px;font-weight:500;color:var(--ghm-text);"><?php echo esc_html($label);?></div>
              <div style="font-size:11px;color:var(--ghm-muted);font-family:monospace;"><?php echo $cap;?></div>
            </td>
            <?php foreach ($roles as $role_slug):
              $role_obj = get_role($role_slug);
              $has_cap  = $role_obj && $role_obj->has_cap($cap);
            ?>
            <td style="text-align:center;">
              <label style="cursor:pointer;">
                <input type="checkbox" name="caps[<?php echo $role_slug;?>][]" value="<?php echo $cap;?>"
                  <?php checked($has_cap,true);?>
                  style="accent-color:var(--ghm-gold);width:18px;height:18px;cursor:pointer;">
              </label>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- PIN Setup -->
    <div class="ghm-form-section" style="max-width:820px;">
      <p class="ghm-form-section-title">⌨️ My PIN (for Front Desk Quick Login)</p>
      <p style="font-size:13px;color:var(--ghm-muted);margin-bottom:14px;">
        Set a 4–8 digit PIN so you can log in quickly at a shared front-desk terminal without typing your password.
        Use the <code>[ghm_pin_login]</code> shortcode on any page.
      </p>
      <div class="ghm-form-grid" style="max-width:400px;">
        <div class="ghm-form-field">
          <label>New PIN (4–8 digits)</label>
          <input type="password" name="ghm_my_pin" placeholder="e.g. 1234" maxlength="8" pattern="[0-9]{4,8}"
            style="letter-spacing:6px;font-size:20px;font-family:monospace;max-width:200px;">
        </div>
      </div>
      <?php $has_pin = get_user_meta(get_current_user_id(),'ghm_pin',true); ?>
      <?php if ($has_pin): ?>
      <p style="font-size:12px;color:var(--ghm-success);margin-top:8px;">✓ PIN is set. Enter a new one above to change it.</p>
      <?php endif; ?>
    </div>

    <input type="hidden" name="ghm_save_permissions" value="1">
    <button type="submit" class="ghm-btn ghm-btn-primary" style="padding:12px 32px;">
      <span class="dashicons dashicons-yes"></span> Save Permissions & PIN
    </button>
  </form>

  <!-- All staff PINs (admin only) -->
  <?php if (current_user_can('administrator')): ?>
  <div class="ghm-form-section" style="max-width:820px;margin-top:20px;">
    <p class="ghm-form-section-title">Staff PINs (Admin View)</p>
    <div class="ghm-table-wrap">
      <table class="ghm-table">
        <thead><tr><th>Staff Member</th><th>Email</th><th>Role</th><th>PIN Set</th><th>Action</th></tr></thead>
        <tbody>
          <?php
          $staff_users = get_users(array('role__in'=>array('ghm_staff','ghm_manager','administrator')));
          foreach ($staff_users as $u):
            $has = get_user_meta($u->ID,'ghm_pin',true);
          ?>
          <tr>
            <td><?php echo esc_html($u->display_name);?></td>
            <td style="font-size:12px;color:var(--ghm-muted);"><?php echo esc_html($u->user_email);?></td>
            <td><?php echo esc_html(implode(', ',array_intersect($u->roles,array('ghm_staff','ghm_manager','administrator'))));?></td>
            <td><?php echo $has ? '<span class="ghm-badge available">✓ Set</span>' : '<span class="ghm-badge unpaid">Not set</span>';?></td>
            <td>
              <?php if ($has): ?>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-clear-pin" data-user="<?php echo $u->ID;?>">Clear PIN</button>
              <?php endif;?>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif;?>
</div>

<script>
(function($){
  $(document).on('click','.ghm-clear-pin',function(){
    if (!confirm('Clear PIN for this staff member?')) return;
    GHM.post('ghm_clear_staff_pin',{user_id:$(this).data('user')})
      .then(()=>{ GHM.toast('PIN cleared.','success'); setTimeout(()=>location.reload(),700); })
      .catch(e=>GHM.toast(e,'error'));
  });
})(jQuery);
</script>
