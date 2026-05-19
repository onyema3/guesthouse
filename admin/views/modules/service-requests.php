<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$filter        = sanitize_key( $_GET['status'] ?? 'open' );
$service_types = class_exists( 'GHM_Guest_Portal' ) ? GHM_Guest_Portal::get_service_types() : array();

// "open" virtual filter = pending + in_progress
$where = '';
$params = array();
if ( $filter === 'open' ) {
    $where = "WHERE sr.status IN ('pending','in_progress')";
} elseif ( $filter === 'all' ) {
    $where = '';
} else {
    $where = 'WHERE sr.status = %s';
    $params[] = $filter;
}

$sql = "SELECT sr.*,
               b.booking_ref, b.room_id,
               r.name AS room_name, r.room_number,
               CONCAT(c.first_name,' ',c.last_name) AS guest_name,
               c.phone AS guest_phone, c.email AS guest_email
          FROM {$wpdb->prefix}ghm_service_requests sr
          LEFT JOIN {$wpdb->prefix}ghm_bookings  b ON b.id  = sr.booking_id
          LEFT JOIN {$wpdb->prefix}ghm_rooms     r ON r.id  = b.room_id
          LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id  = sr.customer_id
          $where
          ORDER BY FIELD(sr.status,'pending','in_progress','resolved','cancelled'),
                   sr.created_at DESC
          LIMIT 200";

$requests = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

// Counts for tabs
$counts = array(
    'open'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_service_requests WHERE status IN ('pending','in_progress')" ),
    'pending'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_service_requests WHERE status='pending'" ),
    'in_progress' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_service_requests WHERE status='in_progress'" ),
    'resolved'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_service_requests WHERE status='resolved'" ),
    'all'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_service_requests" ),
);

$status_meta = array(
    'pending'     => array( 'label' => 'Pending',     'color' => '#9ca3af' ),
    'in_progress' => array( 'label' => 'In Progress', 'color' => '#f59e0b' ),
    'resolved'    => array( 'label' => 'Resolved',    'color' => '#3ecf8e' ),
    'cancelled'   => array( 'label' => 'Cancelled',   'color' => '#ef4444' ),
);
?>
<div id="ghm-wrap">
  <div class="ghm-page-header">
    <h1><span class="dashicons dashicons-bell"></span> Guest Service Requests</h1>
    <span style="font-size:12px;color:var(--ghm-muted);">
      Live requests submitted from the Guest Portal.
    </span>
  </div>

  <div class="ghm-toolbar">
    <div class="ghm-filter-tabs">
      <a href="?page=ghm-service-requests&status=open"        class="<?php echo $filter==='open'?'active':''; ?>">
        Open <span class="ghm-badge"><?php echo (int) $counts['open']; ?></span>
      </a>
      <a href="?page=ghm-service-requests&status=pending"     class="<?php echo $filter==='pending'?'active':''; ?>">
        Pending <span class="ghm-badge"><?php echo (int) $counts['pending']; ?></span>
      </a>
      <a href="?page=ghm-service-requests&status=in_progress" class="<?php echo $filter==='in_progress'?'active':''; ?>">
        In Progress <span class="ghm-badge"><?php echo (int) $counts['in_progress']; ?></span>
      </a>
      <a href="?page=ghm-service-requests&status=resolved"    class="<?php echo $filter==='resolved'?'active':''; ?>">
        Resolved <span class="ghm-badge"><?php echo (int) $counts['resolved']; ?></span>
      </a>
      <a href="?page=ghm-service-requests&status=all"         class="<?php echo $filter==='all'?'active':''; ?>">
        All <span class="ghm-badge"><?php echo (int) $counts['all']; ?></span>
      </a>
    </div>
  </div>

  <?php if ( empty( $requests ) ): ?>
  <div class="ghm-empty">
    <span class="dashicons dashicons-bell"></span>
    <p>No <?php echo esc_html( $filter === 'all' ? '' : $filter ); ?> service requests.</p>
  </div>
  <?php else: ?>
  <div class="ghm-table-wrap">
    <table class="ghm-table" id="ghm-sr-table">
      <thead>
        <tr>
          <th>Reported</th>
          <th>Guest / Room</th>
          <th>Type</th>
          <th>Message</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $requests as $sr ):
          $type_label = $service_types[ $sr->type ] ?? ucfirst( str_replace( '_', ' ', $sr->type ) );
          $sm         = $status_meta[ $sr->status ] ?? array( 'label' => ucfirst( $sr->status ), 'color' => '#9ca3af' );
        ?>
        <tr>
          <td>
            <div><?php echo esc_html( date( 'M j, Y', strtotime( $sr->created_at ) ) ); ?></div>
            <div class="room-meta"><?php echo esc_html( date( 'g:i A', strtotime( $sr->created_at ) ) ); ?></div>
          </td>
          <td>
            <div class="room-name"><?php echo esc_html( $sr->guest_name ?: 'Guest' ); ?></div>
            <div class="room-meta">
              <?php echo esc_html( $sr->room_name ?: '—' ); ?>
              <?php if ( $sr->room_number ): ?>· <?php echo esc_html( $sr->room_number ); ?><?php endif; ?>
            </div>
            <?php if ( $sr->booking_ref ): ?>
            <div class="room-meta" style="color:var(--ghm-gold);font-family:monospace;font-size:11px;">
              <?php echo esc_html( $sr->booking_ref ); ?>
            </div>
            <?php endif; ?>
          </td>
          <td><?php echo esc_html( $type_label ); ?></td>
          <td style="max-width:320px;">
            <?php if ( $sr->message ): ?>
              <div style="white-space:pre-wrap;font-size:13px;color:var(--ghm-text);"><?php echo esc_html( $sr->message ); ?></div>
            <?php else: ?>
              <span style="color:var(--ghm-muted);">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="ghm-badge" style="background:<?php echo esc_attr( $sm['color'] ); ?>22;color:<?php echo esc_attr( $sm['color'] ); ?>;">
              <?php echo esc_html( $sm['label'] ); ?>
            </span>
            <?php if ( $sr->resolved_at ): ?>
            <div class="room-meta" style="margin-top:4px;">
              Resolved <?php echo esc_html( date( 'M j, g:i A', strtotime( $sr->resolved_at ) ) ); ?>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php if ( $sr->status === 'pending' ): ?>
              <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-sr-status" data-id="<?php echo (int) $sr->id; ?>" data-status="in_progress">
                Start
              </button>
              <?php endif; ?>
              <?php if ( $sr->status !== 'resolved' ): ?>
              <button class="ghm-btn ghm-btn-success ghm-btn-sm ghm-sr-status" data-id="<?php echo (int) $sr->id; ?>" data-status="resolved">
                ✓ Resolve
              </button>
              <?php endif; ?>
              <?php if ( $sr->status !== 'resolved' && $sr->status !== 'cancelled' ): ?>
              <button class="ghm-btn ghm-btn-outline ghm-btn-sm ghm-sr-status" data-id="<?php echo (int) $sr->id; ?>" data-status="cancelled">
                Cancel
              </button>
              <?php endif; ?>
              <button class="ghm-btn ghm-btn-danger ghm-btn-sm ghm-sr-delete" data-id="<?php echo (int) $sr->id; ?>" title="Delete">
                <span class="dashicons dashicons-trash"></span>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function($){
  $(document)
    .on('click', '.ghm-sr-status', function(){
      const id = $(this).data('id');
      const status = $(this).data('status');
      GHM.post('ghm_update_service_request', { id, status })
        .then(()=>{ GHM.toast('Request updated.', 'success'); setTimeout(()=>location.reload(), 600); })
        .catch(e=>GHM.toast(e || 'Failed to update.', 'error'));
    })
    .on('click', '.ghm-sr-delete', function(){
      if (!confirm('Delete this service request?')) return;
      const id = $(this).data('id');
      GHM.post('ghm_delete_service_request', { id })
        .then(()=>{ GHM.toast('Deleted.', 'success'); setTimeout(()=>location.reload(), 600); })
        .catch(e=>GHM.toast(e || 'Failed to delete.', 'error'));
    });
})(jQuery);
</script>
