<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Workspaces {

    public static function get_workspaces( $args = array() ) {
        $args['type'] = 'workspace';
        return GHM_Rooms::get_rooms( $args );
    }

    public static function get_workspace( $id ) {
        $room = GHM_Rooms::get_room( $id );
        return ( $room && $room->type === 'workspace' ) ? $room : null;
    }

    public static function get_available_workspaces( $check_in, $check_out ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.* FROM {$wpdb->prefix}ghm_rooms r
             WHERE r.type = 'workspace' AND r.status != 'inactive'
             AND r.id NOT IN (
                 SELECT b.room_id FROM {$wpdb->prefix}ghm_bookings b
                 WHERE b.status NOT IN ('cancelled')
                 AND NOT (b.check_out <= %s OR b.check_in >= %s)
             )
             ORDER BY r.price_hour ASC",
            $check_in, $check_out
        ) );
    }

    public static function get_available_rooms( $check_in, $check_out, $type = 'room' ) {
        global $wpdb;
        $type_condition = $type === 'workspace' ? "r.type = 'workspace'" : "r.type != 'workspace'";
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.* FROM {$wpdb->prefix}ghm_rooms r
             WHERE $type_condition AND r.status != 'inactive'
             AND r.id NOT IN (
                 SELECT b.room_id FROM {$wpdb->prefix}ghm_bookings b
                 WHERE b.status NOT IN ('cancelled')
                 AND NOT (b.check_out <= %s OR b.check_in >= %s)
             )
             ORDER BY r.price_night ASC",
            $check_in, $check_out
        ) );
    }
}
