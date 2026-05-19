<?php if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$year  = absint( $_GET['year']  ?? date('Y') );
$month = absint( $_GET['month'] ?? date('n') );
if ($month < 1) { $month = 12; $year--; }
if ($month > 12){ $month = 1;  $year++; }
$days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
$month_start   = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01";
$month_end     = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-$days_in_month";
$month_name    = date('F Y', mktime(0,0,0,$month,1,$year));

$rooms = GHM_Rooms::get_rooms(array('limit'=>100));
$bookings = $wpdb->get_results($wpdb->prepare(
    "SELECT b.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name
     FROM {$wpdb->prefix}ghm_bookings b
     LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id=b.customer_id
     WHERE b.status NOT IN('cancelled','no_show')
     AND b.check_in <= %s AND b.check_out >= %s
     ORDER BY b.check_in ASC",
    $month_end.' 23:59:59', $month_start
));

// Build occupancy map: room_id => array of booked day-numbers
$occ = array();
foreach ( $bookings as $b ) {
    $start = max(1, (int)date('j', strtotime($b->check_in))  );
    $end   = min($days_in_month, (int)date('j', strtotime($b->check_out)) );
    if ( date('Y-m', strtotime($b->check_in))  !== "$year-".str_pad($month,2,'0',STR_PAD_LEFT) ) $start = 1;
    if ( date('Y-m', strtotime($b->check_out)) !== "$year-".str_pad($month,2,'0',STR_PAD_LEFT) ) $end   = $days_in_month;
    for ( $d = $start; $d < $end; $d++ ) {
        $occ[$b->room_id][$d] = array('ref'=>$b->booking_ref,'guest'=>$b->customer_name,'status'=>$b->status);
    }
}

// Status colours
$status_colors = array(
    'booked'      => '#a78bfa',
    'confirmed'   => '#3ecf8e',
    'checked_in'  => '#60a5fa',
    'checked_out' => '#c9a84c',
);
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-calendar"></span> Occupancy Calendar</h1>
    <div style="display:flex;gap:8px;align-items:center;">
      <a href="?page=ghm-calendar&month=<?php echo $month==1?12:$month-1;?>&year=<?php echo $month==1?$year-1:$year;?>" class="ghm-btn ghm-btn-outline ghm-btn-sm">← Prev</a>
      <strong style="color:var(--ghm-text);min-width:130px;text-align:center;"><?php echo $month_name; ?></strong>
      <a href="?page=ghm-calendar&month=<?php echo $month==12?1:$month+1;?>&year=<?php echo $month==12?$year+1:$year;?>" class="ghm-btn ghm-btn-outline ghm-btn-sm">Next →</a>
    </div>
  </div>

  <!-- Legend -->
  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;font-size:12px;">
    <?php foreach ($status_colors as $st => $col): ?>
    <span style="display:flex;align-items:center;gap:5px;">
      <span style="width:12px;height:12px;border-radius:3px;background:<?php echo $col;?>;display:inline-block;"></span>
      <?php echo ucfirst(str_replace('_',' ',$st)); ?>
    </span>
    <?php endforeach; ?>
    <span style="display:flex;align-items:center;gap:5px;">
      <span style="width:12px;height:12px;border-radius:3px;background:var(--ghm-surface2);border:1px solid var(--ghm-border);display:inline-block;"></span>
      Available
    </span>
  </div>

  <!-- Calendar grid -->
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;min-width:900px;">
      <thead>
        <tr>
          <th style="background:var(--ghm-surface2);padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--ghm-muted);border:1px solid var(--ghm-border);white-space:nowrap;min-width:160px;">Room</th>
          <?php for ($d=1; $d<=$days_in_month; $d++):
            $dow     = date('D', mktime(0,0,0,$month,$d,$year));
            $is_wknd = in_array($dow, array('Sat','Sun'));
          ?>
          <th style="background:<?php echo $is_wknd?'rgba(201,168,76,.08)':'var(--ghm-surface2)';?>;padding:6px 4px;text-align:center;font-size:11px;color:<?php echo $is_wknd?'var(--ghm-gold)':'var(--ghm-muted)';?>;border:1px solid var(--ghm-border);min-width:32px;">
            <div><?php echo $dow[0]; ?></div>
            <div style="font-size:13px;font-weight:600;color:var(--ghm-text);"><?php echo $d; ?></div>
          </th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $room): ?>
        <tr>
          <td style="padding:8px 14px;border:1px solid var(--ghm-border);white-space:nowrap;background:var(--ghm-surface);">
            <div style="font-size:13px;font-weight:600;color:var(--ghm-text);"><?php echo esc_html($room->name); ?></div>
            <div style="font-size:11px;color:var(--ghm-muted);"><?php echo esc_html($room->room_number); ?> · <?php echo ucfirst($room->type); ?></div>
          </td>
          <?php for ($d=1; $d<=$days_in_month; $d++):
            $cell = $occ[$room->id][$d] ?? null;
            $bg   = $cell ? ($status_colors[$cell['status']] ?? '#6b7280') : 'transparent';
            $title= $cell ? htmlspecialchars("{$cell['guest']} ({$cell['ref']})") : '';
          ?>
          <td style="padding:0;border:1px solid var(--ghm-border);background:<?php echo $cell?$bg.'22':'transparent'; ?>;"
              title="<?php echo $title; ?>">
            <?php if ($cell): ?>
            <div style="background:<?php echo $bg;?>;height:28px;margin:2px;border-radius:4px;cursor:pointer;"
                 title="<?php echo $title; ?>"></div>
            <?php endif; ?>
          </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Occupancy rate -->
  <?php
  $total_cells  = count($rooms) * $days_in_month;
  $booked_cells = 0;
  foreach ($occ as $room_days) $booked_cells += count($room_days);
  $occ_rate     = $total_cells > 0 ? round(($booked_cells/$total_cells)*100,1) : 0;
  ?>
  <div style="margin-top:20px;display:flex;gap:20px;align-items:center;">
    <div class="ghm-form-section" style="flex:1;padding:16px 20px;">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--ghm-muted);margin-bottom:6px;">Occupancy Rate — <?php echo $month_name; ?></div>
      <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:36px;font-weight:700;color:var(--ghm-gold);"><?php echo $occ_rate; ?>%</div>
        <div style="flex:1;background:var(--ghm-surface2);border-radius:4px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,var(--ghm-gold),var(--ghm-gold-light));height:100%;width:<?php echo $occ_rate;?>%;border-radius:4px;"></div>
        </div>
        <div style="font-size:13px;color:var(--ghm-muted);"><?php echo $booked_cells; ?> / <?php echo $total_cells; ?> room-days</div>
      </div>
    </div>
  </div>
</div>
