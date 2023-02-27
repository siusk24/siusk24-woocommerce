<?php

namespace Siusk24Woo;

if (!defined('ABSPATH')) {
    exit;
}

use Siusk24Woo\Core;
use Siusk24Woo\Helper;
use WC_Shipping_Method;

if (!class_exists('\Siusk24Woo\ShippingMethod')) {

    class ShippingMethod extends WC_Shipping_Method
    {

        private $core;
        private $api;

        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public function __construct()
        {
            $this->core = new Core;
            $this->api = $this->core->get_api();
            $this->id = Helper::get_prefix();
            $this->method_title = __('Siusk24', 'siusk24');
            $this->method_description = __('Siusk24 shipping method', 'siusk24');
            $this->supports = array(
                'settings'
            );
            $this->init();
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        private function init()
        {
            // Load the settings API
            $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
            $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
            // Save settings in admin if you have any defined
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        private function get_countries_options()
        {
            $options = [];
            $countries = $this->api->get_countries();
            foreach ($countries as $country) {
                $options[$country->code] = $country->name;
            }
            return $options;
        }

        public function init_form_fields()
        {
            $countries_options = $this->get_countries_options();
            if ( empty($countries_options) ) {
                $countries_options = array('- ' . __('Not received countries list from API', 'siusk24') . ' -');
            }
            $currency = get_woocommerce_currency();
            $fields = array(
                'main_logo' => array(
                    'type' => 'logo'
                ),
                'enabled' => array(
                    'label' => __('Enable', 'siusk24'),
                    'type' => 'fieldset_start',
                    'description' => __('Activate SIUSK24.lt services', 'siusk24'),
                    'default' => 'yes'
                ),
                'hr_api' => array(
                    'type' => 'hr',
                    'title_after' => __('API settings', 'siusk24'),
                ),
                'api_url' => array(
                    'title' => __('API URL', 'siusk24'),
                    'type' => 'text',
                    'class' => 'check-api-this',
                    'default' => 'https://www.siusk24.lt',
                    'description' => __('Change only if want use custom Api URL', 'siusk24') . '. ' . sprintf(__('Default is %s', 'siusk24'), '<code>https://www.siusk24.lt</code>'),
                ),
                'api_token' => array(
                    'title' => __('API key', 'siusk24'),
                    'type' => 'text',
                    'class' => 'check-api-this',
                    'description' => __('API key can be provided by SIUSK24 client support team if you haven’t got any', 'siusk24'),

                ),
                'check_api' => array(
                    'title' => __('Check API', 'siusk24'),
                    'type' => 'check_api',
                    'description' => __('API check is possible only after saving the settings', 'siusk24'),
                ),
                /*
                'own_login' => array(
                    'title' => __('Own login', 'siusk24'),
                    'type' => 'checkbox',
                    'description' => __('Check if you have own login', 'siusk24'),
                    'default' => 'no',
                    'class' => 'has-depends'
                ),
                'own_login_user' => array(
                    'title' => __('Own login user', 'siusk24'),
                    'type' => 'text',
                    'custom_attributes' => array(
                        'data-depends' => 'woocommerce_' . Helper::get_prefix() . '_own_login'
                    ),
                ),
                'own_login_password' => array(
                    'title' => __('Own login password', 'siusk24'),
                    'type' => 'text',
                    'custom_attributes' => array(
                        'data-depends' => 'woocommerce_' . Helper::get_prefix() . '_own_login'
                    ),
                ),*/
                'hr_shop' => array(
                    'type' => 'hr',
                    'title_after' => __('Company information', 'siusk24'),
                ),
                'company' => array(
                    'title' => __('Company name', 'siusk24'),
                    'type' => 'text',
                ),
                'bank_account' => array(
                    'title' => __('Bank account', 'siusk24'),
                    'type' => 'text',
                ),
                'company_registration_code' => array(
                    'title' => __('Company registration code', 'siusk24'),
                    'type' => 'text',
                ),
                'company_vat' => array(
                    'title' => __('Company VAT', 'siusk24'),
                    'type' => 'text',
                ),
                'shop_city' => array(
                    'title' => __('City', 'siusk24'),
                    'type' => 'text',
                ),
                'shop_address' => array(
                    'title' => __('Street', 'siusk24'),
                    'type' => 'text',
                ),
                'shop_postcode' => array(
                    'title' => __('Postcode', 'siusk24'),
                    'type' => 'text',
                    'description' => sprintf(__('Example for Latvia: %1$s. Example for other countries: %2$s.', 'siusk24'), '<code>LV-0123</code>', '<code>01234</code>'),
                ),
                'shop_countrycode' => array(
                    'title' => __('Country', 'siusk24'),
                    'type' => 'select',
                    'class' => 'checkout-style pickup-point',
                    'options' => $countries_options,
                    'default' => 'LT',
                    'description' => __('To get a list of countries, first need to save API logins', 'siusk24')
                ),
                'shop_email' => array(
                    'title' => __('Email', 'siusk24'),
                    'type' => 'text',
                ),
                'shop_phone' => array(
                    'title' => __('Phone number', 'siusk24'),
                    'type' => 'text',
                ),
                'shop_name' => array(
                    'title' => __('Shop name', 'siusk24'),
                    'type' => 'text',
                ),
            );
            /*
            $fields['hr_methods'] = array(
                'type' => 'hr'
            );
            $fields['courier_enable'] = array(
                'title' => __('Enable courier', 'siusk24'),
                'type' => 'checkbox',
                'default' => 'no',
                'class' => 'has-depends'
            );
            $fields['courier_title'] = array(
                'title' => __('Courier title', 'siusk24'),
                'type' => 'text',
                'default' => 'Siusk24 courier',
                'custom_attributes' => array(
                    'data-depends' => 'woocommerce_' . Helper::get_prefix() . '_courier_enable'
                ),
            );
            $fields['terminal_enable'] = array(
                'title' => __('Enable terminal', 'siusk24'),
                'type' => 'checkbox',
                'default' => 'no',
                'class' => 'has-depends'
            );
            $fields['terminal_title'] = array(
                'title' => __('Terminal title', 'siusk24'),
                'type' => 'text',
                'default' => 'Siusk24 terminal',
                'custom_attributes' => array(
                    'data-depends' => 'woocommerce_' . Helper::get_prefix() . '_terminal_enable'
                ),
            );
            */

            $fields['hr_after_shop'] = array(
                'type' => 'hr',
                'title_after' => __('Shopping cart settings', 'siusk24'),
            );

            $fields['show_type'] = array(
                'title' => __('Group of services', 'siusk24'),
                'type' => 'select',
                'description' => __('Select applicable shopping cart group of services', 'siusk24'),
                'options' => array(
                    'default' => __('Select', 'siusk24'),
                    'cheapest' => __('Cheapest', 'siusk24'),
                    'expensive' => __('Most expensive', 'siusk24'),
                    'fastest' => __('Fastest', 'siusk24'),
                    'slowest' => __('Slowest', 'siusk24'),
                )
            );

            /*$fields['services_limit'] = array(
                'title' => __('Services limit', 'siusk24'),
                'type' => 'number',
                'description' => __('Select how many services will be visible in checkout. `-1` - for unlimited', 'siusk24'),
                'default' => '-1'
            );*/

            $fields['send_as_one'] = array(
                'label' => __('Shipment consolidation', 'siusk24'),
                'description' => __('Activate this option for orders consolidation to one shipment', 'siusk24'),
                'type' => 'fieldset_start',
                'default' => 'no',
            );

            $fields['hr_shopping_cart'] = array(
                'type' => 'hr',
                'title_after' => __('Service settings', 'siusk24'),
            );

            $services = $this->api->get_services();

            if (is_array($services)) {
                $service_groups = [];
                $service_groups['couriers'] = $services;
                foreach ($service_groups as $group_name => $group_services) {
                    $fields['hr_service_groups_' . $group_name] = array(
                        'type' => 'hr'
                    );
                    $fields[$group_name . '_enable'] = array(
                        'type' => 'fieldset_start',
                        'label' => $group_name
                    );

                    $fields[$group_name . '_title'] = array(
                        'title' => __('Service name', 'siusk24'),
                        'type' => 'text',
                        'default' => ucfirst($group_name),
                    );

                    $fields[$group_name . '_show_type'] = array(
                        'title' => __('Shopping cart service preferences', 'siusk24'),
                        'type' => 'select',
                        'description' => __('Select which service of this group show in the checkout', 'siusk24'),
                        'options' => array(
                            //'default' => __('Default', 'siusk24'),
                            'cheapest' => __('Cheapest', 'siusk24'),
                            'expensive' => __('Most expensive', 'siusk24'),
                            'fastest' => __('Fastest', 'siusk24'),
                            'slowest' => __('Slowest', 'siusk24'),
                        )
                    );

                    /*$fields[$group_name . '_sort_by'] = array(
                        'title' => __('Services order', 'siusk24'),
                        'type' => 'select',
                        'description' => __('Select how services will be sorted in the checkout', 'siusk24'),
                        'options' => array(
                            'default' => __('Default', 'siusk24'),
                            'cheapest' => __('Cheapest first', 'siusk24'),
                            'fastest' => __('Fastest first', 'siusk24'),
                        )
                    );

                    $fields[$group_name . '_show_type'] = array(
                        'title' => __('Services showing', 'siusk24'),
                        'type' => 'select',
                        'description' => __('Select how many this group services will be showing in the checkout', 'siusk24'),
                        'options' => array(
                            //'all' => __('All list', 'siusk24'),
                            'first' => __('First in the list', 'siusk24'),
                            'last' => __('Last in the list', 'siusk24'),
                        )
                    );*/

                    $fields[$group_name . '_price_type'] = array(
                        'title' => __('Price type', 'siusk24'),
                        'type' => 'select',
                        'description' => sprintf(__('Select price type for services. Enter the value in the "%s" field below.', 'siusk24'), __('Value', 'siusk24')),
                        'options' => array(
                            'fixed' => __('Fixed price', 'siusk24'),
                            'addition_percent' => __('Service price with added percentage', 'siusk24') . ' (%)',
                            'addition_eur' => __('Service price with added fixed value', 'siusk24') . ' (' . $currency . ')',
                        ),
                    );
                    $this->add_toggle($fields[$group_name . '_price_type'], array(
                        'group' => $group_name,
                        'field' => 'price_type',
                        'show' => 'additional',
                    ), true); //Toggle display by this field

                    $fields[$group_name . '_price_value'] = array(
                        'title' => __('Value', 'siusk24'),
                        'type' => 'number',
                        'custom_attributes' => array(
                            'step' => 0.01,
                            'min' => 0
                        ),
                        'default' => 5
                    );

                    $fields[$group_name . '_service_price'] = array(
                        'title' => __('Use service price', 'siusk24'),
                        'type' => 'select',
                        'description' => __('Which service price to use', 'siusk24'),
                        'options' => array(
                            'total_incl_vat' => __('With included tax', 'siusk24'),
                            'total_excl_vat' => __('Without tax', 'siusk24'),
                        ),
                        'default' => 'total_excl_vat',
                    );
                    $this->add_toggle($fields[$group_name . '_service_price'], array(
                        'group' => $group_name,
                        'field' => 'price_type',
                        'show' => 'additional',
                    ));

                    $fields[$group_name . '_free_shipping'] = array(
                        'title' => __('Free shipping cart amount', 'siusk24'),
                        'type' => 'number',
                        'description' => __('Leave blank to disable', 'siusk24'),
                        'custom_attributes' => array(
                            'step' => 0.01,
                            'min' => 0
                        ),
                        'default' => 0
                    );

                    $fields[$group_name . '_services_start'] = array(
                        'type' => 'services_list_start',
                        'title' => __('Couriers', 'siusk24'),
                    );

                    $fields[$group_name . '_services_select_start'] = array(
                        'type' => 'services_select_start',
                        'title' => __('Couriers', 'siusk24'),
                    );

                    foreach ($group_services as $service) {
                        $fields[$group_name . '_service_option_' . $service->service_code] = array(
                            'key' => $group_name . '_service_' . $service->service_code,
                            'type' => 'service_option',
                            'service' => $service
                        );
                        /*
                        $fields['service_' . $service->service_code] = array(
                            'title' => $service->name,
                            'type' => 'checkbox',
                            'description' => __(sprintf('Show %s service', $service->name), 'siusk24'),
                            'class' => 'has-depends'
                        );
                        if ($this->core->has_own_login($service)) {
                            $fields['service_' . $service->service_code . '_own_login_user'] = array(
                                'title' => __('Own login user', 'siusk24'),
                                'type' => 'text',
                                'custom_attributes' => array(
                                    'data-depends' => 'woocommerce_' . Helper::get_prefix() . '_service_' . $service->service_code
                                ),
                            );
                            $fields['service_' . $service->service_code . '_own_login_password'] = array(
                                'title' => __('Own login password', 'siusk24'),
                                'type' => 'text',
                                'custom_attributes' => array(
                                    'data-depends' => 'woocommerce_' . Helper::get_prefix() . '_service_' . $service->service_code
                                ),
                            );
                        }
                        */
                    }

                    $fields[$group_name . '_services_select_end'] = array(
                        'type' => 'services_select_end',
                        'title' => __('Couriers', 'siusk24'),
                    );

                    foreach ($group_services as $service) {
                        $fields[$group_name . '_service_' . $service->service_code] = array(
                            'type' => 'service_item',
                            'service' => $service
                        );
                    }

                    $fields[$group_name . '_services_end'] = array(
                        'type' => 'services_list_end',
                    );
                }
            }
            $fields['hr_measurements'] = array(
                'type' => 'hr',
                'title_after' => __('Shipment limitation settings', 'siusk24'),
            );
            $fields['dimensions'] = array(
                'title' => __('Default dimensions', 'siusk24'),
                'type' => 'dimensions',
                'description' => __('Maximum size of the entire basket for post machines. Leave all blank to turn off. <br>
                The preliminary basket size is calculated by trying to arrange all the products (their boxes) according to the dimensions specified in their settings.', 'siusk24'),
            );

            $fields['cart_weight'] = array(
                'title' => sprintf(__('Max cart weight (%s)', 'siusk24'), 'kg'),
                'type' => 'number_with_class',
                'custom_attributes' => array(
                    'step' => 0.001,
                    'min' => 0
                ),
                'class' => 'weight-field',
                'description' => __('Leave blank to disable.', 'siusk24'),
            );

            $fields['hr_settings'] = array(
                'type' => 'hr',
                'title_after' => __('Additional settings', 'siusk24'),
            );
            /* $fields['size_c'] = array(
              'title' => sprintf(__('Max size (%s) for courier', 'siusk24'),get_option('woocommerce_dimension_unit')),
              'type' => 'dimensions',
              'description' => __('Maximum product size for courier. Leave all empty to disable.', 'siusk24') . '<br/>' . __('If the length, width or height of at least one product exceeds the specified values, then it will not be possible to select the courier delivery method for the whole cart.', 'siusk24')
              ); */
            // $fields['restricted_categories'] = array(
            //     'title' => __('Disable for specific categories', 'siusk24'),
            //     'type' => 'multiselect',
            //     'class' => 'wc-enhanced-select',
            //     'description' => __('Select categories you want to disable the Siusk24 method', 'siusk24'),
            //     'options' => Helper::siusk24_get_categories(),
            //     'desc_tip' => true,
            //     'required' => false,
            //     'custom_attributes' => array(
            //         'data-placeholder' => __('Select Categories', 'siusk24'),
            //         'data-name' => 'restricted_categories'
            //     ),
            // );
            /*
            $fields['show_map'] = array(
                'title' => __('Map', 'siusk24'),
                'type' => 'checkbox',
                'description' => __('Show map of terminals.', 'siusk24'),
                'default' => 'yes',
                'class' => 'siusk24_terminal'
            );

            $fields['auto_select'] = array(
                'title' => __('Automatic terminal selection', 'siusk24'),
                'type' => 'checkbox',
                'description' => __('Automatically select terminal by postcode.', 'siusk24'),
                'default' => 'yes',
                'class' => 'siusk24_terminal'
            );*/
            // $fields['terminal_distance'] = array(
            //     'title' => __('Max terminal distance from receiver, km', 'siusk24'),
            //     'type' => 'number',
            //     'custom_attributes' => array(
            //         'step' => 1,
            //         'min' => 0
            //     ),
            //     'default' => 2
            // );

            // $fields['refresh_terminals'] = array(
            //     'title' => __('Update terminals database', 'siusk24'),
            //     'type' => 'sync_button',
            // );

            $fields['refresh_services'] = array(
                'title' => __('Update latest version of services', 'siusk24'),
                'type' => 'services_sync_button',
            );

            $fields['hr_end'] = array(
                'type' => 'hr'
            );

            $this->form_fields = $fields;
        }

        public function add_toggle(&$field_data, $params, $this_control = false)
        {
            $group_name = (isset($params['group'])) ? esc_attr($params['group']) : 'unknown';
            $field = (isset($params['field'])) ? esc_attr($params['field']) : 'unknown';
            $show = (isset($params['show'])) ? esc_attr($params['show']) : '';

            $field_data['class'] = (!empty($field_data['class'])) ? $field_data['class'] . ' ' : '';

            if ($this_control) {
                $field_data['class'] .= 'siusk24-toggle_controller';
                $field_data['custom_attributes']['data-group'] = $group_name;
                $field_data['custom_attributes']['data-field'] = $field;
                $field_data['custom_attributes']['data-show'] = $show;
            } else {
                $field_data['class'] .= 'siusk24-toggle_field siusk24-toggle-' . $group_name . '-' . $field . ' siusk24-toggle_show-' . $show;
            }
        }

        public function generate_dimensions_html($key, $value)
        {
            $product_fields = [
                'product_width' => array(
                    'title' => __('Width', 'siusk24'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => 0.01,
                        'min' => 0
                    ),
                ),
                'product_height' => array(
                    'title' => __('Height', 'siusk24'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => 0.01,
                        'min' => 0
                    ),
                ),
                'product_length' => array(
                    'title' => __('Length', 'siusk24'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => 0.01,
                        'min' => 0
                    ),
                ),
            ];

            $product_weight = [
                'key' => 'product_weight',
                'title' => __('Weight', 'siusk24'),
                'type' => 'number',
                'custom_attributes' => array(
                    'step' => 0.001,
                    'min' => 0
                ),
            ];

            $config = get_option('woocommerce_' . Helper::get_prefix() . '_dimensions', array());
            $title = ($value['title'] ?? "");
            $description = ($value['description'] ?? "");
            $html = '<tr class="dimensions-row"><th class="service-group-title">' . ucfirst($title) . '</th><td><div class="d-wrapper"><div>';

            foreach ($product_fields as $key => $field) {
                $html .= '<fieldset><label>' . $field['title'] . '</label>
                <input class="input-text" type="number" name="siusk24_' . $key . '" id="siusk24_' . $key . '" value="' . ($config[$key] ?? 0) . '" placeholder="" step="' . $field['custom_attributes']['step'] . '" min="' . $field['custom_attributes']['min'] . '">
                </fieldset>';
            }

            $html .= '</div>';
            $html .= '<fieldset class="weight-field"><label>' . $product_weight['title'] . '</label>
                <input class="input-text" type="number" name="siusk24_' . $product_weight['key'] . '" id="siusk24_' . $product_weight['key'] . '" value="' . ($config[$product_weight['key']] ?? 0) . '" placeholder="" step="' . $product_weight['custom_attributes']['step'] . '" min="' . $product_weight['custom_attributes']['min'] . '">
                </fieldset></div>';
            $html .= '<p class="description" style="width:100%;">' . $description . '</p>';
            $html .= '</td></tr>';
            return $html;
        }

        public function generate_number_with_class_html($key, $value)
        {
            $config = get_option('woocommerce_' . Helper::get_prefix() . '_dimensions', array());
            $title = ($value['title'] ?? "");
            $description = ($value['description'] ?? "");
            $class = ($value['class'] ?? "");

            $html = '<tr><th class="service-group-title">' . ucfirst($title) . '</th><td>';
            $html .= '<fieldset class="' . $class . '">
            <input class="input-text" type="number" name="siusk24_' . $key . '" id="siusk24_' . $key . '" value="' . ($config[$key] ?? 0) . '" placeholder="" step="' . $value['custom_attributes']['step'] . '" min="' . $value['custom_attributes']['min'] . '">
            </fieldset>';
            $html .= '<p class="description">' . $description . '</p>';
            $html .= '</td></tr>';
            return $html;
        }

        public function generate_hr_html($key, $value)
        {
            $class = (isset($value['class'])) ? $value['class'] : '';
            $html = '<tr valign="top"><td colspan="2"><hr class="' . $class . '"></td></tr>';
            if (isset($value['title_after'])) {
                $html .= '<tr valign="top" class="' . $key . '"><th colspan="2"><h3>' . $value['title_after'] . '</h3></th></tr>';
            }
            return $html;
        }

        public function generate_check_api_html( $key, $value )
        {
            $last_check_status = get_option(Helper::get_prefix() . '_api_check', '');
            $btn_class = ($last_check_status === '0') ? 'disable_all' : '';
            return $this->build_action_button_row(array(
                'row_title' => $value['title'] ?? '',
                'row_class' => $value['class'] ?? '',
                'btn_title' => __('Check API', 'siusk24'),
                'btn_class' => 'check-api-btn ' . $btn_class,
                'add_html' => '<p class="check-api-status"></p>',
                'description' => $value['description'] ?? '',
            ));
        }

        public function generate_sync_button_html( $key, $value )
        {
            return $this->build_action_button_row(array(
                'row_title' => $value['title'] ?? '',
                'row_class' => $value['class'] ?? '',
                'btn_title' => __('Update', 'siusk24'),
                'btn_class' => 'terminals-sync-btn',
                'description' => $value['description'] ?? '',
            ));
        }

        public function generate_services_sync_button_html( $key, $value )
        {
            $row_params = array(
                'row_title' => $value['title'] ?? '',
                'row_class' => $value['class'] ?? '',
                'btn_title' => __('Update services', 'siusk24'),
                'btn_class' => 'services-sync-btn',
                'description' => $value['description'] ?? '',
            );
            $last_update = get_option(Helper::get_prefix() . '_last_services_update', '');
            if ($last_update) {
                $row_params['add_html'] = '<p class="last-service-update"><span class="clock"></span>' . __(sprintf('Last update at %s', $last_update), 'siusk24') . '</p>';
            }

            return $this->build_action_button_row($row_params);
        }

        private function build_action_button_row( $params )
        {
            $row_class = $params['row_class'] ?? '';
            $row_title = $params['row_title'] ?? '';
            $btn_class = $params['btn_class'] ?? '';
            $btn_title = $params['btn_title'] ?? '';
            $description = $params['description'] ?? '';
            $additional_html = $params['add_html'] ?? '';

            $html = '<tr valign="top" class="' . $row_class . '"><th>' . $row_title . '</th><td colspan="">';
            $html .= '<button type="button" class="button-primary ' . $btn_class . '">' . $btn_title . '</button>';
            if ( ! empty($additional_html) ) {
                $html .= $additional_html;
            }
            if ( ! empty($description) ) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '</td></tr>';

            return $html;
        }

        public function generate_fieldset_start_html($key, $value)
        {
            $service_key = $this->get_field_key($key);
            $title = ($value['label'] ?? "");
            $description = ($value['description'] ?? "");
            $html = '<tr class="fieldset_start" valign="top"><th class="service-group-title">' . ucfirst($title) . '</th><td>';
            $html .= '<label class="switch" for="' . $service_key . '">';
            $html .= '<input type="checkbox" name="' . $service_key . '" id="' . $service_key . '" style="" value="yes" ' . ($this->get_option($key) == 'yes' ? 'checked' : '') . '>';
            $html .= '<span class="slider round"><span class="on">' . __('Enabled', 'siusk24') . '</span><span class="off">' . __('Disabled', 'siusk24') . '</span></span></label>';
            $html .= '<p class="description">' . $description . '</p>';
            $html .= '</td></tr>';
            return $html;
        }

        public function generate_empty_html($key, $value)
        {
            $class = (isset($value['class'])) ? $value['class'] : '';
            $html = '<tr valign="top"><td colspan="2" class="' . $class . '"></td></tr>';
            return $html;
        }

        public function generate_services_list_start_html($key, $value)
        {
            $title = $value['title'] ?? '';
            $html = '<tr valign="top"><th class="titledesc">' . $title . '</th><td class = "services-container">';
            return $html;
        }

        public function generate_services_list_end_html($key, $value)
        {
            $html = '</ul></td></tr>';
            return $html;
        }

        public function generate_services_select_start_html($key, $value)
        {
            $title = $value['title'] ?? '';
            $html = '<select class="multiple-checkboxes" multiple multiselect-search="true">';
            return $html;
        }

        public function generate_services_select_end_html($key, $value)
        {
            $html = '</select><p class="p-active">' . __('Active', 'siusk24') . '</p><p class="no-active">' . __('No active couriers added. To add courier, click on selector above.', 'siusk24') . '</p><ul>';
            return $html;
        }

        public function generate_service_item_html($key, $value)
        {
            $service = (isset($value['service'])) ? $value['service'] : [];
            $img = $service->image ?? '';
            $service_descriotion = $service->description ?? '';
            $service_key = $this->get_field_key($key);
            $html = '<li><div>';
            $html .= '<img src="' . $img . '"/>';
            $html .= '<div class="label-wrapper"><label">' . $service->name . '<p><small>' . $service_descriotion. '</small></p></div></label>';
            $html .= '<div class="tooltip">';
            $html .= '<input class="service-checkbox" type="checkbox" name="' . $service_key . '" id="' . $service_key . '" style="" value="yes" ' . ($this->get_option($key) == 'yes' ? 'checked' : '') . '>';
            $html .= '<span class="tooltiptext">' . __('Deactivate courier', 'siusk24') . '</span>';
            $html .= '</div>';
            $html .= '</div></li>';
            return $html;
        }

        public function generate_service_option_html($key, $value)
        {
            $service_key = $value['key'] ?? '';
            $service = (isset($value['service'])) ? $value['service'] : [];
            $service_id = $this->get_field_key($service_key);
            $html = '<option data-id="' . $service_id . '" ' . ($this->get_option($service_key) == 'yes' ? 'selected="selected"' : '') . ' data-icon="' . $service->image . '">' . $service->name . '</option>';
            return $html;
        }

        public function generate_logo_html($key, $value)
        {
            $html = '<tr><th class="titledesc"><img src = "' . plugin_dir_url(__DIR__) . 'assets/images/s24logo.png" style="width: 200px;"/></th></tr>';
            return $html;
        }

        private function has_restricted_cat()
        {
            // global $woocommerce;
            // $config = $this->core->get_config();
            // foreach ($woocommerce->cart->get_cart() as $cart_item) {
            //     $cats = get_the_terms($cart_item['product_id'], 'product_cat');
            //     foreach ($cats as $cat) {
            //         $cart_categories_ids[] = $cat->term_id;
            //         if ($cat->parent != 0) {
            //             $cart_categories_ids[] = $cat->parent;
            //         }
            //     }
            // }

            // $cart_categories_ids = array_unique($cart_categories_ids);

            // $restricted_categories = $config['restricted_categories'];
            // if (!is_array($restricted_categories)) {
            //     $restricted_categories = array($restricted_categories);
            // }

            // foreach ($cart_categories_ids as $cart_product_categories_id) {
            //     if (in_array($cart_product_categories_id, $restricted_categories)) {
            //         return true;
            //     }
            // }
            return false;
        }

        public function calculate_shipping($package = array())
        {
            try {
                if ($this->has_restricted_cat()) {
                    return;
                }
                $cart_weight = WC()->cart->cart_contents_weight;
                $config = $this->core->get_config();
                $dimensions = $this->core->get_package_dimensions();

                $offers = $this->core->filter_enabled_offers($this->core->get_offers($package));
                $this->core->set_offers_price($offers);
                $this->core->sort_offers($offers);
                $this->core->show_offers($offers);

                $current_service = 0;

                if(!$dimensions['cart_weight'] || (float)$dimensions['cart_weight'] > (float)$cart_weight)
                {
                    foreach ($offers as $offer) {
                        if ($this->core->is_offer_terminal($offer)) {
                            continue;
                        }
                        $group = $offer->group;
                        $courier_title = $config[$group . '_title'] ?? 'Courier';
                        $free_shipping = $this->core->is_free_shipping($offer->group);
                        $rate = array(
                            'id' => $this->id . '_service_' . $offer->service_code,
                            'label' => $courier_title . ' (' . $offer->delivery_time . ')',
                            'cost' => $free_shipping ? 0 : $offer->price
                        );
                        $this->add_rate($rate);
                        $current_service++;
                    }

                    foreach ($offers as $offer) {
                        if (!$this->core->is_offer_terminal($offer)) {
                            continue;
                        }
                        $terminal_title = $config['terminals_title'] ?? 'Parcel terminal';
                        $free_shipping = $this->core->is_free_shipping($offer->group);
                        $rate = array(
                            'id' => $this->id . '_terminal_' . $this->core->get_offer_terminal_type($offer) . '_service_' . $offer->service_code,
                            'label' => $terminal_title . ' (' . $offer->delivery_time . ')',
                            'cost' => $free_shipping ? 0 : $offer->price
                        );
                        $this->add_rate($rate);
                        break;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        public function process_admin_options()
        {
            $dimension_options = get_option('woocommerce_' . Helper::get_prefix() . '_dimensions', array());

            if (isset($_POST[Helper::get_prefix() . '_product_width'])) {
                $dimension_options['product_width'] = $_POST[Helper::get_prefix() . '_product_width'];
            }
            if (isset($_POST[Helper::get_prefix() . '_product_height'])) {
                $dimension_options['product_height'] = $_POST[Helper::get_prefix() . '_product_height'];
            }
            if (isset($_POST[Helper::get_prefix() . '_product_length'])) {
                $dimension_options['product_length'] = $_POST[Helper::get_prefix() . '_product_length'];
            }
            if (isset($_POST[Helper::get_prefix() . '_product_weight'])) {
                $dimension_options['product_weight'] = $_POST[Helper::get_prefix() . '_product_weight'];
            }
            if (isset($_POST[Helper::get_prefix() . '_cart_weight'])) {
                $dimension_options['cart_weight'] = $_POST[Helper::get_prefix() . '_cart_weight'];
            }
            update_option('woocommerce_' . Helper::get_prefix() . '_dimensions', $dimension_options);

            update_option(Helper::get_prefix() . '_services_updated', 0);

            return parent::process_admin_options();
        }
    }
}
