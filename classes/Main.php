<?php

namespace Siusk24Woo;

if (!defined('ABSPATH')) {
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

    public function __construct($init = true) {
        $this->init();
        $this->core = new Core();
        $this->api = $this->core->get_api();
        $this->category = new Category();
        $this->order = new Order($this->api, $this->core);
        $this->manifest = new Manifest($this->api, $this->core);
    }

    private function init() {
        add_action('woocommerce_shipping_init', array($this, 'shipping_method_init'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'front_scripts'), 99);
        add_action('woocommerce_after_shipping_rate', array($this, 'siusk24_show_terminals'));
        //add_action('wp_footer', array($this, 'footer_modal'));
        add_action('siusk24_event', array($this, 'siusk24_event_callback_function'));
        add_filter('cron_schedules', array($this, 'cron_add_5min'));
        add_action('woocommerce_checkout_process', array($this, 'siusk24_terminal_validate'));
        add_action( 'wp_ajax_siusk24_terminals_sync', array($this, 'siusk24_update_terminals'));
        add_action( 'wp_ajax_siusk24_services_sync', array($this, 'siusk24_update_services'));
        add_filter('plugin_action_links_' . SIUSK24_BASENAME, array($this, 'settings_link'));

        if (get_option(Helper::get_prefix() . '_services_updated', 0) == 1) {
            add_action('admin_notices', array($this, 'updated_services_notice'));
        }
    }

    public function front_scripts() {
        if (is_checkout() && ! is_wc_endpoint_url()) {

            wp_enqueue_script('siusk24-helper', plugin_dir_url(__DIR__) . 'assets/js/siusk24_helper.js', array('jquery'), SIUSK24_VERSION);
            wp_enqueue_script('siusk24', plugin_dir_url(__DIR__) . 'assets/js/siusk24.js', array('jquery'), SIUSK24_VERSION);
            wp_enqueue_script('siusk24-terminal', plugin_dir_url(__DIR__) . 'assets/js/terminal.js', array('jquery'), SIUSK24_VERSION);

            wp_enqueue_style('siusk24', plugin_dir_url(__DIR__) . 'assets/css/terminal-mapping.css', array(), SIUSK24_VERSION);
            wp_enqueue_style('siusk24-css', plugin_dir_url(__DIR__) . 'assets/css/siusk24.css', array(), SIUSK24_VERSION);
            //wp_enqueue_script('leaflet', plugin_dir_url(__DIR__) . 'assets/js/leaflet.js', array('jquery'), null, true);
            //wp_enqueue_style('leaflet', plugin_dir_url(__DIR__) . 'assets/css/leaflet.css');

            wp_localize_script('siusk24', 'siusk24data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'siusk24_plugin_url' => plugin_dir_url(__DIR__),
                'text_select_terminal' => __('Show in map', 'siusk24'),
                'text_select_post' => __('Select post office', 'siusk24'),
                'text_search_placeholder' => __('Enter postcode', 'siusk24'),
                'text_not_found' => __('Place not found', 'siusk24'),
                'text_enter_address' => __('Enter postcode/address', 'siusk24'),
                'text_map' => __('Terminal map', 'siusk24'),
                'text_list' => __('Terminals list', 'siusk24'),
                'text_search' => __('Search', 'siusk24'),
                'text_reset' => __('Reset search', 'siusk24'),
                'text_select' => __('Choose terminal', 'siusk24'),
                'text_no_city' => __('City not found', 'siusk24'),
                'text_my_loc' => __('Use my location', 'siusk24'),
                'images_path' => plugin_dir_url(__DIR__) . 'assets/images/'
            ));
        }
    }

    public function admin_scripts() {
       
        // wp_register_script( 'siusk24_admin_jQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.js', null, null, true );
        // wp_enqueue_script('siusk24_admin_jQuery');
        wp_register_script( 'siusk24_admin_multiselect', plugin_dir_url(__DIR__) . 'assets/js/multiselect-dropdown.js', null, null, true );
        wp_enqueue_script('siusk24_admin_multiselect');
        
        wp_register_style('siusk24_admin_style', plugin_dir_url(__DIR__) . 'assets/css/admin.css', false, SIUSK24_VERSION);
        wp_enqueue_style('siusk24_admin_style');

        wp_register_script('siusk24_settings_js', plugin_dir_url(__DIR__) . 'assets/js/settings.js', array('jquery'), SIUSK24_VERSION, true);
        wp_enqueue_script('siusk24_settings_js');
        
        wp_localize_script('siusk24_settings_js', 'siusk24data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
        ));

        wp_localize_script('siusk24_admin_multiselect', 'siusk24_multiselect_config', array(
            'select_couriers' => __('Select couriers', 'siusk24'),
        ));

        $current_page = get_current_screen()->base;
        if ($current_page == 'post') {
            wp_register_script('siusk24_order_js', plugin_dir_url(__DIR__) . 'assets/js/order.js', array('jquery', 'select2'), SIUSK24_VERSION, true);
            wp_enqueue_script('siusk24_order_js');
        }
    }

    public function add_shipping_method($methods) {
        $methods['siusk24'] = 'Siusk24Woo\ShippingMethod';
        return $methods;
    }

    public function shipping_method_init() {
        require "ShippingMethod.php";
        new \Siusk24Woo\ShippingMethod();
    }

    public function siusk24_show_terminals($method) {
        $customer = WC()->session->get('customer');
        $country = "ALL";
        if (!isset($_POST['country'])) {
            return;
        }
        if (isset($customer['shipping_country'])) {
            $country = $customer['shipping_country'];
        } elseif (isset($customer['country'])) {
            $country = $customer['country'];
        }

        $termnal_id = WC()->session->get('siusk24_terminal_id');

        $selected_shipping_method = WC()->session->get('chosen_shipping_methods');
        if (empty($selected_shipping_method)) {
            $selected_shipping_method = array();
        }
        if (!is_array($selected_shipping_method)) {
            $selected_shipping_method = array($selected_shipping_method);
        }

        if (!empty($selected_shipping_method) && stripos($selected_shipping_method[0], 'siusk24_terminal_') !== false && stripos($method->id, 'siusk24_terminal_') !== false) {
            $identifier = $this->core->get_identifier_form_method($method->id);
            echo $this->siusk24_get_terminal_options($method->id, $termnal_id, $country, $identifier);
        }
    }

    public function siusk24_get_terminal_options($method_id, $selected = '', $country = "ALL", $identifier = 'siusk24') {
        //$country = "ALL";

        $siusk24_settings = $this->core->get_config();
        $set_autoselect = (isset($siusk24_settings['auto_select']) && $siusk24_settings['auto_select'] == 'yes') ? 'true' : 'false';
        $max_distance = (isset($siusk24_settings['terminal_distance']) && $siusk24_settings['terminal_distance']) ? $siusk24_settings['terminal_distance'] : '50';
       
        $script = "<script style='display:none;'>
        var siusk24Settings = {
          auto_select:" . $set_autoselect . ",
          max_distance:" . $max_distance . ",
          identifier: '{$identifier}' ,
          country: '{$country}' ,
          api_url: '".$siusk24_settings['api_url']."',    
        };
        var siusk24_current_terminal = '" . $selected . "';
        var siusk24int_terminal_reference = '{$method_id}';
        jQuery('document').ready(function($){     
          $('body').trigger('load-siusk24-terminals');
          $('.siusk24_terminal').select2();
          jQuery('.siusk24_terminal').on('select2:select', function (e){ 
            jQuery('input[name=\"siusk24_terminal\"]').val(jQuery(this).val());
            var text = jQuery('.siusk24_terminal option:selected').text();
            document.querySelector('.tmjs-selected-terminal').innerHTML = text;
          });
        });
        </script>";
        $html = ''; 
        $html .=  $this->render_terminal_select($method_id, $country, $identifier, $selected);
        $html .= '<div id="siusk24_map_container"></div><input type = "hidden" name="siusk24_terminal"/>'.$script;

        return $html;
    }

    public function updated_services_notice() {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('Siusk24 services updated! Please check your selection.', 'siusk24'); ?></p>
            <p><a href = "<?php echo Helper::get_settings_url(); ?>" class = "button-primary"><?php _e('Settings', 'siusk24'); ?></a></p>
        </div>
        <?php
    }

    public static function activated() {
        wp_schedule_event(time(), '5min', 'siusk24_event');
        self::create_terminals_table();
    }

    public static function deactivated() {
        wp_clear_scheduled_hook('siusk24_event');
    }

    public function cron_add_5min($schedules) {
        $schedules['5min'] = array(
            'interval' => 300,
            'display' => __('Every 5 min')
        );
        return $schedules;
    }

    public function siusk24_event_callback_function() {
        $args = array(
            'post_type' => 'shop_order',
            'numberposts' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_siusk24_shipment_id',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => '_siusk24_tracking_numbers',
                    'compare' => 'NOT EXISTS',
                ),
            )
        );
        $orders = get_posts($args);
        foreach ($orders as $order) {
            $shipment_id = get_post_meta($order->ID, '_siusk24_shipment_id', true);
            if ($shipment_id) {
                try {
                    $response = $this->api->get_label($shipment_id);
                    update_post_meta($order->ID, '_siusk24_tracking_numbers', $response->tracking_numbers);
                } catch (\Exception $e) {
                    
                }
            }
        }
    }
    
    public static function create_terminals_table() {      
        global $wpdb; 
        $db_table_name = $wpdb->prefix . 'siusk24_terminals';
        $charset_collate = $wpdb->get_charset_collate();

        if($wpdb->get_var( "show tables like '$db_table_name'" ) != $db_table_name ) {
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

          require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
          dbDelta( $sql );
          add_option( $db_table_name, SIUSK24_VERSION );
        }
    } 


    public function siusk24_terminal_validate() {
        if (isset($_POST['shipping_method'])) {
            foreach ($_POST['shipping_method'] as $ship_method) {
                if (stripos($ship_method, Helper::get_prefix() . '_terminal') !== false && empty($_POST['siusk24_terminal'])) {
                    wc_add_notice(__('Please select parcel terminal.', 'siusk24'), 'error');
                }
            }
        }
    }
    
 
    public function siusk24_update_terminals() {
        $this->api->update_terminals();
        $array_result = array(
            'message' => 'Updated'
        );

        wp_send_json($array_result);
        wp_die();
    }
 
    public function siusk24_update_services() {
        $this->api->get_services(true);
        $array_result = array(
            'message' => 'Updated'
        );

        wp_send_json($array_result);
        wp_die();
    }

    public function settings_link($links) {
        array_unshift($links, '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=siusk24' ) . '">' . __('Settings', 'siusk24lt') . '</a>');
        return $links;
    }

    private function render_terminal_select($method_id = false, $country = 'ALL', $identifier = "siusk24", $selected_id = ''){
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
        return '<select class="siusk24_terminal" name="siusk24_terminal">' . $parcel_terminals . '</select>';
    }

}
