<?php
/**
 * Carrybee Settings Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCB_Settings {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_rcb_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_rcb_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_rcb_get_stores', array( $this, 'ajax_get_stores' ) );
        add_action( 'wp_ajax_rcb_create_store', array( $this, 'ajax_create_store' ) );
    }

    /**
     * Add menu
     */
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Carrybee Shipping', 'royal-carrybee' ),
            __( 'Carrybee', 'royal-carrybee' ),
            'manage_woocommerce',
            'royal-carrybee',
            array( $this, 'render_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'rcb_settings_group', 'rcb_settings' );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts( $hook ) {
        global $post_type, $pagenow;
        
        $order_screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) 
            ? wc_get_page_screen_id( 'shop-order' ) 
            : 'shop_order';
        
        // Check if we're on the settings page
        $is_settings_page = ( $hook === 'woocommerce_page_royal-carrybee' );
        
        // Check if we're on an order edit page (supports both legacy and HPOS)
        $is_order_page = false;
        if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post_type === 'shop_order' ) {
            $is_order_page = true;
        }
        if ( strpos( $hook, 'woocommerce_page_wc-orders' ) !== false || $hook === $order_screen ) {
            $is_order_page = true;
        }
        
        if ( ! $is_settings_page && ! $is_order_page ) {
            return;
        }

        // SweetAlert2 (always)
        wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true );
        
        // DataTables (only on settings page)
        $js_deps = array( 'jquery', 'sweetalert2' );
        if ( $hook === 'woocommerce_page_royal-carrybee' ) {
            wp_enqueue_style( 'datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', array(), '1.13.6' );
            wp_enqueue_script( 'datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.6', true );
            $js_deps[] = 'datatables';
        }

        // Plugin assets
        wp_enqueue_style( 'rcb-admin', RCB_PLUGIN_URL . 'assets/css/admin.css', array(), RCB_VERSION );
        wp_enqueue_script( 'rcb-admin', RCB_PLUGIN_URL . 'assets/js/admin.js', $js_deps, RCB_VERSION, true );

        wp_localize_script( 'rcb-admin', 'rcb_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rcb_admin_nonce' ),
            'i18n'     => array(
                'saving'           => __( 'Saving...', 'royal-carrybee' ),
                'saved'            => __( 'Settings Saved!', 'royal-carrybee' ),
                'error'            => __( 'Error', 'royal-carrybee' ),
                'success'          => __( 'Success', 'royal-carrybee' ),
                'testing'          => __( 'Testing Connection...', 'royal-carrybee' ),
                'connection_ok'    => __( 'Connection Successful!', 'royal-carrybee' ),
                'connection_fail'  => __( 'Connection Failed', 'royal-carrybee' ),
                'confirm_cancel'   => __( 'Are you sure you want to cancel this order?', 'royal-carrybee' ),
            ),
        ) );
    }

    /**
     * Render settings page
     */
    public function render_page() {
        $settings = get_option( 'rcb_settings', array() );
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap rcb-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=royal-carrybee&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'General Settings', 'royal-carrybee' ); ?>
                </a>
                <a href="?page=royal-carrybee&tab=stores" class="nav-tab <?php echo $active_tab === 'stores' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-store"></span> <?php esc_html_e( 'Stores', 'royal-carrybee' ); ?>
                </a>
                <a href="?page=royal-carrybee&tab=orders" class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Orders', 'royal-carrybee' ); ?>
                </a>
                <a href="?page=royal-carrybee&tab=about" class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-info"></span> <?php esc_html_e( 'About', 'royal-carrybee' ); ?>
                </a>
            </h2>

            <div class="rcb-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'stores':
                        $this->render_stores_tab();
                        break;
                    case 'orders':
                        $this->render_orders_tab();
                        break;
                    case 'about':
                        $this->render_about_tab();
                        break;
                    default:
                        $this->render_general_tab( $settings );
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings tab
     */
    private function render_general_tab( $settings ) {
        $webhook_url = rest_url( 'royal-carrybee/v1/webhook' );
        ?>
        <form id="rcb-settings-form" method="post">
            <?php wp_nonce_field( 'rcb_settings_nonce', 'rcb_nonce' ); ?>

            <div class="rcb-settings-grid">
                
                <!-- API Credentials Card -->
                <div class="rcb-settings-card">
                    <div class="rcb-card-header">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php esc_html_e( 'API Credentials', 'royal-carrybee' ); ?>
                    </div>
                    <div class="rcb-card-body">
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Environment', 'royal-carrybee' ); ?></label>
                            <select name="rcb_settings[environment]" id="rcb_environment" class="rcb-select">
                                <option value="sandbox" <?php selected( $settings['environment'] ?? 'sandbox', 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (Testing)', 'royal-carrybee' ); ?></option>
                                <option value="production" <?php selected( $settings['environment'] ?? 'sandbox', 'production' ); ?>><?php esc_html_e( 'Production (Live)', 'royal-carrybee' ); ?></option>
                            </select>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Client ID', 'royal-carrybee' ); ?></label>
                            <input type="text" name="rcb_settings[client_id]" value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>" class="regular-text" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Client Secret', 'royal-carrybee' ); ?></label>
                            <input type="password" name="rcb_settings[client_secret]" value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>" class="regular-text" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Client Context', 'royal-carrybee' ); ?></label>
                            <input type="text" name="rcb_settings[client_context]" value="<?php echo esc_attr( $settings['client_context'] ?? '' ); ?>" class="regular-text" />
                        </div>
                        <button type="button" id="rcb-test-connection" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Test Connection', 'royal-carrybee' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Default Store Card -->
                <div class="rcb-settings-card">
                    <div class="rcb-card-header">
                        <span class="dashicons dashicons-store"></span>
                        <?php esc_html_e( 'Default Store', 'royal-carrybee' ); ?>
                    </div>
                    <div class="rcb-card-body">
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Select Pickup Store', 'royal-carrybee' ); ?></label>
                            <select name="rcb_settings[default_store]" id="rcb_default_store" class="rcb-select" data-saved="<?php echo esc_attr( $settings['default_store'] ?? '' ); ?>">
                                <option value=""><?php esc_html_e( '-- Select Store --', 'royal-carrybee' ); ?></option>
                            </select>
                            <p class="rcb-field-desc"><?php esc_html_e( 'Select the default pickup store for orders.', 'royal-carrybee' ); ?></p>
                        </div>
                        <button type="button" id="rcb-refresh-stores" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh Stores', 'royal-carrybee' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Order Settings Card -->
                <div class="rcb-settings-card">
                    <div class="rcb-card-header">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e( 'Order Settings', 'royal-carrybee' ); ?>
                    </div>
                    <div class="rcb-card-body">
                        <div class="rcb-field-group">
                            <label class="rcb-checkbox-label">
                                <input type="checkbox" name="rcb_settings[auto_create_order]" value="yes" <?php checked( $settings['auto_create_order'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'Auto-create Carrybee order when WooCommerce order is placed', 'royal-carrybee' ); ?>
                            </label>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Default Weight (grams)', 'royal-carrybee' ); ?></label>
                            <input type="number" name="rcb_settings[default_weight]" value="<?php echo esc_attr( $settings['default_weight'] ?? 500 ); ?>" min="1" max="25000" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Default Product Type', 'royal-carrybee' ); ?></label>
                            <select name="rcb_settings[default_product_type]" class="rcb-select">
                                <option value="1" <?php selected( $settings['default_product_type'] ?? 1, 1 ); ?>><?php esc_html_e( 'Parcel', 'royal-carrybee' ); ?></option>
                                <option value="2" <?php selected( $settings['default_product_type'] ?? 1, 2 ); ?>><?php esc_html_e( 'Book', 'royal-carrybee' ); ?></option>
                                <option value="3" <?php selected( $settings['default_product_type'] ?? 1, 3 ); ?>><?php esc_html_e( 'Document', 'royal-carrybee' ); ?></option>
                            </select>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Default Delivery Type', 'royal-carrybee' ); ?></label>
                            <select name="rcb_settings[default_delivery_type]" class="rcb-select">
                                <option value="1" <?php selected( $settings['default_delivery_type'] ?? 1, 1 ); ?>><?php esc_html_e( 'Normal Delivery', 'royal-carrybee' ); ?></option>
                                <option value="2" <?php selected( $settings['default_delivery_type'] ?? 1, 2 ); ?>><?php esc_html_e( 'Express Delivery', 'royal-carrybee' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Webhook Settings Card -->
                <div class="rcb-settings-card">
                    <div class="rcb-card-header">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e( 'Webhook Settings', 'royal-carrybee' ); ?>
                    </div>
                    <div class="rcb-card-body">
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Webhook URL', 'royal-carrybee' ); ?></label>
                            <div class="rcb-input-row">
                                <input type="text" value="<?php echo esc_url( $webhook_url ); ?>" class="regular-text" readonly />
                                <button type="button" class="button rcb-copy-btn" data-copy="<?php echo esc_url( $webhook_url ); ?>">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                            </div>
                            <p class="rcb-field-desc"><?php esc_html_e( 'Provide this URL to Carrybee for webhook notifications.', 'royal-carrybee' ); ?></p>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Webhook Secret', 'royal-carrybee' ); ?></label>
                            <div class="rcb-input-row">
                                <input type="text" name="rcb_settings[webhook_secret]" value="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>" class="regular-text" />
                                <button type="button" class="button rcb-copy-btn" data-copy="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                            </div>
                            <p class="rcb-field-desc"><?php esc_html_e( 'This secret will be sent in X-Carrybee-Webhook-Signature header.', 'royal-carrybee' ); ?></p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Save Button Area -->
            <div class="rcb-save-area">
                <div class="rcb-save-info">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e( 'Changes will apply immediately after saving.', 'royal-carrybee' ); ?>
                </div>
                <button type="submit" class="button button-primary button-hero">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'royal-carrybee' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Render stores tab
     */
    private function render_stores_tab() {
        ?>
        <div class="rcb-stores-section">
            <div class="rcb-section-header">
                <h3><span class="dashicons dashicons-store"></span> <?php esc_html_e( 'Your Stores', 'royal-carrybee' ); ?></h3>
                <button type="button" id="rcb-add-store" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add New Store', 'royal-carrybee' ); ?>
                </button>
            </div>
            
            <table id="rcb-stores-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Store Name', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Contact Person', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Address', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Default', 'royal-carrybee' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Add Store Modal -->
        <div id="rcb-store-modal" class="rcb-modal" style="display:none;">
            <div class="rcb-modal-content">
                <div class="rcb-modal-header">
                    <h3><?php esc_html_e( 'Add New Store', 'royal-carrybee' ); ?></h3>
                    <button type="button" class="rcb-modal-close">&times;</button>
                </div>
                <form id="rcb-store-form">
                    <div class="rcb-modal-body">
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Store Name', 'royal-carrybee' ); ?> *</label>
                            <input type="text" name="name" required maxlength="30" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Contact Person Name', 'royal-carrybee' ); ?> *</label>
                            <input type="text" name="contact_person_name" required maxlength="30" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Contact Number', 'royal-carrybee' ); ?> *</label>
                            <input type="text" name="contact_person_number" required placeholder="01XXXXXXXXX" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Secondary Number', 'royal-carrybee' ); ?></label>
                            <input type="text" name="contact_person_secondary_number" placeholder="01XXXXXXXXX" />
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'City', 'royal-carrybee' ); ?> *</label>
                            <select name="city_id" id="store_city_id" required class="rcb-select"></select>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Zone', 'royal-carrybee' ); ?> *</label>
                            <select name="zone_id" id="store_zone_id" required class="rcb-select" disabled></select>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Area', 'royal-carrybee' ); ?> *</label>
                            <select name="area_id" id="store_area_id" required class="rcb-select" disabled></select>
                        </div>
                        <div class="rcb-field-group">
                            <label class="rcb-field-label"><?php esc_html_e( 'Address', 'royal-carrybee' ); ?> *</label>
                            <textarea name="address" required maxlength="100" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="rcb-modal-footer">
                        <button type="button" class="button rcb-modal-close"><?php esc_html_e( 'Cancel', 'royal-carrybee' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Create Store', 'royal-carrybee' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render orders tab
     */
    private function render_orders_tab() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rcb_orders';
        ?>
        <div class="rcb-orders-section">
            <div class="rcb-section-header">
                <h3><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Carrybee Orders', 'royal-carrybee' ); ?></h3>
            </div>
            
            <table id="rcb-orders-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order ID', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Consignment ID', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Delivery Fee', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'COD Fee', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Collected', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'royal-carrybee' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'royal-carrybee' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $orders = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100" );
                    foreach ( $orders as $order ) :
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $order->order_id ); ?></a></td>
                        <td><code><?php echo esc_html( $order->consignment_id ); ?></code></td>
                        <td><span class="rcb-status rcb-status-<?php echo esc_attr( sanitize_title( $order->status ) ); ?>"><?php echo esc_html( $order->status ); ?></span></td>
                        <td><?php echo wc_price( $order->delivery_fee ); ?></td>
                        <td><?php echo wc_price( $order->cod_fee ); ?></td>
                        <td><?php echo wc_price( $order->collected_amount ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $order->created_at ) ) ); ?></td>
                        <td>
                            <button type="button" class="button button-small rcb-sync-order" data-consignment="<?php echo esc_attr( $order->consignment_id ); ?>">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render about tab
     */
    private function render_about_tab() {
        ?>
        <div class="rcb-about-container">
            
            <!-- Plugin Header -->
            <div class="rcb-about-header">
                <span class="dashicons dashicons-car"></span>
                <div>
                    <h2 class="rcb-about-title"><?php esc_html_e( 'Royal Carrybee', 'royal-carrybee' ); ?></h2>
                    <span class="rcb-about-version">v<?php echo esc_html( RCB_VERSION ); ?></span>
                </div>
            </div>

            <!-- Description -->
            <div class="rcb-about-section">
                <h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'About This Plugin', 'royal-carrybee' ); ?></h3>
                <p><?php esc_html_e( 'Royal Carrybee is a professional WooCommerce shipping integration plugin for Carrybee courier service in Bangladesh. It seamlessly connects your online store with Carrybee\'s delivery network, enabling automated order creation, real-time tracking, and webhook-based status updates.', 'royal-carrybee' ); ?></p>
            </div>

            <!-- Features Grid -->
            <div class="rcb-about-section">
                <h3><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Key Features', 'royal-carrybee' ); ?></h3>
                <div class="rcb-feature-grid">
                    <div class="rcb-feature-item"><span class="dashicons dashicons-car"></span> <?php esc_html_e( 'WooCommerce Shipping Method', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-location"></span> <?php esc_html_e( 'City/Zone/Area Selection', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Auto Order Creation', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Real-time Tracking', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Webhook Integration', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-store"></span> <?php esc_html_e( 'Multiple Store Support', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'COD Support', 'royal-carrybee' ); ?></div>
                    <div class="rcb-feature-item"><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Sandbox Testing', 'royal-carrybee' ); ?></div>
                </div>
            </div>

            <!-- Developer Card -->
            <div class="rcb-developer-card">
                <h3>👨‍💻 <?php esc_html_e( 'Developer', 'royal-carrybee' ); ?></h3>
                <p class="company-name">Royal Technologies</p>
                <p class="company-desc"><?php esc_html_e( 'Professional Web Development & Design Solutions', 'royal-carrybee' ); ?></p>
            </div>

            <!-- Contact Grid -->
            <div class="rcb-contact-grid">
                <div class="rcb-contact-card website">
                    <h4>🌐 <?php esc_html_e( 'Website', 'royal-carrybee' ); ?></h4>
                    <a href="https://www.royaltechbd.com/" target="_blank">www.royaltechbd.com <span class="dashicons dashicons-external" style="font-size: 14px;"></span></a>
                </div>
                <div class="rcb-contact-card email">
                    <h4>📧 <?php esc_html_e( 'Email', 'royal-carrybee' ); ?></h4>
                    <a href="mailto:info@royaltechbd.com">info@royaltechbd.com</a>
                </div>
                <div class="rcb-contact-card phone">
                    <h4>📱 <?php esc_html_e( 'Mobile', 'royal-carrybee' ); ?></h4>
                    <a href="tel:+8801552333272">+880 1552-333272</a>
                </div>
                <div class="rcb-contact-card address">
                    <h4>📍 <?php esc_html_e( 'Address', 'royal-carrybee' ); ?></h4>
                    <p>D - 408, Housing Estate<br>Kushtia - 7000, Bangladesh</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="rcb-footer-text">
                <?php printf( __( 'Royal Carrybee v%s | © %s Royal Technologies', 'royal-carrybee' ), RCB_VERSION, date( 'Y' ) ); ?>
            </div>

        </div>
        <?php
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied', 'royal-carrybee' ) );
        }

        $result = RCB_API::test_connection();

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'rcb_settings_nonce', 'rcb_nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied', 'royal-carrybee' ) );
        }

        $settings = isset( $_POST['rcb_settings'] ) ? $_POST['rcb_settings'] : array();
        
        // Sanitize
        $sanitized = array(
            'environment'          => sanitize_text_field( $settings['environment'] ?? 'sandbox' ),
            'client_id'            => sanitize_text_field( $settings['client_id'] ?? '' ),
            'client_secret'        => sanitize_text_field( $settings['client_secret'] ?? '' ),
            'client_context'       => sanitize_text_field( $settings['client_context'] ?? '' ),
            'default_store'        => sanitize_text_field( $settings['default_store'] ?? '' ),
            'webhook_secret'       => sanitize_text_field( $settings['webhook_secret'] ?? '' ),
            'auto_create_order'    => isset( $settings['auto_create_order'] ) ? 'yes' : 'no',
            'default_weight'       => absint( $settings['default_weight'] ?? 500 ),
            'default_product_type' => absint( $settings['default_product_type'] ?? 1 ),
            'default_delivery_type'=> absint( $settings['default_delivery_type'] ?? 1 ),
        );

        update_option( 'rcb_settings', $sanitized );

        wp_send_json_success( __( 'Settings saved successfully', 'royal-carrybee' ) );
    }

    /**
     * AJAX: Get stores
     */
    public function ajax_get_stores() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        
        $result = RCB_API::get_stores();

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        wp_send_json_success( $result['data']['stores'] ?? array() );
    }

    /**
     * AJAX: Create store
     */
    public function ajax_create_store() {
        check_ajax_referer( 'rcb_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied', 'royal-carrybee' ) );
        }

        $data = array(
            'name'                           => sanitize_text_field( $_POST['name'] ?? '' ),
            'contact_person_name'            => sanitize_text_field( $_POST['contact_person_name'] ?? '' ),
            'contact_person_number'          => sanitize_text_field( $_POST['contact_person_number'] ?? '' ),
            'address'                        => sanitize_textarea_field( $_POST['address'] ?? '' ),
            'city_id'                        => absint( $_POST['city_id'] ?? 0 ),
            'zone_id'                        => absint( $_POST['zone_id'] ?? 0 ),
            'area_id'                        => absint( $_POST['area_id'] ?? 0 ),
        );

        if ( ! empty( $_POST['contact_person_secondary_number'] ) ) {
            $data['contact_person_secondary_number'] = sanitize_text_field( $_POST['contact_person_secondary_number'] );
        }

        $result = RCB_API::create_store( $data );

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( $result['message'] );
        }

        wp_send_json_success( $result['message'] ?? __( 'Store created successfully', 'royal-carrybee' ) );
    }
}
