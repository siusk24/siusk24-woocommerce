<?php
namespace Siusk24Woo;

if (!defined('ABSPATH')) {
  exit;
}

use Siusk24Woo\Helper;
use Siusk24Woo\Terminal;
use Mijora\S24IntApiLib\API as Siusk24_api;

class Api {
    
    private $siusk24_api;
    private $prefix;
    private $config = [];
    private $show_admin_notice = false;
    
    public function __construct($config = array()) {
        $this->config = $config;
        $this->siusk24_api = new Siusk24_api(Helper::get_config_value('api_token', $config, "no_token", true), false, false);
        $this->siusk24_api->setUrl(Helper::get_config_value('api_url', $config) . "/api/v1/");
        $this->prefix = Helper::get_prefix() . '_api';
    }

    public function show_notice_on_error($bool = true) {
        $this->show_admin_notice = ($bool) ? true : false;
        return $this;
    }

    private function show_admin_notice($msg_text, $msg_type = 'info', $dismissible = true) {
        if ( ! $this->show_admin_notice ) return;
        Helper::show_admin_notice($msg_text, $msg_type, $dismissible);
        $this->show_admin_notice = false;
    }
    
    public function get_services($force = false){
        $token = Helper::get_config_value('api_token', $this->config, false);
        if (!$token) {
            return [];
        }
        try {
            $cache_name = $this->prefix . '_services';
            $data = get_transient($cache_name);
            if ($data === false || $force) {
                
                $data = $this->siusk24_api->listAllServices();
                
                set_transient($cache_name, $data, 1800);
                $last_count = get_option(Helper::get_prefix() . '_total_services', 0);
                if ($last_count != count($data) && $last_count > 0){
                    update_option(Helper::get_prefix() . '_services_updated', 1);
                }
                update_option(Helper::get_prefix() . '_total_services', count($data));
                update_option(Helper::get_prefix() . '_last_services_update', date("Y-m-d H:i"));
            }
        } catch (\Exception $e) {
            //echo $e->getMessage();
            $data = [];
        }
        return $data;
    }
    
    public function get_countries() {
        $token = Helper::get_config_value('api_token', $this->config, false);
        
        if ( ! $token ) {
            return [];
        }

        try {
            $cache_name = $this->prefix . '_countries';
            $data = get_transient($cache_name);
            if ($data === false) {
                $data = $this->siusk24_api->listAllCountries();
                set_transient($cache_name, $data, 3600 * 24 * 3);
            }
        } catch (\Exception $e) {
            $data = [];
            $this->show_admin_notice(__('Failed to get countries list', 'siusk24'), 'error', false);
        }

        return $data;
    }
    
    public function update_terminals(){
        try {
            $this->siusk24_api->setTimeout(30);
            $data = $this->siusk24_api->getTerminals('ALL');
        } catch (\Exception $e) {
            $data = [];
        }
            
        if (isset($data->parcel_machines) && is_array($data->parcel_machines)) {
            Terminal::delete();
            foreach ($data->parcel_machines as $terminal) {
                Terminal::insert($terminal);
            }
        }
    }
    
    public function get_terminals($country = null, $identifier = null){
        if (!Terminal::count()) {
            $this->update_terminals();
        }
        
        return Terminal::get($country, $identifier);
    }
    
    public function get_offers($sender, $receiver, $parcels){
        $hash = md5(json_encode(array(
            'sender' => $sender->generateSenderOffers(),
            'receiver' => $receiver->generateReceiverOffers(),
            'parcels' => $parcels
        )));
        $data = get_transient($hash);
        if ($data === false) {
            $data = $this->siusk24_api->getOffers($sender, $receiver, $parcels);
            set_transient($hash, $data, 600);
        }
        return $data;
    }
    
    public function create_order($order){
        return $this->siusk24_api->generateOrder($order);
    }
    
    public function cancel_order($shipment_id){
        return $this->siusk24_api->cancelOrder($shipment_id);
    }
    
    public function get_label($shipment_id){
        return $this->siusk24_api->getLabel($shipment_id);
    }
    
    public function generate_manifest($cart_id){
        return $this->siusk24_api->generateManifest($cart_id);
    }
    
    public function generate_latest_manifest(){
        return $this->siusk24_api->generateManifestLatest();
    }
}
