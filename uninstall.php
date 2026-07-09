<?php
/**
 * Uninstall Royal Carrybee
 *
 * Removes all plugin data when uninstalled
 *
 * @package Royal_Carrybee
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete options
delete_option( 'rcb_settings' );

// Delete custom table
global $wpdb;
$table_name = $wpdb->prefix . 'rcb_orders';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Delete order meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_rcb_%'" );

// For HPOS compatibility
if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
    $wpdb->query( "DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE '_rcb_%'" );
}
