<?php
/**
 * Carrybee WooCommerce Shipping Method
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function rcb_init_shipping_method() {
    if ( ! class_exists( 'RCB_Shipping_Method' ) ) {

        class RCB_Shipping_Method extends WC_Shipping_Method {

            public function __construct( $instance_id = 0 ) {
                $this->id                 = 'carrybee';
                $this->instance_id        = absint( $instance_id );
                $this->method_title       = __( 'Carrybee', 'royal-carrybee' );
                $this->method_description = __( 'Carrybee courier delivery for Bangladesh', 'royal-carrybee' );
                $this->supports           = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );

                $this->init();
            }

            public function init() {
                $this->init_form_fields();
                $this->init_settings();

                $this->title      = $this->get_option( 'title', __( 'Carrybee Delivery', 'royal-carrybee' ) );
                $this->enabled    = $this->get_option( 'enabled', 'yes' );
                $this->tax_status = $this->get_option( 'tax_status', 'none' );

                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
            }

            public function init_form_fields() {
                $this->instance_form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'royal-carrybee' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Enable Carrybee Shipping', 'royal-carrybee' ),
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title'       => __( 'Method Title', 'royal-carrybee' ),
                        'type'        => 'text',
                        'description' => __( 'Title shown to customers at checkout', 'royal-carrybee' ),
                        'default'     => __( 'Carrybee Delivery', 'royal-carrybee' ),
                        'desc_tip'    => true,
                    ),
                    'delivery_type' => array(
                        'title'   => __( 'Delivery Type', 'royal-carrybee' ),
                        'type'    => 'select',
                        'default' => '1',
                        'options' => array(
                            '1' => __( 'Normal Delivery', 'royal-carrybee' ),
                            '2' => __( 'Express Delivery', 'royal-carrybee' ),
                        ),
                    ),
                    'flat_rate' => array(
                        'title'       => __( 'Flat Rate (৳)', 'royal-carrybee' ),
                        'type'        => 'number',
                        'description' => __( 'Flat delivery charge. Set 0 for free shipping.', 'royal-carrybee' ),
                        'default'     => '60',
                        'desc_tip'    => true,
                    ),
                    'free_shipping_threshold' => array(
                        'title'       => __( 'Free Shipping Threshold (৳)', 'royal-carrybee' ),
                        'type'        => 'number',
                        'description' => __( 'Order amount above which shipping is free. Leave empty to disable.', 'royal-carrybee' ),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'tax_status' => array(
                        'title'   => __( 'Tax Status', 'royal-carrybee' ),
                        'type'    => 'select',
                        'default' => 'none',
                        'options' => array(
                            'taxable' => __( 'Taxable', 'royal-carrybee' ),
                            'none'    => __( 'None', 'royal-carrybee' ),
                        ),
                    ),
                );
            }

            public function calculate_shipping( $package = array() ) {
                if ( 'no' === $this->enabled ) {
                    return;
                }

                $cost = floatval( $this->get_option( 'flat_rate', 60 ) );
                $free_threshold = floatval( $this->get_option( 'free_shipping_threshold', 0 ) );

                // Check free shipping threshold
                if ( $free_threshold > 0 && WC()->cart->get_subtotal() >= $free_threshold ) {
                    $cost = 0;
                }

                $rate = array(
                    'id'        => $this->get_rate_id(),
                    'label'     => $this->title,
                    'cost'      => $cost,
                    'calc_tax'  => 'per_order',
                    'meta_data' => array(
                        'delivery_type' => $this->get_option( 'delivery_type', '1' ),
                    ),
                );

                $this->add_rate( $rate );
            }
        }
    }
}

add_action( 'woocommerce_shipping_init', 'rcb_init_shipping_method' );
