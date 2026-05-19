<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Maintenance {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_maintenance (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id      BIGINT UNSIGNED NOT NULL,
            title        VARCHAR(200) NOT NULL,
            description  TEXT,
            priority     VARCHAR(20) NOT NULL DEFAULT 'normal',
            status       VARCHAR(50) NOT NULL DEFAULT 'open',
            category     VARCHAR(100),
            assigned_to  BIGINT UNSIGNED DEFAULT NULL,
            reported_by  BIGINT UNSIGNED DEFAULT NULL,
            resolved_at  DATETIME DEFAULT NULL,
            images       LONGTEXT,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY room_id  (room_id),
            KEY status   (status),
            KEY priority (priority)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_requests( $args = array() ) {
        global $wpdb;
        $where  = '1=1';
        $params = array();
        if ( ! empty($args['status']) )  { $where .= ' AND m.status = %s';   $params[] = $args['status']; }
        if ( ! empty($args['room_id']) ) { $where .= ' AND m.room_id = %d';  $params[] = $args['room_id']; }
        if ( ! empty($args['priority'])){ $where .= ' AND m.priority = %s';  $params[] = $args['priority']; }
        $limit = isset($args['limit']) ? 'LIMIT ' . absint($args['limit']) : 'LIMIT 50';
        $sql   = "SELECT m.*, r.name AS room_name, r.room_number,
                  u.display_name AS assigned_name, ru.display_name AS reporter_name
                  FROM {$wpdb->prefix}ghm_maintenance m
                  LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = m.room_id
                  LEFT JOIN {$wpdb->users} u  ON u.ID  = m.assigned_to
                  LEFT JOIN {$wpdb->users} ru ON ru.ID = m.reported_by
                  WHERE $where ORDER BY FIELD(m.priority,'urgent','high','normal','low'), m.created_at DESC $limit";
        return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
    }

    public static function save( $data, $id = 0 ) {
        global $wpdb;

        // For partial updates (e.g. status-only "Resolve"), merge with the existing row
        // so we don't accidentally blank required fields like title/room_id.
        $existing = null;
        if ( $id > 0 ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ghm_maintenance WHERE id = %d", $id
            ), ARRAY_A );
        }

        $merged = function( $key, $default = '' ) use ( $data, $existing ) {
            if ( array_key_exists( $key, (array) $data ) && $data[ $key ] !== '' && $data[ $key ] !== null ) {
                return $data[ $key ];
            }
            if ( $existing && array_key_exists( $key, $existing ) ) {
                return $existing[ $key ];
            }
            return $default;
        };

        $fields = array(
            'room_id'     => absint( $merged( 'room_id', 0 ) ),
            'title'       => sanitize_text_field( $merged( 'title', '' ) ),
            'description' => sanitize_textarea_field( $merged( 'description', '' ) ),
            'priority'    => sanitize_text_field( $merged( 'priority', 'normal' ) ),
            'status'      => sanitize_text_field( $merged( 'status', 'open' ) ),
            'category'    => sanitize_text_field( $merged( 'category', '' ) ),
            'assigned_to' => $merged( 'assigned_to', null ) ? absint( $merged( 'assigned_to', null ) ) : null,
            'reported_by' => $existing ? $existing['reported_by'] : ( get_current_user_id() ?: null ),
        );
        if ( $fields['status'] === 'resolved' ) {
            $fields['resolved_at'] = current_time('mysql');
            // Free the room from maintenance status if no other open requests
            $open = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_maintenance WHERE room_id=%d AND status='open' AND id!=%d",
                $fields['room_id'], $id
            ) );
            if ( ! $open && $fields['room_id'] ) {
                $wpdb->update( $wpdb->prefix . 'ghm_rooms', array('status'=>'available'), array('id'=>$fields['room_id']) );
            }
        } elseif ( $fields['status'] === 'open' || $fields['status'] === 'in_progress' ) {
            // Mark room as under maintenance
            if ( $fields['room_id'] ) {
                $wpdb->update( $wpdb->prefix . 'ghm_rooms', array('status'=>'maintenance'), array('id'=>$fields['room_id']) );
            }
        }
        if ( $id > 0 ) {
            $wpdb->update( $wpdb->prefix . 'ghm_maintenance', $fields, array('id'=>$id) );
            return $id;
        }
        // For new records, title and room_id are required
        if ( ! $fields['title'] || ! $fields['room_id'] ) {
            return new WP_Error( 'ghm_maintenance_required', 'Title and room are required.' );
        }
        $wpdb->insert( $wpdb->prefix . 'ghm_maintenance', $fields );
        return $wpdb->insert_id;
    }

    public static function count_open() {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ghm_maintenance WHERE status IN ('open','in_progress')");
    }

    public static function get_categories() {
        return array('plumbing','electrical','hvac','furniture','electronics','cleaning','safety','structural','other');
    }
    public static function get_statuses() {
        return array('open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','deferred'=>'Deferred');
    }
    public static function get_priorities() {
        return array('low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent');
    }
}
