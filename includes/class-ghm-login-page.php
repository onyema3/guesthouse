<?php
/**
 * Custom staff login page branding
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Login_Page {

    public static function init() {
        add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_styles' ) );
        add_filter( 'login_headerurl',       array( __CLASS__, 'login_logo_url' ) );
        add_filter( 'login_headertext',      array( __CLASS__, 'login_logo_title' ) );
        add_filter( 'login_message',         array( __CLASS__, 'login_message' ) );
        add_action( 'login_footer',          array( __CLASS__, 'login_footer' ) );
    }

    public static function login_styles() {
        $hotel = esc_js( get_option( 'ghm_hotel_name', get_bloginfo('name') ) );
        echo '<style>
            @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap");
            body.login {
                background: linear-gradient(135deg,#0f1117 0%,#181c27 50%,#1a1a2e 100%) !important;
                font-family: "DM Sans",sans-serif;
            }
            body.login::before {
                content: "";
                position: fixed;
                inset: 0;
                background: radial-gradient(ellipse at 30% 20%,rgba(201,168,76,.08) 0%,transparent 60%),
                            radial-gradient(ellipse at 70% 80%,rgba(96,165,250,.05) 0%,transparent 60%);
                pointer-events: none;
            }
            #login {
                width: 360px;
            }
            #login h1 a {
                background-image: none !important;
                width: auto !important;
                height: auto !important;
                font-family: "Playfair Display",serif;
                font-size: 26px;
                font-weight: 600;
                color: #c9a84c !important;
                text-align: center;
                display: block;
                text-decoration: none;
                letter-spacing: 0.3px;
                line-height: 1.2;
            }
            #login h1 a::before {
                content: "🏨";
                display: block;
                font-size: 36px;
                margin-bottom: 8px;
            }
            #loginform, #lostpasswordform {
                background: rgba(24,28,39,.95) !important;
                border: 1px solid rgba(255,255,255,.08) !important;
                border-radius: 14px !important;
                padding: 28px 28px 24px !important;
                box-shadow: 0 24px 64px rgba(0,0,0,.6) !important;
                backdrop-filter: blur(10px);
            }
            #loginform label, #lostpasswordform label {
                color: rgba(255,255,255,.5) !important;
                font-size: 11px !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
                font-weight: 600 !important;
            }
            #loginform input[type="text"],
            #loginform input[type="password"],
            #loginform input[type="email"],
            #lostpasswordform input[type="text"] {
                background: rgba(255,255,255,.06) !important;
                border: 1px solid rgba(255,255,255,.1) !important;
                border-radius: 8px !important;
                color: #fff !important;
                font-family: "DM Sans",sans-serif !important;
                font-size: 14px !important;
                padding: 10px 14px !important;
                height: auto !important;
                line-height: 1.4 !important;
                transition: border-color .2s !important;
            }
            #loginform input:focus,
            #lostpasswordform input:focus {
                border-color: #c9a84c !important;
                box-shadow: 0 0 0 2px rgba(201,168,76,.15) !important;
                background: rgba(255,255,255,.08) !important;
            }
            .wp-core-ui .button-primary {
                background: linear-gradient(135deg,#c9a84c,#e8c97a) !important;
                border: none !important;
                border-radius: 8px !important;
                color: #1a1a2e !important;
                font-weight: 700 !important;
                font-size: 14px !important;
                font-family: "DM Sans",sans-serif !important;
                padding: 10px 20px !important;
                height: auto !important;
                line-height: 1.4 !important;
                cursor: pointer !important;
                box-shadow: 0 4px 14px rgba(201,168,76,.3) !important;
                transition: all .2s !important;
            }
            .wp-core-ui .button-primary:hover {
                transform: translateY(-1px) !important;
                box-shadow: 0 6px 20px rgba(201,168,76,.4) !important;
            }
            #nav a, #backtoblog a {
                color: rgba(255,255,255,.4) !important;
                font-size: 12px !important;
            }
            #nav a:hover, #backtoblog a:hover {
                color: #c9a84c !important;
            }
            .forgetmenot label { color: rgba(255,255,255,.4) !important; }
            #login_error, .message, .success {
                background: rgba(24,28,39,.9) !important;
                border-left-color: #ef4444 !important;
                color: #fca5a5 !important;
                border-radius: 8px !important;
            }
            .message { border-left-color: #c9a84c !important; color: #e8c97a !important; }
        </style>';
    }

    public static function login_logo_url() {
        return home_url();
    }

    public static function login_logo_title() {
        return get_option( 'ghm_hotel_name', get_bloginfo('name') ) . ' – Staff Portal';
    }

    public static function login_message( $message ) {
        if ( empty( $message ) ) {
            return '<p class="message" style="text-align:center;font-size:13px;">' .
                   esc_html( get_option('ghm_hotel_name','GuestHouse') ) . ' Staff Portal &mdash; Authorized access only.</p>';
        }
        return $message;
    }

    public static function login_footer() {
        echo '<p style="text-align:center;color:rgba(255,255,255,.2);font-size:11px;margin-top:20px;">&copy; ' . date('Y') . ' ' . esc_html(get_option('ghm_hotel_name','GuestHouse')) . '. All rights reserved.</p>';
    }
}

add_action( 'plugins_loaded', array( 'GHM_Login_Page', 'init' ), 15 );
