<?php

namespace Siusk24Woo;

if (!defined('ABSPATH')) {
    exit;
}

class Terminal
{
    
    static function get($country = null, $identifier = null) {
        global $wpdb;
        $db_table_name = $wpdb->prefix . 'siusk24_terminals';
        $sql = "SELECT * FROM {$db_table_name}";
        $condition = [];
        if ($country) {
            $condition[] = "country_code = '{$country}'";
        }
        if ($identifier) {
            $condition[] = "identifier = '{$identifier}'";
        }
        if (!empty($condition)) {
            $sql .= " WHERE " . implode(" AND ", $condition);
        }
        $results = $wpdb->get_results($sql, OBJECT );
        return $results;
    }
    
    static function getById($id) {
        global $wpdb;
        $db_table_name = $wpdb->prefix . 'siusk24_terminals';
        $sql = "SELECT * FROM {$db_table_name} WHERE id = {$id}";
        $results = $wpdb->get_row($sql, OBJECT );
        return $results;
    }
    
    static function count() {
        global $wpdb;
        $db_table_name = $wpdb->prefix . 'siusk24_terminals';
        $rowcount = $wpdb->get_var("SELECT COUNT(*) FROM {$db_table_name}");
        return $rowcount;
    }
    
    static function delete() {
        global $wpdb;
        $db_table_name = $wpdb->prefix . 'siusk24_terminals';
        $wpdb->query("TRUNCATE TABLE {$db_table_name}");
    }
    
    static function insert($data) {
        global $wpdb;
        $db_table_name = $wpdb->prefix . 'siusk24_terminals';
        $wpdb->insert( 
            $db_table_name, 
            array( 
                'id' => $data->id,
                'name' => $data->name ?? "", 
                'city' => $data->city ?? "", 
                'country_code' => $data->country_code,
                'address' => $data->address,
                'zip' => $data->zip,
                'x_cord' => $data->x_cord,
                'y_cord' => $data->y_cord,
                'comment' => $data->comment ?? "",
                'identifier' => $data->identifier,
            ), 
            array( 
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ) 
        );
    }
}
