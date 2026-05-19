<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Staff {

    public static function get_staff( $args = array() ) {
        global $wpdb;
        $limit = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'] ?? 20, $args['offset'] ?? 0 );
        return $wpdb->get_results(
            "SELECT s.*, u.display_name, u.user_email, u.user_login
             FROM {$wpdb->prefix}ghm_staff s
             LEFT JOIN {$wpdb->users} u ON u.ID = s.wp_user_id
             WHERE s.status != 'deleted'
             ORDER BY s.id DESC $limit"
        );
    }

    public static function get_staff_member( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, u.display_name, u.user_email, u.user_login, u.user_registered
             FROM {$wpdb->prefix}ghm_staff s
             LEFT JOIN {$wpdb->users} u ON u.ID = s.wp_user_id
             WHERE s.id = %d", $id
        ) );
    }

    public static function create_staff( $data ) {
        global $wpdb;

        // Create WordPress user
        $user_data = array(
            'user_login'   => sanitize_user( $data['username'] ),
            'user_email'   => sanitize_email( $data['email'] ),
            'user_pass'    => $data['password'] ?? wp_generate_password(),
            'display_name' => sanitize_text_field( $data['first_name'] . ' ' . $data['last_name'] ),
            'first_name'   => sanitize_text_field( $data['first_name'] ),
            'last_name'    => sanitize_text_field( $data['last_name'] ),
            'role'         => $data['role'] ?? 'ghm_staff',
        );

        $wp_user_id = wp_insert_user( $user_data );
        if ( is_wp_error( $wp_user_id ) ) return $wp_user_id;

        $fields = array(
            'wp_user_id' => $wp_user_id,
            'position'   => sanitize_text_field( $data['position'] ?? '' ),
            'department' => sanitize_text_field( $data['department'] ?? '' ),
            'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
            'shift'      => sanitize_text_field( $data['shift'] ?? '' ),
            'hire_date'  => sanitize_text_field( $data['hire_date'] ?? '' ),
            'status'     => 'active',
        );

        $wpdb->insert( $wpdb->prefix . 'ghm_staff', $fields );
        return $wpdb->insert_id;
    }

    public static function update_staff( $id, $data ) {
        global $wpdb;
        $member = self::get_staff_member( $id );
        if ( ! $member ) return new WP_Error( 'not_found', 'Staff member not found.' );

        $fields = array();
        foreach ( array( 'position','department','phone','shift','status','hire_date' ) as $key ) {
            if ( isset( $data[ $key ] ) ) $fields[ $key ] = sanitize_text_field( $data[ $key ] );
        }
        if ( ! empty( $fields ) ) {
            $wpdb->update( $wpdb->prefix . 'ghm_staff', $fields, array( 'id' => $id ) );
        }

        // Update WP user fields
        $wp_data = array( 'ID' => $member->wp_user_id );
        if ( ! empty( $data['first_name'] ) ) $wp_data['first_name'] = sanitize_text_field( $data['first_name'] );
        if ( ! empty( $data['last_name'] ) )  $wp_data['last_name']  = sanitize_text_field( $data['last_name'] );
        if ( ! empty( $data['email'] ) )      $wp_data['user_email'] = sanitize_email( $data['email'] );
        if ( count( $wp_data ) > 1 ) wp_update_user( $wp_data );

        return true;
    }

    public static function delete_staff( $id ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . 'ghm_staff', array( 'status' => 'deleted' ), array( 'id' => $id ) );
    }

    public static function count_staff() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_staff WHERE status = 'active'" );
    }
}
