<?php
/**
 * Plugin Name:       LE Additive Variation Pricing
 * Plugin URI:        https://leadingedge.com.bd
 * Description:       Price each attribute value independently. Final price = (optional Base Price) + sum of all selected attribute values. Activate per-product — existing WooCommerce pricing is untouched for other products.
 * Version:           1.0.0
 * Author:            Leading Edge
 * Author URI:        https://leadingedge.com.bd
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       le-additive-pricing
 * License:           GPL v2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'LE_AP_VERSION', '1.0.0' );
define( 'LE_AP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LE_AP_URL', plugin_dir_url( __FILE__ ) );
define( 'LE_AP_META_KEY', '_le_additive_pricing' );

add_action( 'plugins_loaded', 'le_ap_init' );

function le_ap_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>LE Additive Variation Pricing</strong> requires WooCommerce to be installed and active.</p></div>';
        } );
        return;
    }

    require_once LE_AP_DIR . 'includes/class-admin.php';
    require_once LE_AP_DIR . 'includes/class-frontend.php';
    require_once LE_AP_DIR . 'includes/class-cart.php';

    new LE_AP_Admin();
    new LE_AP_Frontend();
    new LE_AP_Cart();
}
