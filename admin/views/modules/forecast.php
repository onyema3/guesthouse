<?php if ( ! defined( 'ABSPATH' ) ) exit;
$days     = absint($_GET['days'] ?? 30);
$forecast = GHM_Forecasting::get_forecast($days);
$sym      = get_option('ghm_currency_symbol','₦');
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-chart-area"></span> Revenue Forecast</h1>
    <div style="display:flex;gap:8px;align-items:center;">
      <?php foreach ([7,14,30,60,90] as $d): ?>
      <a href="?page=ghm-forecast&days=<?php echo $d;?>"
         class="ghm-btn <?php echo $days===$d?'ghm-btn-primary':'ghm-btn-outline';?> ghm-btn-sm">
        <?php echo $d;?>d
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="ghm-stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
    <div class="ghm-stat-card">
      <div class="stat-label">Expected Revenue (<?php echo $days;?> days)</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym);?>"><?php echo number_format($forecast['total_expected'],2);?></div>
      <div class="room-meta" style="margin-top:4px;font-size:12px;color:var(--ghm-muted);">From booked + confirmed</div>
    </div>
    <div class="ghm-stat-card accent-green">
      <div class="stat-label">Confirmed Revenue</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym);?>"><?php echo number_format($forecast['total_confirmed'],2);?></div>
      <div class="room-meta" style="margin-top:4px;font-size:12px;color:var(--ghm-muted);">Fully confirmed bookings</div>
    </div>
    <div class="ghm-stat-card accent-warning">
      <div class="stat-label">At Risk</div>
      <div class="stat-value money" data-symbol="<?php echo esc_attr($sym);?>"><?php echo number_format($forecast['total_expected']-$forecast['total_confirmed'],2);?></div>
      <div class="room-meta" style="margin-top:4px;font-size:12px;color:var(--ghm-muted);">Booked but unpaid</div>
    </div>
  </div>

  <!-- Daily forecast chart -->
  <div class="ghm-chart-card" style="margin-bottom:24px;">
    <h3>Daily Revenue Projection — Next <?php echo $days;?> Days</h3>
    <canvas id="ghm-forecast-chart" style="max-height:280px;"></canvas>
  </div>

  <!-- Daily breakdown table -->
  <div class="ghm-table-wrap">
    <table class="ghm-table">
      <thead><tr><th>Date</th><th>Day</th><th>Expected Revenue</th><th>Bar</th></tr></thead>
      <tbody>
        <?php
        $max_day = max(array_values($forecast['daily']) ?: [1]);
        foreach ($forecast['daily'] as $date => $amount):
          $dow    = date('D', strtotime($date));
          $is_wknd= in_array($dow,array('Sat','Sun'));
          $pct    = $max_day > 0 ? round(($amount/$max_day)*100) : 0;
        ?>
        <tr style="<?php echo $is_wknd?'background:rgba(201,168,76,.04);':'';?>">
          <td><?php echo date('M j, Y',strtotime($date));?></td>
          <td style="color:<?php echo $is_wknd?'var(--ghm-gold)':'var(--ghm-muted)';?>;font-weight:<?php echo $is_wknd?'600':'400';?>">
            <?php echo $dow;?>
          </td>
          <td>
            <?php if ($amount > 0): ?>
            <strong><?php echo $sym.number_format($amount,2);?></strong>
            <?php else: ?>
            <span style="color:var(--ghm-muted)">—</span>
            <?php endif; ?>
          </td>
          <td style="width:200px;">
            <?php if ($amount > 0): ?>
            <div style="background:var(--ghm-surface2);border-radius:3px;height:8px;overflow:hidden;">
              <div style="background:var(--ghm-gold);height:100%;width:<?php echo $pct;?>%;border-radius:3px;"></div>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<input type="hidden" id="ghm-forecast-data" value='<?php echo esc_attr(json_encode(array_values($forecast['daily']))); ?>'>
<input type="hidden" id="ghm-forecast-labels" value='<?php echo esc_attr(json_encode(array_keys($forecast['daily']))); ?>'>

<script>
(function(){
  const ctx    = document.getElementById('ghm-forecast-chart');
  const data   = JSON.parse(document.getElementById('ghm-forecast-data').value);
  const labels = JSON.parse(document.getElementById('ghm-forecast-labels').value);
  if (!ctx||!data.length) return;
  new Chart(ctx,{
    type:'bar',
    data:{
      labels: labels.map(d=>{ const dt=new Date(d); return dt.toLocaleDateString('en',{month:'short',day:'numeric'}); }),
      datasets:[{
        label:'Projected Revenue',
        data,
        backgroundColor: labels.map(d=>{
          const day=new Date(d).getDay();
          return (day===0||day===6)?'rgba(201,168,76,.8)':'rgba(201,168,76,.4)';
        }),
        borderColor:'#c9a84c',
        borderWidth:1,
        borderRadius:3,
      }]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{display:false}},
      scales:{
        x:{ticks:{color:'#7a7f96',maxTicksLimit:15},grid:{color:'rgba(255,255,255,.05)'}},
        y:{ticks:{color:'#7a7f96',callback:v=>'<?php echo $sym;?>'+Number(v).toLocaleString()},grid:{color:'rgba(255,255,255,.05)'}},
      }
    }
  });
})();
</script>
