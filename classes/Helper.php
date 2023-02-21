<?php

namespace Siusk24Woo;

if (!defined('ABSPATH')) {
    exit;
}

class Helper {

    static function get_prefix() {
        return "siusk24";
    }

    static function domain() {
        return "siusk24";
    }

    /**
     * Use default value, when config not exists
     * 
     * @param string $key - Config key
     * @param array $config - All configs
     * @param mixed $default - Default value
     * @param boolean $not_allow_empty - Use default value when config exists, but value is empty
     * 
     * @return mixed - Config value or default value
     */
    static function get_config_value($key, $config, $default = '', $not_allow_empty = false) {
        return ( !isset($config[$key]) || ($not_allow_empty && empty($config[$key])) ) ? $default : $config[$key];
    }

    static function get_settings_url() {
        return get_admin_url() . 'admin.php?page=wc-settings&tab=shipping&section=' . self::get_prefix();
    }

    static function generate_outside_action_url($action, $action_value) {
        $url = admin_url('admin.php?siusk24_action=' . esc_attr($action) . '&action_value=' . esc_attr($action_value));
        return $url;
    }

    static function generate_manifest_page_url($cart_id = false) {
        $url = admin_url('admin.php?page=siusk24_manifest');
        return $url;
    }

    static function additional_services($get_service = false) {
        $services = array(
            'cod' => __('C.O.D.', 'siusk24'),
            'return' => __('Return', 'siusk24'),
            'ukd' => __('U.K.D', 'siusk24'),
            'doc_return' => __('Document return', 'siusk24'),
            'insurance' => __('Insurance', 'siusk24'),
            'carry_service' => __('Carry service', 'siusk24'),
            'fragile' => __('Fragile', 'siusk24')
        );

        return ($get_service && isset($services[$get_service])) ? $services[$get_service] : $services;
    }

    static function siusk24_get_categories() {
        $cats = self::get_categories_hierarchy();
        $result = [];
        
        foreach ($cats as $item) {
            self::create_categories_list('', $item, $result);
        }

        return $result;
    }

    /**
     * Makes a list of categories to select from in settings page. array(lowest cat id => full cat path name)
     */
    static function create_categories_list($prefix, $data, &$results) {
        if ($prefix) {
            $prefix = $prefix . ' &gt; ';
            $results[$data->term_id] = $prefix . $data->name;
        }
        if (!$data->children) {
            $results[$data->term_id] = $prefix . $data->name;

            return true;
        }

        foreach ($data->children as $child) {
            self::create_categories_list($prefix . $data->name, $child, $results);
        }
    }

    static function get_categories_hierarchy($parent = 0) {
        $taxonomy = 'product_cat';
        $orderby = 'name';
        $hide_empty = 0;

        $args = array(
            'taxonomy' => $taxonomy,
            'parent' => $parent,
            'orderby' => $orderby,
            'hide_empty' => $hide_empty,
            'supress_filter' => true
        );

        $cats = get_categories($args);
        $children = array();
        
        foreach ($cats as $cat) {
            $cat->children = self::get_categories_hierarchy($cat->term_id);
            $children[$cat->term_id] = $cat;
        }

        return $children;
    }

    static function clear_postcode($postcode, $country) {
        return $postcode; //This function is not needed yet
        
        if ($country == 'LV') {
            //It is not clear if it will be needed
        }

        return preg_replace('/[^0-9]/', '', $postcode);
    }

}
