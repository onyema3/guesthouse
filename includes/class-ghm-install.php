<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Install {

    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::insert_defaults();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $tables = array();

        // Rooms table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_rooms (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name         VARCHAR(200) NOT NULL,
            type         VARCHAR(100) NOT NULL DEFAULT 'room',
            description  TEXT,
            capacity     INT NOT NULL DEFAULT 1,
            price_night  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            price_hour   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status       VARCHAR(50) NOT NULL DEFAULT 'available',
            amenities    LONGTEXT,
            images       LONGTEXT,
            floor        VARCHAR(50),
            room_number  VARCHAR(50),
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        // Bookings table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_bookings (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_ref     VARCHAR(50) NOT NULL UNIQUE,
            customer_id     BIGINT UNSIGNED NOT NULL,
            room_id         BIGINT UNSIGNED NOT NULL,
            booking_type    VARCHAR(20) NOT NULL DEFAULT 'room',
            check_in        DATETIME NOT NULL,
            check_out       DATETIME NOT NULL,
            adults          INT NOT NULL DEFAULT 1,
            children        INT NOT NULL DEFAULT 0,
            total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            paid_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status          VARCHAR(50) NOT NULL DEFAULT 'pending',
            payment_status  VARCHAR(50) NOT NULL DEFAULT 'unpaid',
            special_requests TEXT,
            notes           TEXT,
            created_by      BIGINT UNSIGNED,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY customer_id (customer_id),
            KEY room_id     (room_id),
            KEY status      (status)
        ) $charset;";

        // Customers table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_customers (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name   VARCHAR(100) NOT NULL,
            last_name    VARCHAR(100) NOT NULL,
            email        VARCHAR(200) NOT NULL,
            phone        VARCHAR(50),
            country      VARCHAR(100),
            address      TEXT,
            id_type      VARCHAR(50),
            id_number    VARCHAR(100),
            notes        TEXT,
            total_spent  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            visit_count  INT NOT NULL DEFAULT 0,
            status       VARCHAR(50) NOT NULL DEFAULT 'active',
            wp_user_id   BIGINT UNSIGNED,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   email (email)
        ) $charset;";

        // Payments table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_payments (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id      BIGINT UNSIGNED NOT NULL,
            customer_id     BIGINT UNSIGNED NOT NULL,
            amount          DECIMAL(10,2) NOT NULL,
            currency        VARCHAR(10) NOT NULL DEFAULT 'USD',
            method          VARCHAR(50) NOT NULL DEFAULT 'cash',
            status          VARCHAR(50) NOT NULL DEFAULT 'pending',
            transaction_id  VARCHAR(200),
            gateway_response LONGTEXT,
            notes           TEXT,
            created_by      BIGINT UNSIGNED,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY booking_id  (booking_id)
        ) $charset;";

        // Staff table (extends WP users)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_staff (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id   BIGINT UNSIGNED NOT NULL,
            position     VARCHAR(100),
            department   VARCHAR(100),
            phone        VARCHAR(50),
            shift        VARCHAR(50),
            status       VARCHAR(50) NOT NULL DEFAULT 'active',
            hire_date    DATE,
            notes        TEXT,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY wp_user_id (wp_user_id)
        ) $charset;";

        // Activity log
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ghm_activity_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED,
            action      VARCHAR(200) NOT NULL,
            object_type VARCHAR(100),
            object_id   BIGINT UNSIGNED,
            details     LONGTEXT,
            ip_address  VARCHAR(45),
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    private static function create_roles() {
        // GuestHouse Manager role
        add_role( 'ghm_manager', __( 'GuestHouse Manager', 'guesthouse-manager' ), array(
            'read'              => true,
            'ghm_manage_rooms'    => true,
            'ghm_manage_bookings' => true,
            'ghm_manage_customers'=> true,
            'ghm_manage_payments' => true,
            'ghm_view_reports'    => true,
            'ghm_manage_staff'    => true,
        ) );

        // GuestHouse Staff role
        add_role( 'ghm_staff', __( 'GuestHouse Staff', 'guesthouse-manager' ), array(
            'read'              => true,
            'ghm_manage_bookings' => true,
            'ghm_manage_customers'=> true,
            'ghm_view_reports'    => false,
        ) );

        // Give admin all capabilities
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = array( 'ghm_manage_rooms','ghm_manage_bookings','ghm_manage_customers',
                           'ghm_manage_payments','ghm_view_reports','ghm_manage_staff' );
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    private static function insert_defaults() {
        global $wpdb;
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_rooms" );
        if ( $count > 0 ) return;

        // Sample rooms
        $rooms = array(
            array( 'name'=>'Deluxe Room 101', 'type'=>'room', 'capacity'=>2, 'price_night'=>'150.00', 'floor'=>'1', 'room_number'=>'101', 'status'=>'available', 'amenities'=>json_encode(['WiFi','AC','TV','Minibar']) ),
            array( 'name'=>'Standard Room 102', 'type'=>'room', 'capacity'=>2, 'price_night'=>'90.00', 'floor'=>'1', 'room_number'=>'102', 'status'=>'available', 'amenities'=>json_encode(['WiFi','AC','TV']) ),
            array( 'name'=>'Suite 201', 'type'=>'suite', 'capacity'=>4, 'price_night'=>'280.00', 'floor'=>'2', 'room_number'=>'201', 'status'=>'available', 'amenities'=>json_encode(['WiFi','AC','TV','Minibar','Jacuzzi','Living Room']) ),
            array( 'name'=>'Conference Room A', 'type'=>'workspace', 'capacity'=>20, 'price_hour'=>'50.00', 'floor'=>'G', 'room_number'=>'CR-A', 'status'=>'available', 'amenities'=>json_encode(['Projector','Whiteboard','WiFi','AC']) ),
            array( 'name'=>'Co-Working Space', 'type'=>'workspace', 'capacity'=>10, 'price_hour'=>'15.00', 'floor'=>'G', 'room_number'=>'CW-1', 'status'=>'available', 'amenities'=>json_encode(['WiFi','AC','Desks','Printing']) ),
        );

        foreach ( $rooms as $room ) {
            $wpdb->insert( $wpdb->prefix . 'ghm_rooms', $room );
        }
    }
}

/**
 * Re-apply capabilities on every load in case admin role was
 * created before the plugin assigned the custom caps.
 */
function ghm_ensure_admin_caps() {
    $admin = get_role( 'administrator' );
    if ( ! $admin ) return;
    $caps = array(
        'ghm_manage_rooms',
        'ghm_manage_bookings',
        'ghm_manage_customers',
        'ghm_manage_payments',
        'ghm_view_reports',
        'ghm_manage_staff',
    );
    foreach ( $caps as $cap ) {
        if ( ! $admin->has_cap( $cap ) ) {
            $admin->add_cap( $cap );
        }
    }
}
add_action( 'init', 'ghm_ensure_admin_caps' );

/**
 * Create new module tables (safe to run on update)
 */
function ghm_create_module_tables() {
    if ( class_exists('GHM_Dynamic_Pricing') ) GHM_Dynamic_Pricing::create_table();
    if ( class_exists('GHM_Deposits') )        GHM_Deposits::create_table();
    if ( class_exists('GHM_Guest_Portal') )    GHM_Guest_Portal::create_tables();
    if ( class_exists('GHM_Housekeeping') )    GHM_Housekeeping::create_table();
    if ( class_exists('GHM_Maintenance') )     GHM_Maintenance::create_table();
    if ( class_exists('GHM_Discounts') )       GHM_Discounts::create_table();
    if ( class_exists('GHM_Waitlist') )        GHM_Waitlist::create_table();
    if ( class_exists('GHM_Housekeeping') ) GHM_Housekeeping::create_table();
    if ( class_exists('GHM_Maintenance') )  GHM_Maintenance::create_table();
    if ( class_exists('GHM_Discounts') )    GHM_Discounts::create_table();
    if ( class_exists('GHM_Waitlist') )     GHM_Waitlist::create_table();
    // Add new columns to bookings table if missing (safe upgrade)
    global $wpdb;
    $new_columns = array(
        'source'          => "ALTER TABLE {$wpdb->prefix}ghm_bookings ADD COLUMN source VARCHAR(100) DEFAULT NULL AFTER notes",
        'discount_code'   => "ALTER TABLE {$wpdb->prefix}ghm_bookings ADD COLUMN discount_code VARCHAR(50) DEFAULT NULL AFTER source",
        'discount_amount' => "ALTER TABLE {$wpdb->prefix}ghm_bookings ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_code",
        'tax_amount'      => "ALTER TABLE {$wpdb->prefix}ghm_bookings ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_amount",
    );
    foreach ( $new_columns as $col_name => $sql ) {
        $exists = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s",
            $wpdb->prefix . 'ghm_bookings', $col_name
        ) );
        if ( empty( $exists ) ) {
            $wpdb->query( $sql );
        }
    }
}
add_action( 'init', 'ghm_create_module_tables', 5 );
