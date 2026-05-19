<?php
/**
 * Staff Login & Access Control for GuestHouse Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Staff_Access {

    public static function init() {
        add_action( 'wp_login',            array( __CLASS__, 'redirect_staff_on_login' ), 10, 2 );
        add_filter( 'login_redirect',      array( __CLASS__, 'login_redirect_filter' ), 10, 3 );
        add_action( 'admin_init',          array( __CLASS__, 'restrict_admin_access' ) );
        add_action( 'wp_dashboard_setup',  array( __CLASS__, 'remove_dashboard_widgets' ) );
        add_action( 'admin_bar_menu',      array( __CLASS__, 'customize_admin_bar' ), 80 );
        add_filter( 'show_admin_bar',      array( __CLASS__, 'maybe_show_admin_bar' ) );
    }

    /**
     * Redirect GHM staff/managers directly to GHM dashboard on login.
     */
    public static function redirect_staff_on_login( $user_login, $user ) {
        if ( ! $user ) return;
        if ( in_array( 'ghm_staff', (array) $user->roles ) || in_array( 'ghm_manager', (array) $user->roles ) ) {
            wp_redirect( admin_url( 'admin.php?page=ghm-dashboard' ) );
            exit;
        }
    }

    public static function login_redirect_filter( $redirect_to, $requested_redirect, $user ) {
        if ( is_wp_error( $user ) ) return $redirect_to;
        if ( in_array( 'ghm_staff', (array) $user->roles ) || in_array( 'ghm_manager', (array) $user->roles ) ) {
            return admin_url( 'admin.php?page=ghm-dashboard' );
        }
        return $redirect_to;
    }

    /**
     * Prevent staff from accessing WordPress core admin pages they shouldn't see.
     */
    public static function restrict_admin_access() {
        if ( ! is_admin() ) return;

        $user = wp_get_current_user();
        if ( empty($user->ID) ) return;

        $is_ghm_only = in_array( 'ghm_staff', (array) $user->roles ) || in_array( 'ghm_manager', (array) $user->roles );
        if ( ! $is_ghm_only ) return;

        // Allow only GHM and profile pages
        $screen    = get_current_screen();
        $page      = $_GET['page'] ?? '';
        $allowed   = array( 'ghm-dashboard','ghm-rooms','ghm-workspaces','ghm-bookings','ghm-customers','ghm-payments','ghm-staff','ghm-reports','ghm-settings' );

        // Also allow profile edit
        $allowed_files = array( 'profile.php', 'admin.php', 'admin-ajax.php' );
        $current_file  = basename( $_SERVER['SCRIPT_NAME'] ?? '' );

        if ( $current_file === 'admin.php' && ! in_array( $page, $allowed ) && ! empty($page) ) {
            wp_redirect( admin_url( 'admin.php?page=ghm-dashboard' ) );
            exit;
        }

        // Block wp-admin index (replace with GHM dashboard)
        if ( $current_file === 'index.php' ) {
            wp_redirect( admin_url( 'admin.php?page=ghm-dashboard' ) );
            exit;
        }
    }

    /**
     * Remove all default dashboard widgets for GHM users.
     */
    public static function remove_dashboard_widgets() {
        $user = wp_get_current_user();
        if ( ! in_array( 'ghm_staff', (array) $user->roles ) && ! in_array( 'ghm_manager', (array) $user->roles ) ) return;

        global $wp_meta_boxes;
        $wp_meta_boxes['dashboard'] = array();
    }

    /**
     * Customize admin toolbar for GHM staff.
     */
    public static function customize_admin_bar( $wp_admin_bar ) {
        $user = wp_get_current_user();
        if ( ! in_array( 'ghm_staff', (array) $user->roles ) && ! in_array( 'ghm_manager', (array) $user->roles ) ) return;

        // Remove default menus irrelevant to hotel staff
        $remove = array( 'wp-logo','about','wporg','documentation','support-forums','feedback','site-name','view-site','updates','comments','new-content','search' );
        foreach ( $remove as $node ) {
            $wp_admin_bar->remove_menu( $node );
        }

        // Add quick links
        $wp_admin_bar->add_menu( array(
            'id'    => 'ghm-quick',
            'title' => '🏨 ' . get_option( 'ghm_hotel_name', 'GuestHouse' ),
            'href'  => admin_url( 'admin.php?page=ghm-dashboard' ),
        ) );
        $wp_admin_bar->add_menu( array(
            'id'     => 'ghm-new-booking',
            'parent' => 'ghm-quick',
            'title'  => '+ New Booking',
            'href'   => admin_url( 'admin.php?page=ghm-bookings' ),
        ) );
        $wp_admin_bar->add_menu( array(
            'id'     => 'ghm-customers-bar',
            'parent' => 'ghm-quick',
            'title'  => 'Customers',
            'href'   => admin_url( 'admin.php?page=ghm-customers' ),
        ) );
    }

    /**
     * Always show admin bar for GHM users so they can log out.
     */
    public static function maybe_show_admin_bar( $show ) {
        $user = wp_get_current_user();
        if ( in_array( 'ghm_staff', (array) $user->roles ) || in_array( 'ghm_manager', (array) $user->roles ) ) {
            return true;
        }
        return $show;
    }
}

add_action( 'plugins_loaded', array( 'GHM_Staff_Access', 'init' ), 15 );
