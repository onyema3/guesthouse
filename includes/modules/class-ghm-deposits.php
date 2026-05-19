<?php
/**
 * Security / Damage Deposit Module
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Deposits {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_deposits (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id      BIGINT UNSIGNED NOT NULL,
            customer_id     BIGINT UNSIGNED NOT NULL,
            amount          DECIMAL(10,2) NOT NULL,
            currency        VARCHAR(10) NOT NULL DEFAULT 'NGN',
            method          VARCHAR(50) NOT NULL DEFAULT 'cash',
            status          VARCHAR(30) NOT NULL DEFAULT 'held',
            collected_at    DATETIME DEFAULT NULL,
            refunded_at     DATETIME DEFAULT NULL,
            forfeited_at    DATETIME DEFAULT NULL,
            forfeit_reason  TEXT,
            transaction_id  VARCHAR(200),
            notes           TEXT,
            collected_by    BIGINT UNSIGNED,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY booking_id  (booking_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_for_booking( $booking_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_deposits WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
            $booking_id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $where  = '1=1';
        $params = array();
        if ( !empty($args['status']) ) { $where .= ' AND d.status = %s'; $params[] = $args['status']; }
        $limit = isset($args['limit']) ? absint($args['limit']) : 50;
        $sql = "SELECT d.*,
                b.booking_ref, CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                r.name AS room_name, r.room_number
                FROM {$wpdb->prefix}ghm_deposits d
                LEFT JOIN {$wpdb->prefix}ghm_bookings b  ON b.id  = d.booking_id
                LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id  = d.customer_id
                LEFT JOIN {$wpdb->prefix}ghm_rooms r     ON r.id  = b.room_id
                WHERE $where ORDER BY d.created_at DESC LIMIT $limit";
        return $params ? $wpdb->get_results( $wpdb->prepare($sql,$params) ) : $wpdb->get_results($sql);
    }

    public static function collect( $data ) {
        global $wpdb;
        $booking = GHM_Bookings::get_booking( absint($data['booking_id']) );
        if ( !$booking ) return new WP_Error('not_found','Booking not found.');

        $fields = array(
            'booking_id'     => $booking->id,
            'customer_id'    => $booking->customer_id,
            'amount'         => (float)($data['amount'] ?? 0),
            'currency'       => sanitize_text_field($data['currency'] ?? get_option('ghm_currency','NGN')),
            'method'         => sanitize_text_field($data['method'] ?? 'cash'),
            'status'         => 'held',
            'collected_at'   => current_time('mysql'),
            'transaction_id' => sanitize_text_field($data['transaction_id'] ?? ''),
            'notes'          => sanitize_textarea_field($data['notes'] ?? ''),
            'collected_by'   => get_current_user_id(),
        );
        $wpdb->insert( $wpdb->prefix.'ghm_deposits', $fields );
        $id = $wpdb->insert_id;

        // Log activity
        $wpdb->insert($wpdb->prefix.'ghm_activity_log', array(
            'user_id'=>get_current_user_id(),'action'=>'collected_deposit',
            'object_type'=>'booking','object_id'=>$booking->id,
            'details'=>json_encode(array('amount'=>$fields['amount'],'method'=>$fields['method'])),
        ));
        return $id;
    }

    public static function refund( $deposit_id, $notes = '' ) {
        global $wpdb;
        $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ghm_deposits WHERE id=%d",$deposit_id));
        if ( !$deposit || $deposit->status !== 'held' ) return new WP_Error('invalid','Deposit cannot be refunded.');

        $wpdb->update($wpdb->prefix.'ghm_deposits', array(
            'status'      => 'refunded',
            'refunded_at' => current_time('mysql'),
            'notes'       => sanitize_textarea_field($notes),
        ), array('id'=>$deposit_id));

        $wpdb->insert($wpdb->prefix.'ghm_activity_log', array(
            'user_id'=>get_current_user_id(),'action'=>'refunded_deposit',
            'object_type'=>'deposit','object_id'=>$deposit_id,
        ));

        // Notify customer
        do_action('ghm_deposit_refunded', $deposit_id);
        return true;
    }

    public static function forfeit( $deposit_id, $reason ) {
        global $wpdb;
        $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ghm_deposits WHERE id=%d",$deposit_id));
        if ( !$deposit || $deposit->status !== 'held' ) return new WP_Error('invalid','Deposit cannot be forfeited.');

        $wpdb->update($wpdb->prefix.'ghm_deposits', array(
            'status'         => 'forfeited',
            'forfeited_at'   => current_time('mysql'),
            'forfeit_reason' => sanitize_textarea_field($reason),
        ), array('id'=>$deposit_id));

        // Record as income
        GHM_Payments::record_payment(array(
            'booking_id'     => $deposit->booking_id,
            'amount'         => $deposit->amount,
            'currency'       => $deposit->currency,
            'method'         => 'other',
            'notes'          => 'Forfeited deposit: '.$reason,
            'transaction_id' => 'DEP-FORFEIT-'.$deposit_id,
        ));
        return true;
    }

    public static function get_summary() {
        global $wpdb;
        return array(
            'total_held'     => (float)$wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}ghm_deposits WHERE status='held'"),
            'total_refunded' => (float)$wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}ghm_deposits WHERE status='refunded'"),
            'total_forfeited'=> (float)$wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}ghm_deposits WHERE status='forfeited'"),
            'count_held'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ghm_deposits WHERE status='held'"),
        );
    }
}

// Auto-create table
add_action('init', array('GHM_Deposits','create_table'), 4);
