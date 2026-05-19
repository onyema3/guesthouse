<?php if ( ! defined( 'ABSPATH' ) ) exit;
$statuses  = GHM_Bookings::get_statuses();
$all_rooms = GHM_Rooms::get_rooms( array( 'limit' => 200 ) );
?>
<script>window.ghmRooms = <?php echo json_encode( array_values($all_rooms) ); ?>;</script>

<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-calendar-alt"></span> Bookings</h1>
    <?php if ( current_user_can('ghm_manage_bookings') ): ?>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-booking">
      <span class="dashicons dashicons-plus-alt"></span> New Booking
    </button>
    <?php endif; ?>
  </div>

  <!-- Status legend -->
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;font-size:12px;">
    <span style="color:var(--ghm-muted);">Status flow:</span>
    <span class="ghm-badge booked">Booked</span>
    <span style="color:var(--ghm-muted);">→ pay →</span>
    <span class="ghm-badge confirmed">Confirmed</span>
    <span style="color:var(--ghm-muted);">→</span>
    <span class="ghm-badge checked_in">Checked In</span>
    <span style="color:var(--ghm-muted);">→</span>
    <span class="ghm-badge checked_out">Checked Out</span>
  </div>

  <div class="ghm-toolbar">
    <div class="ghm-search-box">
      <span class="dashicons dashicons-search"></span>
      <input type="text" id="ghm-booking-search" placeholder="Search ref, guest, email…">
    </div>
    <div class="ghm-filter-tabs">
      <a href="?page=ghm-bookings" class="<?php echo empty($_GET['status'])?'active':''; ?>">All</a>
      <?php foreach ($statuses as $k => $v): ?>
      <a href="?page=ghm-bookings&status=<?php echo $k; ?>" class="<?php echo ($_GET['status']??'')===$k?'active':''; ?>"><?php echo $v; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ( empty($bookings) ): ?>
  <div class="ghm-empty">
    <span class="dashicons dashicons-calendar-alt"></span>
    <p>No bookings found<?php echo !empty($_GET['status']) ? ' with status "'.esc_html($statuses[$_GET['status']]??$_GET['status']).'"' : ''; ?>.</p>
    <button class="ghm-btn ghm-btn-primary" id="ghm-btn-add-booking">Create First Booking</button>
  </div>
  <?php else: ?>

  <div class="ghm-table-wrap">
    <table class="ghm-table" id="ghm-bookings-table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Guest</th>
          <th>Room</th>
          <th>Check-In</th>
          <th>Check-Out</th>
          <th>Total</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sym = get_option('ghm_currency_symbol','₦');
        foreach ($bookings as $b):
            $status      = $b->status;
            $pay_status  = $b->payment_status;
            $balance     = (float)$b->total_amount - (float)$b->paid_amount;
            $is_terminal = in_array($status, ['cancelled','checked_out','no_show']);
            $can_pay     = !$is_terminal && $pay_status !== 'paid';
            $can_checkin = in_array($status, ['booked','confirmed']);
            $can_checkout= $status === 'checked_in';
            $can_cancel  = !$is_terminal;
        ?>
        <tr>
          <td>
            <strong style="color:var(--ghm-gold);font-size:12px;letter-spacing:.5px;">
              <?php echo esc_html($b->booking_ref); ?>
            </strong>
            <div class="room-meta" style="margin-top:3px;">
              <?php echo date('M j, Y', strtotime($b->created_at)); ?>
            </div>
          </td>
          <td>
            <div class="room-name"><?php echo esc_html($b->customer_name); ?></div>
            <div class="room-meta"><?php echo esc_html($b->customer_email); ?></div>
          </td>
          <td>
            <div class="room-name"><?php echo esc_html($b->room_name); ?></div>
            <div class="room-meta"><?php echo esc_html($b->room_number); ?> &middot; <?php echo esc_html(ucfirst($b->room_type)); ?></div>
          </td>
          <td><?php echo date('M j, Y', strtotime($b->check_in)); ?></td>
          <td><?php echo date('M j, Y', strtotime($b->check_out)); ?></td>
          <td>
            <strong><?php echo $sym.number_format($b->total_amount,2); ?></strong>
            <?php if ($balance > 0 && !$is_terminal): ?>
            <div class="room-meta" style="color:var(--ghm-danger);">
              Bal: <?php echo $sym.number_format($balance,2); ?>
            </div>
            <?php elseif ($pay_status === 'paid'): ?>
            <div class="room-meta" style="color:var(--ghm-success);">Fully paid</div>
            <?php endif; ?>
          </td>
          <td>
            <?php
            // Badge CSS class
            $badge_class = $status;
            echo '<span class="ghm-badge '.$badge_class.'">'.esc_html($statuses[$status] ?? ucfirst($status)).'</span>';

            // If booked + unpaid, show a small note
            if ($status === 'booked' && $pay_status === 'unpaid'):
            ?>
            <div class="room-meta" style="margin-top:4px;color:var(--ghm-warning);">
              ⏳ Awaiting payment
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php $pay_labels = ['paid'=>'Paid','partial'=>'Partial','unpaid'=>'Unpaid','failed'=>'Failed'];
            $pay_badge = ['paid'=>'paid','partial'=>'partial','unpaid'=>'unpaid','failed'=>'cancelled'];
            ?>
            <span class="ghm-badge <?php echo esc_attr($pay_badge[$pay_status]??'unpaid'); ?>">
              <?php echo $pay_labels[$pay_status] ?? ucfirst($pay_status); ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">

              <?php if ($can_pay): ?>
              <button class="ghm-btn ghm-btn-primary ghm-btn-sm ghm-record-payment-btn"
                      data-id="<?php echo $b->id; ?>"
                      title="Record payment — will auto-confirm when fully paid">
                <span class="dashicons dashicons-money-alt" style="font-size:13px;margin-top:2px;"></span>
                <?php echo $pay_status === 'partial' ? 'Add Payment' : 'Pay'; ?>
              </button>
              <?php endif; ?>

              <?php if ($can_checkin): ?>
              <button class="ghm-btn ghm-btn-success ghm-btn-sm ghm-checkin-btn"
                      data-id="<?php echo $b->id; ?>"
                      title="Check guest in">
                Check In
              </button>
              <?php endif; ?>

              <?php if ($can_checkout): ?>
              <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-checkout-btn"
                      data-id="<?php echo $b->id; ?>"
                      title="Check guest out">
                Check Out
              </button>
              <?php endif; ?>

              <?php if ($can_cancel): ?>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-cancel-btn"
                      data-id="<?php echo $b->id; ?>"
                      title="Cancel booking">
                <span class="dashicons dashicons-no" style="font-size:13px;margin-top:2px;"></span> Cancel
              </button>
              <?php endif; ?>
              <a href="<?php echo esc_url(GHM_Invoice::get_url($b->id)); ?>"
                 target="_blank" class="ghm-btn ghm-btn-outline ghm-btn-sm" title="Download Invoice">
                <span class="dashicons dashicons-download" style="font-size:13px;margin-top:2px;"></span>
              </a>

            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total > 20): ?>
  <div style="margin-top:16px;display:flex;gap:8px;justify-content:center;">
    <?php
    $current = absint($_GET['paged'] ?? 1);
    $pages   = ceil($total / 20);
    for ($i = 1; $i <= $pages; $i++):
    ?>
    <a href="?page=ghm-bookings&paged=<?php echo $i; ?>" class="ghm-btn <?php echo $i===$current?'ghm-btn-primary':'ghm-btn-outline'; ?> ghm-btn-sm"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
