<?php
/**
 * Carrybee Order Processing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCB_Order {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Auto-create Carrybee order
        add_action( 'woocommerce_order_status_processing', array( $this, 'create_carrybee_order' ) );
        add_action( 'woocommerce_order_status_on-hold', array( $this, 'create_carrybee_order' ) );
        
        // Add order note with tracking info
        add_action( 'woocommerce_thankyou', array( $this, 'display_tracking_info' ) );
        
        // Add tracking to emails
        add_action( 'woocommerce_email_after_order_table', array( $this, 'add_tracking_to_email' ), 10, 4 );
    }

    /**
     * Create Carrybee order
     */
    public function create_carrybee_order( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        // Check if already created
        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( $consignment_id ) {
            return;
        }

        // Check settings
        $settings = get_option( 'rcb_settings', array() );
        if ( ( $settings['auto_create_order'] ?? 'yes' ) !== 'yes' ) {
            return;
        }

        // Check if store is set
        $store_id = $settings['default_store'] ?? '';
        if ( empty( $store_id ) ) {
            $order->add_order_note( __( 'Carrybee: Could not create order - no default store set.', 'royal-carrybee' ) );
            return;
        }

        // Get location IDs
        $city_id = $order->get_meta( '_rcb_shipping_city_id' );
        $zone_id = $order->get_meta( '_rcb_shipping_zone_id' );
        $area_id = $order->get_meta( '_rcb_shipping_area_id' );

        // Try auto-matching city and zone if missing (for bulk/past orders)
        if ( ! $city_id || ! $zone_id ) {
            $states = WC()->countries->get_states( 'BD' );
            $state_code = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
            $state_name = isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;
            
            // In BD, Woo State (Dropdown) = Carrybee City, Woo City (Text) = Carrybee Zone
            $carrybee_city_name = ! empty( $state_name ) ? $state_name : ( $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city() );
            
            if ( ! empty( $carrybee_city_name ) ) {
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

        // Final fallback: Use Address Details Lookup API based on full address text (like Steadfast)
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
            $msg = __( 'Carrybee: Could not create order - missing city or zone.', 'royal-carrybee' );
            $order->add_order_note( $msg );
            return array( 'success' => false, 'message' => $msg );
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
            'item_weight'         => $this->get_order_weight( $order, $settings ),
            'item_quantity'       => max( 1, absint( $order->get_item_count() ) ),
        );

        // Add area if set
        if ( $area_id ) {
            $order_data['area_id'] = absint( $area_id );
        }

        // Add COD amount if cash on delivery
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
            $err_msg = isset( $result['message'] ) ? $result['message'] : __( 'Unknown API error', 'royal-carrybee' );
            $order->add_order_note( 
                sprintf( __( 'Carrybee: Failed to create order - %s', 'royal-carrybee' ), $err_msg ) 
            );
            return array( 'success' => false, 'message' => $err_msg );
        }

        // Save to order meta
        $consignment = $result['data']['order'] ?? array();
        $consignment_id_str = $consignment['consignment_id'] ?? '';
        $order->update_meta_data( '_rcb_consignment_id', $consignment_id_str );
        $order->update_meta_data( '_rcb_store_id', $consignment['store_id'] ?? '' );
        $order->update_meta_data( '_rcb_delivery_fee', $consignment['delivery_fee'] ?? 0 );
        $order->update_meta_data( '_rcb_cod_fee', $consignment['cod_fee'] ?? 0 );
        $order->save();

        // Save to custom table
        $this->save_to_table( $order_id, $consignment );

        // Add order note
        $order->add_order_note( 
            sprintf( 
                __( 'Carrybee order created. Consignment ID: %s', 'royal-carrybee' ), 
                $consignment_id_str
            ) 
        );

        return array( 'success' => true, 'consignment_id' => $consignment_id_str );
    }

    /**
     * Get order weight
     */
    private function get_order_weight( $order, $settings ) {
        $total_weight = 0;

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product && $product->get_weight() ) {
                // Convert to grams (assuming weight is in kg)
                $weight = floatval( $product->get_weight() ) * 1000;
                $total_weight += $weight * $item->get_quantity();
            }
        }

        // Use default weight if no weight set
        if ( $total_weight <= 0 ) {
            $total_weight = absint( $settings['default_weight'] ?? 500 );
        }

        // Cap at 25kg
        return min( $total_weight, 25000 );
    }

    /**
     * Save to custom table
     */
    private function save_to_table( $order_id, $consignment ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rcb_orders';

        $wpdb->insert(
            $table_name,
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
    }

    /**
     * Display tracking info on thank you page
     */
    public function display_tracking_info( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( ! $consignment_id ) {
            return;
        }
        ?>
        <div class="rcb-tracking-box">
            <h2><?php esc_html_e( 'Delivery Tracking', 'royal-carrybee' ); ?></h2>
            <p>
                <strong><?php esc_html_e( 'Tracking ID:', 'royal-carrybee' ); ?></strong>
                <code><?php echo esc_html( $consignment_id ); ?></code>
            </p>
            <p class="rcb-tracking-note">
                <?php esc_html_e( 'Your order has been submitted for delivery via Carrybee courier.', 'royal-carrybee' ); ?>
            </p>
        </div>
        <style>
        .rcb-tracking-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .rcb-tracking-box h2 { margin-top: 0; color: #1d2327; }
        .rcb-tracking-box code { background: #2271b1; color: #fff; padding: 5px 15px; border-radius: 4px; font-size: 16px; }
        .rcb-tracking-note { color: #666; margin-bottom: 0; }
        </style>
        <?php
    }

    /**
     * Add tracking to email
     */
    public function add_tracking_to_email( $order, $sent_to_admin, $plain_text, $email ) {
        if ( $sent_to_admin ) {
            return;
        }

        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( ! $consignment_id ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n\n" . __( 'Carrybee Tracking ID:', 'royal-carrybee' ) . ' ' . $consignment_id . "\n";
        } else {
            ?>
            <div style="margin: 20px 0; padding: 15px; background: #f7f7f7; border-radius: 5px;">
                <h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Delivery Tracking', 'royal-carrybee' ); ?></h3>
                <p style="margin: 0;">
                    <strong><?php esc_html_e( 'Tracking ID:', 'royal-carrybee' ); ?></strong>
                    <span style="background: #2271b1; color: #fff; padding: 3px 10px; border-radius: 3px;"><?php echo esc_html( $consignment_id ); ?></span>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Cancel Carrybee order
     */
    public static function cancel_order( $order_id, $reason = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        $consignment_id = $order->get_meta( '_rcb_consignment_id' );
        if ( ! $consignment_id ) {
            return false;
        }

        $result = RCB_API::cancel_order( $consignment_id, $reason );

        if ( isset( $result['error'] ) && $result['error'] ) {
            $order->add_order_note( 
                sprintf( __( 'Carrybee: Failed to cancel - %s', 'royal-carrybee' ), $result['message'] ) 
            );
            return false;
        }

        $order->add_order_note( __( 'Carrybee: Order cancelled successfully', 'royal-carrybee' ) );
        
        // Update custom table
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rcb_orders',
            array( 'status' => 'cancelled' ),
            array( 'consignment_id' => $consignment_id ),
            array( '%s' ),
            array( '%s' )
        );

        return true;
    }

    /**
     * Sync order status
     */
    public static function sync_order_status( $consignment_id ) {
        $result = RCB_API::get_order_details( $consignment_id );

        if ( isset( $result['error'] ) && $result['error'] ) {
            return false;
        }

        $data = $result['data'] ?? array();
        
        // Update custom table
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rcb_orders',
            array(
                'status'           => $data['transfer_status'] ?? 'unknown',
                'collected_amount' => floatval( $data['collected_amount'] ?? 0 ),
                'attempts'         => absint( $data['attempt'] ?? 0 ),
            ),
            array( 'consignment_id' => $consignment_id ),
            array( '%s', '%f', '%d' ),
            array( '%s' )
        );

        return $data;
    }
}
