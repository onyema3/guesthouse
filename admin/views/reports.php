<?php if ( ! defined( 'ABSPATH' ) ) exit;
$sym = get_option('ghm_currency_symbol','$');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-chart-bar"></span> Reports & Analytics</h1>
  </div>

  <!-- KPI Row -->
  <div class="ghm-stats-grid">
    <div class="ghm-stat-card">
      <div class="stat-label">Occupancy Rate</div>
      <div class="stat-value"><?php echo $occupancy; ?><small style="font-size:16px;">%</small></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym); ?>"><?php echo number_format(GHM_Payments::get_total_revenue(),2); ?></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Total Bookings</div>
      <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
    </div>
    <div class="ghm-stat-card">
      <div class="stat-label">Total Customers</div>
      <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="ghm-charts-grid" style="grid-template-columns:2fr 1fr 1fr;">
    <div class="ghm-chart-card">
      <h3>Revenue Trend (12 Months)</h3>
      <canvas id="ghm-revenue-chart" style="height:220px;"></canvas>
    </div>
    <div class="ghm-chart-card">
      <h3>Booking Statuses</h3>
      <canvas id="ghm-status-chart" style="height:220px;"></canvas>
    </div>
    <div class="ghm-chart-card">
      <h3>Payment Methods</h3>
      <canvas id="ghm-payment-chart" style="height:220px;"></canvas>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:0;">
    <!-- Top rooms -->
    <div class="ghm-form-section">
      <p class="ghm-form-section-title">Top Performing Rooms</p>
      <div class="ghm-table-wrap">
        <table class="ghm-table">
          <thead><tr><th>Room</th><th>Type</th><th>Bookings</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($top_rooms as $r): ?>
            <tr>
              <td><div class="room-name"><?php echo esc_html($r->name); ?></div><div class="room-meta"><?php echo esc_html($r->room_number); ?></div></td>
              <td><?php echo ucfirst($r->type); ?></td>
              <td><?php echo (int)$r->bookings; ?></td>
              <td><?php echo $sym.number_format($r->revenue??0,2); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="ghm-form-section">
      <p class="ghm-form-section-title">Recent Activity</p>
      <div style="max-height:300px;overflow-y:auto;">
        <?php foreach ($activity as $log): ?>
        <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--ghm-border);">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--ghm-surface2);border:1px solid var(--ghm-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;color:var(--ghm-gold);">
            <?php echo strtoupper(substr($log->display_name??'SY',0,2)); ?>
          </div>
          <div>
            <div style="font-size:13px;color:var(--ghm-text);"><?php echo esc_html(str_replace('_',' ',ucfirst($log->action))); ?>
              <?php if ($log->object_type): ?><span style="color:var(--ghm-muted);">(<?php echo $log->object_type; ?> #<?php echo $log->object_id; ?>)</span><?php endif; ?>
            </div>
            <div style="font-size:11px;color:var(--ghm-muted);margin-top:2px;"><?php echo esc_html($log->display_name??'System'); ?> · <?php echo human_time_diff(strtotime($log->created_at)); ?> ago</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Channel breakdown -->
  <?php if (!empty($channels)): ?>
  <div style="margin-top:20px;" class="ghm-form-section">
    <p class="ghm-form-section-title">Booking Channels</p>
    <div class="ghm-table-wrap">
      <table class="ghm-table">
        <thead><tr><th>Source</th><th>Bookings</th><th>Revenue</th><th>Share</th></tr></thead>
        <tbody>
          <?php
          $total_bks = array_sum(array_column((array)$channels,'bookings'));
          $channel_labels = GHM_Channels::get_channels();
          $sym = get_option('ghm_currency_symbol','₦');
          foreach ($channels as $ch):
            $pct = $total_bks > 0 ? round(($ch->bookings/$total_bks)*100,1) : 0;
          ?>
          <tr>
            <td><?php echo esc_html($channel_labels[$ch->source] ?? ucwords(str_replace('_',' ',$ch->source)));?></td>
            <td><?php echo (int)$ch->bookings;?></td>
            <td><?php echo $sym.number_format($ch->revenue??0,2);?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="flex:1;background:var(--ghm-surface2);border-radius:3px;height:6px;overflow:hidden;">
                  <div style="background:var(--ghm-gold);height:100%;width:<?php echo $pct;?>%;"></div>
                </div>
                <span style="font-size:12px;color:var(--ghm-muted);width:40px;"><?php echo $pct;?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif;?>

  <!-- Export section -->
  <div class="ghm-form-section" style="margin-top:20px;">
    <p class="ghm-form-section-title">📥 Export Reports</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php
      $from_month = date('Y-m-01');
      $to_month   = date('Y-m-t');
      $csv_pay  = wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=payments&from='.$from_month.'&to='.$to_month),'ghm_export');
      $csv_book = wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=bookings'),'ghm_export');
      $print_pay= wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=payments&ghm_export_format=print&from='.$from_month.'&to='.$to_month),'ghm_export');
      $print_book=wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=bookings&ghm_export_format=print'),'ghm_export');
      $csv_act  = wp_nonce_url(admin_url('admin.php?page=ghm-payments&ghm_export=activity&from='.$from_month.'&to='.$to_month),'ghm_export');
      ?>
      <a href="<?php echo esc_url($csv_pay);?>"  class="ghm-btn ghm-btn-outline ghm-btn-sm">⬇ Payments CSV</a>
      <a href="<?php echo esc_url($csv_book);?>" class="ghm-btn ghm-btn-outline ghm-btn-sm">⬇ Bookings CSV</a>
      <a href="<?php echo esc_url($csv_act);?>"  class="ghm-btn ghm-btn-outline ghm-btn-sm">⬇ Activity CSV</a>
      <a href="<?php echo esc_url($print_pay);?>"  target="_blank" class="ghm-btn ghm-btn-outline ghm-btn-sm">🖨 Print Payments</a>
      <a href="<?php echo esc_url($print_book);?>" target="_blank" class="ghm-btn ghm-btn-outline ghm-btn-sm">🖨 Print Bookings</a>
      <button class="ghm-btn ghm-btn-outline ghm-btn-sm" onclick="window.print()">🖨 Print Dashboard</button>
    </div>
  </div>

  <input type="hidden" id="ghm-chart-data" value="<?php echo esc_attr(json_encode($chart_data)); ?>">
</div>
