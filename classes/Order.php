<?php

namespace Siusk24Woo;

use Siusk24Woo\Helper;
use Siusk24Woo\Terminal;

class Order {

    private $api;
    private $core;

    public function __construct($api, $core) {
        $this->api = $api;
        $this->core = $core;
        add_action('add_meta_boxes_shop_order', array($this, 'siusk24_meta_boxes'), 1);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'add_service_code'));
        add_action('wp_ajax_create_siusk24_order', array($this, 'create_order'));
        add_action('wp_ajax_load_siusk24_order', array($this, 'load_order'));
        add_action('wp_ajax_delete_siusk24_order', array($this, 'delete_order'));
        add_action('init', array($this, 'print_label'));
        
    }

    public function siusk24_meta_boxes($post) {
        if ($this->is_siusk24_order($post)) {
            add_meta_box('siusk24_shipping_meta_box', __('Siusk24', 'siusk24'), array($this, 'meta_box_content'), 'shop_order', 'side', 'core');
        }
    }

    public function meta_box_content($post) {
        $manifest_date = get_post_meta($post->ID, '_siusk24_manifest_date', true);
        $shipment_id = get_post_meta($post->ID, '_siusk24_shipment_id', true);
        $cart_id = get_post_meta($post->ID, '_siusk24_cart_id', true);
        $terminal_id = get_post_meta($post->ID, '_siusk24_terminal_id', true);
        $identifier = get_post_meta($post->ID, '_siusk24_identifier', true);
        $receiver_country = get_post_meta( $post->ID, '_shipping_country', true );
        $carrier_code = get_post_meta($post->ID, '_siusk24_service', true);
        $carrier = $this->core->get_service_info($carrier_code);
        $available_services = $this->core->get_additional_services($carrier_code);
        ?>
        <img src = "<?php echo plugin_dir_url(__DIR__); ?>assets/images/s24logo.png" style="width: 100px;"/>
        <div class ="errors"></div>
        <p>
            <?php $this->build_title(__("Carrier", 'siusk24')); ?> <?php echo $carrier->name ?? '-'; ?>
        </p>
        <?php if ($shipment_id && $cart_id): ?>
            <?php
            $tracking = get_post_meta($post->ID, '_siusk24_tracking_numbers', true);
            $label_ready = false;
            if (empty($tracking)) {
                try {
                    $response = $this->api->get_label($shipment_id);
                    update_post_meta($post->ID, '_siusk24_tracking_numbers', $response->tracking_numbers);
                    $tracking = $response->tracking_numbers;
                    $label_ready = true;
                } catch (\Exception $e) {
                    $tracking = [__('Generating...', 'siusk24')];
                }
            } else {
                $label_ready = true;
            }
            $active_additional_services = $this->get_active_additional_services($post->ID, $available_services);
            ?>
            <?php if (!empty($active_additional_services)) : ?>
                <p>
                    <?php echo $this->build_title(__("Active services", 'siusk24'), false) . implode(', ', $active_additional_services); ?>
                </p>
            <?php endif; ?>
            <p>
                <?php echo $this->build_title(__("Shipment ID", 'siusk24'), false) . $shipment_id; ?>
            </p>
            <p>
                <?php echo $this->build_title(__("Cart ID", 'siusk24'), false) . $cart_id; ?>
            </p>
            <p>
                <?php echo $this->build_title(__("Tracking", 'siusk24'), false) . implode(', ', $tracking); ?>
            </p>
            <?php if ($label_ready === true): ?>
                <p>
                    <a href ="<?php echo Helper::generate_outside_action_url('print_label', $shipment_id); ?>" target = "_blank" class="button button-primary"><?php _e('Print label', 'siusk24'); ?></a>
                </p>
            <?php endif; ?>
            <?php if (!$manifest_date): ?>    
            <div >
                <button type="button" value="delete" id="siusk24_delete" name="siusk24_delete" class="button siusk24-btn button-danger"><?php _e('Delete', 'siusk24'); ?></button>
            </div>
            <?php endif; ?>    
        <?php else: ?>
            <?php if ($terminal_id): ?>  
                <p> 
                    <?php $this->render_terminal_select($terminal_id, $receiver_country, $identifier); ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($available_services)): ?>  
                <?php $this->render_services($available_services, $post); ?>
                
            <?php endif; ?>    
            <?php $this->render_hs(); ?>    
            <div class = "siusk24-row">
                <button type="button" value="create" id="siusk24_create" name="siusk24_create" class="button button-primary"><?php _e('Create', 'siusk24'); ?></button>
            </div>
        <?php endif; ?>
            <div class ="siusk24-loader-container">
                <div class ="siusk24-loader"></div>    
            </div>
        <?php
    }

    private function build_title($title, $echo = true) {
        $output = '<span class="siusk24_title">' . $title . ':</span>';

        if ($echo) {
            echo $output;
        } else {
            return $output;
        }
    }
    
    private function render_terminal_select($selected_id = false, $country = 'ALL', $identifier = "siusk24"){
        $terminals = $this->api->get_terminals($country, $identifier);
        $parcel_terminals = '';
        if (is_array($terminals)) {
            $grouped_options = array();
            foreach ($terminals as $terminal) {
                if (!isset($grouped_options[$terminal->city])) {
                    $grouped_options[(string) $terminal->city] = array();
                }    
                $grouped_options[(string) $terminal->city][(string) $terminal->id] = $terminal->name . ', ' . $terminal->address;
            }
            $counter = 0;
            foreach ($grouped_options as $city => $locs) {
                $parcel_terminals .= '<optgroup data-id = "' . $counter . '" label = "' . $city . '">';
                foreach ($locs as $key => $loc) {
                    $parcel_terminals .= '<option value = "' . $key . '" ' . ($key == $selected_id ? 'selected' : '') . '>' . $loc . '</option>';
                }

                $parcel_terminals .= '</optgroup>';
                $counter++;
            }
        }
        echo $this->build_title(__("Terminal", 'siusk24'), false) . '<select class="siusk24_terminal" name="siusk24_terminal">' . $parcel_terminals . '</select>';
    }
    
    private function render_services($services, $order) {
        $all_services = Helper::additional_services();
        $this->build_title(__("Services", 'siusk24'));
        echo '<ul class = "siusk24-services">';
        foreach ($services as $id) {
            if (!isset($all_services[$id])) {
                continue;
            }
            echo '<li><input type = "checkbox" id = "service_'.$id.'" class = "siusk24_services" name = "services[]" value = "'.$id.'"/><label for = "service_'.$id.'">'.$all_services[$id].'</label>';
            if ($id == 'cod') {
                echo '<span class = "cod-amount"><input type = "number" name = "cod_amount" value = "'.get_post_meta($order->ID, '_order_total', true).'">EUR</span>';
            }
            echo '</li>';      
        }
        echo '</ul>';
    }
    
    private function render_eori() {
        echo '<p>';
        $this->build_title(__("EORI number", 'siusk24'));
        echo '<input type = "text" class = "siusk24_eori"/>';
        echo '</p>';
    }

    private function render_hs() {
        echo '<p>';
        $this->build_title(__("HS code", 'siusk24'));
        echo '<input type = "text" class = "siusk24_hs"/>';
        echo '</p>';
    }

    private function get_active_additional_services($order_id, $available_services) {
        $additional_services = array();
        
        $addserv_insurance = get_post_meta($order_id, '_siusk24_insurance', true);
        if (!empty($addserv_insurance) && in_array('insurance', $available_services)) {
            $price = wc_price($addserv_insurance, array('currency' => 'EUR'));
            $additional_services['insurance'] = Helper::additional_services('insurance') . ' (' . $price . ')';
        }

        return $additional_services;
    }

    public function print_label($shipment_id) {
        if (current_user_can( 'edit_shop_orders' ) && is_admin() && isset($_GET['siusk24_label'])) {
            $shipment_id = $_GET['siusk24_label'];
            $this->core->print_label($shipment_id);
        }
    }

    public function create_order() {
        $status = $this->core->register_order(array(
            'wc_order_id' => $_POST['order_id'] ?? 0,
            'services' => $_POST['services'] ?? [],
            'terminal' => $_POST['terminal'] ?? 0,
            'cod_amount' => $_POST['cod_amount'] ?? false,
            'eori_number' => $_POST['eori'] ?? false,
            'hs_code' => $_POST['hs'] ?? false,
        ));
        wp_send_json_success($status);
    }

    public function load_order() {
        $id = $_POST['order_id'] ?? 0;
        if ($id && $post = get_post($id)) {
            try {
                ob_start();
                $this->meta_box_content($post);
                $content = ob_get_contents();
                ob_end_clean();
                wp_send_json_success(['status' => 'ok', 'content' => $content]);
            } catch (\Exception $e) {
                wp_send_json_success(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }
        wp_send_json_success(['status' => 'error', 'msg' => __('Order not found', 'siusk24')]);
    }

    public function delete_order() {
        $id = $_POST['order_id'] ?? 0;

        $status = $this->core->remove_order($id);
        wp_send_json_success($status);
    }

    public function is_siusk24_order($post) {
        $order = wc_get_order($post->ID);
        return $order->has_shipping_method(Helper::get_prefix());
    }

    public function add_service_code($order_id) {
        //$methods_params = siusk24lt_configs('method_params');

        if (isset($_POST[Helper::get_prefix() . '_terminal']) && $order_id) {
            update_post_meta($order_id, '_siusk24_terminal_id', $_POST[Helper::get_prefix() . '_terminal']);
        }
        if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
            foreach ($_POST['shipping_method'] as $ship_method) {
                if (stripos($ship_method, Helper::get_prefix() . '_service') !== false) {
                    $service_code = str_ireplace(Helper::get_prefix() . '_service_', '', $ship_method);
                    update_post_meta($order_id, '_siusk24_service', $service_code);
                    update_post_meta($order_id, '_siusk24_method', 1);
                    break;
                }
                if (stripos($ship_method, Helper::get_prefix() . '_terminal') !== false) {
                    $service_code = $this->core->get_service_form_method($ship_method);
                    $identifier = $this->core->get_identifier_form_method($ship_method);
                    update_post_meta($order_id, '_siusk24_service', $service_code);
                    update_post_meta($order_id, '_siusk24_identifier', $identifier);
                    update_post_meta($order_id, '_siusk24_method', 1);
                    break;
                }
            }
        }
    }

}
