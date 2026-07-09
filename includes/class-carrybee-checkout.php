<?php
/**
 * Carrybee Checkout Customizations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCB_Checkout {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add custom checkout fields
        add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ) );
        
        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_rcb_get_cities', array( $this, 'ajax_get_cities' ) );
        add_action( 'wp_ajax_nopriv_rcb_get_cities', array( $this, 'ajax_get_cities' ) );
        add_action( 'wp_ajax_rcb_get_zones', array( $this, 'ajax_get_zones' ) );
        add_action( 'wp_ajax_nopriv_rcb_get_zones', array( $this, 'ajax_get_zones' ) );
        add_action( 'wp_ajax_rcb_get_areas', array( $this, 'ajax_get_areas' ) );
        add_action( 'wp_ajax_nopriv_rcb_get_areas', array( $this, 'ajax_get_areas' ) );
        
        // Save custom fields to order
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
        
        // Display in admin
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_admin_order_meta' ) );
    }

    /**
     * Enqueue checkout scripts
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_style( 'rcb-checkout', RCB_PLUGIN_URL . 'assets/css/frontend.css', array(), RCB_VERSION );
        wp_enqueue_script( 'rcb-checkout', RCB_PLUGIN_URL . 'assets/js/checkout.js', array( 'jquery', 'selectWoo' ), RCB_VERSION, true );

        wp_localize_script( 'rcb-checkout', 'rcb_checkout', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rcb_checkout_nonce' ),
            'i18n'     => array(
                'select_city' => __( 'Select City', 'royal-carrybee' ),
                'select_zone' => __( 'Select Zone', 'royal-carrybee' ),
                'select_area' => __( 'Select Area', 'royal-carrybee' ),
                'loading'     => __( 'Loading...', 'royal-carrybee' ),
            ),
        ) );
    }

    /**
     * Customize checkout fields
     */
    public function customize_checkout_fields( $fields ) {
        // Add Carrybee location fields
        $fields['shipping']['shipping_rcb_city'] = array(
            'type'        => 'select',
            'label'       => __( 'City', 'royal-carrybee' ),
            'required'    => true,
            'class'       => array( 'form-row-wide', 'rcb-city-field' ),
            'priority'    => 45,
            'options'     => array( '' => __( 'Select City', 'royal-carrybee' ) ),
        );

        $fields['shipping']['shipping_rcb_zone'] = array(
            'type'        => 'select',
            'label'       => __( 'Zone', 'royal-carrybee' ),
            'required'    => true,
            'class'       => array( 'form-row-first', 'rcb-zone-field' ),
            'priority'    => 46,
            'options'     => array( '' => __( 'Select Zone', 'royal-carrybee' ) ),
        );

        $fields['shipping']['shipping_rcb_area'] = array(
            'type'        => 'select',
            'label'       => __( 'Area', 'royal-carrybee' ),
            'required'    => false,
            'class'       => array( 'form-row-last', 'rcb-area-field' ),
            'priority'    => 47,
            'options'     => array( '' => __( 'Select Area', 'royal-carrybee' ) ),
        );

        // Also add to billing if shipping to different address
        $fields['billing']['billing_rcb_city'] = array(
            'type'        => 'select',
            'label'       => __( 'City', 'royal-carrybee' ),
            'required'    => true,
            'class'       => array( 'form-row-wide', 'rcb-city-field' ),
            'priority'    => 45,
            'options'     => array( '' => __( 'Select City', 'royal-carrybee' ) ),
        );

        $fields['billing']['billing_rcb_zone'] = array(
            'type'        => 'select',
            'label'       => __( 'Zone', 'royal-carrybee' ),
            'required'    => true,
            'class'       => array( 'form-row-first', 'rcb-zone-field' ),
            'priority'    => 46,
            'options'     => array( '' => __( 'Select Zone', 'royal-carrybee' ) ),
        );

        $fields['billing']['billing_rcb_area'] = array(
            'type'        => 'select',
            'label'       => __( 'Area', 'royal-carrybee' ),
            'required'    => false,
            'class'       => array( 'form-row-last', 'rcb-area-field' ),
            'priority'    => 47,
            'options'     => array( '' => __( 'Select Area', 'royal-carrybee' ) ),
        );

        return $fields;
    }

    /**
     * AJAX: Get cities
     */
    public function ajax_get_cities() {
        if ( ! check_ajax_referer( 'rcb_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed' );
        }

        $result = RCB_API::get_cities();

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        $cities = $result['data']['cities'] ?? $result['cities'] ?? $result['data'] ?? array();
        wp_send_json_success( $cities );
    }

    /**
     * AJAX: Get zones
     */
    public function ajax_get_zones() {
        if ( ! check_ajax_referer( 'rcb_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed' );
        }

        $city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

        if ( ! $city_id ) {
            wp_send_json_error( __( 'City ID required', 'royal-carrybee' ) );
        }

        $result = RCB_API::get_zones( $city_id );

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        $zones = $result['data']['zones'] ?? $result['zones'] ?? $result['data'] ?? array();
        wp_send_json_success( $zones );
    }

    /**
     * AJAX: Get areas
     */
    public function ajax_get_areas() {
        if ( ! check_ajax_referer( 'rcb_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed' );
        }

        $city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
        $zone_id = isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : 0;

        if ( ! $city_id || ! $zone_id ) {
            wp_send_json_error( __( 'City and Zone ID required', 'royal-carrybee' ) );
        }

        $result = RCB_API::get_areas( $city_id, $zone_id );

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        $areas = $result['data']['areas'] ?? $result['areas'] ?? $result['data'] ?? array();
        wp_send_json_success( $areas );
    }

    /**
     * Save order meta
     */
    public function save_order_meta( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // Shipping fields
        if ( isset( $_POST['shipping_rcb_city'] ) ) {
            $order->update_meta_data( '_rcb_shipping_city_id', absint( $_POST['shipping_rcb_city'] ) );
        }
        if ( isset( $_POST['shipping_rcb_zone'] ) ) {
            $order->update_meta_data( '_rcb_shipping_zone_id', absint( $_POST['shipping_rcb_zone'] ) );
        }
        if ( isset( $_POST['shipping_rcb_area'] ) ) {
            $order->update_meta_data( '_rcb_shipping_area_id', absint( $_POST['shipping_rcb_area'] ) );
        }

        // Billing fields  
        if ( isset( $_POST['billing_rcb_city'] ) ) {
            $order->update_meta_data( '_rcb_billing_city_id', absint( $_POST['billing_rcb_city'] ) );
        }
        if ( isset( $_POST['billing_rcb_zone'] ) ) {
            $order->update_meta_data( '_rcb_billing_zone_id', absint( $_POST['billing_rcb_zone'] ) );
        }
        if ( isset( $_POST['billing_rcb_area'] ) ) {
            $order->update_meta_data( '_rcb_billing_area_id', absint( $_POST['billing_rcb_area'] ) );
        }

        $order->save();
    }

    /**
     * Display in admin order
     */
    public function display_admin_order_meta( $order ) {
        $city_id = $order->get_meta( '_rcb_shipping_city_id' );
        $zone_id = $order->get_meta( '_rcb_shipping_zone_id' );
        $area_id = $order->get_meta( '_rcb_shipping_area_id' );

        if ( $city_id || $zone_id || $area_id ) {
            echo '<div class="rcb-admin-location">';
            echo '<h4>' . esc_html__( 'Carrybee Location IDs', 'royal-carrybee' ) . '</h4>';
            echo '<p><strong>' . esc_html__( 'City ID:', 'royal-carrybee' ) . '</strong> ' . esc_html( $city_id ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Zone ID:', 'royal-carrybee' ) . '</strong> ' . esc_html( $zone_id ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Area ID:', 'royal-carrybee' ) . '</strong> ' . esc_html( $area_id ) . '</p>';
            echo '</div>';
        }
    }
}
