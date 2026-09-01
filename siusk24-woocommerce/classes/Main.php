<?php

namespace Siusk24Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Siusk24Woo\Category;
use Siusk24Woo\Order;
use Siusk24Woo\Manifest;
use Siusk24Woo\Helper;
use Siusk24Woo\Core;
use Siusk24Woo\ShippingMethod;

class Main {

	private $core;
	private $category;
	private $order;
	private $manifest;
	private $api;
	private $config;

	public function __construct( $init = true ) {
		$this->init();
		$this->core     = new Core();
		$this->api      = $this->core->get_api();
		$this->category = new Category();
		$this->order    = new Order( $this->api, $this->core );
		$this->manifest = new Manifest( $this->api, $this->core );
	}

	private function init() {
		add_action( 'woocommerce_shipping_init', array( $this, 'shipping_method_init' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_scripts' ), 99 );
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'siusk24_show_terminals' ) );
		add_action( 'wp_footer', array( $this, 'block_checkout_css' ) );
		add_action( 'siusk24_event', array( $this, 'siusk24_event_callback_function' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_add_5min' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'siusk24_terminal_validate' ) );
		add_action( 'wp_ajax_siusk24_check_api', array( $this, 'siusk24_check_api' ) );
		add_action( 'wp_ajax_siusk24_terminals_sync', array( $this, 'siusk24_update_terminals' ) );
		add_action( 'wp_ajax_siusk24_services_sync', array( $this, 'siusk24_update_services' ) );
		add_filter( 'plugin_action_links_' . SIUSK24_BASENAME, array( $this, 'settings_link' ) );

		if ( get_option( Helper::get_prefix() . '_services_updated', 0 ) == 1 ) {
			add_action( 'admin_notices', array( $this, 'updated_services_notice' ) );
		}

		// integration with Woocommerce blocks start.
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				if ( ! $integration_registry->is_registered( 'siusk_24_block' ) ) {
					$integration_registry->register( new BlockCheckout() );
				}
			}
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_script' ), 100 );

		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'block_checkout_save_terminal_id' ), 10, 2 );
		// integration with Woocommerce blocks end.
	}


	public function enqueue_block_script() {

		if ( has_block( 'woocommerce/checkout' ) ) {

			$siusk24_settings = $this->core->get_config();
			$set_autoselect   = ( isset( $siusk24_settings['auto_select'] ) && $siusk24_settings['auto_select'] == 'yes' ) ? 'true' : 'false';
			$max_distance     = ( isset( $siusk24_settings['terminal_distance'] ) && $siusk24_settings['terminal_distance'] ) ? $siusk24_settings['terminal_distance'] : '50';

			wp_enqueue_script( 'siusk24-terminal-block', plugin_dir_url( __DIR__ ) . 'assets/js/block-checkout.js', array( 'jquery' ), SIUSK24_VERSION );

			wp_localize_script(
				'siusk24-terminal-block',
				'siusk24_block',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					'auto_select'  => $set_autoselect,
					'identifier'   => '',
					'max_distance' => $max_distance,
					'api_url'      => $siusk24_settings['api_url'],
				)
			);

		}
	}

	public function front_scripts() {
		if ( ( is_checkout() || has_block( 'woocommerce/checkout' ) ) && ! is_wc_endpoint_url() ) {

			wp_enqueue_script( 'siusk24-helper', plugin_dir_url( __DIR__ ) . 'assets/js/siusk24_helper.js', array( 'jquery' ), SIUSK24_VERSION, array( 'in_footer' => true ) );
			wp_enqueue_script( 'siusk24', plugin_dir_url( __DIR__ ) . 'assets/js/siusk24.js', array( 'jquery' ), SIUSK24_VERSION, array( 'in_footer' => true ) );
			wp_enqueue_script( 'siusk24-terminal', plugin_dir_url( __DIR__ ) . 'assets/js/terminal.js', array( 'jquery' ), SIUSK24_VERSION, array( 'in_footer' => true ) );

			wp_enqueue_style( 'siusk24', plugin_dir_url( __DIR__ ) . 'assets/css/terminal-mapping.css', array(), SIUSK24_VERSION );
			wp_enqueue_style( 'siusk24-css', plugin_dir_url( __DIR__ ) . 'assets/css/siusk24.css', array(), SIUSK24_VERSION );
			//wp_enqueue_script('leaflet', plugin_dir_url(__DIR__) . 'assets/js/leaflet.js', array('jquery'), null, true);
			//wp_enqueue_style('leaflet', plugin_dir_url(__DIR__) . 'assets/css/leaflet.css');

			wp_localize_script(
				'siusk24',
				'siusk24data',
				array(
					'ajax_url'                => admin_url( 'admin-ajax.php' ),
					'siusk24_plugin_url'      => plugin_dir_url( __DIR__ ),
					'text_select_terminal'    => __( 'Show in map', 'siusk24' ),
					'text_select_post'        => __( 'Select post office', 'siusk24' ),
					'text_search_placeholder' => __( 'Enter postcode', 'siusk24' ),
					'text_not_found'          => __( 'Place not found', 'siusk24' ),
					'text_enter_address'      => __( 'Enter postcode/address', 'siusk24' ),
					'text_map'                => __( 'Terminal map', 'siusk24' ),
					'text_list'               => __( 'Terminals list', 'siusk24' ),
					'text_search'             => __( 'Search', 'siusk24' ),
					'text_reset'              => __( 'Reset search', 'siusk24' ),
					'text_select'             => __( 'Choose terminal', 'siusk24' ),
					'text_no_city'            => __( 'City not found', 'siusk24' ),
					'text_my_loc'             => __( 'Use my location', 'siusk24' ),
					'images_path'             => plugin_dir_url( __DIR__ ) . 'assets/images/',
				)
			);
		}
	}

	public function admin_scripts() {

		// wp_register_script( 'siusk24_admin_jQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.js', null, null, true );
		// wp_enqueue_script('siusk24_admin_jQuery');
		wp_register_script( 'siusk24_admin_multiselect', plugin_dir_url( __DIR__ ) . 'assets/js/multiselect-dropdown.js', null, null, true );
		wp_enqueue_script( 'siusk24_admin_multiselect' );

		wp_register_style( 'siusk24_admin_style', plugin_dir_url( __DIR__ ) . 'assets/css/admin.css', false, SIUSK24_VERSION );
		wp_enqueue_style( 'siusk24_admin_style' );

		wp_register_script( 'siusk24_settings_js', plugin_dir_url( __DIR__ ) . 'assets/js/settings.js', array( 'jquery' ), SIUSK24_VERSION, true );
		wp_enqueue_script( 'siusk24_settings_js' );

		wp_localize_script(
			'siusk24_settings_js',
			'siusk24data',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		wp_localize_script(
			'siusk24_admin_multiselect',
			'siusk24_multiselect_config',
			array(
				'select_couriers' => __( 'Select couriers', 'siusk24' ),
			)
		);

		//$current_page = get_current_screen()->base;

		$order_id         = null;
		$is_siusk24_order = false;

		if ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
			$order_id = sanitize_text_field( wp_unslash( $_GET['post'] ) );
		} elseif ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {
			$order_id = sanitize_text_field( wp_unslash( $_GET['id'] ) );
		}

		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && ! is_wp_error( $order ) ) {
				$helper           = new \Siusk24Woo\Helper();
				$is_siusk24_order = $helper->is_siusk24_order( $order );
			}
		}

		if ( $is_siusk24_order ) {
			wp_register_script( 'siusk24_order_js', plugin_dir_url( __DIR__ ) . 'assets/js/order.js', array( 'jquery', 'select2' ), SIUSK24_VERSION, true );
			wp_enqueue_script( 'siusk24_order_js' );
		}
	}

	public function add_shipping_method( $methods ) {
		$methods['siusk24'] = 'Siusk24Woo\ShippingMethod';
		return $methods;
	}

	public function shipping_method_init() {
		require 'ShippingMethod.php';
		new \Siusk24Woo\ShippingMethod();
	}

	public function siusk24_show_terminals( $method ) {
		$customer = WC()->session->get( 'customer' );
		$country  = 'ALL';
		if ( ! isset( $_POST['country'] ) ) {
			return;
		}
		if ( isset( $customer['shipping_country'] ) ) {
			$country = $customer['shipping_country'];
		} elseif ( isset( $customer['country'] ) ) {
			$country = $customer['country'];
		}

		$termnal_id = WC()->session->get( 'siusk24_terminal_id' );

		$selected_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $selected_shipping_method ) ) {
			$selected_shipping_method = array();
		}
		if ( ! is_array( $selected_shipping_method ) ) {
			$selected_shipping_method = array( $selected_shipping_method );
		}

		if ( ! empty( $selected_shipping_method ) && stripos( $selected_shipping_method[0], 'siusk24_terminal_' ) !== false && stripos( $method->id, 'siusk24_terminal_' ) !== false ) {
			$identifier = $this->core->get_identifier_form_method( $method->id );
			echo $this->siusk24_get_terminal_options( $method->id, $termnal_id, $country, $identifier );
		}
	}

	public function siusk24_get_terminal_options( $method_id, $selected = '', $country = 'ALL', $identifier = 'siusk24' ) {
		//$country = "ALL";

		$siusk24_settings = $this->core->get_config();
		$set_autoselect   = ( isset( $siusk24_settings['auto_select'] ) && $siusk24_settings['auto_select'] == 'yes' ) ? 'true' : 'false';
		$max_distance     = ( isset( $siusk24_settings['terminal_distance'] ) && $siusk24_settings['terminal_distance'] ) ? $siusk24_settings['terminal_distance'] : '50';

		$script = "<script style='display:none;'>
        var siusk24Settings = {
          auto_select:" . $set_autoselect . ',
          max_distance:' . $max_distance . ",
          identifier: '{$identifier}' ,
          country: '{$country}' ,
          api_url: '" . $siusk24_settings['api_url'] . "',    
        };
        var siusk24_current_terminal = '" . $selected . "';
        var siusk24int_terminal_reference = '{$method_id}';
        jQuery('document').ready(function($){     
          $('body').trigger('load-siusk24-terminals');
          $('.siusk24_terminal').select2();
          jQuery('.siusk24_terminal').on('select2:select', function (e){ 
            console.log('Classic checkout terminal_id selected');
            console.log(jQuery(this).val());
            jQuery('#siusk24_terminal_hidden').val(jQuery(this).val());
            var text = jQuery('.siusk24_terminal option:selected').text();
            document.querySelector('.tmjs-selected-terminal').innerHTML = text;
            jQuery('.tmjs-selected-terminal').addClass('show-selected');
          });
        });
        </script>";
		$html   = '';
		$html  .= $this->render_terminal_select( $method_id, $country, $identifier, $selected );
		$html  .= '<div id="siusk24_map_container"></div><input type="hidden" id="siusk24_terminal_hidden"/>' . $script;

		return $html;
	}

	public function updated_services_notice() {
		?>
		<div class="notice notice-warning">
			<p><?php _e( 'Siusk24 services updated! Please check your selection.', 'siusk24' ); ?></p>
			<p><a href = "<?php echo Helper::get_settings_url(); ?>" class = "button-primary"><?php _e( 'Settings', 'siusk24' ); ?></a></p>
		</div>
		<?php
	}

	public static function activated() {
		wp_schedule_event( time(), '5min', 'siusk24_event' );
		self::create_terminals_table();
	}

	public static function deactivated() {
		wp_clear_scheduled_hook( 'siusk24_event' );
	}

	public function cron_add_5min( $schedules ) {
		$schedules['5min'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 min' ),
		);
		return $schedules;
	}

	public function siusk24_event_callback_function() {

		// Array to store order objects.
		$order_objects = array();

		// Meta query criteria.
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_siusk24_shipment_id',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_siusk24_tracking_numbers',
				'compare' => 'NOT EXISTS',
			),
		);

		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
			$query_args    = array(
				'limit'      => -1,
				'status'     => 'any',
				'type'       => 'shop_order',
				'meta_query' => $meta_query,
				'return'     => 'objects',
			);
			$order_objects = wc_get_orders( $query_args );
		} else {
			// Traditional method using get_posts
			$args = array(
				'post_type'   => 'shop_order',
				'numberposts' => -1,
				'post_status' => 'any',
				'meta_query'  => $meta_query,
			);

			$posts = get_posts( $args );

			// Convert post objects to WC_Order objects.
			foreach ( $posts as $post ) {
				$order_objects[] = wc_get_order( $post->ID );
			}
		}

		foreach ( $order_objects as $order ) {
			// ver.1.0.2
			$shipment_id = $order->get_meta( $order->get_id(), '_siusk24_shipment_id' );

			if ( $shipment_id ) {

				try {
					$response = $this->api->get_label( $shipment_id );
					$order->update_meta_data( '_siusk24_tracking_numbers', $response->tracking_numbers );
					$order->save();

				} catch ( \Exception $e ) {
					if ( function_exists( 'wc_get_logger' ) ) {
						\wc_get_logger()->debug( 'SIUSK_24:', array( 'source' => 'siusk-24' ) );
						\wc_get_logger()->debug( print_r( __METHOD__ . ': ' . __LINE__, true ), array( 'source' => 'siusk-24' ) );
						\wc_get_logger()->debug( print_r( $e->getMessage(), true ), array( 'source' => 'siusk-24' ) );
					}
				}
			}
		}
	}

	public static function create_terminals_table() {
		global $wpdb;
		$db_table_name   = $wpdb->prefix . 'siusk24_terminals';
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( "show tables like '$db_table_name'" ) != $db_table_name ) {
				$sql = "CREATE TABLE $db_table_name (
                       id int(11) NOT NULL auto_increment,
                       name varchar(255) NOT NULL,
                       city varchar(255) NOT NULL,
                       country_code varchar(10) NOT NULL,
                       address varchar(255) NOT NULL,
                       zip varchar(50) NOT NULL,
                       x_cord varchar(20) NOT NULL,
                       y_cord varchar(20) NOT NULL,
                       comment varchar(255) NOT NULL,
                       identifier varchar(50) NOT NULL,
                       UNIQUE KEY id (id)
               ) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			add_option( $db_table_name, SIUSK24_VERSION );
		}
	}


	public function siusk24_terminal_validate() {
		if ( isset( $_POST['shipping_method'] ) ) {
			foreach ( $_POST['shipping_method'] as $ship_method ) {
				if ( stripos( $ship_method, Helper::get_prefix() . '_terminal' ) !== false && empty( $_POST['siusk24_terminal'] ) ) {
					wc_add_notice( __( 'Please select parcel terminal.', 'siusk24' ), 'error' );
				}
			}
		}
	}


	public function siusk24_update_terminals() {
		$this->api->update_terminals();
		$array_result = array(
			'message' => 'Updated',
		);

		wp_send_json( $array_result );
		wp_die();
	}

	public function siusk24_check_api() {
		$return       = array(
			'status'  => 'success',
			'message' => __( 'API credentials is good', 'siusk24' ),
		);
		$check_result = $this->api->get_countries( true );
		if ( empty( $check_result ) ) {
			$return['status']  = 'error';
			$return['message'] = __( 'API credentials not working', 'siusk24' );
			update_option( Helper::get_prefix() . '_api_check', '0' );
		} else {
			update_option( Helper::get_prefix() . '_api_check', '1' );
		}
		wp_send_json( $return );
		wp_die();
	}

	public function siusk24_update_services() {
		$this->api->get_services( true );
		$array_result = array(
			'message' => 'Updated',
		);

		wp_send_json( $array_result );
		wp_die();
	}

	public function settings_link( $links ) {
		array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=siusk24' ) . '">' . __( 'Settings', 'siusk24lt' ) . '</a>' );
		return $links;
	}

	private function render_terminal_select( $method_id = false, $country = 'ALL', $identifier = 'siusk24', $selected_id = '' ) {
		$terminals        = $this->api->get_terminals( $country, $identifier );
		$parcel_terminals = '';
		if ( is_array( $terminals ) ) {
			$grouped_options = array();
			foreach ( $terminals as $terminal ) {
				if ( ! isset( $grouped_options[ $terminal->city ] ) ) {
					$grouped_options[ (string) $terminal->city ] = array();
				}
				$grouped_options[ (string) $terminal->city ][ (string) $terminal->id ] = $terminal->name . ', ' . $terminal->address;
			}
			$counter = 0;
			foreach ( $grouped_options as $city => $locs ) {
				$parcel_terminals .= '<optgroup data-id = "' . $counter . '" label = "' . $city . '">';
				foreach ( $locs as $key => $loc ) {
					$parcel_terminals .= '<option value = "' . $key . '" ' . ( $key == $selected_id ? 'selected' : '' ) . '>' . $loc . '</option>';
				}

				$parcel_terminals .= '</optgroup>';
				++$counter;
			}
		}
		return '<select class="siusk24_terminal" name="siusk24_terminal">' . $parcel_terminals . '</select>';
	}


	/**
	 * Save locker point to order_meta
	 *
	 * @param @param \WC_Order $order Order object.
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return void
	 * @throws RouteException
	 * @since 1.0.4
	 */
	public function block_checkout_save_terminal_id( $order, $request ) {
		if ( ! $order ) {
			return;
		}

		$shipping_method_id = null;
		$service_code       = null;

		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$shipping_method_id          = $item->get_method_id();
			$shipping_method_instance_id = $item->get_instance_id();
		}

		$request_body = json_decode( $request->get_body(), true );

		if ( ! empty( $request_body['extensions']['siusk24']['service-id'] ) ) {
			$service_code = sanitize_text_field( wp_unslash( $request_body['extensions']['siusk24']['service-id'] ) );
			if ( ! empty( $shipping_method_id ) && ! empty( $service_code ) ) {
				$order->update_meta_data( '_siusk24_service', $service_code );
				$order->update_meta_data( '_siusk24_method', 1 );
				$order->save();
			}
		}

		if ( ! empty( $request_body['extensions']['siusk24']['terminal-id'] ) ) {

			$terminal_id      = sanitize_text_field( wp_unslash( $request_body['extensions']['siusk24']['terminal-id'] ) );
			$fallback_service = $service_code;

			if ( ! empty( $request_body['extensions']['siusk24']['terminal-method-id'] ) ) {
				$terminal_method_id = sanitize_text_field( wp_unslash( $request_body['extensions']['siusk24']['terminal-method-id'] ) );
				$parsed_service     = $this->core->get_service_form_method( $terminal_method_id );
				if ( ! empty( $parsed_service ) ) {
					$fallback_service = $parsed_service;
				}
			}

			$this->core->apply_selected_terminal_to_order( $order, $terminal_id, $fallback_service );
			$order->save();

		} else {

			// Throw error
			/*throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'siusk24_terminal_required',
					'Siusk 24: ' . __( 'Terminal (parcel locker) must be choosen', 'siusk24' ),
					400
			);*/

		}
	}




	public function block_checkout_css() {
		if ( has_block( 'woocommerce/checkout' ) ) {
			?>
			<style>/* Position the wrapper to align with option-layout */
				/* Override inline width on wrapper */
				#siusk24_generated_wrapper {
					width: 100% !important;
					box-sizing: border-box;
				}

				/* Make all Select2 elements take full width of wrapper */
				#siusk24_generated_wrapper .select2,
				#siusk24_generated_wrapper .select2-container,
				#siusk24_generated_wrapper .select2-selection,
				#siusk24_generated_wrapper .select2-selection--single {
					width: 100% !important;
					box-sizing: border-box;
				}

				/* Ensure the wrapper aligns with option-layout (accounting for radio button) */
				.wc-block-components-radio-control__option:has(#siusk24_generated_wrapper) {
					flex-wrap: wrap;
				}

				/* Match the left padding/margin of option-layout */
				.wc-block-components-radio-control__option #siusk24_generated_wrapper {
					flex: 0 0 calc(100% - 24px); /* 24px = typical radio button space */
					margin-left: 24px;
				}

				/* Also style the map container if present */
				#siusk24_generated_wrapper #siusk24_map_container {
					width: 100%;
					box-sizing: border-box;
				}
				a.tmjs-open-modal-btn {
					max-width: 90%;
				}

				span#select2-siusk24_terminal-xv-container {
					background: #dbf8e0 !important;
				}
				.siusk24_terminal {
					max-width: 90% !important;
				}
				select[name="siusk24_terminal"] {
					height: 30px;
					margin-bottom: 20px;
				}
			</style>
			<?php
		}
	}
}
