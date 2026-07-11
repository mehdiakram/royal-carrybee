<?php
/**
 * Carrybee Admin Enhancements
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCB_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
        add_action( 'wp_ajax_rcb_sync_order', array( $this, 'ajax_sync_order' ) );
        add_action( 'wp_ajax_rcb_cancel_order', array( $this, 'ajax_cancel_order' ) );
        add_action( 'wp_ajax_rcb_get_cities', array( $this, 'ajax_get_cities' ) );
        add_action( 'wp_ajax_rcb_get_zones', array( $this, 'ajax_get_zones' ) );
        add_action( 'wp_ajax_rcb_get_areas', array( $this, 'ajax_get_areas' ) );
        add_action( 'wp_ajax_rcb_create_order', array( $this, 'ajax_create_order' ) );

        // Bulk actions for WooCommerce orders
        add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_actions' ) );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'bulk_action_admin_notice' ) );

        // Orders Table custom column and quick actions
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_table_column' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_table_column' ), 20, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_table_column' ), 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_order_table_column' ), 20, 2 );
        add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_table_actions' ), 100, 2 );
    }

    public function register_bulk_actions( $bulk_actions ) {
        $bulk_actions['rcb_bulk_create'] = __( '🐝 Carrybee: Send to Delivery (Bulk)', 'royal-carrybee' );
        $bulk_actions['rcb_bulk_sync']   = __( '🐝 Carrybee: Sync Status (Bulk)', 'royal-carrybee' );
        return $bulk_actions;
    }

    public function handle_bulk_actions( $redirect_to, $action, $ids ) {
        if ( ! in_array( $action, array( 'rcb_bulk_create', 'rcb_bulk_sync' ), true ) ) {
            return $redirect_to;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return $redirect_to;
        }

        $created_count = 0;
        $failed_count  = 0;
        $synced_count  = 0;
        $error_messages = array();

        if ( $action === 'rcb_bulk_create' ) {
            if ( ! class_exists( 'RCB_Order' ) ) {
                require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-order.php';
            }
            $rcb_order_instance = RCB_Order::get_instance();

            foreach ( $ids as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( ! $order ) {
                    continue;
                }

                $consignment_id = $order->get_meta( '_rcb_consignment_id' );
                if ( $consignment_id ) {
                    continue;
                }

                $result = $rcb_order_instance->create_carrybee_order( $order_id );
                if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] ) {
                    $created_count++;
                } else {
                    $failed_count++;
                    if ( is_array( $result ) && ! empty( $result['message'] ) && count( $error_messages ) < 3 ) {
                        $error_messages[] = '#' . $order_id . ': ' . $result['message'];
                    }
                }
            }

            $redirect_to = add_query_arg( array(
                'rcb_bulk_action' => 'created',
                'rcb_created'     => $created_count,
                'rcb_failed'      => $failed_count,
                'rcb_err'         => urlencode( implode( ' | ', $error_messages ) ),
            ), $redirect_to );
        } elseif ( $action === 'rcb_bulk_sync' ) {
            if ( ! class_exists( 'RCB_Order' ) ) {
                require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-order.php';
            }
            foreach ( $ids as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( ! $order ) {
                    continue;
                }
                $consignment_id = $order->get_meta( '_rcb_consignment_id' );
                if ( $consignment_id ) {
                    $sync_res = RCB_Order::sync_order_status( $consignment_id );
                    if ( $sync_res ) {
                        $synced_count++;
                    }
                }
            }

            $redirect_to = add_query_arg( array(
                'rcb_bulk_action' => 'synced',
                'rcb_synced'      => $synced_count,
            ), $redirect_to );
        }

        return $redirect_to;
    }

    public function bulk_action_admin_notice() {
        if ( empty( $_GET['rcb_bulk_action'] ) ) {
            return;
        }

        $action = sanitize_text_field( $_GET['rcb_bulk_action'] );
        if ( $action === 'created' ) {
            $created = absint( $_GET['rcb_created'] ?? 0 );
            $failed  = absint( $_GET['rcb_failed'] ?? 0 );
            $err_str = ! empty( $_GET['rcb_err'] ) ? sanitize_text_field( urldecode( $_GET['rcb_err'] ) ) : '';

            if ( $created > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . sprintf( __( '🐝 Successfully created %d Carrybee order(s).', 'royal-carrybee' ), $created ) . '</strong></p></div>';
            }
            if ( $failed > 0 ) {
                $err_msg = $err_str ? ' (' . esc_html( $err_str ) . ')' : '';
                echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf( __( 'Could not create Carrybee order for %d order(s)%s.', 'royal-carrybee' ), $failed, $err_msg ) . '</p></div>';
            }
        } elseif ( $action === 'synced' ) {
            $synced = absint( $_GET['rcb_synced'] ?? 0 );
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . sprintf( __( '🐝 Successfully synced %d Carrybee order(s).', 'royal-carrybee' ), $synced ) . '</strong></p></div>';
        }
    }

    public function add_order_meta_box() {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) 
            ? wc_get_page_screen_id( 'shop-order' ) 
            : 'shop_order';

        add_meta_box(
            'rcb-order-info',
            __( '🐝 Carrybee Shipping', 'royal-carrybee' ),
            array( $this, 'render_order_meta_box' ),
            $screen,
            'side',
            'high'
        );
    }

    public function render_order_meta_box( $post_or_order ) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
        if ( ! $order ) return;

        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        $delivery_fee = $order->get_meta( '_rcb_delivery_fee' );
        $cod_fee = $order->get_meta( '_rcb_cod_fee' );
        $collected = $order->get_meta( '_rcb_collected_amount' );

        global $wpdb;
        $rcb_order = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rcb_orders WHERE order_id = %d", $order->get_id()
        ) );
        ?>
        <div class="rcb-metabox">
            <?php if ( $consignment_id ) : ?>
                <div class="rcb-info-row">
                    <span class="label"><?php esc_html_e( 'Consignment ID', 'royal-carrybee' ); ?></span>
                    <code class="value"><?php echo esc_html( $consignment_id ); ?></code>
                </div>
                <?php if ( $rcb_order ) : ?>
                <div class="rcb-info-row">
                    <span class="label"><?php esc_html_e( 'Status', 'royal-carrybee' ); ?></span>
                    <span class="rcb-status"><?php echo esc_html( $rcb_order->status ); ?></span>
                </div>
                <div class="rcb-info-row">
                    <span class="label"><?php esc_html_e( 'Delivery Fee', 'royal-carrybee' ); ?></span>
                    <span class="value">৳<?php echo esc_html( $rcb_order->delivery_fee ); ?></span>
                </div>
                <div class="rcb-info-row">
                    <span class="label"><?php esc_html_e( 'COD Fee', 'royal-carrybee' ); ?></span>
                    <span class="value">৳<?php echo esc_html( $rcb_order->cod_fee ); ?></span>
                </div>
                <div class="rcb-info-row">
                    <span class="label"><?php esc_html_e( 'Collected', 'royal-carrybee' ); ?></span>
                    <span class="value">৳<?php echo esc_html( $rcb_order->collected_amount ); ?></span>
                </div>
                <div class="rcb-info-row">
                    <span class="label"><?php esc_html_e( 'Attempts', 'royal-carrybee' ); ?></span>
                    <span class="value"><?php echo esc_html( $rcb_order->attempts ); ?></span>
                </div>
                <?php endif; ?>
                <div class="rcb-actions">
                    <button type="button" class="button rcb-sync-btn" data-consignment="<?php echo esc_attr( $consignment_id ); ?>">
                        <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Sync', 'royal-carrybee' ); ?>
                    </button>
                    <button type="button" class="button rcb-cancel-btn" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
                        <span class="dashicons dashicons-no"></span> <?php esc_html_e( 'Cancel', 'royal-carrybee' ); ?>
                    </button>
                </div>
            <?php else : ?>
                <p class="rcb-no-order"><?php esc_html_e( 'No Carrybee order created yet.', 'royal-carrybee' ); ?></p>
                
                <?php 
                $city_id  = $order->get_meta( '_rcb_shipping_city_id' );
                $zone_id  = $order->get_meta( '_rcb_shipping_zone_id' );
                
                // Get order address for display/hint
                $ship_city  = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
                $ship_state = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
                $ship_addr1 = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1();
                $ship_addr2 = $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2();
                $ship_name  = trim( ( $order->get_shipping_first_name() ?: $order->get_billing_first_name() ) . ' ' . ( $order->get_shipping_last_name() ?: $order->get_billing_last_name() ) );
                $ship_phone = $order->get_billing_phone() ?: $order->get_shipping_phone();
                ?>

                <?php if ( $ship_city || $ship_state || $ship_addr1 ) : ?>
                <div class="rcb-order-address-preview">
                    <p class="rcb-address-label"><?php esc_html_e( 'Order Address:', 'royal-carrybee' ); ?></p>
                    <table class="rcb-addr-table">
                        <?php if ( $ship_name ) : ?>
                        <tr><td><?php esc_html_e( 'Name:', 'royal-carrybee' ); ?></td><td><?php echo esc_html( $ship_name ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $ship_phone ) : ?>
                        <tr><td><?php esc_html_e( 'Phone:', 'royal-carrybee' ); ?></td><td><?php echo esc_html( $ship_phone ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $ship_addr1 ) : ?>
                        <tr><td><?php esc_html_e( 'Address:', 'royal-carrybee' ); ?></td><td><?php echo esc_html( $ship_addr1 . ( $ship_addr2 ? ', ' . $ship_addr2 : '' ) ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $ship_city ) : ?>
                        <tr><td><?php esc_html_e( 'City:', 'royal-carrybee' ); ?></td><td><strong><?php echo esc_html( $ship_city ); ?></strong></td></tr>
                        <?php endif; ?>
                        <?php if ( $ship_state ) : ?>
                        <tr><td><?php esc_html_e( 'State:', 'royal-carrybee' ); ?></td><td><strong><?php echo esc_html( $ship_state ); ?></strong></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if ( ! $city_id || ! $zone_id ) : ?>
                    <div class="rcb-manual-location">
                        <p class="rcb-location-title">
                            <span class="dashicons dashicons-location"></span>
                            <?php esc_html_e( 'Carrybee Location', 'royal-carrybee' ); ?>
                            <span class="rcb-autofill-badge" id="rcb-autofill-badge" style="display:none;">✨ <?php esc_html_e( 'Auto-filled', 'royal-carrybee' ); ?></span>
                        </p>
                        <p class="rcb-location-hint"><?php esc_html_e( 'City & Zone will auto-fill from your order address.', 'royal-carrybee' ); ?></p>
                        
                        <div class="rcb-select-wrap">
                            <label><?php esc_html_e( 'City/District', 'royal-carrybee' ); ?></label>
                            <select id="rcb_manual_city" class="rcb-select">
                                <option value=""><?php esc_html_e( 'Loading cities...', 'royal-carrybee' ); ?></option>
                            </select>
                        </div>
                        <div class="rcb-select-wrap">
                            <label><?php esc_html_e( 'Zone/Thana', 'royal-carrybee' ); ?></label>
                            <select id="rcb_manual_zone" class="rcb-select" disabled>
                                <option value=""><?php esc_html_e( 'Select Zone/Thana', 'royal-carrybee' ); ?></option>
                            </select>
                        </div>
                        <div class="rcb-select-wrap">
                            <label><?php esc_html_e( 'Area (Optional)', 'royal-carrybee' ); ?></label>
                            <select id="rcb_manual_area" class="rcb-select" disabled>
                                <option value=""><?php esc_html_e( 'Select Area', 'royal-carrybee' ); ?></option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="button" class="button button-primary rcb-create-btn" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
                    <span class="dashicons dashicons-external" style="margin-top:3px;"></span> <?php esc_html_e( 'Sent to Carrybee', 'royal-carrybee' ); ?>
                </button>
            <?php endif; ?>
        </div>
        <style>
        .rcb-metabox { padding: 5px 0; }
        .rcb-info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .rcb-info-row .label { color: #666; font-size: 12px; }
        .rcb-info-row .value { font-weight: 600; }
        .rcb-info-row code { background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 3px; }
        .rcb-status { background: #d1fae5; color: #065f46; padding: 2px 10px; border-radius: 10px; font-size: 11px; }
        .rcb-actions { margin-top: 15px; display: flex; gap: 8px; }
        .rcb-actions .button { flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; }
        .rcb-cancel-btn { color: #d63638 !important; border-color: #d63638 !important; }
        .rcb-no-order { color: #666; font-style: italic; margin: 10px 0; }
        </style>
        <?php
    }

    public function ajax_sync_order() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        $consignment_id = sanitize_text_field( $_POST['consignment_id'] ?? '' );
        $result = RCB_Order::sync_order_status( $consignment_id );
        $result ? wp_send_json_success( $result ) : wp_send_json_error( 'Sync failed' );
    }

    public function ajax_cancel_order() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $reason = sanitize_text_field( $_POST['reason'] ?? 'Cancelled by admin' );
        $result = RCB_Order::cancel_order( $order_id, $reason );
        $result ? wp_send_json_success() : wp_send_json_error( 'Cancel failed' );
    }

    public function ajax_get_cities() {
        // Verify nonce with proper error message
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'royal-carrybee' ) );
        }
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'royal-carrybee' ) );
        }
        
        $result = RCB_API::get_cities();
        
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        
        // Handle different response structures
        $cities = $result['data']['cities'] ?? $result['cities'] ?? $result['data'] ?? array();
        wp_send_json_success( $cities );
    }

    public function ajax_get_zones() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        $city_id = absint( $_POST['city_id'] ?? 0 );
        $result = RCB_API::get_zones( $city_id );
        
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        
        $zones = $result['data']['zones'] ?? $result['zones'] ?? $result['data'] ?? array();
        wp_send_json_success( $zones );
    }

    public function ajax_get_areas() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        $city_id = absint( $_POST['city_id'] ?? 0 );
        $zone_id = absint( $_POST['zone_id'] ?? 0 );
        $result = RCB_API::get_areas( $city_id, $zone_id );
        
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        
        $areas = $result['data']['areas'] ?? $result['areas'] ?? $result['data'] ?? array();
        wp_send_json_success( $areas );
    }

    /**
     * AJAX: Create Carrybee order manually
     */
    public function ajax_create_order() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied', 'royal-carrybee' ) );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( __( 'Invalid order ID', 'royal-carrybee' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( __( 'Order not found', 'royal-carrybee' ) );
        }

        // Check if already created
        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( $consignment_id ) {
            wp_send_json_error( __( 'Carrybee order already exists', 'royal-carrybee' ) );
        }

        // Check settings
        $settings = get_option( 'rcb_settings', array() );
        $store_id = $settings['default_store'] ?? '';
        
        if ( empty( $store_id ) ) {
            wp_send_json_error( __( 'No default store set. Please configure in settings.', 'royal-carrybee' ) );
        }

        // Get location IDs
        // Allow get IDs from POST if provided (manual update)
        $city_id = isset($_POST['city_id']) ? absint($_POST['city_id']) : $order->get_meta( '_rcb_shipping_city_id' );
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : $order->get_meta( '_rcb_shipping_zone_id' );
        $area_id = isset($_POST['area_id']) ? absint($_POST['area_id']) : $order->get_meta( '_rcb_shipping_area_id' );

        // Save manually provided IDs to order
        if ( isset($_POST['city_id']) ) {
             $order->update_meta_data( '_rcb_shipping_city_id', $city_id );
             $order->update_meta_data( '_rcb_shipping_zone_id', $zone_id );
             $order->update_meta_data( '_rcb_shipping_area_id', $area_id );
             $order->save();
        }

        if ( ! $city_id || ! $zone_id ) {
            // Server-side auto match if missing (when clicked from table quick action or 1-click button)
            $states = WC()->countries->get_states( 'BD' );
            $state_code = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
            $state_name = isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;

            // In BD, Woo State (Dropdown) = Carrybee City, Woo City (Text) = Carrybee Zone
            $carrybee_city_name = ! empty( $state_name ) ? $state_name : ( $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city() );

            if ( ! $city_id && ! empty( $carrybee_city_name ) ) {
                $cities_res = RCB_API::get_cities();
                $cities = $cities_res['data']['cities'] ?? $cities_res['cities'] ?? $cities_res['data'] ?? array();
                if ( is_array( $cities ) && ! empty( $cities ) ) {
                    foreach ( $cities as $c ) {
                        if ( isset( $c['name'] ) && ( strcasecmp( trim( $c['name'] ), trim( $carrybee_city_name ) ) === 0 || stripos( trim( $carrybee_city_name ), trim( $c['name'] ) ) !== false || stripos( trim( $c['name'] ), trim( $carrybee_city_name ) ) !== false ) ) {
                            $city_id = $c['id'];
                            break;
                        }
                    }
                }
            }

            if ( $city_id && ! $zone_id ) {
                $carrybee_zone_name = $order->get_shipping_city() ? $order->get_shipping_city() : ( $order->get_billing_city() ? $order->get_billing_city() : ( $order->get_shipping_address_2() ? $order->get_shipping_address_2() : '' ) );
                if ( ! empty( $carrybee_zone_name ) ) {
                    $zones_res = RCB_API::get_zones( $city_id );
                    $zones = $zones_res['data']['zones'] ?? $zones_res['zones'] ?? $zones_res['data'] ?? array();
                    if ( is_array( $zones ) && ! empty( $zones ) ) {
                        foreach ( $zones as $z ) {
                            if ( isset( $z['name'] ) && ( strcasecmp( trim( $z['name'] ), trim( $carrybee_zone_name ) ) === 0 || stripos( trim( $carrybee_zone_name ), trim( $z['name'] ) ) !== false || stripos( trim( $z['name'] ), trim( $carrybee_zone_name ) ) !== false ) ) {
                                $zone_id = $z['id'];
                                break;
                            }
                        }
                    }
                }
            }

            if ( $city_id && $zone_id ) {
                $order->update_meta_data( '_rcb_shipping_city_id', $city_id );
                $order->update_meta_data( '_rcb_shipping_zone_id', $zone_id );
                $order->save();
            }
        }

        if ( ! $city_id || ! $zone_id ) {
            $addr1 = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1();
            $addr2 = $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2();
            $city  = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
            
            $full_address = trim( implode( ', ', array_filter( array( $addr1, $addr2, $city ) ) ) );
            if ( ! empty( $full_address ) ) {
                $lookup_res = RCB_API::get_address_details( $full_address );
                if ( ! isset( $lookup_res['error'] ) && ! empty( $lookup_res['data'] ) ) {
                    $city_id = $lookup_res['data']['city_id'] ?? $city_id;
                    $zone_id = $lookup_res['data']['zone_id'] ?? $zone_id;
                    
                    if ( $city_id && $zone_id ) {
                        $order->update_meta_data( '_rcb_shipping_city_id', $city_id );
                        $order->update_meta_data( '_rcb_shipping_zone_id', $zone_id );
                        $order->save();
                    }
                }
            }
        }

        if ( ! $city_id || ! $zone_id ) {
            wp_send_json_error( __( 'Missing shipping city or zone data. Please select location manually on the order page.', 'royal-carrybee' ) );
        }

        $first_name = $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name();
        $last_name  = $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name();
        $recipient_name = trim( $first_name . ' ' . $last_name );
        if ( empty( $recipient_name ) ) {
            $recipient_name = 'Customer';
        }

        $address_1 = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1();
        $address_2 = $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2();
        $recipient_address = trim( trim( $address_1 . ', ' . $address_2, ', ' ) );
        if ( empty( $recipient_address ) ) {
            $recipient_address = 'N/A';
        }

        $phone = $order->get_billing_phone() ? $order->get_billing_phone() : $order->get_shipping_phone();
        $clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
        if ( substr( $clean_phone, 0, 3 ) === '880' && strlen( $clean_phone ) > 11 ) {
            $clean_phone = substr( $clean_phone, 2 );
        }
        if ( empty( $clean_phone ) ) {
            $clean_phone = $phone;
        }

        // Prepare order data
        $order_data = array(
            'store_id'            => $store_id,
            'merchant_order_id'   => (string) $order_id,
            'delivery_type'       => absint( $settings['default_delivery_type'] ?? 1 ),
            'product_type'        => absint( $settings['default_product_type'] ?? 1 ),
            'recipient_phone'     => $clean_phone,
            'recipient_name'      => $recipient_name,
            'recipient_address'   => $recipient_address,
            'city_id'             => absint( $city_id ),
            'zone_id'             => absint( $zone_id ),
            'item_weight'         => absint( $settings['default_weight'] ?? 500 ),
            'item_quantity'       => max( 1, absint( $order->get_item_count() ) ),
        );

        if ( $area_id ) {
            $order_data['area_id'] = absint( $area_id );
        }

        if ( $order->get_payment_method() === 'cod' ) {
            $order_data['collectable_amount'] = absint( $order->get_total() );
        }

        // Product description
        $items_desc = array();
        foreach ( $order->get_items() as $item ) {
            $items_desc[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        $order_data['product_description'] = implode( ', ', array_slice( $items_desc, 0, 3 ) );

        // Create order via API
        $result = RCB_API::create_order( $order_data );

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        // Save to order meta
        $consignment = $result['data']['order'] ?? array();
        $order->update_meta_data( '_rcb_consignment_id', $consignment['consignment_id'] ?? '' );
        $order->update_meta_data( '_rcb_store_id', $consignment['store_id'] ?? '' );
        $order->update_meta_data( '_rcb_delivery_fee', $consignment['delivery_fee'] ?? 0 );
        $order->update_meta_data( '_rcb_cod_fee', $consignment['cod_fee'] ?? 0 );
        $order->save();

        // Save to custom table
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'rcb_orders',
            array(
                'order_id'        => $order_id,
                'consignment_id'  => $consignment['consignment_id'] ?? '',
                'store_id'        => $consignment['store_id'] ?? '',
                'status'          => 'pending',
                'delivery_fee'    => floatval( $consignment['delivery_fee'] ?? 0 ),
                'cod_fee'         => floatval( $consignment['cod_fee'] ?? 0 ),
            ),
            array( '%d', '%s', '%s', '%s', '%f', '%f' )
        );

        $order->add_order_note( 
            sprintf( __( 'Carrybee order created manually. Consignment ID: %s', 'royal-carrybee' ), $consignment['consignment_id'] ?? 'N/A' ) 
        );

        wp_send_json_success( array(
            'message'        => __( 'Carrybee order created successfully!', 'royal-carrybee' ),
            'consignment_id' => $consignment['consignment_id'] ?? '',
        ) );
    }

    /**
     * Add column to WooCommerce Orders table
     */
    public function add_order_table_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $column ) {
            $new_columns[ $key ] = $column;
            if ( 'order_status' === $key ) {
                $new_columns['rcb_status'] = __( '🐝 Carrybee', 'royal-carrybee' );
            }
        }
        if ( ! isset( $new_columns['rcb_status'] ) ) {
            $new_columns['rcb_status'] = __( '🐝 Carrybee', 'royal-carrybee' );
        }
        return $new_columns;
    }

    /**
     * Render column content for Legacy Orders table
     */
    public function render_order_table_column( $column, $post_id ) {
        if ( 'rcb_status' === $column ) {
            $order = wc_get_order( $post_id );
            if ( $order ) {
                $this->render_order_column_content( $order );
            }
        }
    }

    /**
     * Render column content for HPOS Orders table
     */
    public function render_hpos_order_table_column( $column, $order ) {
        if ( 'rcb_status' === $column ) {
            if ( $order instanceof WC_Order ) {
                $this->render_order_column_content( $order );
            }
        }
    }

    /**
     * Render Carrybee column inside order list row
     */
    private function render_order_column_content( $order ) {
        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( $consignment_id ) {
            echo '<span class="rcb-status rcb-status-delivered" style="font-size:10px; padding:2px 6px; display:inline-block; margin-bottom:3px;" title="' . esc_attr__( 'Consignment ID', 'royal-carrybee' ) . '">📦 ' . esc_html( $consignment_id ) . '</span><br>';
            echo '<button type="button" class="button button-small rcb-sync-btn" data-consignment="' . esc_attr( $consignment_id ) . '" style="padding:0 6px; min-height:22px; line-height:20px; font-size:11px;">' . esc_html__( 'Sync', 'royal-carrybee' ) . '</button>';
        } else {
            echo '<button type="button" class="button button-small rcb-create-btn rcb-table-send-btn" data-order="' . esc_attr( $order->get_id() ) . '" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color:#fff; border:none; padding:2px 8px; min-height:24px; line-height:20px; border-radius:4px; font-weight:600; font-size:11px; cursor:pointer; box-shadow:0 1px 3px rgba(245,158,11,0.3);">🐝 ' . esc_html__( 'Sent to Carrybee', 'royal-carrybee' ) . '</button>';
        }
    }

    /**
     * Add quick action icon to Orders actions column
     */
    public function add_order_table_actions( $actions, $order ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) {
            return $actions;
        }

        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( $consignment_id ) {
            $actions['rcb_sync'] = array(
                'url'    => 'javascript:void(0);',
                'name'   => __( 'Carrybee: Sync (' . $consignment_id . ')', 'royal-carrybee' ),
                'action' => 'rcb_sync rcb-sync-btn',
            );
        } else {
            $actions['rcb_send'] = array(
                'url'    => 'javascript:void(0);',
                'name'   => __( 'Sent to Carrybee (এক ক্লিকে)', 'royal-carrybee' ),
                'action' => 'rcb_send rcb-create-btn',
            );
        }
        return $actions;
    }
}
