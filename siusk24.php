<?php
/**
 * Plugin Name: Siusk24
 * Version: 1.0.0
 * Plugin URI: https://github.com/mijora
 * Description: Official Siusk24 plugin that combine shipping between different countries
 * Author: Mijora
 * Author URI: https://mijora.lt/
 * Text Domain: siusk24
 * Domain Path: /languages
 *
 * Requires at least: 5.1
 * Tested up to: 6.0.2
 * WC requires at least: 4.0
 * WC tested up to: 7.3.0
 * Requires PHP: 7.2
 *
 */

require 'vendor/autoload.php';

use Siusk24Woo\Main;

if (!defined('ABSPATH')) {
  exit;
}

define('SIUSK24_VERSION', '1.0.0');
define('SIUSK24_BASENAME', plugin_basename(__FILE__));
define('SIUSK24_PLUGIN_DIR', plugin_dir_path( __FILE__ ));

register_activation_hook(__FILE__, array( 'Siusk24Woo\Main', 'activated' ) );
register_deactivation_hook( __FILE__, array( 'Siusk24Woo\Main', 'deactivated' ) );

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    new Main();
}
