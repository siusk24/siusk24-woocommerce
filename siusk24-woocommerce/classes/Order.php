<?php

namespace Siusk24Woo;

use Siusk24Woo\Helper;
use Siusk24Woo\Terminal;

class Order {

	private $api;
	private $core;

	public function __construct( $api, $core ) {
		$this->api  = $api;
		$this->core = $core;
		add_action( 'add_meta_boxes', array( $this, 'siusk24_meta_boxes' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_service_code' ) );
		add_action( 'wp_ajax_create_siusk24_order', array( $this, 'create_order' ) );
		add_action( 'wp_ajax_load_siusk24_order', array( $this, 'load_order' ) );
		add_action( 'wp_ajax_delete_siusk24_order', array( $this, 'delete_order' ) );
		add_action( 'init', array( $this, 'print_label' ) );
	}

	public function siusk24_meta_boxes( $post_type, $post ) {

		$helper           = new \Siusk24Woo\Helper();
		$is_siusk24_order = $helper->is_siusk24_order( $post );

		if ( $is_siusk24_order ) {
			add_meta_box( 'siusk24_shipping_meta_box', __( 'Siusk24', 'siusk24' ), array( $this, 'meta_box_content' ), '', 'side', 'core' );
		}
	}

	public function meta_box_content( $post ) {

		// ver.1.0.2
		$order_id = null;

		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
			// HPOS usage is enabled.
			if ( is_a( $post, 'WC_Order' ) ) {
				$order_id = $post->get_id();
			}
		} else {
			// Traditional orders are in use.
			if ( is_object( $post ) && $post->post_type == 'shop_order' ) {
				$order_id = $post->ID;
			}
		}

		if ( ! $order_id ) {
			return;
		}

		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order || is_wp_error( $wc_order ) ) {
			return;
		}

		$manifest_date = $wc_order->get_meta( '_siusk24_manifest_date' );
		$shipment_id   = $wc_order->get_meta( '_siusk24_shipment_id' );
		$cart_id       = $wc_order->get_meta( '_siusk24_cart_id' );
		$terminal_id   = $wc_order->get_meta( '_siusk24_terminal_id' );
		$identifier    = $wc_order->get_meta( '_siusk24_identifier' );

		$receiver_country = $wc_order->get_shipping_country();

		$carrier_code = $wc_order->get_meta( '_siusk24_service' );

		if ( $terminal_id && empty( $shipment_id ) ) {
			$resolved = $this->core->resolve_locker_from_terminal( $terminal_id, $carrier_code );
			$dirty    = false;
			if ( ! empty( $resolved['identifier'] ) && $identifier !== $resolved['identifier'] ) {
				$identifier = $resolved['identifier'];
				$wc_order->update_meta_data( '_siusk24_identifier', $identifier );
				$dirty = true;
			}
			if ( ! empty( $resolved['service_code'] ) && $carrier_code !== $resolved['service_code'] ) {
				$carrier_code = $resolved['service_code'];
				$wc_order->update_meta_data( '_siusk24_service', $carrier_code );
				$dirty = true;
			}
			if ( $dirty ) {
				$wc_order->save();
			}
		}

		$carrier            = $this->core->get_service_info( $carrier_code );
		$available_services = $this->core->get_additional_services( $carrier_code );
		?>
		<img src = "<?php echo plugin_dir_url( __DIR__ ); ?>assets/images/s24logo.png" style="width: 100px;"/>
		<div class ="errors"></div>
		<p>
			<?php $this->build_title( __( 'Carrier', 'siusk24' ) ); ?> <?php echo $carrier->name ?? '-'; ?>
		</p>
		<?php if ( $shipment_id && $cart_id ) : ?>
			<?php
			$tracking    = $wc_order->get_meta( '_siusk24_tracking_numbers' );
			$label_ready = false;
			if ( empty( $tracking ) ) {
				try {
					$response = $this->api->get_label( $shipment_id );
					// ver.1.0.2
					$wc_order->update_meta_data( '_siusk24_tracking_numbers', $response->tracking_numbers );
					$wc_order->save();

					$tracking    = $response->tracking_numbers;
					$label_ready = true;
				} catch ( \Exception $e ) {
					$tracking = array( __( 'Generating...', 'siusk24' ) );
				}
			} else {
				$label_ready = true;
			}
			$active_additional_services = $this->get_active_additional_services( $order_id, $available_services );
			?>
			<?php if ( ! empty( $active_additional_services ) ) : ?>
				<p>
					<?php echo $this->build_title( __( 'Active services', 'siusk24' ), false ) . implode( ', ', $active_additional_services ); ?>
				</p>
			<?php endif; ?>
			<p>
				<?php echo $this->build_title( __( 'Shipment ID', 'siusk24' ), false ) . $shipment_id; ?>
			</p>
			<p>
				<?php echo $this->build_title( __( 'Cart ID', 'siusk24' ), false ) . $cart_id; ?>
			</p>
			<p>
				<?php echo $this->build_title( __( 'Tracking', 'siusk24' ), false ) . implode( ', ', $tracking ); ?>
			</p>
			<?php if ( $label_ready === true ) : ?>
				<p>
					<a href ="<?php echo Helper::generate_outside_action_url( 'print_label', $shipment_id ); ?>" target = "_blank" class="button button-primary"><?php _e( 'Print label', 'siusk24' ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( ! $manifest_date ) : ?>    
			<div >
				<button type="button" value="delete" id="siusk24_delete" name="siusk24_delete" class="button siusk24-btn button-danger"><?php _e( 'Delete', 'siusk24' ); ?></button>
			</div>
			<?php endif; ?>    
		<?php else : ?>
			<?php if ( $terminal_id ) : ?>  
				<p> 
					<?php $this->render_terminal_select( $terminal_id, $receiver_country, $identifier ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $available_services ) ) : ?>  
				<?php $this->render_services( $available_services, $post ); ?>
				
			<?php endif; ?>    
			<?php $this->render_hs(); ?>    
			<div class = "siusk24-row">
				<button type="button" value="create" id="siusk24_create" name="siusk24_create" class="button button-primary"><?php _e( 'Create', 'siusk24' ); ?></button>
			</div>
		<?php endif; ?>
			<div class ="siusk24-loader-container">
				<div class ="siusk24-loader"></div>    
			</div>
		<?php
	}

	private function build_title( $title, $echo = true ) {
		$output = '<span class="siusk24_title">' . $title . ':</span>';

		if ( $echo ) {
			echo $output;
		} else {
			return $output;
		}
	}

	private function render_terminal_select( $selected_id = false, $country = 'ALL', $identifier = 'siusk24' ) {
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
		echo $this->build_title( __( 'Terminal', 'siusk24' ), false ) . '<select class="siusk24_terminal" name="siusk24_terminal">' . $parcel_terminals . '</select>';
	}

	private function render_services( $services, $order ) {
		// ver.1.0.2
		$order_id    = null;
		$order_total = '';

		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
			// HPOS usage is enabled.
			if ( is_a( $order, 'WC_Order' ) ) {
				$order_id = $order->get_id();
			}
		} else {
			// Traditional orders are in use.
			if ( is_object( $order ) && $order->post_type == 'shop_order' ) {
				$order_id = $order->ID;
			}
		}

		if ( ! empty( $order_id ) ) {
			$wc_order = wc_get_order( $order_id );
			if ( $wc_order && ! is_wp_error( $wc_order ) ) {
				$order_total = $wc_order->get_meta( '_order_total' );
			}
		}

		$all_services = Helper::additional_services();
		$this->build_title( __( 'Services', 'siusk24' ) );
		echo '<ul class = "siusk24-services">';
		foreach ( $services as $id ) {
			if ( ! isset( $all_services[ $id ] ) ) {
				continue;
			}
			echo '<li><input type = "checkbox" id = "service_' . $id . '" class = "siusk24_services" name = "services[]" value = "' . $id . '"/><label for = "service_' . $id . '">' . $all_services[ $id ] . '</label>';
			if ( $id == 'cod' ) {
				echo '<span class = "cod-amount"><input type = "number" name = "cod_amount" value = "' . esc_attr( $order_total ) . '">EUR</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	private function render_eori() {
		echo '<p>';
		$this->build_title( __( 'EORI number', 'siusk24' ) );
		echo '<input type = "text" class = "siusk24_eori"/>';
		echo '</p>';
	}

	private function render_hs() {
		echo '<p>';
		$this->build_title( __( 'HS code', 'siusk24' ) );
		echo '<input type = "text" class = "siusk24_hs"/>';
		echo '</p>';
	}

	private function get_active_additional_services( $order_id, $available_services ) {
		$additional_services = array();
		// ver.1.0.2
		$wc_order = wc_get_order( $order_id );
		if ( $wc_order && ! is_wp_error( $wc_order ) ) {
			$addserv_insurance = $wc_order->get_meta( '_siusk24_insurance' );
			if ( ! empty( $addserv_insurance ) && in_array( 'insurance', $available_services ) ) {
				$price                            = wc_price( $addserv_insurance, array( 'currency' => 'EUR' ) );
				$additional_services['insurance'] = Helper::additional_services( 'insurance' ) . ' (' . $price . ')';
			}
		}

		return $additional_services;
	}

	public function print_label( $shipment_id ) {
		if ( current_user_can( 'edit_shop_orders' ) && is_admin() && isset( $_GET['siusk24_label'] ) ) {
			$shipment_id = $_GET['siusk24_label'];
			$this->core->print_label( $shipment_id );
		}
	}

	public function create_order() {
		$status = $this->core->register_order(
			array(
				'wc_order_id' => $_POST['order_id'] ?? 0,
				'services'    => $_POST['services'] ?? array(),
				'terminal'    => $_POST['terminal'] ?? 0,
				'cod_amount'  => $_POST['cod_amount'] ?? false,
				'eori_number' => $_POST['eori'] ?? false,
				'hs_code'     => $_POST['hs'] ?? false,
			)
		);
		wp_send_json_success( $status );
	}

	public function load_order() {

		$order_id = null;

		if ( ! empty( $_POST['order_id'] ) ) {
			$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		}

		if ( $order_id ) {

			try {

				$wc_order = wc_get_order( $order_id );

				ob_start();
				$this->meta_box_content( $wc_order );
				$content = ob_get_contents();
				ob_end_clean();
				wp_send_json_success(
					array(
						'status'  => 'ok',
						'content' => $content,
					)
				);
			} catch ( \Exception $e ) {
				wp_send_json_success(
					array(
						'status' => 'error',
						'msg'    => $e->getMessage(),
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'status' => 'error',
				'msg'    => __( 'Order not found', 'siusk24' ),
			)
		);
	}

	public function delete_order() {
		$id = $_POST['order_id'] ?? 0;

		$status = $this->core->remove_order( $id );
		wp_send_json_success( $status );
	}



	public function add_service_code( $order_id ) {
		//$methods_params = siusk24lt_configs('method_params');

		$wc_order = wc_get_order( $order_id );
		if ( ! $wc_order || is_wp_error( $wc_order ) ) {
			return;
		}

		$terminal_id = 0;
		if ( ! empty( $_POST[ Helper::get_prefix() . '_terminal' ] ) ) {
			$terminal_id = absint( wp_unslash( $_POST[ Helper::get_prefix() . '_terminal' ] ) );
		}

		$fallback_service   = '';
		$is_terminal_method = false;

		if ( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
			foreach ( $_POST['shipping_method'] as $ship_method ) {
				if ( stripos( $ship_method, Helper::get_prefix() . '_terminal' ) !== false ) {
					$is_terminal_method = true;
					$fallback_service   = $this->core->get_service_form_method( $ship_method );
					break;
				}
				if ( stripos( $ship_method, Helper::get_prefix() . '_service' ) !== false ) {
					$fallback_service = str_ireplace( Helper::get_prefix() . '_service_', '', $ship_method );
					break;
				}
			}
		}

		if ( $is_terminal_method && $terminal_id ) {
			$this->core->apply_selected_terminal_to_order( $wc_order, $terminal_id, $fallback_service );
			$wc_order->save();
			return;
		}

		if ( $terminal_id ) {
			$wc_order->update_meta_data( '_siusk24_terminal_id', $terminal_id );
		}

		if ( $fallback_service ) {
			$wc_order->update_meta_data( '_siusk24_service', $fallback_service );
			$wc_order->update_meta_data( '_siusk24_method', 1 );
		}

		$wc_order->save();
	}
}
