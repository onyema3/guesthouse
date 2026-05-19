<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Rooms {

    public static function get_rooms( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'type'    => '',
            'status'  => '',
            'search'  => '',
            'limit'   => 20,
            'offset'  => 0,
            'orderby' => 'id',
            'order'   => 'ASC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $params = array();

        if ( ! empty( $args['type'] ) ) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[] = '(name LIKE %s OR room_number LIKE %s)';
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $order_sql  = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'id ASC';
        $limit_sql  = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );

        if ( $params ) {
            $sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ghm_rooms WHERE $where_sql ORDER BY $order_sql $limit_sql", $params );
        } else {
            $sql = "SELECT * FROM {$wpdb->prefix}ghm_rooms WHERE $where_sql ORDER BY $order_sql $limit_sql";
        }

        return $wpdb->get_results( $sql );
    }

    public static function get_room( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ghm_rooms WHERE id = %d", $id ) );
    }

    public static function count_rooms( $args = array() ) {
        global $wpdb;
        $where = '1=1';
        if ( ! empty( $args['type'] ) )   $where .= $wpdb->prepare( ' AND type = %s', $args['type'] );
        if ( ! empty( $args['status'] ) ) $where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms WHERE $where" );
    }

    public static function save_room( $data, $id = 0 ) {
        global $wpdb;
        $fields = array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'type'        => sanitize_text_field( $data['type'] ?? 'room' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'capacity'    => absint( $data['capacity'] ?? 1 ),
            'price_night' => (float) ( $data['price_night'] ?? 0 ),
            'price_hour'  => (float) ( $data['price_hour'] ?? 0 ),
            'status'      => sanitize_text_field( $data['status'] ?? 'available' ),
            'floor'       => sanitize_text_field( $data['floor'] ?? '' ),
            'room_number' => sanitize_text_field( $data['room_number'] ?? '' ),
            'amenities'   => is_array( $data['amenities'] ?? null ) ? json_encode( $data['amenities'] ) : ( $data['amenities'] ?? '' ),
        );

        if ( $id > 0 ) {
            $result = $wpdb->update( $wpdb->prefix . 'ghm_rooms', $fields, array( 'id' => $id ) );
            self::log( 'updated_room', $id );
            return $id;
        } else {
            $wpdb->insert( $wpdb->prefix . 'ghm_rooms', $fields );
            $new_id = $wpdb->insert_id;
            self::log( 'created_room', $new_id );
            return $new_id;
        }
    }

    public static function delete_room( $id ) {
        global $wpdb;
        // Check for active bookings
        $active = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE room_id = %d AND status NOT IN ('cancelled','checked_out')",
            $id
        ) );
        if ( $active > 0 ) return new WP_Error( 'has_bookings', __( 'Cannot delete room with active bookings.', 'guesthouse-manager' ) );
        return $wpdb->delete( $wpdb->prefix . 'ghm_rooms', array( 'id' => $id ) );
    }

    public static function is_room_available( $room_id, $check_in, $check_out, $exclude_booking_id = 0 ) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings
             WHERE room_id = %d
             AND status NOT IN ('cancelled')
             AND id != %d
             AND NOT (check_out <= %s OR check_in >= %s)",
            $room_id, $exclude_booking_id, $check_in, $check_out
        );
        return (int) $wpdb->get_var( $sql ) === 0;
    }

    public static function get_room_types() {
        return array(
            'room'      => __( 'Room',                     'guesthouse-manager' ),
            'suite'     => __( 'Suite',                    'guesthouse-manager' ),
            'apartment' => __( 'Apartment',                'guesthouse-manager' ),
            'workspace' => __( 'Workspace',                'guesthouse-manager' ),
            'hall'      => __( 'Event Hall / Meeting Room','guesthouse-manager' ),
        );
    }

    /**
     * Returns the billing unit label for a given room type.
     * Used in UI to show the correct price label.
     */
    public static function get_billing_unit( $room_type ) {
        switch ( $room_type ) {
            case 'workspace': return 'hour';
            case 'hall':      return 'day';
            default:          return 'night';
        }
    }

    /**
     * Returns the price to display for a room (and the rate label).
     */
    public static function get_display_price( $room ) {
        switch ( $room->type ) {
            case 'workspace':
                return array( 'price' => (float) $room->price_hour,  'unit' => '/hr',    'label' => 'per hour' );
            case 'hall':
                return array( 'price' => (float) $room->price_night, 'unit' => '/day',   'label' => 'per day'  );
            default:
                return array( 'price' => (float) $room->price_night, 'unit' => '/night', 'label' => 'per night');
        }
    }

    public static function get_room_statuses() {
        return array(
            'available'   => __( 'Available', 'guesthouse-manager' ),
            'occupied'    => __( 'Occupied', 'guesthouse-manager' ),
            'maintenance' => __( 'Maintenance', 'guesthouse-manager' ),
            'reserved'    => __( 'Reserved', 'guesthouse-manager' ),
            'inactive'    => __( 'Inactive', 'guesthouse-manager' ),
        );
    }

    private static function log( $action, $id ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ghm_activity_log', array(
            'user_id'     => get_current_user_id(),
            'action'      => $action,
            'object_type' => 'room',
            'object_id'   => $id,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ) );
    }
}
