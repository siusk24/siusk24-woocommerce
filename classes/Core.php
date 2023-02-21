<?php

namespace Siusk24Woo;

use Siusk24Woo\Category;
use Siusk24Woo\Product;
use Siusk24Woo\Api;
use Siusk24Woo\Helper;
use OmnivaApi\Sender;
use OmnivaApi\Receiver;
use OmnivaApi\Parcel;
use OmnivaApi\Item;
use OmnivaApi\Order as ApiOrder;
use setasign\Fpdi\Fpdi;

class Core
{
    private $api;
    private $config;
    private $package_dimensions;

    public function __construct()
    {
        $this->api = new Api($this->get_config());
    }

    public function show_admin_notice($msg_text, $msg_type = 'info', $dismissible = true)
    {
        add_action('admin_notices', function () use ($msg_text, $msg_type, $dismissible) {
            $additional_classes = '';
            if ($dismissible) {
                $additional_classes = ' is-dismissible';
            }

            echo '<div class="notice notice-' . $msg_type . $additional_classes . '"><p>' . $msg_text . '</p></div>';
        });
    }

    public function get_api()
    {
        return $this->api;
    }

    public function get_config()
    {
        if (empty($this->config)) {
            $this->config = get_option('woocommerce_' . Helper::get_prefix() . '_settings', array());
        }
        return $this->config;
    }

    public function get_package_dimensions()
    {
        if (empty($this->package_dimensions)) {
            $this->package_dimensions = get_option('woocommerce_' . Helper::get_prefix() . '_dimensions', array());
        }
        return $this->package_dimensions;
    }

    public function get_country_id($country_code)
    {
        foreach ($this->api->get_countries() as $id => $country) {
            if ($country->code == $country_code) {
                return $country->id;
            }
        }
    }

    public function get_sender()
    {
        $config = $this->get_config();
        //$send_off = $config['send_off'];
        $send_off = 'courier';

        $sender = new Sender($send_off);
        $sender->setCompanyName($config['company']);
        $sender->setContactName($config['company']);
        $sender->setStreetName($config['shop_address']);
        $sender->setZipcode($config['shop_postcode']);
        $sender->setCity($config['shop_city']);
        $sender->setCountryId($this->get_country_id($config['shop_countrycode']));
        $sender->setPhoneNumber($config['shop_phone']);
        return $sender;
    }

    public function get_receiver($package)
    {
        $config = $this->get_config();
        //$send_off = $config['send_off'];
        $send_off = 'courier';

        if (is_array($package)) {
            //create from array at checkout
            $user = false;
            if ($package['user']['ID']) {
                $user = get_userdata($package['user']['ID']);
            }
            $receiver = new Receiver($send_off);
            if ($user) {
                $receiver->setContactName($user->first_name . ' ' . $user->last_name);
            } else {
                $customer = WC()->session->get('customer');
                if ($customer) {
                    $receiver->setContactName($customer['first_name'] . ' ' . $customer['last_name']);
                } else {
                    $receiver->setContactName("");
                }
            }
            $country_code = $package['destination']['country'];
            $receiver->setStreetName($package['destination']['address']);
            $receiver->setZipcode(Helper::clear_postcode($package['destination']['postcode'], $country_code));
            $receiver->setCity($package['destination']['city']);
            $receiver->setCountryId($this->get_country_id($country_code));
            $receiver->setStateCode($package['destination']['state'] ?? null);
            $receiver->setPhoneNumber((string) WC()->checkout->get_value('shipping_phone') ?? WC()->checkout->get_value('billing_phone'));
            return $receiver;
        } elseif (is_object($package)) {
            $country_code = $package->get_shipping_country();
            //create from object on order
            $receiver = new Receiver($send_off);
            $receiver->setCompanyName($package->get_shipping_company());
            $receiver->setContactName($package->get_shipping_first_name() . ' ' . $package->get_shipping_last_name());
            $receiver->setStreetName($package->get_shipping_address_1());
            $receiver->setZipcode(Helper::clear_postcode($package->get_shipping_postcode(), $country_code));
            $receiver->setCity($package->get_shipping_city());
            $receiver->setCountryId($this->get_country_id($country_code));
            $receiver->setStateCode($package->get_shipping_state());
            $receiver->setPhoneNumber((string)$package->get_billing_phone());
            return $receiver;
        }

        return false;
    }

    //TO DO calculate dimensions, weight
    public function get_parcels($order = false)
    {
        global $woocommerce;
        $config = $this->get_config();
        $package_dimensions = $this->get_package_dimensions();
        $send_as_one = $config['send_as_one'] ?? 'no';

        $product = new Product();
        $product->set_config($config);
        $product->set_package_dimensions($package_dimensions);
        $parcels = [];
        $parcel_objects = [];
        if ($order) {
            $items = $order->get_items();
        } else {
            $items = $woocommerce->cart->get_cart();
        }
        foreach ($items as $id => $data) {
            $parcel = new Parcel();
            if ($order) {
                $_product = $data->get_product();
                $parcel->setAmount($data->get_quantity());
            } else {
                $_product = $data['data'];
                $parcel->setAmount($data['quantity']);
            }
            if ($product->get_virtual($_product)) {
                continue;
            }
            //Get weight and dimensions. Set default if empty
            $product_weight = (!empty($product->get_weight($_product))) ? $product->get_weight($_product) : 1;
            $product_height = (!empty($product->get_height($_product))) ? $product->get_height($_product) : 1;
            $product_width = (!empty($product->get_width($_product))) ? $product->get_width($_product) : 1;
            $product_length = (!empty($product->get_length($_product))) ? $product->get_length($_product) : 1;
            //Change weight and dimensions unit to kg and cm
            $product_weight = $this->change_weight_unit($product_weight, get_option('woocommerce_weight_unit'), 'kg');
            $product_height = $this->change_dimension_unit($product_height, get_option('woocommerce_dimension_unit'), 'cm');
            $product_width = $this->change_dimension_unit($product_width, get_option('woocommerce_dimension_unit'), 'cm');
            $product_length = $this->change_dimension_unit($product_length, get_option('woocommerce_dimension_unit'), 'cm');
            //Add weight and dimensions to parcel
            $parcel->setUnitWeight($product_weight);
            $parcel->setHeight($product_height);
            $parcel->setWidth($product_width);
            $parcel->setLength($product_length);
            $parcel_objects[] = $parcel;
            $parcels[] = $parcel->generateParcel();
        }

        if ($send_as_one == 'yes') {
            $main_parcel = new Parcel();
            $total_weight = 0;
            $total_volume = 0;
            $total_amount = 1;
            foreach ($parcel_objects as $parcel) {
                try {
                    $parcel_data = $parcel->__toArray();
                    $total_weight += $parcel_data['weight'] * $parcel_data['amount'];
                    $total_volume += $parcel_data['x'] * $parcel_data['y'] * $parcel_data['z'] * $parcel_data['amount'];
                } catch (\Throwable $e) {
                }
            }
            $cube_size = ceil($total_volume ** (1 / 3));
            $main_parcel->setAmount(1);
            $main_parcel->setUnitWeight($total_weight);
            $main_parcel->setHeight($cube_size);
            $main_parcel->setWidth($cube_size);
            $main_parcel->setLength($cube_size);
            return [$main_parcel->generateParcel()];
        }
        return $parcels;
    }

    public function get_items($order)
    {
        $config = $this->get_config();
        $items = [];
        $order_items = $order->get_items();
        foreach ($order_items as $id => $data) {
            $item = new Item();
            $item->setItemAmount($data->get_quantity());
            $item->setDescription(substr($data->get_name(), 0, 39));
            $item->setItemPrice($data->get_total() / $data->get_quantity());
            $item->setCountryId($this->get_country_id($config['shop_countrycode']));
            $items[] = $item->generateItem();
        }
        return $items;
    }

    public function get_offers($package)
    {
        $parcels = $this->get_parcels();
        return $this->api->get_offers($this->get_sender(), $this->get_receiver($package), $parcels);
    }

    private function selected_services()
    {
        $selected = [];
        foreach ($this->config as $key => $value) {
            if ($value == "yes" && stripos($key, '_service_') !== false) {
                $data = explode('_', $key);
                if (count($data) == 3) {
                    $group = $data[0];
                    $service_code = $data[2];
                    $group_enabled = isset($this->config[$group . '_enable']) && $this->config[$group . '_enable'] == 'yes' ? true : false;
                    if ($group_enabled) {
                        $selected[$service_code] = $group;
                    }
                }
            }
        }
        return $selected;
    }

    public function filter_enabled_offers($offers)
    {
        $config = $this->get_config();
        $own_login = isset($config['own_login']) && $config['own_login'] == 'yes' ? true : false;
        $filtered_offers = [];
        $selected_services = $this->selected_services();

        foreach ($offers as $offer) {
            if (isset($selected_services[$offer->service_code])) {
                //check if has own login and info is entered in settings
                if (!$this->is_own_login_ok($offer)) {
                    continue;
                }
                $offer->group = $selected_services[$offer->service_code];
                $filtered_offers[] = $offer;
            }
        }
        return $filtered_offers;
    }

    public function set_offers_price(&$offers)
    {

        foreach ($offers as $offer) {
            $price = $this->get_offer_price($offer);
            $offer->org_price = $price;

            $type = $this->config[$offer->group . '_price_type'];
            $value = $this->config[$offer->group . '_price_value'];
            $offer->price = $this->calculate_price($price, $type, $value);
        }
    }

    private function get_offer_price($offer)
    {
        $get_price = $this->config[$offer->group . '_service_price'] ?? 'total_excl_vat';

        switch ($get_price) {
            case 'total_excl_vat':
                $price = $offer->total_price_excl_vat;
                break;
            case 'total_incl_vat':
                $price = $offer->total_price_with_vat;
                break;
            default:
                $price = $offer->price;
        }

        return $price;
    }

    public function set_offers_name(&$offers)
    {
        $name = $this->config['courier_title'];

        foreach ($offers as $offer) {
            $offer->org_name = $offer->name;
            $offer->name = $name;
        }
    }

    public function sort_offers(&$offers)
    {
        $edited_offers = array();

        $main_show_type = $this->config['show_type'] ?? 'default';
        if ($main_show_type == 'default') {
            $grouped = $this->group_offers($offers);

            foreach ($grouped as $group => $grouped_offers) {
                $show_type = $this->config[$group . '_show_type'] ?? 'cheapest';
                switch ($show_type) {
                    case 'cheapest':
                        usort($grouped[$group], function ($v, $k) {
                            return $k->price <= $v->price;
                        });
                        break;
                    case 'expensive':
                        usort($grouped[$group], function ($v, $k) {
                            return $k->price >= $v->price;
                        });
                        break;
                    case 'fastest':
                        usort($grouped[$group], function ($v, $k) {
                            return $this->get_offer_delivery($k) <= $this->get_offer_delivery($v);
                        });
                        break;
                    case 'slowest':
                        usort($grouped[$group], function ($v, $k) {
                            return $this->get_offer_delivery($k) >= $this->get_offer_delivery($v);
                        });
                        break;
                }

                foreach ($grouped[$group] as $offer) {
                    $edited_offers[] = $offer;
                }
            }
            $offers = $edited_offers;
        } else {
            switch ($main_show_type) {
                case 'cheapest':
                    usort($offers, function ($v, $k) {
                        return $k->price <= $v->price;
                    });
                    break;
                case 'expensive':
                    usort($offers, function ($v, $k) {
                        return $k->price >= $v->price;
                    });
                    break;
                case 'fastest':
                    usort($offers, function ($v, $k) {
                        return $this->get_offer_delivery($k) <= $this->get_offer_delivery($v);
                    });
                    break;
                case 'slowest':
                    usort($offers, function ($v, $k) {
                        return $this->get_offer_delivery($k) >= $this->get_offer_delivery($v);
                    });
                    break;
            }
        }
    }

    private function group_offers($offers)
    {
        $grouped = array();
        foreach ($offers as $offer) {
            if (!isset($grouped[$offer->group])) {
                $grouped[$offer->group] = [];
            }
            $grouped[$offer->group][] = $offer;
        }

        return $grouped;
    }

    public function show_offers(&$offers)
    {
        $edited_offers = array();
        $grouped = $this->group_offers($offers);

        foreach ($grouped as $group => $grouped_offers) {
            $grouped[$group] = array_slice($grouped_offers, 0, 1);

            foreach ($grouped[$group] as $offer) {
                $edited_offers[] = $offer;
            }
        }

        $offers = $edited_offers;
    }

    public function get_offer_terminal_type($offer)
    {
        $services = $this->api->get_services();
        foreach ($services as $service) {
            if ($offer->service_code == $service->service_code) {
                if (isset($service->parcel_terminal_type)) {
                    return $service->parcel_terminal_type;
                }
                return '';
            }
        }
        return '';
    }

    public function get_identifier_form_method($title)
    {
        $title = str_ireplace('siusk24_terminal_', '', $title);
        $data = explode('_service_', $title);
        return $data[0] ?? '';
    }

    public function get_service_form_method($title)
    {
        $title = str_ireplace('siusk24_terminal_', '', $title);
        $data = explode('_service_', $title);
        return $data[1] ?? '';
    }

    public function get_offer_delivery($offer)
    {
        $re = '/^[^\d]*(\d+)/';
        preg_match($re, $offer->delivery_time, $matches, PREG_OFFSET_CAPTURE, 0);
        return $matches[0] ?? 1;
    }

    public function is_offer_terminal($offer)
    {
        $services = $this->api->get_services();
        foreach ($services as $service) {
            if ($offer->service_code == $service->service_code) {
                if ($service->delivery_to_address == false) {
                    return true;
                }
                return false;
            }
        }
        return false;
    }

    private function calculate_price($price, $type, $value)
    {
        if (!$value) {
            return $price;
        }
        if ($type == "fixed") {
            $price = $value;
        } else if ($type == "addition_percent") {
            $price += round($price * $value / 100, 2);
        } else if ($type == "addition_eur") {
            $price += $value;
        }
        return $price;
    }

    public function is_free_shipping($group_name)
    {
        $cart_total = WC()->cart->get_cart_contents_total();

        $free_ship = $this->config[$group_name . '_free_shipping'] ?? 0;
        if ($free_ship > 0 && $free_ship <= $cart_total) {
            return true;
        }
        return false;
    }

    public function get_additional_services($service_code)
    {
        $services = $this->api->get_services();
        $allowed_services = [];
        foreach ($services as $service) {
            if ($service->service_code == $service_code) {
                if (isset($service->additional_services)) {
                    foreach ($service->additional_services as $add_service => $status) {
                        if ($status == true) {
                            $allowed_services[] = $add_service;
                        }
                    }
                }
                break;
            }
        }
        return $allowed_services;
    }

    public function has_own_login($service)
    {
        if (isset($service->additional_services)) {
            foreach ($service->additional_services as $add_service => $status) {
                if ($add_service == 'own_login') {
                    return $status;
                }
            }
        }
        return false;
    }

    public function is_own_login_ok($offer)
    {
        $services = $this->api->get_services();
        foreach ($services as $service) {
            if ($service->service_code == $offer->service_code) {
                if (isset($service->additional_services)) {
                    foreach ($service->additional_services as $add_service => $status) {
                        if ($add_service == 'own_login' && $status == true && (empty('service_' . $service->service_code . '_own_login_user') || empty('service_' . $service->service_code . '_own_login_password'))) {
                            return false;
                        } else {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function change_weight_unit($value, $current_unit, $new_unit)
    {
        $to_kg = array(
            'mg' => 0.000001,
            'g' => 0.001,
            'kg' => 1,
            't' => 1000,
            'gr' => 0.0000648,
            'k' => 0.0002,
            'oz' => 0.02835,
            'lb' => 0.45359,
            'cnt' => 100,
        );

        if (isset($to_kg[$current_unit]) && isset($to_kg[$new_unit])) {
            $current_kg = $value * $to_kg[$current_unit]; //Change value to kg
            return $current_kg / $to_kg[$new_unit]; //Change kg value to new unit
        }

        return $value;
    }

    public function change_dimension_unit($value, $current_unit, $new_unit)
    {
        //TODO: make dimension change
        return $value;
    }

    public function get_service_info($service_code, $services = false)
    {
        if (!$services) {
            $services = $this->api->get_services();
        }

        foreach ($services as $service) {
            if ($service->service_code == $service_code) {
                return $service;
            }
        }

        return false;
    }

    public function get_wc_order($wc_order_id)
    {
        if (!$wc_order_id) {
            throw new \Exception(__('Order ID not received', 'siusk24'));
        }

        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order) {
            throw new \Exception(__('Order not found', 'siusk24'));
        }

        return $wc_order;
    }

    public function register_order($params)
    {
        $wc_order_id = $params['wc_order_id'] ?? 0;
        $allow_regenarate = $params['regenerate'] ?? false;
        $services = $params['services'] ?? [];
        $terminal = $params['terminal'] ?? 0;
        $cod_amount = $params['cod_amount'] ?? false;
        $eori_number = $params['eori_number'] ?? false;
        $hs_code = $params['hs_code'] ?? false;

        try {
            $wc_order = $this->get_wc_order($wc_order_id);

            if (!$allow_regenarate && !empty(get_post_meta($wc_order_id, '_siusk24_shipment_id', true))) {
                return ['status' => 'error', 'msg' => __('The shipment is already registered', 'siusk24')];
            }

            if (empty($cod_amount)) {
                $cod_amount = get_post_meta($wc_order_id, '_order_total', true);
            }
            $service_code = get_post_meta($wc_order_id, '_siusk24_service', true);

            $sender = $this->get_sender();
            $receiver = $this->get_receiver($wc_order);

            if ($terminal) {
                $terminal_obj = Terminal::getById($terminal);
                if (!$terminal_obj || !$terminal_obj->zip) {
                    return ['status' => 'error', 'msg' => __('Terminal not found', 'siusk24')];
                }
                $receiver->setShippingType('terminal');
                $receiver->setZipcode(Helper::clear_postcode($terminal_obj->zip, $terminal_obj->country_code));
            }
            if ($eori_number) {
                $receiver->setEori($eori_number);
            }
            if ($hs_code) {
                $receiver->setHsCode($hs_code);
            }

            $api_order = new ApiOrder();
            $api_order->setSender($sender);
            $api_order->setReceiver($receiver);
            $api_order->setServiceCode($service_code);
            $api_order->setParcels($this->get_parcels($wc_order));
            $api_order->setItems($this->get_items($wc_order));
            $api_order->setReference($wc_order->get_order_number());
            $api_order->setAdditionalServices($services, $cod_amount);
            $response = $this->api->create_order($api_order);

            update_post_meta($wc_order_id, '_siusk24_shipment_id', $response->shipment_id);
            update_post_meta($wc_order_id, '_siusk24_cart_id', $response->cart_id);
            if (!empty($response->insurance)) {
                update_post_meta($wc_order_id, '_siusk24_insurance', esc_sql($response->insurance));
            }

            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    public function print_label($shipment_id)
    {
        try {
            $response = $this->api->get_label($shipment_id);
            $pdf = base64_decode($response->base64pdf);
            $this->generate_pdf($shipment_id, $pdf);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }

    public function print_labels($shipments_ids)
    {
        try {
            $temp_dir = $this->get_temp_dir();
            $labels = array();
            foreach ($shipments_ids as $order_id => $shipment_id) {
                try {
                    $response = $this->api->get_label($shipment_id);
                } catch (\Exception $e) {
                    continue;
                }

                if (!empty($response->tracking_numbers)) {
                    update_post_meta($order_id, '_siusk24_tracking_numbers', $response->tracking_numbers);
                }

                $pdf_dir = $temp_dir . '/' . $shipment_id . '.pdf';
                $pdf = fopen($pdf_dir, 'w');
                fwrite($pdf, base64_decode($response->base64pdf));
                fclose($pdf);
                $labels[] = $pdf_dir;
            }

            if (empty($labels)) {
                return ['status' => 'error', 'msg' => __('Failed to get labels', 'siusk24')];
            }
            $merged_file = base64_decode($this->merge_pdfs($labels));

            foreach ($labels as $label) {
                unlink($label);
            }

            $this->generate_pdf('Omniva_global_labels_' . current_time('Ymd_His') . '.pdf', $merged_file);
        } catch (\Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
        exit;
    }

    private function generate_pdf($file_name, $file_content)
    {
        header('Content-type:application/pdf');
        header('Content-disposition: inline; filename="' . $file_name . '"');
        header('content-Transfer-Encoding:binary');
        header('Accept-Ranges:bytes');
        echo $file_content;
        exit;
    }

    private function merge_pdfs($pdf_files)
    {
        $pdf = new Fpdi();

        foreach ($pdf_files as $file) {
            $page_count = $pdf->setSourceFile($file);
            for ($page_no = 1; $page_no <= $page_count; $page_no++) {
                $template_id = $pdf->importPage($page_no);
                $pdf->AddPage('P');
                $pdf->useTemplate($template_id, ['adjustPageSize' => true]);
            }
        }

        return base64_encode($pdf->Output('S'));
    }

    public function remove_order($wc_order_id)
    {
        try {
            $wc_order = $this->get_wc_order($wc_order_id);

            $shipment_id = get_post_meta($wc_order_id, '_siusk24_shipment_id', true);
            $this->api->cancel_order($shipment_id);
            delete_post_meta($wc_order_id, '_siusk24_shipment_id');
            delete_post_meta($wc_order_id, '_siusk24_cart_id');
            delete_post_meta($wc_order_id, '_siusk24_tracking_numbers');
            delete_post_meta($wc_order_id, '_siusk24_insurance');

            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    public function generate_manifests($carts_ids)
    {
        try {
            $temp_dir = $this->get_temp_dir();
            $manifests = array();
            foreach ($carts_ids as $cart_id) {
                $response = $this->api->generate_manifest($cart_id);
                if (empty($response->manifest)) {
                    continue;
                }
                $this->update_order_meta_by_cart_id($cart_id);
                $pdf_dir = $temp_dir . '/' . $cart_id . '.pdf';
                $pdf = fopen($pdf_dir, 'w');
                fwrite($pdf, base64_decode($response->manifest));
                fclose($pdf);
                $manifests[] = $pdf_dir;
            }

            if (empty($manifests)) {
                throw new \Exception(__('Failed to get manifests', 'siusk24'));
            }

            $merged_file = base64_decode($this->merge_pdfs($manifests));

            foreach ($manifests as $manifest) {
                unlink($manifest);
            }

            $this->generate_pdf('Omniva_global_manifests_' . current_time('Ymd_His') . '.pdf', $merged_file);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function generate_latest_manifest()
    {
        try {
            $response = $this->api->generate_latest_manifest();
            if (empty($response->manifest)) {
                throw new \Exception(__('Failed to get manifest', 'siusk24'));
            }
            $this->update_order_meta_by_cart_id($response->cart_id);
            $pdf = base64_decode($response->manifest);
            $this->generate_pdf($response->cart_id, $pdf);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        exit;
    }

    private function update_order_meta_by_cart_id($cart_id)
    {
        $args = array(
            'limit' => -1,
            'return' => 'ids',
            'meta_key' => '_siusk24_cart_id',
            'meta_value' => $cart_id,
            'meta_compare' => '='
        );

        $results = wc_get_orders($args);
        if (empty($results)) {
            return false;;
        }

        foreach ($results as $order_id) {
            $date = get_post_meta($order_id, '_siusk24_manifest_date', true);
            if (!$date) {
                update_post_meta($order_id, '_siusk24_manifest_date', date('Y-m-d H:i:s'));
            }
        }

        return true;
    }

    public function get_temp_dir()
    {
        $temp_dir = SIUSK24_PLUGIN_DIR . 'var/temp';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }

        return $temp_dir;
    }
}
