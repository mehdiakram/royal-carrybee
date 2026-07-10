<?php
/**
 * Plugin Name: Royal Carrybee
 * Plugin URI: https://royaltechbd.com/royal-carrybee
 * Description: WooCommerce shipping integration with Carrybee courier service for Bangladesh.
 * Version: 26.07.10
 * Author: Royal Technologies
 * Author URI: https://royaltechbd.com
 * Text Domain: royal-carrybee
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'RCB_VERSION', '26.07.10' );
define( 'RCB_PLUGIN_FILE', __FILE__ );
define( 'RCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RCB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Royal_Carrybee {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        
        // Register AJAX handlers early (before WooCommerce check) for AJAX requests
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $this->register_ajax_handlers();
        }
        
        // Activation/Deactivation
        register_activation_hook( RCB_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( RCB_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . RCB_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

        // Declare WooCommerce HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    /**
     * Register AJAX handlers early for AJAX requests
     */
    private function register_ajax_handlers() {
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-api.php';
        
        add_action( 'wp_ajax_rcb_get_cities', array( $this, 'ajax_get_cities' ) );
        add_action( 'wp_ajax_rcb_get_zones', array( $this, 'ajax_get_zones' ) );
        add_action( 'wp_ajax_rcb_get_areas', array( $this, 'ajax_get_areas' ) );
        add_action( 'wp_ajax_rcb_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_rcb_get_stores', array( $this, 'ajax_get_stores' ) );
        add_action( 'wp_ajax_rcb_create_store', array( $this, 'ajax_create_store' ) );
        add_action( 'wp_ajax_rcb_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_rcb_sync_order', array( $this, 'ajax_sync_order' ) );
        add_action( 'wp_ajax_rcb_cancel_order', array( $this, 'ajax_cancel_order' ) );
        add_action( 'wp_ajax_rcb_create_order', array( $this, 'ajax_create_order' ) );
    }

    /**
     * AJAX: Get cities
     */
    public function ajax_get_cities() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed. Please refresh the page.' );
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
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        $city_id = absint( $_POST['city_id'] ?? 0 );
        $result = RCB_API::get_zones( $city_id );
        
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        
        // Try multiple possible response structures
        $zones = array();
        if ( isset( $result['data']['zones'] ) ) {
            $zones = $result['data']['zones'];
        } elseif ( isset( $result['zones'] ) ) {
            $zones = $result['zones'];
        } elseif ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
            $zones = $result['data'];
        } elseif ( is_array( $result ) && ! isset( $result['error'] ) ) {
            // Maybe the result itself is the zones array
            $zones = $result;
        }
        
        wp_send_json_success( $zones );
    }

    /**
     * AJAX: Get areas
     */
    public function ajax_get_areas() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
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
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        $result = RCB_API::get_cities();
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        wp_send_json_success( $result );
    }

    /**
     * AJAX: Get stores
     */
    public function ajax_get_stores() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        $result = RCB_API::get_stores();
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        $stores = $result['data']['stores'] ?? $result['stores'] ?? $result['data'] ?? array();
        wp_send_json_success( $stores );
    }

    /**
     * AJAX: Create store (proxy to settings class)
     */
    public function ajax_create_store() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        $data = array(
            'name'                  => sanitize_text_field( $_POST['name'] ?? '' ),
            'contact_person_name'   => sanitize_text_field( $_POST['contact_person_name'] ?? '' ),
            'contact_person_number' => sanitize_text_field( $_POST['contact_person_number'] ?? '' ),
            'address'               => sanitize_textarea_field( $_POST['address'] ?? '' ),
            'city_id'               => absint( $_POST['city_id'] ?? 0 ),
            'zone_id'               => absint( $_POST['zone_id'] ?? 0 ),
            'area_id'               => absint( $_POST['area_id'] ?? 0 ),
        );
        if ( ! empty( $_POST['contact_person_secondary_number'] ) ) {
            $data['contact_person_secondary_number'] = sanitize_text_field( $_POST['contact_person_secondary_number'] );
        }
        $result = RCB_API::create_store( $data );
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        wp_send_json_success( $result['message'] ?? 'Store created successfully' );
    }

    /**
     * AJAX: Save settings (proxy)
     */
    public function ajax_save_settings() {
        if ( ! wp_verify_nonce( $_POST['rcb_nonce'] ?? '', 'rcb_settings_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        $settings = isset( $_POST['rcb_settings'] ) ? $_POST['rcb_settings'] : array();
        $sanitized = array(
            'environment'           => sanitize_text_field( $settings['environment'] ?? 'sandbox' ),
            'client_id'             => sanitize_text_field( $settings['client_id'] ?? '' ),
            'client_secret'         => sanitize_text_field( $settings['client_secret'] ?? '' ),
            'client_context'        => sanitize_text_field( $settings['client_context'] ?? '' ),
            'default_store'         => sanitize_text_field( $settings['default_store'] ?? '' ),
            'webhook_secret'        => sanitize_text_field( $settings['webhook_secret'] ?? '' ),
            'auto_create_order'     => isset( $settings['auto_create_order'] ) ? 'yes' : 'no',
            'default_weight'        => absint( $settings['default_weight'] ?? 500 ),
            'default_product_type'  => absint( $settings['default_product_type'] ?? 1 ),
            'default_delivery_type' => absint( $settings['default_delivery_type'] ?? 1 ),
        );
        update_option( 'rcb_settings', $sanitized );
        wp_send_json_success( 'Settings saved successfully' );
    }

    /**
     * AJAX: Sync order
     */
    public function ajax_sync_order() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        $consignment_id = sanitize_text_field( $_POST['consignment_id'] ?? '' );
        $result = RCB_API::get_order_details( $consignment_id );
        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }
        wp_send_json_success( $result['data'] ?? $result );
    }

    /**
     * AJAX: Cancel order
     */
    public function ajax_cancel_order() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $reason = sanitize_text_field( $_POST['reason'] ?? 'Cancelled by admin' );
        
        if ( ! class_exists( 'RCB_Order' ) ) {
            require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-order.php';
        }
        
        $result = RCB_Order::cancel_order( $order_id, $reason );
        $result ? wp_send_json_success() : wp_send_json_error( 'Cancel failed' );
    }

    /**
     * AJAX: Create order
     */
    public function ajax_create_order() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rcb_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        
        // Load WooCommerce if needed
        if ( ! function_exists( 'wc_get_order' ) ) {
            wp_send_json_error( 'WooCommerce is required.' );
        }
        
        // Proxy to admin class if loaded
        if ( class_exists( 'RCB_Admin' ) ) {
            $admin = RCB_Admin::get_instance();
            if ( method_exists( $admin, 'ajax_create_order' ) ) {
                $admin->ajax_create_order();
                return;
            }
        }
        
        wp_send_json_error( 'Order creation handler not available.' );
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RCB_PLUGIN_FILE, true );
        }
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=royal-carrybee' ) . '">' . __( 'Settings', 'royal-carrybee' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Load classes
        $this->includes();
        $this->init_classes();
    }

    /**
     * Load textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'royal-carrybee', false, dirname( RCB_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-api.php';
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-settings.php';
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-shipping-method.php';
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-checkout.php';
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-order.php';
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-webhook.php';
        require_once RCB_PLUGIN_DIR . 'includes/class-carrybee-admin.php';
    }

    /**
     * Initialize classes
     */
    private function init_classes() {
        RCB_Settings::get_instance();
        RCB_Checkout::get_instance();
        RCB_Order::get_instance();
        RCB_Webhook::get_instance();
        RCB_Admin::get_instance();

        // Add shipping method
        add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
    }

    /**
     * Add shipping method
     */
    public function add_shipping_method( $methods ) {
        $methods['carrybee'] = 'RCB_Shipping_Method';
        return $methods;
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Royal Carrybee requires WooCommerce to be installed and active.', 'royal-carrybee' ); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $default_settings = array(
            'environment' => 'sandbox',
            'client_id' => '',
            'client_secret' => '',
            'client_context' => '',
            'default_store' => '',
            'webhook_secret' => wp_generate_password( 32, false ),
            'auto_create_order' => 'yes',
            'default_weight' => 500,
            'default_product_type' => 1,
            'default_delivery_type' => 1,
        );
        
        if ( ! get_option( 'rcb_settings' ) ) {
            update_option( 'rcb_settings', $default_settings );
        }

        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'rcb_orders';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            consignment_id varchar(50) NOT NULL,
            store_id varchar(50) NOT NULL,
            status varchar(50) DEFAULT 'pending',
            delivery_fee decimal(10,2) DEFAULT 0,
            cod_fee decimal(10,2) DEFAULT 0,
            collected_amount decimal(10,2) DEFAULT 0,
            attempts int(5) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY consignment_id (consignment_id)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize plugin
 */
function royal_carrybee() {
    return Royal_Carrybee::get_instance();
}

// Start the plugin
royal_carrybee();
