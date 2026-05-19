<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Discounts {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_discounts (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code          VARCHAR(50) NOT NULL UNIQUE,
            type          VARCHAR(20) NOT NULL DEFAULT 'percent',
            value         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            min_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            max_uses      INT DEFAULT NULL,
            used_count    INT NOT NULL DEFAULT 0,
            valid_from    DATE DEFAULT NULL,
            valid_until   DATE DEFAULT NULL,
            room_ids      LONGTEXT DEFAULT NULL,
            status        VARCHAR(20) NOT NULL DEFAULT 'active',
            description   VARCHAR(200),
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY   (id),
            UNIQUE KEY    code (code)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function validate( $code, $amount, $room_id = 0 ) {
        global $wpdb;
        $discount = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ghm_discounts WHERE code = %s AND status = 'active'", strtoupper($code)
        ) );
        if ( ! $discount ) return new WP_Error('invalid', 'Discount code not found or inactive.');

        $today = date('Y-m-d');
        if ( $discount->valid_from   && $today < $discount->valid_from )  return new WP_Error('not_started','Code is not valid yet.');
        if ( $discount->valid_until  && $today > $discount->valid_until ) return new WP_Error('expired',   'This code has expired.');
        if ( $discount->max_uses !== null && $discount->used_count >= $discount->max_uses ) return new WP_Error('maxed','This code has reached its maximum uses.');
        if ( $discount->min_amount > 0 && $amount < $discount->min_amount ) return new WP_Error('min_amount', 'Minimum booking amount of ' . get_option('ghm_currency_symbol','₦') . number_format($discount->min_amount,2) . ' required.');

        if ( $discount->room_ids ) {
            $allowed = json_decode( $discount->room_ids, true );
            if ( $room_id && ! in_array( $room_id, (array)$allowed ) ) return new WP_Error('not_applicable','Code not valid for this room.');
        }

        $discount_amount = $discount->type === 'percent'
            ? round( $amount * ($discount->value / 100), 2 )
            : min( (float)$discount->value, $amount );

        return array(
            'code'            => $discount->code,
            'discount_id'     => $discount->id,
            'type'            => $discount->type,
            'value'           => $discount->value,
            'discount_amount' => $discount_amount,
            'final_amount'    => max(0, round($amount - $discount_amount, 2)),
            'description'     => $discount->description,
        );
    }

    public static function apply( $discount_id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}ghm_discounts SET used_count = used_count + 1 WHERE id = %d", $discount_id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $limit = isset($args['limit']) ? absint($args['limit']) : 50;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ghm_discounts ORDER BY created_at DESC LIMIT $limit" );
    }

    public static function save( $data, $id = 0 ) {
        global $wpdb;
        $fields = array(
            'code'        => strtoupper( sanitize_text_field( $data['code'] ) ),
            'type'        => sanitize_text_field( $data['type']        ?? 'percent' ),
            'value'       => (float)( $data['value']                   ?? 0 ),
            'min_amount'  => (float)( $data['min_amount']              ?? 0 ),
            'max_uses'    => !empty($data['max_uses']) ? absint($data['max_uses']) : null,
            'valid_from'  => !empty($data['valid_from'])  ? sanitize_text_field($data['valid_from'])  : null,
            'valid_until' => !empty($data['valid_until']) ? sanitize_text_field($data['valid_until']) : null,
            'status'      => sanitize_text_field( $data['status']      ?? 'active' ),
            'description' => sanitize_text_field( $data['description'] ?? '' ),
        );
        if ( $id > 0 ) { $wpdb->update( $wpdb->prefix.'ghm_discounts', $fields, array('id'=>$id) ); return $id; }
        $wpdb->insert( $wpdb->prefix.'ghm_discounts', $fields );
        return $wpdb->insert_id;
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix.'ghm_discounts', array('id'=>$id) );
    }
}
