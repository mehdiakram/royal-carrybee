<?php
/**
 * Carrybee Webhook Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCB_Webhook {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
    }

    public function register_webhook_endpoint() {
        register_rest_route( 'royal-carrybee/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_webhook' ),
            'permission_callback' => array( $this, 'verify_webhook' ),
        ) );
    }

    public function verify_webhook( $request ) {
        $settings = get_option( 'rcb_settings', array() );
        $secret = $settings['webhook_secret'] ?? '';
        if ( empty( $secret ) ) return true;
        
        $signature = $request->get_header( 'X-Carrybee-Webhook-Signature' );
        if ( $signature !== $secret ) {
            return new WP_Error( 'invalid_signature', 'Invalid webhook signature', array( 'status' => 401 ) );
        }
        return true;
    }

    public function handle_webhook( $request ) {
        $payload = $request->get_json_params();
        if ( empty( $payload ) || ! isset( $payload['event'] ) ) {
            return new WP_Error( 'invalid_payload', 'Invalid payload', array( 'status' => 400 ) );
        }

        $event = sanitize_text_field( $payload['event'] );
        $consignment_id = sanitize_text_field( $payload['consignment_id'] ?? '' );
        $merchant_order_id = sanitize_text_field( $payload['merchant_order_id'] ?? '' );

        $order_id = $merchant_order_id ? absint( $merchant_order_id ) : $this->get_order_by_consignment( $consignment_id );
        if ( ! $order_id ) return new WP_REST_Response( array( 'status' => 'order_not_found' ), 200 );

        $order = wc_get_order( $order_id );
        if ( ! $order ) return new WP_REST_Response( array( 'status' => 'order_not_found' ), 200 );

        $this->process_event( $order, $event, $payload );
        return new WP_REST_Response( array( 'status' => 'processed' ), 200 );
    }

    private function get_order_by_consignment( $consignment_id ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}rcb_orders WHERE consignment_id = %s", $consignment_id
        ) );
    }

    private function process_event( $order, $event, $payload ) {
        $reason = sanitize_text_field( $payload['reason'] ?? '' );
        $attempt = absint( $payload['attempt'] ?? 0 );
        $collected = floatval( $payload['collected_amount'] ?? 0 );

        $this->update_order_table( $payload );

        $messages = array(
            'order.picked' => '✅ Parcel picked up',
            'order.in-transit' => '🚚 In transit',
            'order.assigned-for-delivery' => "🏍️ Assigned for delivery (Attempt: $attempt)",
            'order.at-the-sorting-hub' => '🏢 At sorting hub',
            'order.received-at-last-mile-hub' => '📍 At last mile hub',
        );

        if ( isset( $messages[$event] ) ) {
            $order->add_order_note( 'Carrybee: ' . $messages[$event] );
        } elseif ( $event === 'order.delivered' ) {
            $order->update_meta_data( '_rcb_collected_amount', $collected );
            $order->save();
            $order->add_order_note( sprintf( '🎉 Carrybee: DELIVERED! Collected: ৳%s', $collected ) );
            $order->update_status( 'completed', 'Delivered by Carrybee' );
        } elseif ( $event === 'order.delivery-failed' ) {
            $order->add_order_note( sprintf( '❌ Carrybee: Delivery failed. Reason: %s', $reason ?: 'N/A' ) );
        } elseif ( $event === 'order.returned-to-merchant' ) {
            $order->add_order_note( '📦 Carrybee: Returned to merchant' );
            $order->update_status( 'cancelled', 'Returned by Carrybee' );
        } elseif ( $event === 'order.paid' ) {
            $invoice = sanitize_text_field( $payload['invoice_id'] ?? '' );
            $order->update_meta_data( '_rcb_invoice_id', $invoice );
            $order->save();
            $order->add_order_note( sprintf( '💰 Carrybee: Paid. Invoice: %s', $invoice ) );
        }
    }

    private function update_order_table( $payload ) {
        global $wpdb;
        $consignment_id = $payload['consignment_id'] ?? '';
        if ( empty( $consignment_id ) ) return;

        $status = ucwords( str_replace( '-', ' ', str_replace( 'order.', '', $payload['event'] ?? '' ) ) );
        $data = array( 'status' => $status );
        if ( isset( $payload['collected_amount'] ) ) $data['collected_amount'] = floatval( $payload['collected_amount'] );
        if ( isset( $payload['attempt'] ) ) $data['attempts'] = absint( $payload['attempt'] );

        $wpdb->update( $wpdb->prefix . 'rcb_orders', $data, array( 'consignment_id' => $consignment_id ) );
    }
}
