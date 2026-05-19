<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-id-alt"></span> Customers / CRM</h1>
    <?php if ( current_user_can('ghm_manage_customers') ): ?>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-customer">
      <span class="dashicons dashicons-plus-alt"></span> Add Customer
    </button>
    <?php endif; ?>
  </div>

  <?php if ( $view === 'edit' && $customer ): ?>
  <!-- Customer Detail View -->
  <a href="?page=ghm-customers" class="ghm-btn ghm-btn-outline ghm-btn-sm" style="margin-bottom:18px;">
    <span class="dashicons dashicons-arrow-left-alt"></span> Back to Customers
  </a>
  <div class="ghm-detail-layout">
    <div>
      <div class="ghm-form-section">
        <p class="ghm-form-section-title">Customer Details</p>
        <div class="ghm-form-grid">
          <div><span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">Name</span><div style="font-weight:600;margin-top:4px;"><?php echo esc_html($customer->first_name.' '.$customer->last_name); ?></div></div>
          <div><span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">Email</span><div style="margin-top:4px;"><?php echo esc_html($customer->email); ?></div></div>
          <div><span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">Phone</span><div style="margin-top:4px;"><?php echo esc_html($customer->phone ?: '—'); ?></div></div>
          <div><span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">Country</span><div style="margin-top:4px;"><?php echo esc_html($customer->country ?: '—'); ?></div></div>
          <div><span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">ID Type</span><div style="margin-top:4px;"><?php echo esc_html($customer->id_type ?: '—'); ?></div></div>
          <div><span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">ID Number</span><div style="margin-top:4px;"><?php echo esc_html($customer->id_number ?: '—'); ?></div></div>
        </div>
        <?php if ($customer->notes): ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--ghm-border);">
          <span style="color:var(--ghm-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">Notes</span>
          <div style="margin-top:6px;font-size:13px;color:var(--ghm-text);"><?php echo nl2br(esc_html($customer->notes)); ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Booking history -->
      <div class="ghm-form-section">
        <p class="ghm-form-section-title">Booking History (<?php echo count($customer_bookings); ?>)</p>
        <?php if (empty($customer_bookings)): ?>
        <div style="color:var(--ghm-muted);font-size:13px;">No bookings yet.</div>
        <?php else: ?>
        <div class="ghm-table-wrap">
          <table class="ghm-table">
            <thead><tr><th>Ref</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
              <?php $sym = get_option('ghm_currency_symbol','$');
              foreach ($customer_bookings as $b): $statuses = GHM_Bookings::get_statuses(); ?>
              <tr>
                <td style="font-size:11px;color:var(--ghm-gold);"><?php echo esc_html($b->booking_ref); ?></td>
                <td><?php echo esc_html($b->room_name); ?></td>
                <td><?php echo date('M j, Y', strtotime($b->check_in)); ?></td>
                <td><?php echo date('M j, Y', strtotime($b->check_out)); ?></td>
                <td><?php echo $sym.number_format($b->total_amount,2); ?></td>
                <td><span class="ghm-badge <?php echo esc_attr($b->status); ?>"><?php echo $statuses[$b->status]??$b->status; ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="ghm-profile-card">
        <div class="ghm-profile-avatar"><?php echo strtoupper(substr($customer->first_name,0,1).substr($customer->last_name,0,1)); ?></div>
        <div class="ghm-profile-name"><?php echo esc_html($customer->first_name.' '.$customer->last_name); ?></div>
        <div class="ghm-profile-email"><?php echo esc_html($customer->email); ?></div>
        <span class="ghm-badge <?php echo esc_attr($customer->status); ?>"><?php echo ucfirst($customer->status); ?></span>
        <ul class="ghm-info-list" style="margin-top:16px;text-align:left;">
          <li><span class="label">Total Stays</span><span class="value"><?php echo $customer->visit_count; ?></span></li>
          <li><span class="label">Total Spent</span><span class="value"><?php echo get_option('ghm_currency_symbol','$').number_format($customer->total_spent,2); ?></span></li>
          <li><span class="label">Customer Since</span><span class="value"><?php echo date('M Y', strtotime($customer->created_at)); ?></span></li>
        </ul>
        <div style="margin-top:16px;display:flex;gap:8px;">
          <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-edit-customer" data-id="<?php echo $customer->id; ?>" style="flex:1">Edit</button>
        </div>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- Customer List -->
  <div class="ghm-toolbar">
    <div class="ghm-search-box">
      <span class="dashicons dashicons-search"></span>
      <input type="text" id="ghm-customer-search-main" placeholder="Search customers…">
    </div>
  </div>
  <div class="ghm-table-wrap">
    <table class="ghm-table" id="ghm-customers-table">
      <thead>
        <tr><th>Name</th><th>Email</th><th>Phone</th><th>Country</th><th>Stays</th><th>Total Spent</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($customers)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ghm-muted);">No customers found.</td></tr>
        <?php else:
        $sym = get_option('ghm_currency_symbol','$');
        foreach ($customers as $c): ?>
        <tr>
          <td><div class="room-name"><?php echo esc_html($c->first_name.' '.$c->last_name); ?></div></td>
          <td><?php echo esc_html($c->email); ?></td>
          <td><?php echo esc_html($c->phone ?: '—'); ?></td>
          <td><?php echo esc_html($c->country ?: '—'); ?></td>
          <td><?php echo $c->visit_count; ?></td>
          <td><?php echo $sym.number_format($c->total_spent,2); ?></td>
          <td><span class="ghm-badge <?php echo esc_attr($c->status); ?>"><?php echo ucfirst($c->status); ?></span></td>
          <td>
            <div style="display:flex;gap:6px;">
              <a href="?page=ghm-customers&view=edit&id=<?php echo $c->id; ?>" class="ghm-btn ghm-btn-outline ghm-btn-sm">View</a>
              <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-edit-customer" data-id="<?php echo $c->id; ?>">Edit</button>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-delete-customer" data-id="<?php echo $c->id; ?>"><span class="dashicons dashicons-trash"></span></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
