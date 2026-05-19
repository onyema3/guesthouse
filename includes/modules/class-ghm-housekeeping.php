<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Housekeeping {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_housekeeping (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id      BIGINT UNSIGNED NOT NULL,
            status       VARCHAR(50) NOT NULL DEFAULT 'dirty',
            assigned_to  BIGINT UNSIGNED DEFAULT NULL,
            notes        TEXT,
            priority     VARCHAR(20) NOT NULL DEFAULT 'normal',
            started_at   DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY room_id  (room_id),
            KEY status   (status)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $where  = '1=1';
        $params = array();
        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND h.status = %s';
            $params[] = $args['status'];
        }
        $sql = "SELECT h.*, r.name AS room_name, r.room_number, r.floor, r.type AS room_type,
                u.display_name AS assigned_name
                FROM {$wpdb->prefix}ghm_housekeeping h
                LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = h.room_id
                LEFT JOIN {$wpdb->users} u ON u.ID = h.assigned_to
                WHERE $where ORDER BY h.priority DESC, h.updated_at DESC";
        return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
    }

    public static function get_by_room( $room_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT h.*, r.name AS room_name, r.room_number FROM {$wpdb->prefix}ghm_housekeeping h
             LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = h.room_id WHERE h.room_id = %d
             ORDER BY h.id DESC LIMIT 1", $room_id
        ) );
    }

    public static function upsert( $room_id, $data ) {
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ghm_housekeeping WHERE room_id = %d", $room_id
        ) );

        $fields = array(
            'room_id'     => absint( $room_id ),
            'status'      => sanitize_text_field( $data['status']      ?? 'dirty' ),
            'assigned_to' => !empty($data['assigned_to']) ? absint($data['assigned_to']) : null,
            'notes'       => sanitize_textarea_field( $data['notes']   ?? '' ),
            'priority'    => sanitize_text_field( $data['priority']    ?? 'normal' ),
        );

        if ( $fields['status'] === 'cleaning' && empty( $existing ) ) {
            $fields['started_at'] = current_time('mysql');
        }
        if ( $fields['status'] === 'clean' || $fields['status'] === 'inspected' ) {
            $fields['completed_at'] = current_time('mysql');
        }

        if ( $existing ) {
            $wpdb->update( $wpdb->prefix . 'ghm_housekeeping', $fields, array('id' => $existing) );
            return $existing;
        } else {
            $wpdb->insert( $wpdb->prefix . 'ghm_housekeeping', $fields );
            return $wpdb->insert_id;
        }
    }

    // Called automatically when a guest checks out
    public static function mark_dirty_on_checkout( $booking_id ) {
        $booking = GHM_Bookings::get_booking( $booking_id );
        if ( ! $booking ) return;
        self::upsert( $booking->room_id, array(
            'status'   => 'dirty',
            'priority' => 'high',
            'notes'    => 'Auto-flagged after checkout of booking ' . $booking->booking_ref,
        ) );
    }

    public static function get_statuses() {
        return array(
            'dirty'     => array( 'label' => 'Dirty',        'color' => '#ef4444', 'icon' => '🔴' ),
            'cleaning'  => array( 'label' => 'Being Cleaned','color' => '#f59e0b', 'icon' => '🟡' ),
            'clean'     => array( 'label' => 'Clean',        'color' => '#3ecf8e', 'icon' => '🟢' ),
            'inspected' => array( 'label' => 'Inspected',    'color' => '#60a5fa', 'icon' => '✅' ),
            'do_not_disturb' => array( 'label' => 'Do Not Disturb', 'color' => '#a78bfa', 'icon' => '🟣' ),
        );
    }

    public static function get_priorities() {
        return array(
            'low'    => 'Low',
            'normal' => 'Normal',
            'high'   => 'High — Guest Arriving Soon',
            'urgent' => 'Urgent',
        );
    }
}

// Auto-flag room dirty on checkout
add_action( 'ghm_booking_checked_out', array( 'GHM_Housekeeping', 'mark_dirty_on_checkout' ) );
