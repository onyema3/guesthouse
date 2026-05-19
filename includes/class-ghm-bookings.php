<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Bookings {

    public static function get_bookings( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'status'      => '',
            'customer_id' => 0,
            'room_id'     => 0,
            'date_from'   => '',
            'date_to'     => '',
            'search'      => '',
            'limit'       => 20,
            'offset'      => 0,
        );
        $args   = wp_parse_args( $args, $defaults );
        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'b.status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['customer_id'] ) ) {
            $where[]  = 'b.customer_id = %d';
            $params[] = $args['customer_id'];
        }
        if ( ! empty( $args['room_id'] ) ) {
            $where[]  = 'b.room_id = %d';
            $params[] = $args['room_id'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'b.check_in >= %s';
            $params[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'b.check_out <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = '(b.booking_ref LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $limit_sql = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );

        $sql = "SELECT b.*,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                c.email AS customer_email, c.phone AS customer_phone,
                r.name AS room_name, r.type AS room_type, r.room_number
                FROM {$wpdb->prefix}ghm_bookings b
                LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
                LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = b.room_id
                WHERE $where_sql ORDER BY b.created_at DESC $limit_sql";

        return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
    }

    public static function get_booking( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*,
             CONCAT(c.first_name,' ',c.last_name) AS customer_name,
             c.email AS customer_email, c.phone AS customer_phone,
             r.name AS room_name, r.type AS room_type, r.room_number, r.price_night, r.price_hour
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
             LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = b.room_id
             WHERE b.id = %d", $id
        ) );
    }

    public static function get_booking_by_ref( $ref ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*,
             CONCAT(c.first_name,' ',c.last_name) AS customer_name,
             r.name AS room_name, r.room_number
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = b.customer_id
             LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = b.room_id
             WHERE b.booking_ref = %s", $ref
        ) );
    }

    public static function count_bookings( $args = array() ) {
        global $wpdb;
        $where = '1=1';
        if ( ! empty( $args['status'] ) ) $where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE $where" );
    }

    /**
     * Create a new booking.
     *
     * Status logic:
     *   - Default status = 'booked'  (reservation made, payment pending)
     *   - Status becomes 'confirmed' only when fully paid
     *   - Paystack init passes status = 'pending' temporarily; verify upgrades to 'confirmed'
     *   - Admin-created bookings also start as 'booked'
     */
    public static function create_booking( $data ) {
        global $wpdb;

        if ( ! GHM_Rooms::is_room_available( $data['room_id'], $data['check_in'], $data['check_out'] ) ) {
            return new WP_Error( 'not_available', __( 'Room is not available for the selected dates.', 'guesthouse-manager' ) );
        }

        $ref = self::generate_ref();

        // Determine initial status:
        //   'pending'  → Paystack init only (temp, overridden on verify)
        //   'booked'   → normal reservation without upfront payment
        $initial_status = sanitize_text_field( $data['status'] ?? 'booked' );
        if ( ! array_key_exists( $initial_status, self::get_statuses() ) ) {
            $initial_status = 'booked';
        }

        $fields = array(
            'booking_ref'      => $ref,
            'customer_id'      => absint( $data['customer_id'] ),
            'room_id'          => absint( $data['room_id'] ),
            'booking_type'     => sanitize_text_field( $data['booking_type']      ?? 'room' ),
            'check_in'         => sanitize_text_field( $data['check_in'] ),
            'check_out'        => sanitize_text_field( $data['check_out'] ),
            'adults'           => absint( $data['adults']                          ?? 1 ),
            'children'         => absint( $data['children']                        ?? 0 ),
            'total_amount'     => (float)( $data['total_amount']                  ?? 0 ),
            'paid_amount'      => 0.00,
            'status'           => $initial_status,
            'payment_status'   => 'unpaid',
            'special_requests' => sanitize_textarea_field( $data['special_requests'] ?? '' ),
            'notes'            => sanitize_textarea_field( $data['notes']            ?? '' ),
            'source'           => sanitize_text_field( $data['source']              ?? 'direct_website' ),
            'discount_amount'  => (float)( $data['discount_amount']               ?? 0 ),
            'tax_amount'       => (float)( $data['tax_amount']                    ?? 0 ),
            'created_by'       => get_current_user_id(),
        );

        $result = $wpdb->insert( $wpdb->prefix . 'ghm_bookings', $fields );
        if ( false === $result ) return new WP_Error( 'db_error', $wpdb->last_error );

        $booking_id = $wpdb->insert_id;

        // Mark room as reserved
        $wpdb->update( $wpdb->prefix . 'ghm_rooms', array( 'status' => 'reserved' ), array( 'id' => $data['room_id'] ) );

        // Increment customer visit count
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}ghm_customers SET visit_count = visit_count + 1 WHERE id = %d",
            $data['customer_id']
        ) );

        self::log( 'created_booking', $booking_id );
        do_action( 'ghm_booking_created', $booking_id, $fields );

        return $booking_id;
    }

    public static function update_booking( $id, $data ) {
        global $wpdb;
        $allowed = array(
            'status', 'payment_status', 'notes', 'special_requests',
            'check_in', 'check_out', 'adults', 'children',
            'total_amount', 'paid_amount',
        );
        $fields = array();
        foreach ( $allowed as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $fields[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }
        if ( empty( $fields ) ) return false;

        $wpdb->update( $wpdb->prefix . 'ghm_bookings', $fields, array( 'id' => $id ) );
        self::log( 'updated_booking', $id );
        do_action( 'ghm_booking_updated', $id, $fields );
        return true;
    }

    public static function cancel_booking( $id ) {
        global $wpdb;
        $booking = self::get_booking( $id );
        if ( ! $booking ) return new WP_Error( 'not_found', 'Booking not found.' );
        $wpdb->update( $wpdb->prefix . 'ghm_bookings', array( 'status' => 'cancelled' ), array( 'id' => $id ) );
        $wpdb->update( $wpdb->prefix . 'ghm_rooms',    array( 'status' => 'available' ), array( 'id' => $booking->room_id ) );
        self::log( 'cancelled_booking', $id );
        do_action( 'ghm_booking_cancelled', $id );
        return true;
    }

    public static function checkin_booking( $id ) {
        global $wpdb;
        $booking = self::get_booking( $id );
        if ( ! $booking ) return new WP_Error( 'not_found', 'Booking not found.' );
        $wpdb->update( $wpdb->prefix . 'ghm_bookings', array( 'status' => 'checked_in' ), array( 'id' => $id ) );
        $wpdb->update( $wpdb->prefix . 'ghm_rooms',    array( 'status' => 'occupied'   ), array( 'id' => $booking->room_id ) );
        self::log( 'checked_in_booking', $id );
        return true;
    }

    public static function checkout_booking( $id ) {
        global $wpdb;
        $booking = self::get_booking( $id );
        if ( ! $booking ) return new WP_Error( 'not_found', 'Booking not found.' );
        $wpdb->update( $wpdb->prefix . 'ghm_bookings', array( 'status' => 'checked_out' ), array( 'id' => $id ) );
        $wpdb->update( $wpdb->prefix . 'ghm_rooms',    array( 'status' => 'available'   ), array( 'id' => $booking->room_id ) );
        self::log( 'checked_out_booking', $id );
        do_action( 'ghm_booking_checked_out', $id );
        return true;
    }

    /**
     * Called by GHM_Payments::record_payment() after a payment is logged.
     * Upgrades booking status to 'confirmed' when fully paid.
     */
    public static function maybe_confirm_on_payment( $booking_id ) {
        global $wpdb;
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        // Only upgrade statuses that are not yet in a terminal/advanced state
        $upgradeable = array( 'booked', 'pending' );
        if ( ! in_array( $booking->status, $upgradeable, true ) ) return;

        $new_paid = (float) $booking->paid_amount;

        if ( $new_paid >= (float) $booking->total_amount && (float) $booking->total_amount > 0 ) {
            // Fully paid → confirmed
            $wpdb->update(
                $wpdb->prefix . 'ghm_bookings',
                array( 'status' => 'confirmed', 'payment_status' => 'paid' ),
                array( 'id' => $booking_id )
            );
            self::log( 'auto_confirmed_booking', $booking_id );
            do_action( 'ghm_booking_confirmed', $booking_id );
        }
    }

    /**
     * Billing rules:
     *   workspace  → hourly  (price_hour × hours)
     *   hall       → daily   (price_night used as day rate × days)
     *   room/suite/apartment → nightly (price_night × nights)
     */
    public static function calculate_amount( $room_id, $check_in, $check_out, $booking_type = 'room' ) {
        $room = GHM_Rooms::get_room( $room_id );
        if ( ! $room ) return 0;

        $in  = new DateTime( $check_in );
        $out = new DateTime( $check_out );

        $room_type = $room->type;

        // Hourly billing: workspace
        if ( $room_type === 'workspace' || $booking_type === 'workspace' ) {
            $diff   = $in->diff( $out );
            $hours  = $diff->days * 24 + $diff->h + ( $diff->i / 60 );
            $hours  = max( 1, $hours );
            $amount = round( $hours * (float) $room->price_hour, 2 );
            return (float) apply_filters( 'ghm_calculate_amount', $amount, $room, $check_in, $check_out );
        }

        // Daily billing: hall / meeting room
        if ( $room_type === 'hall' ) {
            $days   = max( 1, (int) $in->diff( $out )->days );
            $amount = round( $days * (float) $room->price_night, 2 );
            return (float) apply_filters( 'ghm_calculate_amount', $amount, $room, $check_in, $check_out );
        }

        // Nightly billing: room, suite, apartment
        $nights = max( 1, (int) $in->diff( $out )->days );
        $amount = round( $nights * (float) $room->price_night, 2 );
        return (float) apply_filters( 'ghm_calculate_amount', $amount, $room, $check_in, $check_out );
    }

    /**
     * Full status list including the new 'booked' status.
     *
     * Flow:
     *   booked → confirmed (on full payment)
     *   booked / confirmed → checked_in → checked_out
     *   any → cancelled / no_show
     */
    public static function get_statuses() {
        return array(
            'pending'     => __( 'Pending',      'guesthouse-manager' ),
            'booked'      => __( 'Booked',       'guesthouse-manager' ),
            'confirmed'   => __( 'Confirmed',    'guesthouse-manager' ),
            'checked_in'  => __( 'Checked In',   'guesthouse-manager' ),
            'checked_out' => __( 'Checked Out',  'guesthouse-manager' ),
            'cancelled'   => __( 'Cancelled',    'guesthouse-manager' ),
            'no_show'     => __( 'No Show',      'guesthouse-manager' ),
        );
    }

    private static function generate_ref() {
        return 'GHM-' . strtoupper( substr( uniqid(), -6 ) ) . '-' . date( 'Ymd' );
    }

    private static function log( $action, $id ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ghm_activity_log', array(
            'user_id'     => get_current_user_id(),
            'action'      => $action,
            'object_type' => 'booking',
            'object_id'   => $id,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ) );
    }
}
