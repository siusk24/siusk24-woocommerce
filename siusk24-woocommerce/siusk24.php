<?php
/**
 * Plugin Name: Siusk24
 * Version: 1.0.2
 * Plugin URI: https://github.com/mijora
 * Description: Official Siusk24 plugin that combine shipping between different countries
 * Author: Mijora
 * Author URI: https://mijora.lt/
 * Text Domain: siusk24
 * Domain Path: /languages
 *
 * Requires at least: 5.1
 * Tested up to: 6.9
 * WC requires at least: 4.0
 * WC tested up to: 10.4.3
 * Requires PHP: 7.2
 *
 */

require 'vendor/autoload.php';

use Siusk24Woo\Main;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIUSK24_VERSION', '1.0.2' );
define( 'SIUSK24_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIUSK24_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'Siusk24Woo\Main', 'activated' ) );
register_deactivation_hook( __FILE__, array( 'Siusk24Woo\Main', 'deactivated' ) );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	add_action(
		'after_setup_theme',
		function () {
			new Main();
		}
	);

	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);
}
