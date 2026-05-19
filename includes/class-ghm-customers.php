<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Customers {

    public static function get_customers( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'search'  => '',
            'status'  => '',
            'limit'   => 20,
            'offset'  => 0,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );
        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $limit_sql  = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
        $sql = "SELECT * FROM {$wpdb->prefix}ghm_customers WHERE $where_sql ORDER BY created_at DESC $limit_sql";

        if ( $params ) return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        return $wpdb->get_results( $sql );
    }

    public static function get_customer( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ghm_customers WHERE id = %d", $id ) );
    }

    public static function get_customer_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ghm_customers WHERE email = %s", $email ) );
    }

    public static function count_customers( $args = array() ) {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_customers" );
    }

    public static function save_customer( $data, $id = 0 ) {
        global $wpdb;
        $fields = array(
            'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'      => sanitize_email( $data['email'] ?? '' ),
            'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
            'country'    => sanitize_text_field( $data['country'] ?? '' ),
            'address'    => sanitize_textarea_field( $data['address'] ?? '' ),
            'id_type'    => sanitize_text_field( $data['id_type'] ?? '' ),
            'id_number'  => sanitize_text_field( $data['id_number'] ?? '' ),
            'notes'      => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'     => sanitize_text_field( $data['status'] ?? 'active' ),
        );

        if ( $id > 0 ) {
            $wpdb->update( $wpdb->prefix . 'ghm_customers', $fields, array( 'id' => $id ) );
            return $id;
        } else {
            // Check for duplicate email
            $existing = self::get_customer_by_email( $fields['email'] );
            if ( $existing ) return new WP_Error( 'duplicate_email', __( 'A customer with this email already exists.', 'guesthouse-manager' ) );
            $wpdb->insert( $wpdb->prefix . 'ghm_customers', $fields );
            return $wpdb->insert_id;
        }
    }

    public static function delete_customer( $id ) {
        global $wpdb;
        $bookings = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_bookings WHERE customer_id = %d AND status NOT IN ('cancelled','checked_out')",
            $id
        ) );
        if ( $bookings > 0 ) return new WP_Error( 'has_bookings', __( 'Cannot delete customer with active bookings.', 'guesthouse-manager' ) );
        return $wpdb->delete( $wpdb->prefix . 'ghm_customers', array( 'id' => $id ) );
    }

    public static function get_customer_bookings( $customer_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, r.name AS room_name, r.room_number 
             FROM {$wpdb->prefix}ghm_bookings b
             LEFT JOIN {$wpdb->prefix}ghm_rooms r ON r.id = b.room_id
             WHERE b.customer_id = %d ORDER BY b.check_in DESC",
            $customer_id
        ) );
    }

    public static function update_customer_spent( $customer_id ) {
        global $wpdb;
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}ghm_payments WHERE customer_id = %d AND status = 'completed'",
            $customer_id
        ) );
        $wpdb->update( $wpdb->prefix . 'ghm_customers', array( 'total_spent' => (float) $total ), array( 'id' => $customer_id ) );
    }
}
