<?php

namespace Siusk24Woo;

use Siusk24Woo\Helper;

class Category {
    
    public function __construct() {
        add_action('product_cat_add_form_fields', array($this, 'add_fields'), 99, 1);
        add_action('product_cat_edit_form_fields', array($this, 'edit_fields'), 99, 1);
        add_action('edited_product_cat', array($this, 'save_fields'), 10, 1);
        add_action('create_product_cat', array($this, 'save_fields'), 10, 1);
    }
    
    public function add_fields() {
        ?>   
        <div class="form-field">
            <label for="wh_meta_title"><?php _e('Default weight, kg', 'siusk24'); ?></label>
            <input type="number" name="og_default_weight" id="og_default_weight" value="" placeholder = "<?php _e('Weight', 'siusk24'); ?>">
        </div>
        <div class="form-field">
            <label for="og_default_size"><?php _e('Width x Height x Length, cm', 'siusk24'); ?></label>
            <input type="number" name="og_default_width" id="og_default_width" class ="category_size" value="" placeholder = "<?php _e('Width', 'siusk24'); ?>"> x 
            <input type="number" name="og_default_height" id="og_default_height" class ="category_size" value="" placeholder = "<?php _e('Height', 'siusk24'); ?>"> x 
            <input type="number" name="og_default_length" id="og_default_length" class ="category_size" value="" placeholder = "<?php _e('Length', 'siusk24'); ?>">            
        </div>
        <?php
    }
    
    public function edit_fields($term) {
        $term_id = $term->term_id;
        $weight = get_term_meta($term_id, 'og_default_weight', true);
        $width = get_term_meta($term_id, 'og_default_width', true);
        $height = get_term_meta($term_id, 'og_default_height', true);
        $length = get_term_meta($term_id, 'og_default_length', true);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="og_default_weight"><?php _e('Default weight, kg', 'siusk24'); ?></label></th>
            <td>
                <input type="number" name="og_default_weight" id="og_default_weight" value="<?php echo esc_attr($weight) ? esc_attr($weight) : ''; ?>" placeholder = "<?php _e('Weight', 'siusk24'); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="og_default_size"><?php _e('Width x Height x Length, cm', 'siusk24'); ?></label></th>
            <td>
                <input type="number" name="og_default_width" id="og_default_width" class ="category_size" value="<?php echo esc_attr($width) ? esc_attr($width) : ''; ?>" placeholder = "<?php _e('Width', 'siusk24'); ?>"> x 
                <input type="number" name="og_default_height" id="og_default_height" class ="category_size" value="<?php echo esc_attr($height) ? esc_attr($height) : ''; ?>" placeholder = "<?php _e('Height', 'siusk24'); ?>"> x 
                <input type="number" name="og_default_length" id="og_default_length" class ="category_size" value="<?php echo esc_attr($length) ? esc_attr($length) : ''; ?>" placeholder = "<?php _e('Length', 'siusk24'); ?>">          
            </td>
        </tr>
        <?php
    }
    
    // Save extra taxonomy fields callback function.
    public function save_fields($term_id) {
        $fields = [
            'weight', 'width', 'height', 'length'
        ];
        foreach ($fields as $field){
            $data = filter_input(INPUT_POST, 'og_default_' . $field);
            update_term_meta($term_id, 'og_default_' . $field, $data);
        }
    }
}
