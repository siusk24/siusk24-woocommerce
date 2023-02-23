<?php

namespace Siusk24Woo;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Siusk24Woo\Helper;

class Manifest {

    private $tab_strings = array();
    private $filter_keys = array();
    private $max_per_page = 25;
    private $api;
    private $core;

    public function __construct($api, $core) {
        $this->tab_strings = array(
            'all_orders' => __('All orders', 'siusk24'),
            'new_orders' => __('New orders', 'siusk24'),
            'completed_orders' => __('Completed orders', 'siusk24')
        );

        $this->filter_keys = array(
            'customer',
            'status',
            'barcode',
            'manifest',
            'id',
            'start_date',
            'end_date'
        );

        $this->api = $api;
        $this->core = $core;
        add_action('admin_menu', array($this, 'register_siusk24_manifest_menu_page'));
        add_action('siusk24_admin_manifest_head', array($this, 'siusk24_admin_manifest_scripts'));
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, 'handle_custom_siusk24_query_var'), 10, 2);
        add_action('init', array($this, 'execute_single_action'));
        add_action('init', array($this, 'execute_mass_action'));
        add_action('init', array($this, 'execute_outside_action'));
    }

    public function siusk24_admin_manifest_scripts() {
        wp_enqueue_style('siusk24_admin_manifest', plugin_dir_url(__DIR__) . 'assets/css/admin_manifest.css');
        wp_enqueue_style('bootstrap-datetimepicker', plugin_dir_url(__DIR__) . 'assets/js/datetimepicker/bootstrap-datetimepicker.min.css');
        wp_enqueue_script('moment', plugin_dir_url(__DIR__) . 'assets/js/moment.min.js', array(), null, true);
        wp_enqueue_script('bootstrap-datetimepicker', plugin_dir_url(__DIR__) . 'assets/js/datetimepicker/bootstrap-datetimepicker.min.js', array('jquery', 'moment'), null, true);
        wp_enqueue_script('siusk24_admin_manifest', plugin_dir_url(__DIR__) . 'assets/js/manifest.js', array('jquery'), false, true);
    }

    public function register_siusk24_manifest_menu_page() {
        add_submenu_page(
                'woocommerce',
                __('Siusk24 shipments', 'siusk24'),
                __('Siusk24 shipments', 'siusk24'),
                'manage_woocommerce',
                'siusk24-manifest',
                array($this, 'render_page'),
                //plugins_url('siusk24-woocommerce/images/icon.png'),
                10
        );
    }

    public function execute_outside_action() {
        if (!current_user_can( 'edit_shop_orders' ) || !is_admin() || !isset($_GET['siusk24_action'])) {
            return;
        }

        try {
            if (empty($_GET['action_value'])) {
                throw new \Exception(__('Not received action value', 'siusk24'));
            }
            if ($_GET['siusk24_action'] == 'print_label') {
                $this->core->print_label(esc_attr($_GET['action_value']));
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }

    public function execute_single_action() {
        if (!current_user_can('edit_shop_orders') || !is_admin() || !isset($_GET['page']) || $_GET['page'] != 'siusk24-manifest') {
            return;
        }

        try {
            if (isset($_POST['print_label'])) {
                $this->core->print_label(esc_attr($_POST['print_label']));
            }
            if (isset($_POST['print_manifest'])) {
                $this->core->generate_manifests(array(esc_attr($_POST['print_manifest'])));
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg .= '<br/><a href="javascript:window.location.href=window.location.href">' . __('Refresh page', 'siusk24') . '</a>';
            $this->core->show_admin_notice($msg, 'error');
        }
    }

    public function execute_mass_action() {
        if (!current_user_can('edit_shop_orders') || !is_admin() || !isset($_GET['page']) || $_GET['page'] != 'siusk24-manifest') {
            return;
        }

        try {
            $selected_orders = (!empty($_POST['orders'])) ? $_POST['orders'] : array();
            if (isset($_POST['generate_labels'])) {
                $result = $this->mass_generate_labels($selected_orders);
                $this->show_label_generation_result_notice($result);
            }
            if (isset($_POST['print_labels'])) {
                $this->mass_print_labels($selected_orders);
            }
            if (isset($_POST['generate_manifest'])) {
                $this->mass_generate_manifest($selected_orders);
            }
            if (isset($_POST['latest_manifest'])) {
                $this->mass_get_latest_manifest();
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg .= '<br/><a href="javascript:window.location.href=window.location.href">' . __('Refresh page', 'siusk24') . '</a>';
            $this->core->show_admin_notice($msg, 'error');
        }
    }

    private function mass_generate_labels($orders_ids) {
        if (empty($orders_ids)) {
            throw new \Exception(__('Selected orders not received', 'siusk24'));
        }

        $count_generated = 0;
        $failed_orders = array();
        foreach ($orders_ids as $order_id) {
            $order = \wc_get_order($order_id);
            if (empty($order)) {
                $failed_orders[$order_id] = __('Unable to get WooCommerce order', 'siusk24');
                continue;
            }

            $terminal_id = $order->get_meta('_siusk24_terminal_id');
            
            $status = $this->core->register_order(array(
                'wc_order_id' => $order_id,
                'services' => array(),
                'terminal' => (!empty($terminal_id)) ? $terminal_id : 0,
                'cod_amount' => false,
                'eori_number' => false,
                'hs_code' => false,
            ));

            if ($status['status'] != 'ok') {
                if (!isset($status['msg'])) {
                    $status['msg'] = __('Unknown error', 'siusk24');
                }
                $failed_orders[$order_id] = $status['msg'];
                continue;
            }

            $count_generated++;
        }

        return array(
            'total' => count($orders_ids),
            'success' => $count_generated,
            'failed_orders' => $failed_orders,
        );
    }

    private function show_label_generation_result_notice($result) {
        $msg = '';
        $type = 'warning';
        if (!empty($result['failed_orders'])) {
            $msg .= __('Failed to generate labels for these orders', 'siusk24') . ':<br/>';
            $msg .= $this->build_failed_orders_list($result['failed_orders']);
        } else if (empty($result['success'])) {
            $msg .= __('A label could not be generated for any order', 'siusk24');
            $type = 'error';
        } else if ($result['total'] > $result['success']) {
            $msg .= __('Not all orders were successful in generating labels. Unknown error.', 'siusk24');   
        } else {
            $msg .= __('Labels successfully generated for all orders', 'siusk24');
            $type = 'success';
        }

        $msg .= '<br/><a href="javascript:window.location.href=window.location.href">' . __('Refresh page', 'siusk24') . '</a>';

        if (!empty($msg)) {
            $this->core->show_admin_notice($msg, $type);
        }
    }

    private function mass_print_labels($orders_ids) {
        if (empty($orders_ids)) {
            throw new \Exception(__('Selected orders not received', 'siusk24'));
        }

        $shipments_ids = array();
        foreach ($orders_ids as $order_id) {
            $order = \wc_get_order($order_id);
            if (empty($order)) {
                continue;
            }

            $shipments_ids[$order_id] = $order->get_meta('_siusk24_shipment_id');
        }
        
        $status = $this->core->print_labels($shipments_ids);
        if ($status['status'] != 'ok') {
            if (!isset($status['msg'])) {
                $status['msg'] = __('Unknown error', 'siusk24');
            }

            throw new \Exception($status['msg']);
        }
    }

    private function mass_generate_manifest($orders_ids) {
        if (empty($orders_ids)) {
            throw new \Exception(__('Selected orders not received', 'siusk24'));
        }

        $carts_ids = array();
        foreach ($orders_ids as $order_id) {
            $order = \wc_get_order($order_id);
            if (empty($order)) {
                continue;
            }

            $cart_id = $order->get_meta('_siusk24_cart_id');
            if (!in_array($cart_id, $carts_ids)) {
                $carts_ids[] = $cart_id;
            }
        }

        $status = $this->core->generate_manifests($carts_ids);
        if ($status['status'] != 'ok') {
            if (!isset($status['msg'])) {
                $status['msg'] = __('Unknown error', 'siusk24');
            }

            throw new \Exception($status['msg']);
        }
    }

    private function mass_get_latest_manifest() {
        $this->core->generate_latest_manifest();
    }

    private function build_failed_orders_list($failed_orders) {
        $rows = '';

        foreach ($failed_orders as $order_id => $msg) {
            $order = wc_get_order($order_id);
            if (!empty($order)) {
                $order_id = $order->get_order_number();
            }
            $rows .= '<b>' . $order_id . ':</b> ' . $msg . '<br/>';
        }

        return $rows;
    }

    private function build_failed_orders_table($failed_orders, $add_style = true) {
        $style = '';
        $header = '<tr><th>' . __('Order ID', 'siusk24') . '</th><th>' . __('Problem', 'siusk24') . '</th>';
        $rows = '';
        
        foreach ($failed_orders as $order_id => $msg) {
            $order = wc_get_order($order_id);
            if (!empty($order)) {
                $order_id = $order->get_order_number();
            }
            $rows .= '<tr><td>' . $order_id . '</td><td>' . $msg . '</td>';
        }

        if ($add_style) {
            $style .= '<style>
                table.siusk24-notice-table, .siusk24-notice-table th, .siusk24-notice-table td {
                    border: 1px solid black;
                    border-collapse: collapse;
                    border-color: #f7a690;
                }
                table.siusk24-notice-table {
                    margin-top: 5px;
                }
                .siusk24-notice-table th, .siusk24-notice-table td {
                    padding: 2px 5px;
                }
            </style>';
        }

        return $style . '<table class="siusk24-notice-table">' . $header . $rows . '</table>';
    }

    public function handle_custom_siusk24_query_var($query, $query_vars) {
        if (!empty($query_vars['siusk24_method'])) {
            $query['meta_query'][] = array(
                'key' => '_siusk24_method',
                'value' => $query_vars['siusk24_method']//esc_attr( $query_vars['siusk24_method'] ),
            );
        }

        if (isset($query_vars['siusk24_barcode'])) {
            $query['meta_query'][] = array(
                'key' => '_siusk24_tracking_numbers',
                'value' => $query_vars['siusk24_barcode'],
                'compare' => 'LIKE'
            );
        }

        if (isset($query_vars['siusk24_customer'])) {
            $query['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key' => '_billing_first_name',
                    'value' => $query_vars['siusk24_customer'],
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_billing_last_name',
                    'value' => $query_vars['siusk24_customer'],
                    'compare' => 'LIKE'
                )
            );
        }

        if (isset($query_vars['siusk24_manifest'])) {
            $query['meta_query'][] = array(
                'key' => '_siusk24_cart_id',
                'value' => $query_vars['siusk24_manifest'],
            );
        }

        if (isset($query_vars['siusk24_manifest_date'])) {
            $filter_by_date = false;
            if ($query_vars['siusk24_manifest_date'][0] && $query_vars['siusk24_manifest_date'][1]) {
                $filter_by_date = array(
                    'key' => '_siusk24_manifest_date',
                    'value' => $query_vars['siusk24_manifest_date'],
                    'compare' => 'BETWEEN'
                );
            } elseif ($query_vars['siusk24_manifest_date'][0] && !$query_vars['siusk24_manifest_date'][1]) {
                $filter_by_date = array(
                    'key' => '_siusk24_manifest_date',
                    'value' => $query_vars['siusk24_manifest_date'][0],
                    'compare' => '>='
                );
            } elseif (!$query_vars['siusk24_manifest_date'][0] && $query_vars['siusk24_manifest_date'][1]) {
                $filter_by_date = array(
                    'key' => '_siusk24_manifest_date',
                    'value' => $query_vars['siusk24_manifest_date'][1],
                    'compare' => '<='
                );
            }

            if ($filter_by_date) {
                $query['meta_query'][] = $filter_by_date;
            }
        }

        return $query;
    }

    /**
     * helper function to create links
     */
    public function make_link($args) {
        $query_args = array('page' => 'siusk24-manifest');
        $query_args = array_merge($query_args, $args);
        return add_query_arg($query_args, admin_url('/admin.php'));
    }

    public function render_page() {
        // append custom css and js
        do_action('siusk24_admin_manifest_head');
        ?>

        <div class="wrap siusk24 page-siusk24_manifest">
            
            <img src = "<?php echo plugin_dir_url(__DIR__); ?>assets/images/s24logo.png" style="width: 100px;"/>
            <h1><?php _e('International manifest', 'siusk24'); ?></h1>

            <?php
            $paged = 1;
            if (isset($_GET['paged']))
                $paged = filter_input(INPUT_GET, 'paged');

            $action = 'all_orders';
            if (isset($_GET['action'])) {
                $action = filter_input(INPUT_GET, 'action');
            }

            $filters = array();
            foreach ($this->filter_keys as $filter_key) {
                if (isset($_POST['filter_' . $filter_key]) && intval($_POST['filter_' . $filter_key]) !== -1) {
                    $filters[$filter_key] = filter_input(INPUT_POST, 'filter_' . $filter_key); //$_POST['filter_' . $filter_key];
                } else {
                    $filters[$filter_key] = false;
                }
            }

            // Handle query variables depending on selected tab
            switch ($action) {
                case 'new_orders':
                    $page_title = $this->tab_strings[$action];
                    $args = array(
                        'status' => array('wc-processing', 'wc-on-hold', 'wc-pending'),
                    );
                    break;
                case 'completed_orders':
                    $page_title = $this->tab_strings[$action];
                    $args = array(
                        'status' => array('wc-completed'),
                    );
                    break;
                case 'all_orders':
                default:
                    $action = 'all_orders';
                    $page_title = $this->tab_strings['all_orders'];
                    $args = array();
                    break;
            }

            foreach ($filters as $key => $filter) {
                if ($filter) {
                    switch ($key) {
                        case 'status':
                            $args = array_merge(
                                    $args,
                                    array('status' => $filter)
                            );
                            break;
                        case 'barcode':
                            $args = array_merge(
                                    $args,
                                    array('siusk24_barcode' => $filter)
                            );
                            break;
                        case 'manifest':
                            $args = array_merge(
                                    $args,
                                    array('siusk24_manifest' => $filter)
                            );
                            break;
                        case 'customer':
                            $args = array_merge(
                                    $args,
                                    array('siusk24_customer' => $filter)
                            );
                            break;
                    }
                }
            }
            // date filter is a special case
            if ($filters['start_date'] || $filters['end_date']) {
                $args = array_merge(
                        $args,
                        array('siusk24_manifest_date' => array($filters['start_date'], $filters['end_date']))
                );
            }

            // Get orders with extra info about the results.
            $args = array_merge(
                    $args,
                    array(
                        'siusk24_method' => 1,
                        'paginate' => true,
                        'limit' => $this->max_per_page,
                        'paged' => $paged,
                    )
            );
            
            // Searching by ID takes priority
            $singleOrder = false;
            if ($filters['id']) {
                $singleOrder = wc_get_order($filters['id']);
                if ($singleOrder) {
                    $orders = array($singleOrder); // table printer expects array
                    $paged = 1;
                }
            }

            // if there is no search by ID use to custom query
            $results = false;
            if (!$singleOrder) {
                $results = wc_get_orders($args);
                $orders = $results->orders;
            }

            $thereIsOrders = ($singleOrder || ($results && $results->total > 0));

            // make pagination
            $page_links = false;
            if ($results) {
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '?paged=%#%',
                    'prev_text' => __('&laquo;', 'text-domain'),
                    'next_text' => __('&raquo;', 'text-domain'),
                    'total' => $results->max_num_pages,
                    'current' => $paged,
                    'type' => 'plain'
                ));
            }

            $order_statuses = wc_get_order_statuses();
            $carriers = $this->api->get_services();
            ?>
            <ul class="nav nav-tabs">
                <?php foreach ($this->tab_strings as $tab => $tab_title) : ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $action == $tab ? 'active' : ''; ?>" href="<?php echo $this->make_link(array('paged' => ($action == $tab ? $paged : 1), 'action' => $tab)); ?>"><?php echo $tab_title; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($page_links) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo $page_links; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php
            if ($thereIsOrders) {
                $this->render_table_mass_action_buttons();
            }
            ?>
            <div class="table-container">
                <form id="filter-form" class="" action="<?php echo $this->make_link(array('action' => $action)); ?>" method="POST" target="_blank">
                    <?php wp_nonce_field('siusk24_labels', 'siusk24_labels_nonce'); ?>
                    <table class="wp-list-table widefat fixed striped posts">
                        <thead>

                            <tr class="siusk24-filter">
                                <td class="manage-column column-cb check-column"><input type="checkbox" class="check-all" /></td>
                                <th class="manage-column column-order_id">
                                    <input type="text" class="d-inline" name="filter_id" id="filter_id" value="<?php echo $filters['id']; ?>" placeholder="<?php echo __('ID', 'siusk24'); ?>" aria-label="Order ID filter">
                                </th>
                                <th class="manage-column">
                                    <input type="text" class="d-inline" name="filter_customer" id="filter_customer" value="<?php echo $filters['customer']; ?>" placeholder="<?php echo __('Customer', 'siusk24'); ?>" aria-label="Order ID filter">
                                </th>
                                <th class="column-order_status">
                                    <select class="d-inline" name="filter_status" id="filter_status" aria-label="Order status filter">
                                        <option value="-1" selected><?php echo _x('All', 'All status', 'siusk24'); ?></option>
                                        <?php foreach ($order_statuses as $status_key => $status) : ?>
                                            <option value="<?php echo $status_key; ?>" <?php echo ($status_key == $filters['status'] ? 'selected' : ''); ?>><?php echo $status; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th class="column-order_date">
                                </th>
                                <th class="manage-column column-carrier">
                                </th>
                                <th class="manage-column column-barcode">
                                    <input type="text" class="d-inline" name="filter_barcode" id="filter_barcode" value="<?php echo $filters['barcode']; ?>" placeholder="<?php echo __('Barcode', 'siusk24'); ?>" aria-label="Order barcode filter">
                                </th>
                                <th class="manage-column">
                                    <input type="text" class="d-inline" name="filter_manifest" id="filter_manifest" value="<?php echo $filters['manifest']; ?>" placeholder="<?php echo __('Manifest ID', 'siusk24'); ?>" aria-label="Manifest ID filter">
                                </th>
                                <th class="column-manifest_date">
                                    <div class='datetimepicker'>
                                        <div>
                                            <input name="filter_start_date" type='text' class="" id='datetimepicker1' data-date-format="YYYY-MM-DD" value="<?php echo $filters['start_date']; ?>" placeholder="<?php echo __('From', 'siusk24'); ?>" autocomplete="off" />
                                        </div>
                                        <div>
                                            <input name="filter_end_date" type='text' class="" id='datetimepicker2' data-date-format="YYYY-MM-DD" value="<?php echo $filters['end_date']; ?>" placeholder="<?php echo __('To', 'siusk24'); ?>" autocomplete="off" />
                                        </div>
                                    </div>
                                </th>
                                <th class="manage-column">
                                    <div class="siusk24-action-buttons-container">
                                        <button class="button action" type="submit"><?php echo __('Filter', 'siusk24'); ?></button>
                                        <button id="clear_filter_btn" class="button action" type="submit"><?php echo __('Reset', 'siusk24'); ?></button>
                                    </div>
                                </th>
                            </tr>

                            <tr class="table-header">
                                <td class="manage-column column-cb check-column"></td>
                                <th scope="col" class="column-order_id"><?php echo __('ID', 'siusk24'); ?></th>
                                <th scope="col" class="manage-column"><?php echo __('Customer', 'siusk24'); ?></th>
                                <th scope="col" class="column-order_status"><?php echo __('Order Status', 'siusk24'); ?></th>
                                <th scope="col" class="column-order_date"><?php echo __('Order Date', 'siusk24'); ?></th>
                                <th scope="col" class="manage-column column-carrier"><?php echo __('Service', 'siusk24'); ?></th>
                                <th scope="col" class="manage-column column-barcode"><?php echo __('Barcode', 'siusk24'); ?></th>
                                <th scope="col" class="manage-column"><?php echo __('Manifest ID', 'siusk24'); ?></th>
                                <th scope="col" class="column-manifest_date"><?php echo __('Manifest date', 'siusk24'); ?></th>
                                <th scope="col" class="manage-column column-actions"><?php echo __('Actions', 'siusk24'); ?></th>
                            </tr>

                        </thead>
                        <tbody>
                            <?php $date_tracker = false; ?>
                            <?php foreach ($orders as $order) : ?>
                                <?php
                                $manifest_date = $order->get_meta('_siusk24_manifest_date');
                                $cart_id = $order->get_meta('_siusk24_cart_id');
                                $date = date('Y-m-d H:i', strtotime($manifest_date));
                                ?>
                                <?php if ($action == 'completed_orders' && $date_tracker !== $date) : ?>
                                    <tr>
                                        <td colspan="9" class="manifest-date-title">
                                            <?php echo $date_tracker = $date; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="data-row">
                                    <th scope="row" class="check-column"><input type="checkbox" name="items[]" class="manifest-item" value="<?php echo $order->get_id(); ?>" /></th>
                                    <td class="manage-column column-order_id">
                                        <a href="<?php echo $order->get_edit_order_url(); ?>">#<?php echo $order->get_order_number(); ?></a>
                                    </td>
                                    <td class="column-order_number">
                                        <div class="data-grid-cell-content">
                                            <?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?>
                                        </div>
                                    </td>
                                    <td class="column-order_status">
                                        <div class="data-grid-cell-content">
                                            <?php echo wc_get_order_status_name($order->get_status()); ?>
                                        </div>
                                    </td>
                                    <td class="column-order_date">
                                        <div class="data-grid-cell-content">
                                            <?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?>
                                        </div>
                                    </td>
                                    <td class="manage-column column-carrier">
                                        <div class="data-grid-cell-content">
                                            <?php
                                            $carrier_code = get_post_meta($order->get_id(), '_siusk24_service', true);
                                            $carrier = $this->core->get_service_info($carrier_code, $carriers);
                                            $carrier_name = $carrier->name ?? '-';
                                            $carrier_img = $carrier->image ?? false;
                                            ?>
                                            <?php if ($carrier_img) : ?>
                                                <img src="<?php echo esc_url($carrier_img); ?>" alt="<?php echo esc_attr($carrier_name); ?>"/>
                                            <?php endif; ?>
                                            <span><?php echo $carrier_name; ?></span>
                                        </div>
                                    </td>
                                    <td class="manage-column column-barcode">
                                        <div class="data-grid-cell-content">
                                            <?php $barcode = $order->get_meta('_siusk24_tracking_numbers'); ?>
                                            <?php $shipment_id = $order->get_meta('_siusk24_shipment_id'); ?>
                                            <?php if ($barcode) : ?>
                                                <?php echo implode(', ', $barcode);  ?>
                                            <?php endif; ?>
                                            <?php $error = $order->get_meta('_siusk24_error'); ?>
                                            <?php if ($error) : ?>
                                                <?php if ($barcode) : ?><br /><?php endif; ?>
                                                <span><?php echo '<b>' . __('Error', 'siusk24') . ':</b> ' . $error; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-manifest_id">
                                        <div class="data-grid-cell-content">
                                            <?php echo $cart_id; ?>
                                        </div>
                                    </td>
                                    <td class="column-manifest_date">
                                        <div class="data-grid-cell-content">
                                            <?php echo $manifest_date; ?>
                                        </div>
                                    </td>
                                    <td class="manage-column column-actions">
                                        <?php if ($barcode && $shipment_id) : ?>
                                            <button type="submit" name="print_label" value="<?php echo esc_attr($shipment_id); ?>" class="button action">
                                                <?php echo __('Print label', 'siusk24'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($barcode && $cart_id && !$manifest_date) : ?>
                                            <button type="submit" name="print_manifest" value="<?php echo esc_attr($cart_id); ?>" class="button action">
                                                <?php echo __('Generate manifest', 'siusk24'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($manifest_date && $cart_id) : ?>
                                            <button type="submit" name="print_manifest" value="<?php echo esc_attr($cart_id); ?>" class="button action">
                                                <?php echo __('Print manifest', 'siusk24'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($shipment_id && !$barcode && !$manifest_date) : ?>
                                            <span class="no-results"><?php _e('Preparing...', 'siusk24'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$orders) : ?>
                                <tr>
                                    <td colspan="10">
                                        <?php echo __('No orders found', 'woocommerce'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <script>
                jQuery('document').ready(function ($) {
                    // "From" date picker
                    $('#datetimepicker1').datetimepicker({
                        pickTime: false,
                        useCurrent: false
                    });
                    // "To" date picker
                    $('#datetimepicker2').datetimepicker({
                        pickTime: false,
                        useCurrent: false
                    });

                    // Set limits depending on date picker selections
                    $("#datetimepicker1").on("dp.change", function (e) {
                        $('#datetimepicker2').data("DateTimePicker").setMinDate(e.date);
                    });
                    $("#datetimepicker2").on("dp.change", function (e) {
                        $('#datetimepicker1').data("DateTimePicker").setMaxDate(e.date);
                    });

                    // Pass on filters to pagination links
                    $('.tablenav-pages').on('click', 'a', function (e) {
                        e.preventDefault();
                        var form = document.getElementById('filter-form');
                        form.action = e.target.href;
                        form.submit();
                    });

                    // Filter cleanup and page reload
                    $('#clear_filter_btn').on('click', function (e) {
                        e.preventDefault();
                        $('#filter_id, #filter_customer, #filter_barcode, #filter_manifest, #datetimepicker1, #datetimepicker2').val('');
                        $('#filter_status').val('-1');
                        document.getElementById('filter-form').submit();
                    });

                    $('.check-all').on('click', function () {
                        var checked = $(this).prop('checked');
                        $(this).parents('table').find('.manifest-item').each(function () {
                            $(this).prop('checked', checked);
                        });
                    });
                    
                    $('.generate_manifest').on('click', function () {
                        setTimeout(function() {
                            location.reload();//reload page
                        }, 5000);
                    });
                     
                });
            </script>
            <?php
        }

        private function render_table_mass_action_buttons() {
            ?>
            <div class="mass-print-container">
                <form method="post" action="" onsubmit="siusk24_get_selected_orders(this)" class="inline" target="_blank">
                    <?php echo $this->build_mass_action_button(array(
                        'title' => __('Generate labels', 'siusk24'),
                        'name' => 'generate_labels'
                    )); ?>
                    <?php echo $this->build_mass_action_button(array(
                        'title' => __('Print labels', 'siusk24'),
                        'name' => 'print_labels'
                    )); ?>
                    <?php echo $this->build_mass_action_button(array(
                        'title' => __('Generate manifests', 'siusk24'),
                        'name' => 'generate_manifest'
                    )); ?>
                    <?php echo $this->build_mass_action_button(array(
                        'title' => __('Print latest manifest', 'siusk24'),
                        'name' => 'latest_manifest'
                    )); ?>
                </form>
            </div>
            <?php
        }

        private function build_mass_action_button($params) {
            if (!is_array($params)) {
                return '';
            }

            $title = $params['title'] ?? '[title]';
            $name = $params['name'] ?? '';
            $id = $params['id'] ?? '';
            //$url = $params['url'] ?? '';

            ob_start();
            ?>
            <button type="submit" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="button action siusk24-btn-generate_labels"><?php echo esc_html($title); ?></button>
            <?php
            $output = ob_get_clean();

            return $output;
        }

    }
    