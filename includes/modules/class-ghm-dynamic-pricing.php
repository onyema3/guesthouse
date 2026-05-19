<?php
/**
 * Dynamic Pricing — seasonal rates, weekend surcharges, special event pricing
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Dynamic_Pricing {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_pricing_rules (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(200) NOT NULL,
            type        VARCHAR(30)  NOT NULL DEFAULT 'date_range',
            room_ids    LONGTEXT DEFAULT NULL,
            room_types  LONGTEXT DEFAULT NULL,
            date_from   DATE DEFAULT NULL,
            date_to     DATE DEFAULT NULL,
            days_of_week VARCHAR(20) DEFAULT NULL,
            adjustment  DECIMAL(10,2) NOT NULL DEFAULT 0,
            adj_type    VARCHAR(10) NOT NULL DEFAULT 'percent',
            priority    INT NOT NULL DEFAULT 0,
            status      VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get the adjusted price for a room on a specific date.
     * Returns the final price per night/hour.
     */
    public static function get_price( $room, $date ) {
        global $wpdb;
        $base_price = (float)( $room->type === 'workspace' ? $room->price_hour : $room->price_night );
        if ( $base_price <= 0 ) return $base_price;

        $rules = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ghm_pricing_rules WHERE status='active' ORDER BY priority DESC"
        );
        if ( empty($rules) ) return $base_price;

        $d       = new DateTime( $date );
        $dow     = $d->format('N'); // 1=Mon … 7=Sun
        $today   = $d->format('Y-m-d');
        $multiplier = 1.0;

        foreach ( $rules as $rule ) {
            // Check date range
            if ( $rule->date_from && $today < $rule->date_from ) continue;
            if ( $rule->date_to   && $today > $rule->date_to   ) continue;

            // Check days of week
            if ( $rule->days_of_week ) {
                $allowed_days = array_map('trim', explode(',', $rule->days_of_week));
                if ( ! in_array((string)$dow, $allowed_days) ) continue;
            }

            // Check room match
            if ( $rule->room_ids ) {
                $ids = json_decode($rule->room_ids, true);
                if ( ! in_array($room->id, (array)$ids) ) {
                    // Check room type
                    if ( $rule->room_types ) {
                        $types = json_decode($rule->room_types, true);
                        if ( ! in_array($room->type, (array)$types) ) continue;
                    } else {
                        continue;
                    }
                }
            } elseif ( $rule->room_types ) {
                $types = json_decode($rule->room_types, true);
                if ( ! in_array($room->type, (array)$types) ) continue;
            }

            // Apply adjustment
            if ( $rule->adj_type === 'percent' ) {
                $multiplier *= 1 + ($rule->adjustment / 100);
            } else {
                // Fixed amount — convert to multiplier relative to base price
                $base_price += (float)$rule->adjustment;
            }
        }

        return round( $base_price * $multiplier, 2 );
    }

    /**
     * Calculate total for a date range using dynamic pricing (per-day calculation).
     */
    public static function calculate_total( $room, $check_in, $check_out ) {
        $in    = new DateTime( $check_in );
        $out   = new DateTime( $check_out );
        $total = 0.0;

        if ( $room->type === 'workspace' ) {
            // Hourly — use single dynamic price for the day
            $diff  = $in->diff($out);
            $hours = $diff->days * 24 + $diff->h + ($diff->i / 60);
            $price = self::get_price($room, $in->format('Y-m-d'));
            return round($price * max(1, $hours), 2);
        }

        if ( $room->type === 'hall' ) {
            $days = max(1, (int)$in->diff($out)->days);
            $cur  = clone $in;
            for ($i = 0; $i < $days; $i++) {
                $total += self::get_price($room, $cur->format('Y-m-d'));
                $cur->modify('+1 day');
            }
            return round($total, 2);
        }

        // Nightly
        $nights = max(1, (int)$in->diff($out)->days);
        $cur    = clone $in;
        for ($i = 0; $i < $nights; $i++) {
            $total += self::get_price($room, $cur->format('Y-m-d'));
            $cur->modify('+1 day');
        }
        return round($total, 2);
    }

    public static function get_rules() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ghm_pricing_rules ORDER BY priority DESC"
        );
    }

    public static function save_rule( $data, $id = 0 ) {
        global $wpdb;
        $fields = array(
            'name'        => sanitize_text_field($data['name'] ?? 'Rule'),
            'type'        => sanitize_text_field($data['type'] ?? 'date_range'),
            'room_ids'    => !empty($data['room_ids'])    ? json_encode(array_map('absint',(array)$data['room_ids']))   : null,
            'room_types'  => !empty($data['room_types'])  ? json_encode(array_map('sanitize_text_field',(array)$data['room_types'])) : null,
            'date_from'   => !empty($data['date_from'])   ? sanitize_text_field($data['date_from'])  : null,
            'date_to'     => !empty($data['date_to'])     ? sanitize_text_field($data['date_to'])    : null,
            'days_of_week'=> !empty($data['days_of_week'])? sanitize_text_field(implode(',', (array)$data['days_of_week'])) : null,
            'adjustment'  => (float)($data['adjustment'] ?? 0),
            'adj_type'    => in_array($data['adj_type']??'percent', array('percent','fixed')) ? $data['adj_type'] : 'percent',
            'priority'    => absint($data['priority'] ?? 0),
            'status'      => sanitize_text_field($data['status'] ?? 'active'),
        );
        if ($id > 0) { $wpdb->update($wpdb->prefix.'ghm_pricing_rules', $fields, array('id'=>$id)); return $id; }
        $wpdb->insert($wpdb->prefix.'ghm_pricing_rules', $fields);
        return $wpdb->insert_id;
    }

    public static function delete_rule($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix.'ghm_pricing_rules', array('id'=>absint($id)));
    }
}

// Hook into amount calculation — override if dynamic pricing enabled
add_filter('ghm_calculate_amount', function($amount, $room, $check_in, $check_out) {
    if (!get_option('ghm_dynamic_pricing_enabled', 0)) return $amount;
    return GHM_Dynamic_Pricing::calculate_total($room, $check_in, $check_out);
}, 10, 4);
