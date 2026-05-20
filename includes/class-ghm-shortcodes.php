<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GHM_Shortcodes {

    public static function init() {
        add_shortcode( 'ghm_booking_form',  array( __CLASS__, 'booking_form' ) );
        add_shortcode( 'ghm_rooms_list',    array( __CLASS__, 'rooms_list' ) );
        add_shortcode( 'ghm_booking_confirmation', array( __CLASS__, 'booking_confirmation' ) );
        add_shortcode( 'ghm_waitlist_form',         array( __CLASS__, 'waitlist_form' ) );
        add_shortcode( 'ghm_reviews',               array( __CLASS__, 'reviews_list' ) );
        // Guest portal is registered by GHM_Guest_Portal::init() via plugins_loaded
        add_action( 'wp_enqueue_scripts',   array( __CLASS__, 'enqueue_scripts' ) );
    }

    public static function enqueue_scripts() {
        wp_enqueue_style( 'ghm-public', GHM_PLUGIN_URL . 'public/css/ghm-public.css', array(), GHM_VERSION );

        // Paystack inline JS must load before our public script
        $deps = array( 'jquery' );
        if ( class_exists( 'GHM_Paystack' ) && GHM_Paystack::is_enabled() ) {
            wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), null, true );
            $deps[] = 'paystack-inline';
        }

        wp_enqueue_script( 'ghm-public', GHM_PLUGIN_URL . 'public/js/ghm-public.js', $deps, GHM_VERSION, true );

        wp_localize_script( 'ghm-public', 'ghmPublic', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ghm_public_nonce' ),
        ) );

        // Paystack config
        if ( class_exists( 'GHM_Paystack' ) && GHM_Paystack::is_enabled() ) {
            wp_localize_script( 'ghm-public', 'ghmPaystack', array(
                'enabled'    => true,
                'public_key' => GHM_Paystack::public_key(),
                'currency'   => strtoupper( get_option( 'ghm_currency', 'NGN' ) ),
            ) );
        }

        // Flutterwave config
        if ( class_exists( 'GHM_Flutterwave' ) && GHM_Flutterwave::is_enabled() ) {
            wp_enqueue_script( 'flutterwave-inline', 'https://checkout.flutterwave.com/v3.js', array(), null, true );
            wp_localize_script( 'ghm-public', 'ghmFlutterwave', array(
                'enabled'    => true,
                'public_key' => GHM_Flutterwave::public_key(),
                'currency'   => strtoupper( get_option( 'ghm_currency', 'NGN' ) ),
            ) );
        }
    }

    public static function booking_form( $atts ) {
        $atts = shortcode_atts( array(
            'type'    => 'room',
            'room_id' => 0,
        ), $atts );

        $rooms = $atts['room_id']
            ? array( GHM_Rooms::get_room( $atts['room_id'] ) )
            : GHM_Rooms::get_rooms( array( 'status' => 'available', 'type' => $atts['type'] !== 'workspace' ? '' : 'workspace' ) );

        ob_start();
        include GHM_PLUGIN_DIR . 'templates/booking-form.php';
        return ob_get_clean();
    }

    public static function rooms_list( $atts ) {
        $atts  = shortcode_atts( array( 'type' => '' ), $atts );
        $rooms = GHM_Rooms::get_rooms( array( 'status' => 'available', 'type' => $atts['type'] ) );
        ob_start();
        include GHM_PLUGIN_DIR . 'templates/rooms-list.php';
        return ob_get_clean();
    }


    public static function waitlist_form( $atts ) {
        $atts    = shortcode_atts( array( 'room_id' => 0 ), $atts );
        $room_id = absint( $atts['room_id'] );
        $room    = $room_id ? GHM_Rooms::get_room( $room_id ) : null;
        $success = false;
        if ( isset($_POST['ghm_waitlist_submit']) && wp_verify_nonce($_POST['ghm_waitlist_nonce'],'ghm_waitlist') ) {
            $rid = !empty($_POST['room_id']) ? absint($_POST['room_id']) : $room_id;
            $id  = GHM_Waitlist::add( array(
                'room_id'    => $rid,
                'first_name' => sanitize_text_field($_POST['first_name']??''),
                'last_name'  => sanitize_text_field($_POST['last_name']??''),
                'email'      => sanitize_email($_POST['email']??''),
                'phone'      => sanitize_text_field($_POST['phone']??''),
                'check_in'   => sanitize_text_field($_POST['check_in']??''),
                'check_out'  => sanitize_text_field($_POST['check_out']??''),
                'adults'     => absint($_POST['adults']??1),
            ) );
            $success = $id > 0;
        }
        ob_start();
        include GHM_PLUGIN_DIR . 'templates/waitlist-form.php';
        return ob_get_clean();
    }

    public static function booking_confirmation( $atts ) {
        $ref     = get_query_var( 'ghm_booking_ref' ) ?: ( $_GET['ref'] ?? '' );
        $booking = $ref ? GHM_Bookings::get_booking_by_ref( sanitize_text_field( $ref ) ) : null;
        ob_start();
        include GHM_PLUGIN_DIR . 'templates/booking-confirmation.php';
        return ob_get_clean();
    }

    /**
     * Public list of approved guest reviews.
     * Usage: [ghm_reviews limit="10" min_rating="0" layout="cards"]
     */
    public static function reviews_list( $atts ) {
        $atts = shortcode_atts( array(
            'limit'      => 10,
            'min_rating' => 0,
            'layout'     => 'cards', // cards | list
        ), $atts );

        global $wpdb;
        $limit      = max( 1, min( 100, (int) $atts['limit'] ) );
        $min_rating = max( 0, min( 5, (int) $atts['min_rating'] ) );

        $reviews = $wpdb->get_results( $wpdb->prepare(
            "SELECT rv.*, CONCAT(c.first_name,' ',c.last_name) AS guest_name,
                    r.name AS room_name
               FROM {$wpdb->prefix}ghm_reviews rv
               LEFT JOIN {$wpdb->prefix}ghm_bookings  b ON b.id = rv.booking_id
               LEFT JOIN {$wpdb->prefix}ghm_customers c ON c.id = rv.customer_id
               LEFT JOIN {$wpdb->prefix}ghm_rooms     r ON r.id = b.room_id
              WHERE rv.status = 'approved' AND rv.rating >= %d
              ORDER BY rv.created_at DESC
              LIMIT %d",
            $min_rating, $limit
        ) );

        $avg = (float) $wpdb->get_var(
            "SELECT AVG(rating) FROM {$wpdb->prefix}ghm_reviews WHERE status='approved'"
        );
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ghm_reviews WHERE status='approved'"
        );

        ob_start();
        ?>
        <div class="ghm-reviews-wrap" style="font-family:inherit;">
          <?php if ( $count > 0 ): ?>
          <div class="ghm-reviews-summary" style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding:14px 18px;background:#fafafa;border:1px solid #eee;border-radius:10px;">
            <div style="font-size:30px;font-weight:700;color:#1a1a2e;line-height:1;">
              <?php echo number_format( $avg, 1 ); ?>
              <span style="font-size:18px;color:#9ca3af;">/ 5</span>
            </div>
            <div>
              <div style="font-size:18px;color:#f59e0b;letter-spacing:1px;">
                <?php
                $full = (int) round( $avg );
                for ( $i = 1; $i <= 5; $i++ ) {
                    echo $i <= $full ? '★' : '<span style="color:#e5e7eb;">★</span>';
                }
                ?>
              </div>
              <div style="font-size:13px;color:#6b7280;">
                Based on <?php echo (int) $count; ?> verified guest review<?php echo $count === 1 ? '' : 's'; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if ( empty( $reviews ) ): ?>
          <p style="color:#9ca3af;font-size:14px;">No reviews yet — be our next happy guest!</p>
          <?php else: ?>
          <div class="ghm-reviews-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
            <?php foreach ( $reviews as $rv ):
              $name = trim( (string) $rv->guest_name );
              if ( $name === '' ) {
                  $name = 'Anonymous Guest';
              } else {
                  // Show first name + first initial of last name to keep some privacy
                  $parts = preg_split( '/\s+/', $name );
                  $first = $parts[0];
                  $last  = isset( $parts[1] ) ? mb_substr( $parts[1], 0, 1 ) . '.' : '';
                  $name  = trim( $first . ' ' . $last );
              }
            ?>
            <div class="ghm-review-card" style="background:#fff;border:1px solid #eee;border-radius:10px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.03);">
              <div style="font-size:16px;color:#f59e0b;letter-spacing:1px;margin-bottom:6px;">
                <?php for ( $i = 1; $i <= 5; $i++ ) {
                    echo $i <= (int) $rv->rating ? '★' : '<span style="color:#e5e7eb;">★</span>';
                } ?>
              </div>
              <?php if ( ! empty( $rv->title ) ): ?>
              <div style="font-weight:600;font-size:15px;color:#1a1a2e;margin-bottom:6px;">
                <?php echo esc_html( $rv->title ); ?>
              </div>
              <?php endif; ?>
              <?php if ( ! empty( $rv->comment ) ): ?>
              <p style="font-size:14px;line-height:1.55;color:#374151;margin:0 0 10px;">
                <?php echo nl2br( esc_html( $rv->comment ) ); ?>
              </p>
              <?php endif; ?>
              <div style="font-size:12px;color:#6b7280;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                <span><?php echo esc_html( $name ); ?><?php echo $rv->room_name ? ' · ' . esc_html( $rv->room_name ) : ''; ?></span>
                <span><?php echo esc_html( date( 'M Y', strtotime( $rv->created_at ) ) ); ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
