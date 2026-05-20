<?php
/**
 * GuestHouse Manager — Content Security Policy Override
 *
 * When a CSP header is being set elsewhere (theme, security plugin, server
 * config) that blocks payment gateway SDKs, this module detects the existing
 * CSP on pages that use the booking form or guest portal and appends the
 * Paystack/Flutterwave domains so the checkout modals can load.
 *
 * Enabled by default when any payment gateway is active. Can be disabled via:
 *   add_filter('ghm_override_csp', '__return_false');
 *
 * Works by hooking into 'send_headers' (priority 9999 — runs after almost
 * everything) and replacing the Content-Security-Policy header with one that
 * includes the required payment domains.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_CSP {

    public static function init() {
        // Only act on front-end page loads, not admin/AJAX/REST
        if ( is_admin() || wp_doing_ajax() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
            return;
        }
        add_action( 'template_redirect', array( __CLASS__, 'maybe_override_csp' ), 9999 );
    }

    /**
     * Check if the current page uses a booking form or portal shortcode,
     * and if a payment gateway is enabled. If so, override the CSP.
     */
    public static function maybe_override_csp() {
        if ( ! apply_filters( 'ghm_override_csp', true ) ) return;

        // Check if any payment gateway is active
        $ps_enabled  = class_exists('GHM_Paystack')    && GHM_Paystack::is_enabled();
        $flw_enabled = class_exists('GHM_Flutterwave') && GHM_Flutterwave::is_enabled();
        if ( ! $ps_enabled && ! $flw_enabled ) return;

        // Check if the current page contains our shortcodes
        global $post;
        if ( ! $post ) return;

        $dominated = false;
        $shortcodes = array( 'ghm_booking_form', 'ghm_guest_portal', 'ghm_rooms_list' );
        foreach ( $shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                $dominated = true;
                break;
            }
        }
        if ( ! $dominated ) return;

        // Override the CSP at output time
        add_action( 'send_headers', array( __CLASS__, 'send_csp_header' ), 99999 );

        // Also catch headers already sent via ob — use a late wp_head action
        // to emit a <meta> tag as an absolute fallback
        add_action( 'wp_head', array( __CLASS__, 'emit_csp_meta' ), 1 );
    }

    /**
     * Build the required CSP domains for payment gateways.
     */
    private static function payment_domains() {
        $script_src  = array();
        $frame_src   = array();
        $connect_src = array();

        if ( class_exists('GHM_Paystack') && GHM_Paystack::is_enabled() ) {
            $script_src[]  = 'https://js.paystack.co';
            $frame_src[]   = 'https://checkout.paystack.com';
            $frame_src[]   = 'https://standard.paystack.co';
            $connect_src[] = 'https://api.paystack.co';
            $connect_src[] = 'https://checkout.paystack.com';
        }
        if ( class_exists('GHM_Flutterwave') && GHM_Flutterwave::is_enabled() ) {
            $script_src[]  = 'https://checkout.flutterwave.com';
            $frame_src[]   = 'https://checkout.flutterwave.com';
            $frame_src[]   = 'https://ravemodal-dev.herokuapp.com';
            $connect_src[] = 'https://api.flutterwave.com';
            $connect_src[] = 'https://checkout.flutterwave.com';
        }

        return array(
            'script-src'  => $script_src,
            'frame-src'   => $frame_src,
            'connect-src' => $connect_src,
        );
    }

    /**
     * Merge payment domains into an existing CSP string.
     */
    private static function merge_csp( $existing ) {
        $domains = self::payment_domains();

        foreach ( $domains as $directive => $sources ) {
            if ( empty( $sources ) ) continue;

            $sources_str = implode( ' ', $sources );

            if ( preg_match( '/(' . preg_quote($directive, '/') . ')\s+([^;]*)/i', $existing, $m ) ) {
                // Directive exists — append our sources (skip duplicates)
                $current = $m[2];
                $additions = array();
                foreach ( $sources as $src ) {
                    if ( stripos( $current, $src ) === false ) {
                        $additions[] = $src;
                    }
                }
                if ( $additions ) {
                    $new_val = $m[1] . ' ' . $current . ' ' . implode(' ', $additions);
                    $existing = str_replace( $m[0], $new_val, $existing );
                }
            } else {
                // Directive doesn't exist — append it before the last semicolon or at end
                $existing = rtrim( $existing, '; ' ) . '; ' . $directive . ' ' . $sources_str . ';';
            }
        }

        return $existing;
    }

    /**
     * Replace the CSP header.
     */
    public static function send_csp_header() {
        // Get existing CSP from PHP's headers list
        $existing_csp = '';
        if ( function_exists('headers_list') ) {
            foreach ( headers_list() as $header ) {
                if ( stripos( $header, 'Content-Security-Policy:' ) === 0 ) {
                    $existing_csp = trim( substr( $header, strlen('Content-Security-Policy:') ) );
                    break;
                }
            }
        }

        if ( ! $existing_csp ) {
            // No existing CSP header — nothing to fix, gateway scripts should load
            return;
        }

        $new_csp = self::merge_csp( $existing_csp );

        // Remove old and set new
        if ( function_exists('header_remove') ) {
            header_remove( 'Content-Security-Policy' );
        }
        header( 'Content-Security-Policy: ' . $new_csp, true );
    }

    /**
     * Fallback: emit a <meta> CSP in <head>.
     * NOTE: Multiple CSPs intersect (most restrictive wins), so this only
     * helps if no HTTP header CSP exists. If the HTTP header was already
     * patched by send_csp_header(), this meta tag is harmless (same policy).
     */
    public static function emit_csp_meta() {
        $domains = self::payment_domains();
        if ( empty( $domains['script-src'] ) && empty( $domains['frame-src'] ) ) return;

        // Build a permissive meta CSP that only lists the payment-required directives
        $parts = array();
        if ( !empty($domains['script-src']) ) {
            $parts[] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . implode(' ', $domains['script-src']);
        }
        if ( !empty($domains['frame-src']) ) {
            $parts[] = "frame-src 'self' " . implode(' ', $domains['frame-src']);
        }
        if ( !empty($domains['connect-src']) ) {
            $parts[] = "connect-src 'self' " . implode(' ', $domains['connect-src']);
        }

        // Note: a <meta> CSP intersects with the HTTP header CSP (stricter wins).
        // If the HTTP header was already patched, this meta is redundant but safe.
        // If the HTTP header was NOT patched (e.g., set by the web server outside PHP),
        // this meta alone cannot loosen it. But it's here as documentation / fallback for
        // edge cases where the CSP is being set late via output buffering.
        echo '<meta http-equiv="Content-Security-Policy" content="' . esc_attr( implode('; ', $parts) ) . '">' . "\n";
    }
}

add_action( 'plugins_loaded', array( 'GHM_CSP', 'init' ), 5 );
