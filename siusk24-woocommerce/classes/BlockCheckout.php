<?php

namespace Siusk24Woo;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;

class BlockCheckout implements IntegrationInterface {


	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'siusk_24_block';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {

		$script_url = plugin_dir_url( __DIR__ ) . 'assets/build/siusk24-block-frontend.js';

		$dep = array(
			'dependencies' => array( 'wc-settings', 'wp-data', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-primitives' ),
			'version'      => SIUSK24_VERSION,
		);

		$script_asset = $dep;

		wp_register_script(
			'siusk-24-wc-block-checkout',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'siusk-24-wc-block-checkout' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'siusk-24-wc-block-checkout' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {

		$customer = WC()->session->get( 'customer' );
		$country  = 'ALL';

		if ( isset( $customer['shipping_country'] ) ) {
			$country = $customer['shipping_country'];
		} elseif ( isset( $customer['country'] ) ) {
			$country = $customer['country'];
		}

		$termnal_id = WC()->session->get( 'siusk24_terminal_id' );

		$core       = new Core();
		$api        = $core->get_api();
		$identifier = null;
		$terminals  = $api->get_terminals( $country, $identifier );

		return array(
			'terminals'      => $terminals,
			'map_btn_text'   => esc_html__( 'Select Parcel Locker', 'woocommerce-inpost' ),
			'script_country' => $country,
			'count'          => count( $terminals ),
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}

		return SIUSK24_VERSION;
	}
}
