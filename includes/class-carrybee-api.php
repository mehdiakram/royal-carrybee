<?php
/**
 * Carrybee API Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCB_API {

    /**
     * API Base URLs
     */
    private static $base_urls = array(
        'production' => 'https://developers.carrybee.com',
        'sandbox'    => 'https://stage-sandbox.carrybee.com',
    );

    /**
     * Get settings
     */
    private static function get_settings() {
        return get_option( 'rcb_settings', array() );
    }

    /**
     * Get base URL
     */
    private static function get_base_url() {
        $settings = self::get_settings();
        $env = isset( $settings['environment'] ) ? $settings['environment'] : 'sandbox';
        return self::$base_urls[ $env ];
    }

    /**
     * Get headers
     */
    private static function get_headers() {
        $settings = self::get_settings();
        return array(
            'Content-Type'   => 'application/json',
            'Client-ID'      => isset( $settings['client_id'] ) ? $settings['client_id'] : '',
            'Client-Secret'  => isset( $settings['client_secret'] ) ? $settings['client_secret'] : '',
            'Client-Context' => isset( $settings['client_context'] ) ? $settings['client_context'] : '',
        );
    }

    /**
     * Make API request
     */
    private static function request( $endpoint, $method = 'GET', $body = null ) {
        $url = self::get_base_url() . $endpoint;
        
        $args = array(
            'method'  => $method,
            'headers' => self::get_headers(),
            'timeout' => 30,
        );

        if ( $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return array(
                'error'   => true,
                'message' => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'error'   => true,
                'message' => __( 'Invalid JSON response from API', 'royal-carrybee' ),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( is_array( $data ) && ( $response_code >= 400 || ! empty( $data['error'] ) || ( isset( $data['status'] ) && $data['status'] === false ) || ! empty( $data['errors'] ) ) ) {
            $msg = ! empty( $data['message'] ) ? $data['message'] : ( ! empty( $data['error_description'] ) ? $data['error_description'] : __( 'Carrybee API Error', 'royal-carrybee' ) );
            
            $err_details = array();
            if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
                foreach ( $data['errors'] as $field => $errs ) {
                    $field_name = ucfirst( str_replace( '_', ' ', $field ) );
                    if ( is_array( $errs ) ) {
                        $err_details[] = $field_name . ': ' . implode( ', ', $errs );
                    } else {
                        $err_details[] = $field_name . ': ' . $errs;
                    }
                }
            } elseif ( ! empty( $data['error'] ) && is_string( $data['error'] ) && $data['error'] !== $msg && $data['error'] !== '1' ) {
                $err_details[] = $data['error'];
            }
            
            if ( ! empty( $err_details ) ) {
                if ( $msg === 'Validation error' || $msg === 'Carrybee API Error' ) {
                    $msg = implode( ' | ', $err_details );
                } else {
                    $msg .= ' (' . implode( ' | ', $err_details ) . ')';
                }
            }
            
            return array(
                'error'   => true,
                'message' => $msg,
                'data'    => $data,
                'code'    => $response_code,
            );
        }

        return $data;
    }

    /**
     * Test connection
     */
    public static function test_connection() {
        return self::get_cities();
    }

    /**
     * Get cities
     */
    public static function get_cities() {
        return self::request( '/api/v2/cities' );
    }

    /**
     * Get zones for a city
     */
    public static function get_zones( $city_id ) {
        return self::request( '/api/v2/cities/' . intval( $city_id ) . '/zones' );
    }

    /**
     * Get areas for a zone
     */
    public static function get_areas( $city_id, $zone_id ) {
        return self::request( '/api/v2/cities/' . intval( $city_id ) . '/zones/' . intval( $zone_id ) . '/areas' );
    }

    /**
     * Area suggestion search
     */
    public static function area_suggestion( $search ) {
        return self::request( '/api/v2/area-suggestion?search=' . urlencode( $search ) );
    }

    /**
     * Get city and zone from address
     */
    public static function get_address_details( $query ) {
        return self::request( '/api/v2/address-details', 'POST', array( 'query' => $query ) );
    }

    /**
     * Create store
     */
    public static function create_store( $data ) {
        return self::request( '/api/v2/stores', 'POST', $data );
    }

    /**
     * Get stores
     */
    public static function get_stores() {
        return self::request( '/api/v2/stores' );
    }

    /**
     * Create order
     */
    public static function create_order( $data ) {
        return self::request( '/api/v2/orders', 'POST', $data );
    }

    /**
     * Create bulk orders
     */
    public static function create_bulk_orders( $orders ) {
        return self::request( '/api/v2/orders-bulk', 'POST', array( 'orders' => $orders ) );
    }

    /**
     * Cancel order
     */
    public static function cancel_order( $consignment_id, $reason = '' ) {
        return self::request( 
            '/api/v2/orders/' . sanitize_text_field( $consignment_id ) . '/cancel', 
            'POST', 
            array( 'cancellation_reason' => $reason ) 
        );
    }

    /**
     * Get order details
     */
    public static function get_order_details( $consignment_id ) {
        return self::request( '/api/v2/orders/' . sanitize_text_field( $consignment_id ) . '/details' );
    }
}
