<?php

/**
 * This class contains common functions for creating invoices and orders
 *
 * @package   Woo_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2022 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_Hub_Document_Admin', false)) {

    class Woo_Fortnox_Hub_Document_Admin
    {

        /**
         * Set up filters and actions when constructing the class
         *
         * @suppress PHP0415
         */
        public function __construct()
        {

            add_action('wp_ajax_fortnox_sync', array($this, 'ajax_sync_single_order'));

            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            } else {
                $hpos_enabled = false;
            }

            if ($hpos_enabled) {

                $screen_id = 'woocommerce_page_wc-orders';

                add_filter('manage_' . $screen_id . '_columns', array($this, 'add_fortnox_column'), 20, 2);
                add_action('manage_' . $screen_id . '_custom_column', array($this, 'invoice_number_content'), 10, 2);
                add_filter('bulk_actions-' . $screen_id, array($this, 'define_bulk_actions'));
                add_filter('handle_bulk_actions-' . $screen_id, array($this, 'handle_bulk_actions'), 10, 3);

            } else {
                add_filter('manage_edit-shop_order_columns', array($this, 'add_fortnox_column'), 20);
                add_action('manage_shop_order_posts_custom_column', array($this, 'invoice_number_content'), 10, 2);
                add_filter('bulk_actions-edit-shop_order', array($this, 'define_bulk_actions'));
                add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
            }

            add_action('woo_fortnox_hub_sync_order_manually', array($this, 'sync_order_manually'));

            add_filter('woocommerce_shop_order_search_results', array($this, 'search_fortnox_values'),10,3);
            add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, 'fortnox_custom_meta_query'), 10, 2 );


            if (!wc_string_to_bool(get_option('fortnox_hide_admin_order_meta'))) {
                add_action('add_meta_boxes', array($this, 'order_meta_general'), 10, 2);
            }

            if ('yes' == get_option('fortnox_enable_order_cleaning')) {
                add_action('wp_ajax_fortnox_clean', array($this, 'clean_single_order'));
                add_action('woo_fortnox_hub_clean_order_manually', array($this, 'clean_order_manually'));
            }

        }

        public function fortnox_custom_meta_query($query, $query_vars) {
            if ( ! empty( $query_vars['fortnox_document_number'] ) ) {
                $fortnox_document_number = esc_attr( $query_vars['fortnox_document_number']);

                $new_meta_query = array( 
                    'relation' => 'OR',
                    'wcfh_invoice_number_1' => array(
                        'key' => '_fortnox_invoice_number',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    ),
                    'wcfh_invoice_number_2' => array(
                        'key' => 'Fortnox Invoice number',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    ),
                    'wcfh_order_number_1' => array(
                        'key' => '_fortnox_order_documentnumber',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    ),
                    'wcfh_order_number_2' => array(
                        'key' => 'FORTNOX_ORDER_DOCUMENTNUMBER',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    ),
                );

                if (wc_string_to_bool(get_option('fortnox_include_vat_number_in_search'))) {
                    $new_meta_query['wcfh_vat_number_1'] = array(
                        'key' => '_billing_vat_number',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    );
                    $new_meta_query['wcfh_vat_number_2'] = array(
                        'key' => '_vat_number',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    );

                    $new_meta_query['wcfh_vat_number_3'] = array(
                        'key' => 'vat_number',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    );

                    $new_meta_query['wcfh_vat_number_4'] = array(
                        'key' => 'yweu_billing_vat',
                        'value' => $fortnox_document_number,
                        'compare' => '=',
                    );
                }

                $query['meta_query'][] = $new_meta_query;


            }
        
            return $query;
        }

        public function search_fortnox_values ($order_ids, $term, $search_fields) {
            $fortnox_orders = wc_get_orders(
                array(
                    'fortnox_document_number' => $term,
                    'return' => 'ids',
                    'limit' => -1
                ),

            );

            $order_ids = array_unique(array_merge($order_ids, $fortnox_orders));

            return $order_ids;
        }

        /**
         * Display order meta information in order details
         *
         * @param WC_Order $order
         * @return void
         */
        public function order_meta_general($screen_id, $post_or_order)
        {

            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            } else {
                $hpos_enabled = false;
            }

            $screen = 'shop_order';

            if ($hpos_enabled) {
                $screen = 'woocommerce_page_wc-orders';
            }

            if ($screen_id !== $screen) {
                return;
            }

            $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : wc_get_order($post_or_order->get_id());

            if (!$order) {
                return;
            }

            if (!(WCFH_Util::fortnox_wc_order_creates($order) == 'invoice' || WCFH_Util::fortnox_wc_order_creates($order) == 'order')) {
                return;
            }

            add_meta_box(
                'fortnox-order-meta',
                __('Fortnox', 'woo-fortnox-hub'),
                [$this, 'meta_box_fortnox'],
                $screen,
                'side',
                'high'
            );

        }

        public function meta_box_fortnox($post_or_order)
        {
            $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : wc_get_order($post_or_order->get_id());

            if (!$order) {
                return;
            }

            $order_id = $order->get_id();

            $ocr = $order->get_meta('fortnox_invoice_ocr', true);

            if (WCFH_Util::fortnox_wc_order_creates($order) == 'order') {
                $fn_order_number = WCFH_Util::get_fortnox_order_documentnumber($order_id);

                echo '<div id="fortnox_order_number" class="address">';
                echo '<p><strong>' . sprintf(__('Order number: ', 'woo-fortnox-hub')) . ' </strong>' . ($fn_order_number ? $fn_order_number : '') . '</p>';
                echo '</div>';
            }

            $fn_invoice_number = WCFH_Util::get_fortnox_invoice_number($order_id);

            echo '<div id="fortnox_invoice_number" class="address">';
            echo '<p><strong>' . sprintf(__('Invoice number: ', 'woo-fortnox-hub')) . ' </strong>' . ($fn_invoice_number ? $fn_invoice_number : '') . '</p>';
            echo '</div>';

            echo '<div id="fortnox_ocr_number" class="address">';
            echo '<p><strong>OCR: </strong>' . ($ocr ? $ocr : '') . '</p>';
            echo '</div>';

        }

        /**
         * Add a 'Fortnox' column in the order-list
         *
         * @param array $columns
         * @return array
         */
        public function add_fortnox_column($columns)
        {

            $creates = get_option('fortnox_woo_order_creates');
            $use_woocommerce_order_number = 'yes' === get_option('fortnox_use_woocommerce_order_number');

            $new_columns = array();

            foreach ($columns as $column_name => $column_info) {

                $new_columns[$column_name] = $column_info;

                if ('order_number' === $column_name) {

                    if (('invoice' == $creates && !$use_woocommerce_order_number) || ('order' == $creates && $use_woocommerce_order_number)) {
                        $new_columns['fortnox_invoice_number'] = __('Fortnox Invoice', 'woo-fortnox-hub');
                    } elseif ('order' == $creates) {
                        $new_columns['fortnox_order_number'] = __('Fortnox Order/Invoice', 'woo-fortnox-hub');
                    }

                    $new_columns['fortnox_sync_document'] = __('Fortnox', 'woo-fortnox-hub');

                    if ('yes' == get_option('fortnox_enable_order_cleaning')) {
                        $new_columns['fortnox_clean_meta'] = __('Clean Fortnox','woo-fortnox-hub');
                    }

                }
            }

            return $new_columns;

        }

        public function refunded_not_synced($order)
        {
            $refund_ids = array();

            if (!empty($refunds = $order->get_refunds())) {

                foreach ($refunds as $refund) {
                    $refund_id = $refund->get_id();
                
                    $fn_invoice_number = WCFH_Util::get_fortnox_invoice_number($refund_id);

                    if (!$fn_invoice_number) {
                        
                        array_push($refund_ids, $refund_id);
                    }

                }
            }

            return array_reverse($refund_ids);
        }

        public function invoice_number_content($column, $order_id = '')
        {

            if (!$order_id) {

                global $post;

                $order_id = $post->ID;

            }

            if ('fortnox_invoice_number' == $column || 'fortnox_order_number' == $column) {
                $fn_invoice = WCFH_Util::get_fortnox_invoice_number($order_id);

                if ('fortnox_invoice_number' == $column) {
                    echo sprintf('%s', $fn_invoice ? $fn_invoice : '-');
                }

                if ('fortnox_order_number' == $column) {
                    $fn_order = WCFH_Util::get_fortnox_order_documentnumber($order_id);
                    echo sprintf('%s/%s', $fn_order ? $fn_order : '-', $fn_invoice ? $fn_invoice : '-');
                }
            }

            if ('fortnox_sync_document' === $column) {
                $order = wc_get_order($order_id);
                $wc_order_creates = WCFH_Util::fortnox_wc_order_creates($order);

                $already_synced = false;
                if ('order' === $wc_order_creates) {
                    $already_synced = WCFH_Util::get_fortnox_order_documentnumber($order_id);
                } elseif ('invoice' === $wc_order_creates) {
                    $already_synced = WCFH_Util::get_fortnox_invoice_number($order_id);
                } elseif ('stockchange' === $wc_order_creates) {
                    $already_synced = $order->get_meta('_fortnox_stockchange_timestamp', true);
                }

                if ($already_synced) {
                    echo '<a class="button button wc-action-button fortnox sync" data-order-id="' . esc_html($order->get_id()) . '">Resync</a>';
                } else {
                    echo '<a class="button button wc-action-button fortnox sync" data-order-id="' . esc_html($order->get_id()) . '">Sync</a>';
                }

            }

            if ('fortnox_clean_meta' == $column) {

                $order = wc_get_order($order_id);
    
                echo '<a class="button button wc-action-button fortnox clean" data-order-id="' . esc_html($order->get_id()) . '">Clean</a>';
            }
        }

        /**
         * Manually sync a WooCommerce order to Fortnox
         *
         * @param string $order_id
         * @return void
         */
        public function sync_order_manually($order_id)
        {

            $order = wc_get_order($order_id);
            $wc_order_creates = WCFH_Util::fortnox_wc_order_creates($order);

            if ('order' === $wc_order_creates) {

                if ('cancelled' == $order->get_status('edit')) {
                    do_action('woo_fortnox_hub_cancelled_order', $order_id);
                    return;
                }

                if (!WCFH_Util::get_fortnox_invoice_number($order_id)) {
                    do_action('woo_fortnox_hub_processing_order', $order_id);
                }

            } elseif ('invoice' === $wc_order_creates) {

                if ('cancelled' == $order->get_status('edit')) {
                    do_action('woo_fortnox_hub_cancelled_invoice', $order_id);
                    return;
                }

                do_action('woo_fortnox_hub_processing_invoice', $order_id);

            } elseif ('stockchange' === $wc_order_creates) {

                if ('cancelled' == $order->get_status('edit')) {
                    do_action('woo_fortnox_hub_cancelled_stockchange', $order_id);
                    return;
                }

                do_action('woo_fortnox_hub_processing_stockchange', $order_id);

            }

            $check_invoices = wc_string_to_bool(get_option('fortnox_check_invoices_automatically'));

            $refund_ids = $this->refunded_not_synced($order);      

            foreach ($refund_ids as $refund_id) {

                if (count($refund_ids) > 1 || ($order->get_remaining_refund_amount() > 0) || ($order->has_free_item() && $order->get_remaining_refund_items() > 0)) {

                    if (in_array($wc_order_creates, array('order', 'invoice'))) {
                        do_action('woo_fortnox_hub_partially_refunded_invoice', $order_id, $refund_id);
                    } elseif ('stockchange' === $wc_order_creates) {
                        do_action('woo_fortnox_hub_partially_refunded_stockchange', $order_id, $refund_id);
                    }

                } else {

                    if (in_array($wc_order_creates, array('order', 'invoice'))) {
                        do_action('woo_fortnox_hub_fully_refunded_invoice', $order_id, $refund_id);
                    } elseif ('stockchange' === $wc_order_creates) {
                        do_action('woo_fortnox_hub_fully_refunded_stockchange', $order_id, $refund_id);
                    }
                }
            }

            if (($invoice_number = WCFH_Util::get_fortnox_invoice_number($order_id)) && $check_invoices) {
                do_action('fortnox_process_changed_invoices', $invoice_number);
            }

        }

        /**
         * Ajax function to sync a single order
         *
         * @return void
         */
        public function ajax_sync_single_order()
        {

            if (!wp_verify_nonce($_POST['nonce'], 'ajax-fortnox-hub')) {
                wp_die();
            }

            $order_id = sanitize_key($_POST['order_id']);

            WC_FH()->logger->add(sprintf('ajax_sync_single_order (%s): Order sync requested', $order_id));

            do_action('woo_fortnox_hub_sync_order_manually', $order_id);

            wp_send_json_success();

        }

        /**
         * Handle the bulk sync action
         *
         * @param string$redirect_to
         * @param string $action
         * @param array $ids
         * @return string
         */
        public function handle_bulk_actions($redirect_to, $action, $ids)
        {
            if ('fortnox_sync_order' == $action) {
                foreach (array_reverse($ids) as $order_id) {
                    as_schedule_single_action(as_get_datetime_object()->getTimestamp(), 'woo_fortnox_hub_sync_order_manually', array($order_id));
                }
            }

            if ('fortnox_clean_invoice' == $action) {
                foreach (array_reverse($ids) as $order_id) {
                    as_schedule_single_action(as_get_datetime_object(), 'woo_fortnox_hub_clean_order_manually', array($order_id));
                }
            }

            return esc_url_raw($redirect_to);
        }

        public function define_bulk_actions($actions)
        {
            $actions['fortnox_sync_order'] = __('Sync Order to Fortnox', 'woo-fortnox-hub');

            if ('yes' == get_option('fortnox_enable_order_cleaning')) {
                $actions['fortnox_clean_invoice'] = __('Clean Fortnox invoice from WooCommerce order', 'woo-fortnox-hub');
            }

            return $actions;
        }

        public function clean_single_order()
        {

            if (!wp_verify_nonce($_POST['nonce'], 'ajax-fortnox-hub')) {
                wp_die();
            }

            $order_id = sanitize_key($_POST['order_id']);

            WC_FH()->logger->add(sprintf(__('clean_single_order (%s): Order clean requested', 'woo-fortnox-hub'), $order_id));

            do_action('woo_fortnox_hub_clean_order_manually', $order_id);

            wp_send_json_success();

        }

        public function clean_order_manually($order_id)
        {
            $order = wc_get_order($order_id);

            $order->delete_meta_data('_fortnox_invoice_number');
            $order->delete_meta_data('_fortnox_customer_number');
            $order->delete_meta_data('_fortnox_order_documentnumber');
            $order->delete_meta_data('_fortnox_email_is_sent_for_order');
            $order->delete_meta_data('fortnox_invoice_ocr');

            if (!empty($refunds = $order->get_refunds())) {
                foreach ($refunds as $refund) {
                    $refund->delete_meta_data('_fortnox_invoice_number');
                    $refund->delete_meta_data('_fortnox_email_is_sent_for_order');
                    $refund->save();
                }
            }

            $order->save();
        }
    }

    new Woo_Fortnox_Hub_Document_Admin();
}
