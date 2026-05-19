<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-building"></span> Dashboard</h1>
    <span style="color:var(--ghm-muted);font-size:13px;"><?php echo date_i18n( 'l, F j, Y' ); ?></span>
  </div>

  <!-- Stats grid -->
  <div class="ghm-stats-grid">
    <div class="ghm-stat-card">
      <div class="stat-label">Available Rooms</div>
      <div class="stat-value accent-green"><?php echo $stats['available_rooms']; ?></div>
      <div class="stat-icon dashicons dashicons-admin-home"></div>
    </div>
    <div class="ghm-stat-card accent-info">
      <div class="stat-label">Occupied Rooms</div>
      <div class="stat-value"><?php echo $stats['occupied_rooms']; ?></div>
      <div class="stat-icon dashicons dashicons-groups"></div>
    </div>
    <div class="ghm-stat-card" style="border-top-color:#a78bfa;">
      <div class="stat-label">Booked (Unpaid)</div>
      <div class="stat-value" style="color:#a78bfa;"><?php echo $stats['booked_unpaid'] ?? 0; ?></div>
      <div class="stat-icon dashicons dashicons-calendar"></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Confirmed (Paid)</div>
      <div class="stat-value accent-green"><?php echo $stats['confirmed_paid'] ?? 0; ?></div>
      <div class="stat-icon dashicons dashicons-yes-alt"></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Check-ins Today</div>
      <div class="stat-value accent-warning"><?php echo $stats['checkins_today']; ?></div>
      <div class="stat-icon dashicons dashicons-arrow-down-alt"></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Check-outs Today</div>
      <div class="stat-value accent-info"><?php echo $stats['checkouts_today']; ?></div>
      <div class="stat-icon dashicons dashicons-arrow-up-alt"></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Revenue Today</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr( get_option('ghm_currency_symbol','$') ); ?>"><?php echo number_format( $stats['revenue_today'], 2 ); ?></div>
      <div class="stat-icon dashicons dashicons-money-alt"></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Revenue This Month</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr( get_option('ghm_currency_symbol','$') ); ?>"><?php echo number_format( $stats['revenue_this_month'], 2 ); ?></div>
      <div class="stat-icon dashicons dashicons-chart-area"></div>
    </div>
    <div class="ghm-stat-card accent-danger">
      <div class="stat-label">Pending Payments</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr( get_option('ghm_currency_symbol','$') ); ?>"><?php echo number_format( $stats['pending_payments'], 2 ); ?></div>
      <div class="stat-icon dashicons dashicons-warning"></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Total Customers</div>
      <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
      <div class="stat-icon dashicons dashicons-id-alt"></div>
    </div>
  </div>

  <!-- Charts -->
  <div class="ghm-charts-grid">
    <div class="ghm-chart-card">
      <h3>Revenue & Bookings (Last 6 Months)</h3>
      <canvas id="ghm-revenue-chart" style="height:240px;"></canvas>
    </div>
    <div class="ghm-chart-card">
      <h3>Booking Status</h3>
      <canvas id="ghm-status-chart" style="height:240px;"></canvas>
    </div>
  </div>

  <!-- Maintenance alert banner -->
  <?php if (!empty($maint_open ?? 0)): ?>
  <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
    <span style="font-size:13px;color:var(--ghm-danger);">🔧 <strong><?php echo $maint_open; ?></strong> open maintenance request<?php echo $maint_open>1?'s':'';?> require attention</span>
    <a href="?page=ghm-maintenance" class="ghm-btn ghm-btn-danger ghm-btn-sm">View Requests</a>
  </div>
  <?php endif; ?>

  <!-- Quick links -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-top:4px;">
    <?php $links = [
      ['ghm-bookings',    'New Booking',   'dashicons-plus-alt',   'ghm-btn-primary'],
      ['ghm-calendar',    'Calendar',      'dashicons-calendar',   'ghm-btn-outline'],
      ['ghm-housekeeping','Housekeeping',  'dashicons-yes',        'ghm-btn-outline'],
      ['ghm-maintenance', 'Maintenance',   'dashicons-admin-tools','ghm-btn-outline'],
      ['ghm-customers',   'Customers',     'dashicons-id-alt',     'ghm-btn-outline'],
      ['ghm-reports',     'Reports',       'dashicons-chart-bar',  'ghm-btn-outline'],
    ];
    foreach ($links as $link): ?>
    <a href="<?php echo admin_url('admin.php?page='.$link[0]); ?>" class="ghm-btn <?php echo $link[3]; ?>" style="justify-content:center;padding:14px;">
      <span class="dashicons <?php echo $link[2]; ?>"></span> <?php echo $link[1]; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <input type="hidden" id="ghm-chart-data" value="<?php echo esc_attr( json_encode( $chart_data ) ); ?>">
</div>
