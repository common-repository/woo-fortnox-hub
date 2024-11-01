<?php

/**
 * Utility functions for WooCommerce Fortnox Hub.
 *
 * @package   WooCommerce_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */
// Prevent direct file access

defined('ABSPATH') || exit;

if (!class_exists('WCFH_Util', false)) {
    class WCFH_Util
    {
        /**
         * replace empty fields with API_BLANK (= the field is cleared)
         */
        public static function api_blank(&$indata, $key)
        {
            if (!$indata) {
                $indata = 'API_BLANK';
            }
        }

        public static function remove_blanks($items)
        {
            if (is_array($items)) {
                foreach ($items as $key => $item) {
                    if ($item === '') {
                        unset($items[$key]);
                    }
                }
            }
            return $items;
        }

        public static function clear_row_blanks ($row) {
            $row_keys = [
                'ArticleNumber',
                'Description',
                'AccountNumber',
                'Price',
                'DeliveredQuantity'
            ];

            //Check row for blanks or values that do not exist - if so set them to API_BLANK
            foreach ($row_keys as $key) {
                if (!isset($row[$key]) || $row[$key] == '') {
                    $row[$key] = 'API_BLANK';
                }
            }

            return $row;
        }

        public static function create_text_row ($text) {
            return [
                'ArticleNumber' => 'API_BLANK',
                'Description' => self::clean_fortnox_text($text,50),
                'AccountNumber' => 'API_BLANK',
                'Price' => 'API_BLANK',
                'DeliveredQuantity' => 'API_BLANK'
            ];
        }

        public static function get_bank_account($order)
        {
            return strval(get_option('fortnox_' . self::get_payment_method($order, 'get_bank_account') . '_bank_account'));
        }

        /**
         * sset_fortnox_order_vouchernumber function
         *
         * Set the Fortnox Order DocumentNumber on an order
         *
         * @access public
         * @return void
         */
        public static function set_fortnox_order_vouchernumber(&$order, $fortnox_payment_vouchernumber)
        {
            if ($fortnox_payment_vouchernumber != "") {
                if ($order->meta_exists('_fortnox_order_vouchernumber')) {
                    $order->update_meta_data('_fortnox_order_vouchernumber', $fortnox_payment_vouchernumber);
                } else {
                    $order->add_meta_data('_fortnox_order_vouchernumber', $fortnox_payment_vouchernumber, true);
                }
            } else {
                $order->delete_meta_data('_fortnox_order_vouchernumber');
            }
            $order->save_meta_data();
        }

        /**
         * get_fortnox_order_vouchernumber
         *
         * If the order has a Fortnox Order DocumentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_fortnox_order_vouchernumber($order_id)
        {
            $order = wc_get_order($order_id);
            return (($result = $order->get_meta('_fortnox_order_vouchernumber', true)) == "" ? false : $result);
        }

        /**
         * sset_fortnox_payment_vouchernumber function
         *
         * Set the Fortnox Order DocumentNumber on an order
         *
         * @access public
         * @return void
         */
        public static function set_fortnox_payment_vouchernumber(&$order, $fortnox_payment_vouchernumber)
        {
            if ($fortnox_payment_vouchernumber != "") {
                if ($order->meta_exists('_fortnox_payment_vouchernumber')) {
                    $order->update_meta_data('_fortnox_payment_vouchernumber', $fortnox_payment_vouchernumber);
                } else {
                    $order->add_meta_data('_fortnox_payment_vouchernumber', $fortnox_payment_vouchernumber, true);
                }
            } else {
                $order->delete_meta_data('_fortnox_payment_vouchernumber');
            }

            $order->save_meta_data();
        }

        /**
         * get_fortnox_payment_vouchernumber
         *
         * If the order has a Fortnox payment documentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_fortnox_payment_vouchernumber($order_id)
        {
            $order = wc_get_order($order_id);
            return (($result = $order->get_meta('_fortnox_payment_vouchernumber', true)) == "" ? false : $result);
        }

        /**
         * set_fortnox_invoice_number
         *
         * Set the Fortnox Invoice DocumentNumber on an order
         *
         * @access public
         * @return void
         */
        public static function set_fortnox_invoice_number(&$order, $fortnox_order_documentnumber)
        {
            $order_id = $order->get_id();

            $fortnox_order_documentnumber = apply_filters('fortnox_hub_set_fortnox_invoice_number', $fortnox_order_documentnumber, $order_id);

            if ($fortnox_order_documentnumber != "") {
                if ($order->meta_exists('_fortnox_invoice_number')) {
                    $order->update_meta_data('_fortnox_invoice_number', $fortnox_order_documentnumber);
                } else {
                    $order->add_meta_data('_fortnox_invoice_number', $fortnox_order_documentnumber, true);
                }                
            } else {
                $order->delete_meta_data('_fortnox_invoice_number');
            }

            $order->save_meta_data();
        }

        /**
         * get_fortnox_invoce_documentnumber
         *
         * If the order has a Fortnox Order DocumentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_fortnox_invoice_number($order_id)
        {
            $order = wc_get_order($order_id);

            if (!$order) {
                return false;
            }

            $result = $order->get_meta('_fortnox_invoice_number', true);
            if ((!$result)) {
                $result = $order->get_meta('Fortnox Invoice number', true);
            }

            $result = apply_filters('fortnox_hub_get_fortnox_invoice_number', $result, $order_id);

            return $result;
        }

        /**
         * Create currencydata if the order is paid via stripe
         *
         * Set the Fortnox Order DocumentNumber on an order
         *
         * @param WC_Order $order
         *
         * @access public
         * @return void
         */
        public static function create_currency_payment_data($order)
        {
            $stripe_currency = $order->get_meta('_stripe_currency', true);
            $order_currency = $order->get_currency();
            $woo_cost = $order->get_total();

            WC_FH()->logger->add(sprintf('create_currency_payment_data (%s): Order total is %s %s', $order->get_id(), $order_currency, $woo_cost));

            if ($stripe_currency && $stripe_currency == 'SEK' && $order_currency != 'SEK' && empty($order->get_refunds())) {
                $stripe_cost = $order->get_meta('_stripe_fee', true) + $order->get_meta('_stripe_net', true);
                $currency_rate = floatval($stripe_cost / $woo_cost);

                WC_FH()->logger->add(sprintf('create_currency_payment_data (%s): Stripe total is %s %s', $order->get_id(), $stripe_currency, $stripe_cost));

                if ($currency_rate > 0) {
                    return array(
                        'CurrencyRate' => $currency_rate,
                        'CurrencyUnit' => 1,
                    );
                }
            }

            if ($order_currency != 'SEK') {
                return array(
                    'CurrencyUnit' => 'API_BLANK',
                );
            }

            return array(
                'CurrencyRate' => 1,
                'CurrencyUnit' => 1,
            );
        }

        /**
         * set_fortnox_order_documentnumber function
         *
         * Set the Fortnox Order DocumentNumber on an order
         *
         * @access public
         * @return void
         */

        public static function set_fortnox_order_documentnumber(&$order, $fortnox_order_documentnumber)
        {
            if ($fortnox_order_documentnumber != "") {
                if ($order->meta_exists('_fortnox_order_documentnumber')) {
                    $order->update_meta_data('_fortnox_order_documentnumber', $fortnox_order_documentnumber);
                } else {
                    $order->add_meta_data('_fortnox_order_documentnumber', $fortnox_order_documentnumber, true);
                }
            } else {
                $order->delete_meta_data('_fortnox_order_documentnumber');
            }

            $order->save_meta_data();
        }

        /**
         * get_fortnox_order_documentnumber
         *
         * If the order has a Fortnox Order DocumentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_fortnox_order_documentnumber($order_id)
        {
            $order = wc_get_order($order_id);

            if (!$order) {
                return '';
            }

            $result = $order->get_meta('_fortnox_order_documentnumber', true);
            if (!$result) {
                $result = $order->get_meta('FORTNOX_ORDER_DOCUMENTNUMBER', true);
            }

            return ($result == "" ? false : $result);
        }

        public static function clean_fortnox_text($str, $max_len = false, $empty = '')
        {
            $re = '/[^\p{L}\’\\\\\x{030a}a-zåäöéáœæøüA-ZÅÄÖÉÁÜŒÆØ0-9 –:\.`´’,;\^¤#%§£$€¢¥©™°&\/\(\)=\+\-\*_\!?²³®½\@\x{00a0}\n\r]*/u';
            $subst = '';

            $result = preg_replace($re, $subst, $str);

            if ($max_len !== false) {
                $result = substr($result, 0, $max_len);
            }

            return empty($result) ? $empty : self::normalize_fortnox_text($result);
        }

        public static function normalize_fortnox_text($str) {
            $rules_array = [
                ['/&amp;/','&']
            ];

            foreach ($rules_array as $rule) {
                $str = preg_replace($rule[0], $rule[1], $str);
            }

            return $str;
        }

        public static function get_product_categories()
        {
            $cat_args = array(
                'orderby' => 'name',
                'order' => 'asc',
                'hide_empty' => false,
            );
            return get_terms('product_cat', $cat_args);
        }

        public static function get_category_options()
        {
            $category_options = array();
            $product_categories = self::get_product_categories();

            if (!empty($product_categories)) {
                foreach ($product_categories as $category) {
                    $category_options[$category->slug] = $category->name;
                }
            }

            return $category_options;
        }

        public static function is_izettle($order)
        {
            if ('shop_order_refund' == $order->get_type()) {
                $parent_id = $order->get_parent_id();
                $parent = wc_get_order($parent_id);
                return in_array($parent->get_created_via(), array('izettle', 'zettle'));
            } else {
                return in_array($order->get_created_via(), array('izettle', 'zettle'));
            }
        }

        public static function set_fortnox_article_number($product, $article_number)
        {
            $product->set_sku(apply_filters('fortnox_set_sku', $article_number, $product));
        }

        public static function get_fortnox_article_number($product)
        {
            return apply_filters('fortnox_get_sku', $product->get_sku('edit'), $product);
        }

        /**
         * Find a WooCommerce product from a Fortnox Article
         *
         * @since 1.0.0
         *
         * @param string $article_number A Fortnox article number
         *
         * @return int $product_id A Wocommerce product id or 0 if not found
         */
        public static function get_product_id_from_article_number($article_number)
        {
            if (!empty($article_number)) {
                return wc_get_product_id_by_sku($article_number);
            }

            return 0;
        }

        public static function decode_external_reference($external_reference)
        {
            return strstr(WCFH_Util::encode_external_reference($external_reference), ':', true);
        }

        public static function encode_external_reference($external_reference)
        {
            $cost_center = get_option('fortnox_cost_center');
            $project = get_option('fortnox_project');
            return implode(':', array($external_reference, $cost_center, $project));
        }

        /**
         * Set_fortnox_customer_number function
         *
         * Set the Fortnox CustomerNumber on an order
         *
         * @access private
         * @return void
         */
        public static function set_fortnox_customer_number(&$order, $fortnox_customer_number)
        {
            if ($fortnox_customer_number != "") {
                if ($order->meta_exists('_fortnox_customer_number')) {
                    $order->update_meta_data('_fortnox_customer_number', $fortnox_customer_number);
                } else {
                    $order->add_meta_data('_fortnox_customer_number', $fortnox_customer_number, true);
                }
            } else {
                $order->delete_meta_data('_fortnox_customer_number');
            }

            $order->save_meta_data();
        }

        /**
         * get_fortnox_customer_number
         *
         * If the order has a Fortnox CustomerNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         */
        public static function get_fortnox_customer_number($order)
        {
            if (!WCFH_Util::is_izettle($order)) {
                return ($order->get_meta('_fortnox_customer_number', true));
            } else {
                return get_option('fortnox_izettle_customer_number', false);
            }
        }

        /*
         * Inserts a new key/value before the key in the array.
         *
         * @param $key
         *   The key to insert before.
         * @param $array
         *   An array to insert in to.
         * @param $new_key
         *   The key to insert.
         * @param $new_value
         *   An value to insert.
         *
         * @return
         *   The new array if the key exists, FALSE otherwise.
         *
         * @see array_insert_after()
         */
        public static function array_insert_before($key, array&$array, $new_key, $new_value)
        {
            if (array_key_exists($key, $array)) {
                $new = array();
                foreach ($array as $k => $value) {
                    if ($k === $key) {
                        $new[$new_key] = $new_value;
                    }
                    $new[$k] = $value;
                }
                return $new;
            }
            return false;
        }

        /*
         * Inserts a new key/value after the key in the array.
         *
         * @param $key
         *   The key to insert after.
         * @param $array
         *   An array to insert in to.
         * @param $new_key
         *   The key to insert.
         * @param $new_value
         *   An value to insert.
         *
         * @return
         *   The new array if the key exists, FALSE otherwise.
         *
         * @see array_insert_before()
         */
        public static function array_insert_after($key, array&$array, $new_key, $new_value)
        {
            if (array_key_exists($key, $array)) {
                $new = array();
                foreach ($array as $k => $value) {
                    $new[$k] = $value;
                    if ($k === $key) {
                        $new[$new_key] = $new_value;
                    }
                }
                return $new;
            }
            return false;
        }

        public static function maybe_get_option($option, $default)
        {
            if (false === $default) {
                return get_option($option);
            }
            return '';
        }

        public static function check_sync_config($ps_sync = false, $ps_pricelist = false, $order_creates = false, $pr_sync = false, $pr_pricelist = false, $product_stocklevel = false)
        {
            $ps_sync = WCFH_Util::maybe_get_option('fortnox_sync_from_fortnox_automatically', $ps_sync);
            $ps_pricelist = WCFH_Util::maybe_get_option('fortnox_process_price', $ps_pricelist);
            $pr_sync = WCFH_Util::maybe_get_option('fortnox_create_products_automatically', $pr_sync);
            $pr_pricelist = WCFH_Util::maybe_get_option('fortnox_wc_product_pricelist', $pr_pricelist);

            if ('yes' == $ps_sync && 'yes' == $pr_sync) {
                if ('' != $ps_pricelist && '' != $pr_pricelist && $ps_pricelist != $pr_pricelist) {
                    return __('When syncing both "product" and "price & stocklevel" automatically they must be using the same pricelist. Automated syncing stopped.', 'woo-fortnox-hub');
                }
            }

            return false;
        }

        public static function datetime_display($datetime)
        {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
        }

        public static function service_message()
        {
            $valid_to = strtotime(get_option('fortnox_valid_to'));
            $now = time();
            if ($valid_to && $now > $valid_to) {
                return (object) array(
                    'message' => sprintf(__('Your BjornTech Fortnox Hub service expired %s, go to <a href="%s">our webshop</a> to purchase a subscription', 'woo-fortnox-hub'), WCFH_Util::datetime_display($valid_to), self::get_purchase_link()),
                    'type' => 'error',
                );
            }
            return false;
        }

        /**
         * @return string
         */
        public static function get_purchase_link()
        {
            $authorization_code = get_option('fortnox_authorization_code');
            return 'https://bjorntech.com/sv/produkt/fortnox-1000/?token=' . $authorization_code . "&utm_source=wp-fortnox&utm_medium=plugin&utm_campaign=product";
        }

        public static function check_if_invoice_already_created($id, $check_cancel = true)
        {
            $invoices = WC_FH()->fortnox->getInvoicesByExternalInvoiceReference1($id);
            if ($invoices["MetaInformation"]["@TotalResources"] > 0) {
                foreach ($invoices['Invoices'] as $invoice) {
                    if (!$check_cancel || !rest_sanitize_boolean($invoice['Cancelled'])) {
                        return WC_FH()->fortnox->get_invoice($invoice['DocumentNumber']);
                    }
                }
            }
            return false;
        }

        public static function check_if_order_already_created($id, $check_cancel = true)
        {
            $orders = WC_FH()->fortnox->getOrdersByExternalInvoiceReference1($id);
            if ($orders["MetaInformation"]["@TotalResources"] > 0) {
                foreach ($orders['Orders'] as $order) {
                    if (!$check_cancel || !rest_sanitize_boolean($order['Cancelled'])) {
                        return WC_FH()->fortnox->get_order($order['DocumentNumber']);
                    }
                }
            }
            return false;
        }

        public static function wc_version_check($version = '4.0')
        {
            if (class_exists('WooCommerce')) {
                global $woocommerce;
                if (version_compare($woocommerce->version, $version, ">=")) {
                    return true;
                }
            }
            return false;
        }

        public static function prices_include_tax()
        {
            return (wc_tax_enabled() && 'yes' == get_option('woocommerce_prices_include_tax'));
        }

        /**
         * Checks what type of interaction to perform with Fortnox
         */
        public static function fortnox_wc_order_creates($order)
        {
            if (!is_object($order)) {
                $order = wc_get_order($order);
            }

            $creates = get_option('fortnox_woo_order_creates');
            if ('order' == $creates && WCFH_Util::is_izettle($order)) {
                return 'invoice';
            }
            return apply_filters('fortnox_wc_order_creates', $creates, $order);
        }

        public static function get_available_payment_gateways()
        {
            $available_gateways = array();

            if (WC()->payment_gateways()) {
                $wc_payment_gateways = WC_Payment_Gateways::instance();
                foreach ($wc_payment_gateways->payment_gateways() as $gateway) {
                    if (wc_string_to_bool($gateway->enabled)) {
                        $available_gateways[$gateway->id] = $gateway;
                    }
                }
            }

            return apply_filters('fortnox_payment_gateways', $available_gateways);
        }

        public static function get_fortnox_scopes() {
            $scopes = [
              "archive", 
              "bookkeeping", 
              "costcenter", 
              "companyinformation", 
              "connectfile",
              "currency", 
              "customer", 
              "invoice",
              "noxfinansinvoice", 
              "deletevoucher", 
              "article", 
              "order",
              "inbox",
              "payment", 
              "price", 
              "print", 
              "profile", 
              "project", 
              "settings", 
              "supplier", 
              "supplierinvoice", 
              "warehouse"
            ];
            return implode("%20", $scopes);
          }

          public static function map_customer_nox_delivery_method($order) {
            $fn_customer_id = WCFH_Util::get_fortnox_customer_number($order);
            $customer = WC_FH()->fortnox->get_customer_by_id($fn_customer_id);
            $customer_delivery_method = $customer['DefaultDeliveryTypes']['Invoice'];
            
            switch ($customer_delivery_method) {
              case "PRINT":
                $nox_send_method = "LETTER";
                break;
              case "EMAIL":
                $nox_send_method = "EMAIL";
                break;
              case "ELECTRONICINVOICE":
                $nox_send_method = "EINVOICE";
                break;
              default:
                $nox_send_method = false;
            }

            WC_FH()->logger->add(sprintf('map_customer_nox_delivery_method (%s): Found %s as customer delivery method - using %s as NOX delivery method', $order->get_id(), $customer_delivery_method, ($nox_send_method ? $nox_send_method : 'false')));
            
            return $nox_send_method;
          }
          

        public static function is_european_country($country)
        {
            $countries = new WC_Countries();
            return in_array($country, $countries->get_european_union_countries('eu_vat'));
        }

        public static function get_countries()
        {
            $countries = new WC_Countries();
            return $countries->get_countries();
        }

        public static function do_not_queue_requests()
        {

            if (is_admin() && !wc_string_to_bool(get_option('fortnox_queue_admin_requests'))) {
                return true;
            } else {
                return false;
            }
        }

        public static function get_accounting_method()
        {
            if (!empty($financial_year = apply_filters('fortnox_get_financial_year', array(), time()))) {
                $accounting_method = reset($financial_year)['AccountingMethod'];
            } else {
                $accounting_method = '';
            }

            return $accounting_method;
        }

        public static function get_order_statuses()
        {
            $order_statuses = array();

            foreach (wc_get_order_statuses() as $slug => $name) {
                $order_statuses[str_replace('wc-', '', $slug)] = $name;
            }

            return $order_statuses;
        }

        public static function weight_to_grams($weight, $unit = 'kg')
        {
            $weight = (float) $weight;

            switch ($unit) {
                case 'kg':
                    $response = $weight * 1000;
                    break;
                case 'lbs':
                    $response = $weight * 453.59237;
                    break;
                case 'oz':
                    $response = $weight * 28.3495231;
                    break;
                case 'g':
                default:
                    $response = $weight;
            }

            return $response;
        }

        public static function weight_from_grams($weight, $unit = 'kg')
        {
            $response = $weight;

            if (is_numeric($weight)) {
                switch ($unit) {
                    case 'kg':
                        $response = $weight / 1000;
                        break;
                    case 'lbs':
                        $response = $weight / 453.59237;
                        break;
                    case 'oz':
                        $response = $weight / 28.3495231;
                        break;
                    case 'g':
                    default:
                        $response = $weight;
                }
            }

            return $response;
        }

        public static function dimension_to_millimeters($dimension, $unit = 'cm')
        {
            $response = $dimension;

            if (is_numeric($dimension)) {
                switch ($unit) {
                    case 'm':
                        $response = $dimension * 1000;
                        break;
                    case 'cm':
                        $response = $dimension * 10;
                        break;
                    case 'in':
                        $response = $dimension * 25.4;
                        break;
                    case 'yd':
                        $response = $dimension * 914.4;
                        break;
                }
            }

            return $response;
        }

        public static function dimension_from_millimeters($dimension, $unit = 'cm')
        {
            $response = $dimension;

            if (is_numeric($dimension)) {
                switch ($unit) {
                    case 'm':
                        $response = $dimension / 1000;
                        break;
                    case 'cm':
                        $response = $dimension / 10;
                        break;
                    case 'in':
                        $response = $dimension / 25.4;
                        break;
                    case 'yd':
                        $response = $dimension / 914.4;
                        break;
                }
            }

            return $response;
        }

        public static function display_name($id)
        {
            switch ($id) {
                case 'wcfh_sync_wc_products':
                    return __('WooCommerce products', 'woo-izettle-integration');
                    break;
                case 'wcfh_sync_fn_products':
                    return __('Fortnox articles', 'woo-izettle-integration');
                    break;
            }
            return '';
        }

        public static function get_processing_queue($group)
        {
            return as_get_scheduled_actions(
                array(
                    'group' => $group,
                    'status' => ActionScheduler_Store::STATUS_PENDING,
                    'claimed' => false,
                    'per_page' => -1,
                ),
                'ids'
            );
        }

        public static function display_sync_button($id, $class = '')
        {
            if (!empty($processing_queue = self::get_processing_queue($id))) {
                echo '<div id=' . $id . '_status name="' . $id . '" class="wcfh_processing_status" ></div>';
                $button_text = __('Cancel', 'woo-fortnox-hub');
            } else {
                $button_text = __('Start', 'woo-fortnox-hub');
            }

            echo '<div id=' . $id . '_titledesc>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc ' . $class . '">';
            echo '<label for="' . $id . '">' . __('Action', 'woo-fortnox-hub') . '</label>';
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            echo '<button id="' . $id . '" class="button wcfh_processing_button">' . $button_text . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        public static function get_term_by_slug($slug)
        {
            $term = get_term_by('slug', $slug, 'product_cat');
            return $term->term_id ? $term->term_id : '';
        }

        /**
         * Check if the product should be synced or nor
         *
         * @since 4.1.0
         *
         * @param WC_Product $product The WooCommerce product to be checked
         *
         * @return bool True if the product can be synced and falese if not
         */
        public static function is_syncable($product)
        {
            $product_id = $product->get_id();

            if ($product->is_type('variation') && ($parent_id = $product->get_parent_id()) && ($parent = wc_get_product($parent_id))) {
                WC_FH()->logger->add(sprintf('is_syncable (%s): Changed check to product parent %s', $product_id, $parent_id));
            } else {
                $parent_id = $product_id;
                $parent = $product;
            }

            $product_type = $parent->get_type();
            $products_include = get_option('fortnox_wc_products_include', array('simple', 'variable'));
            if (!in_array($product_type, $products_include)) {
                WC_FH()->logger->add(sprintf('is_syncable (%s): Product type "%s" is not within "%s"', $product_id, $product_type, implode(',', $products_include)));
                return false;
            }

            $product_statuses = get_option('fortnox_wc_get_product_status', array('publish'));
            $status = $parent->get_status('edit');
            if (!in_array($status, $product_statuses)) {
                WC_FH()->logger->add(sprintf('is_syncable (%s): Product status "%s" is not within "%s"', $product_id, $status, implode(',', $product_statuses)));
                return false;
            }

            $category_ids = $parent->get_category_ids('edit');
            $product_categories = !($product_categories_raw = get_option('fortnox_wc_products_product_categories')) ? array() : array_map('self::get_term_by_slug', $product_categories_raw);

            if (wc_string_to_bool(get_option('fortnox_wc_products_include_subcategories', 'no'))) {
                foreach ($product_categories as $category) {
                    $child_categories = get_term_children($category, 'product_cat');
                    $product_categories = array_merge($product_categories, $child_categories);
                }
                $product_categories = array_unique($product_categories);
            }

            if (!empty($product_categories) && empty(array_intersect($category_ids, $product_categories))) {
                WC_FH()->logger->add(sprintf('is_syncable (%s): Product categories "%s" is not within "%s"', $product_id, implode(',', $category_ids), implode(',', $product_categories)));
                return false;
            }

            return true;
        }

        public static function maybe_get_parent($product)
        {
            //Check if product is null or empty - return false if yes
            if (!$product || empty($product)) {
                return false;
            }

            if (!wc_string_to_bool(get_option('fortnox_wc_product_update_variable_parent'))) {
                return $product;
            }

            if (!$product->is_type('variation')) {
                WC_FH()->logger->add(sprintf('maybe_get_parent (%s): Product is not a variation', $product->get_id()));
                return $product;
            }

            $parent_id = $product->get_parent_id();

            if (!$parent_id) {
                WC_FH()->logger->add(sprintf('maybe_get_parent (%s): Product is a variation but does not have a parent', $product->get_id()));
                return $product;
            }

            $parent = wc_get_product($parent_id);

            if (self::maybe_sync_variants($parent)) {
                WC_FH()->logger->add(sprintf('maybe_get_parent (%s): Product is a variation but parent ("%s") does not have SKU or does not manage stocklevel', $product->get_id(), $parent->get_id()));
                return $product;
            }

            WC_FH()->logger->add(sprintf('maybe_get_parent (%s): Product is a variation and has a parent ("%s") that can be synced', $product->get_id(), $parent->get_id()));
            return $parent;
        }

        public static function maybe_sync_variants($product){
            $product_type = $product->get_type();

            if ('variable' != $product_type) {
                WC_FH()->logger->add(sprintf('maybe_sync_variants (%s): Product is not variable', $product->get_id()));
                return false;
            }

            if (!wc_string_to_bool(get_option('fortnox_wc_product_update_variable_parent'))) {
                WC_FH()->logger->add(sprintf('maybe_sync_variants (%s): Product is variable', $product->get_id()));
                return true;
            }

            //Check if variable product manages stocklevel and not variants
            $manage_stock = $product->get_manage_stock('edit');

            //Check if variable product has SKU
            $article_number = WCFH_Util::get_fortnox_article_number($product);

            //Check if product is virtual
            $virtual = $product->is_virtual();

            if (($manage_stock || $virtual) && $article_number) {
                WC_FH()->logger->add(sprintf('maybe_sync_variants (%s): Variable product has SKU and manages stocklevel or is virtual', $product->get_id()));
                return false;
            } else {
                WC_FH()->logger->add(sprintf('maybe_sync_variants (%s): Variable product does not have SKU or does not manage stocklevel', $product->get_id()));
                return true;
            }
        }

        public static function get_parent_if_variation ($product) {
            if ($product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
                $parent = wc_get_product($parent_id);
                return $parent;
            } else {
                return $product;
            }
        }

        public static function get_tax_rate($product)
        {
            $tax_array = array(
                'country' => WC()->countries->get_base_country(),
                'state' => WC()->countries->get_base_state(),
                'city' => WC()->countries->get_base_city(),
                'postcode' => WC()->countries->get_base_postcode(),
                'tax_class' => $product->get_tax_class(),
            );

            return WC_Tax::find_rates($tax_array);
        }

        public static function maybe_add_vat($price, $product)
        {
            $tax_rates = self::get_tax_rate($product);

            if ($tax_rates) {
                $tax_rate = round(reset($tax_rates)['rate']);

                if (false !== $tax_rate) {
                    $tax_multiplier = 1 + ($tax_rate / 100);
                    $original_price = $price;
                    $price = strval($price * $tax_multiplier);
                } else {
                    WC_FH()->logger->add(sprintf('maybe_add_vat (%s): No VAT tax rate found', $product->get_id()));
                }
            }

            return $price;
        }

        public static function maybe_remove_vat($price, $product)
        {
            $tax_rates = self::get_tax_rate($product);

            if ($tax_rates) {
                $tax_rate = round(reset($tax_rates)['rate']);

                if (false !== $tax_rate) {
                    $tax_multiplier = 1 + ($tax_rate / 100);
                    $original_price = $price;
                    $price = substr(strval($price / $tax_multiplier), 0, 15);
                } else {
                    WC_FH()->logger->add(sprintf('maybe_remove_vat: No VAT tax rate found for product %s', $product->get_id()));
                }
            }

            return $price;
        }

        public static function get_product_types()
        {
            $types = wc_get_product_types();
            if (isset($types['grouped'])) {
                unset($types['grouped']);
            }
            if (isset($types['external'])) {
                unset($types['external']);
            }
            return $types;
        }

        /**
         * Get an array of available variations for the current product.
         * Use our own to get all variations regardless of filtering
         *
         * @param WC_Product $product
         * @return array
         */
        public static function get_all_variations($product)
        {
            $available_variations = array();

            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);

                $available_variations[] = $product->get_available_variation($variation);
            }
            $available_variations = array_values(array_filter($available_variations));

            return $available_variations;
        }

        public static function object_diff(stdClass $obj1, stdClass $obj2): bool
        {
            $array1 = json_decode(json_encode($obj1, JSON_INVALID_UTF8_IGNORE), true);
            $array2 = json_decode(json_encode($obj2, JSON_INVALID_UTF8_IGNORE), true);
            return self::array_diff($array1, $array2);
        }

        public static function array_diff(array $array1, array $array2): bool
        {
            foreach ($array1 as $key => $value) {
                if (array_key_exists($key, $array2)) {
                    if ($value instanceof stdClass) {
                        $r = self::object_diff((object) $value, (object) $array2[$key]);
                        if ($r === true) {
                            return true;
                        }
                    } elseif (is_array($value)) {
                        $r = self::array_diff((array) $value, (array) $array2[$key]);
                        if ($r === true) {
                            return true;
                        }
                    } elseif (is_double($value)) {
                        // required to avoid rounding errors due to the
                        // conversion from string representation to double
                        if (0 !== bccomp($value, $array2[$key], 12)) {
                            WC_FH()->logger->add(sprintf('array_diff: Key {%s} was changed from "%s" to "%s"', $key, $array2[$key], $value));
                            return true;
                        }
                    } else {
                        if ($value != $array2[$key]) {
                            WC_FH()->logger->add(sprintf('array_diff: Key {%s} was changed from "%s" to "%s"', $key, $array2[$key], $value));
                            return true;
                        }
                    }
                } else {
                    WC_FH()->logger->add(sprintf('array_diff: Key {%s} does not exist in old data', $array1[$key]));
                    return true;
                }
            }
            return false;
        }

        public static function get_option_key($key, $default_key)
        {
            $update_key = get_option("fortnox_metadata_mapping_{$key}", $default_key ? $default_key : "_fortnox_{$key}");
            return $update_key;
        }

        public static function decamelize($string)
        {
            return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
        }

        public static function get_metadata($product, $fortnox_key, $default_key = false)
        {
            if (!is_object($product)) {
                $product = wc_get_product($product);
            }

            $key = self::decamelize($fortnox_key);
            return $product->get_meta(apply_filters('fortnox_get_metadata_key', self::get_option_key($key, $default_key), $product, $fortnox_key, $default_key), true, 'edit');
        }

        public static function update_metadata($product, $fortnox_key, $metadata, $save = false, $default_key = false)
        {
            if (!is_object($product)) {
                $product = wc_get_product($product);
            }

            $key = self::decamelize($fortnox_key);

            $product->update_meta_data(apply_filters('fortnox_update_metadata_key', self::get_option_key($key, $default_key), $product, $fortnox_key, $default_key, $metadata), $metadata);

            if ($save) {
                $product->save();
            }
        }

        public static function delete_metadata($product, $fortnox_key, $default_key = false)
        {
            if (!is_object($product)) {
                $product = wc_get_product($product);
            }

            $key = self::decamelize($fortnox_key);

            $product->delete_meta_data(apply_filters('fortnox_delete_metadata_key', self::get_option_key($key, $default_key), $product, $fortnox_key, $default_key));
        }

        public static function add_failed_order($order_id)
        {
            $failed_orders = ($data = get_site_transient('fortnox_failed_orders')) ? $data : array();
            $failer_orders[$order_id] = time();
            set_site_transient('fortnox_failed_orders', $failed_orders);
        }

        public static function clear_failed_order($order_id)
        {
            $failed_orders = ($data = get_site_transient('fortnox_failed_orders')) ? $data : array();
            if (array_key_exists($order_id, $failed_orders)) {
                $failed_orders = array_diff($failed_orders, array($order_id => ''));
                if (empty($failed_orders)) {
                    delete_site_transient('fortnox_failed_orders');
                } else {
                    set_site_transient('fortnox_failed_orders', $failed_orders);
                }
            }
        }

        public static function get_wcpbc_pricing_zone_id ($zone) {
            if (method_exists('WCPBC_Pricing_Zone','get_zone_id')) {
                return $zone->get_zone_id();
            } else {
                return $zone->get_id();
            }
        }

        public static function valid_housework_types()
        {
            $type_none = array(
                '' => __("None", 'woo-fortnox-hub'),
            );
            return array_merge($type_none, self::valid_housework_types_rot(), self::valid_housework_types_rut(), self::valid_housework_types_green());
        }

        public static function valid_housework_types_rot()
        {
            return array(
                'rot_CONSTRUCTION' => __("Rot - Construction", 'woo-fortnox-hub'),
                'rot_ELECTRICITY' => __("Rot - Electricity", 'woo-fortnox-hub'),
                'rot_GLASSMETALWORK' => __("Rot - Glass & metal work", 'woo-fortnox-hub'),
                'rot_GROUNDDRAINAGEWORK' => __("Rot - Grounddrainage work", 'woo-fortnox-hub'),
                'rot_MASONRY' => __("Rot - Masonry", 'woo-fortnox-hub'),
                'rot_PAINTINGWALLPAPERING' => __("Rot - Painting & Wallpapering", 'woo-fortnox-hub'),
                'rot_HVAC' => __("Rot - HVAC", 'woo-fortnox-hub'),
                'rot_OTHERCOSTS' => __("Rot - Other costs", 'woo-fortnox-hub'),
            );
        }

        public static function valid_housework_types_rut()
        {
            return array(
                'rut_HOMEMAINTENANCE' => __("Rut - Home mainrenance", 'woo-fortnox-hub'),
                'rut_FURNISHING' => __("Rut - Furnsishing", 'woo-fortnox-hub'),
                'rut_TRANSPORTATIONSERVICES' => __("Rut - Transportation services", 'woo-fortnox-hub'),
                'rut_WASHINGANDCAREOFCLOTHING' => __("Rut - Washing and care of chlothing", 'woo-fortnox-hub'),
                'rut_MAJORAPPLIANCEREPAIR' => __("Rut - Major appliance repair", 'woo-fortnox-hub'),
                'rut_MOVINGSERVICES' => __("Rut - Moving serivices", 'woo-fortnox-hub'),
                'rut_ITSERVICES' => __("Rut - IT services ", 'woo-fortnox-hub'),
                'rut_CLEANING' => __("Rut - Cleaning", 'woo-fortnox-hub'),
                'rut_TEXTILECLOTHING' => __("Rut - Textileclothing", 'woo-fortnox-hub'),
                'rut_SNOWPLOWING' => __("Rut - Snowplowing", 'woo-fortnox-hub'),
                'rut_GARDENING' => __("Rut - Gardening", 'woo-fortnox-hub'),
                'rut_BABYSITTING' => __("Rut - Baysitting", 'woo-fortnox-hub'),
                'rut_OTHERCARE' => __("Rut - Other care", 'woo-fortnox-hub'),
                'rut_OTHERCOSTS' => __("Rut - Other costs", 'woo-fortnox-hub'),
            );
        }

        public static function valid_housework_types_green()
        {
            return array(
                'green_SOLARCELLS' => __("Green - Solar cells", 'woo-fortnox-hub'),
                'green_STORAGESELFPRODUCEDELECTRICTY' => __("Green - Storage selfproduced electricity", 'woo-fortnox-hub'),
                'green_CHARGINGSTATIONELECTRICVEHICLE' => __("Green - Charging station electring vehicle", 'woo-fortnox-hub'),
                'green_OTHERCOSTS' => __("Green - Other costs", 'woo-fortnox-hub'),
            );
        }

        public static function get_invoice_email_information($order)
        {
            if ('yes' == get_option('fortnox_send_customer_email_invoice_' . self::get_payment_method($order, 'get_invoice_email_information'), get_option('fortnox_send_customer_email_invoice'))) {

                if (get_option('fortnox_send_customer_email_invoice_payment_method_specific') == 'yes' ) {
                    return array(
                        "EmailInformation" => array(
                            "EmailAddressFrom" => ($email_from = get_option('fornox_invoice_email_from_' . self::get_payment_method($order, 'get_invoice_email_information'))) ? $email_from : 'API_BLANK',
                            "EmailAddressTo" => ($billing_email = $order->get_billing_email()) ? $billing_email : 'API_BLANK',
                            "EmailSubject" => ($email_subject = get_option('fornox_invoice_email_subject_' . self::get_payment_method($order, 'get_invoice_email_information'))) ? $email_subject : 'API_BLANK',
                            "EmailBody" => ($email_body = get_option('fornox_invoice_email_body_' . self::get_payment_method($order, 'get_invoice_email_information'))) ? $email_body : 'API_BLANK',
                        ),
                    );
                } else {
                    return array(
                        "EmailInformation" => array(
                            "EmailAddressFrom" => ($email_from = get_option('fornox_invoice_email_from')) ? $email_from : 'API_BLANK',
                            "EmailAddressTo" => ($billing_email = $order->get_billing_email()) ? $billing_email : 'API_BLANK',
                            "EmailSubject" => ($email_subject = get_option('fornox_invoice_email_subject')) ? $email_subject : 'API_BLANK',
                            "EmailBody" => ($email_body = get_option('fornox_invoice_email_body')) ? $email_body : 'API_BLANK',
                        ),
                    );
                }
            }

            return array();
        }

        public static function eu_number_is_validated($order)
        {
            if ('true' == $order->get_meta('_vat_number_is_validated', true, 'edit')) {
                return true;
            }

            if ('valid' == $order->get_meta('_vat_number_validated', true, 'edit')) {
                return true;
            }

            if (is_callable($order, 'is_order_eu_vat_exempt')) {
                return $order->is_order_eu_vat_exempt();
            }

            if (function_exists('alg_wc_eu_vat_get_field_id') && (wc_string_to_bool($order->get_meta('is_vat_exempt')) || wc_string_to_bool($order->get_meta('exempt_vat_from_admin'))) ) {
                return true;
            }

            return false;
        }

        public static function get_payment_method($order, $function = '')
        {
            if ($order->get_type() == 'shop_order_refund') {
                return 'shop_refund';
            } else {
                return apply_filters('fortnox_get_order_payment_method', $order->get_payment_method(), $order, $function);
            }
        }

        public static function is_payment_gateway($payment_method)
        {
            return array_key_exists($payment_method, self::get_available_payment_gateways());
        }

        public static function transform_order_rows($rows){
            $new_rows = array();

            $read_only_props = [
                'ContributionPercent',
                'ContributionValue',
                'Total'
            ];

            foreach ($rows as $row) {
                if (!empty($row['OrderedQuantity'])) {
                    $new_row = $row;
                        
                    $new_row['DeliveredQuantity'] = $row['OrderedQuantity'];

                    foreach ($read_only_props as $read_only_prop) {
                        unset($new_row[$read_only_prop]);
                    }

                    $new_rows[] = $new_row;
                }
            }

            return (count($new_rows) < 1) ? false : $new_rows;
        } 

        public static function get_order_by_order_number($order_number)
        {

            if (!$order_number) {
                return null;
            }

            if (!has_filter('woocommerce_order_number') && !wc_string_to_bool(get_option('fortnox_wc_custom_order_number_used'))) {
                return wc_get_order($order_number);
            }

            update_option('fortnox_wc_custom_order_number_used', 'yes');

            WC_FH()->logger->add(sprintf('get_order_by_order_number: Searching for order number %s', $order_number));

            $order_ids = wc_get_orders(array(
                'limit' => -1,
                'type' => 'shop_order',
                'return' => 'ids',
                'status' => array_keys(wc_get_order_statuses()),
                'meta_key' => '_alg_wc_custom_order_number',
                'meta_value' => $order_number,
                'meta_compare' => '=',
            ));

            if (count($order_ids) === 1) {
                WC_FH()->logger->add(sprintf('get_order_by_order_number: Got "%s" as orders for order number %s', implode(',', $order_ids), $order_number));
                return wc_get_order(reset($order_ids));
            }

            if (class_exists('WC_Seq_Order_Number') && method_exists('WC_Seq_Order_Number', 'find_order_by_order_number')) {
                $seq_order_number = WC_Seq_Order_Number::instance()->find_order_by_order_number($order_number);

                WC_FH()->logger->add(sprintf('get_order_by_order_number: Found order number %s with Seq Order Number %s', $order_number, $seq_order_number));

                if ($seq_order_number) {
                    WC_FH()->logger->add(sprintf('get_order_by_order_number: Order %s found', $seq_order_number));
                    return wc_get_order($seq_order_number);
                }
            }


            if (!has_filter('woocommerce_order_number')) {
                WC_FH()->logger->add(sprintf('get_order_by_order_number: Custom order numbers not found - returning order based on normal order number', ));
                return wc_get_order($order_number);
            }

            return null;

        }

        public static function get_cost_centers () {
            $cost_centers = apply_filters('fortnox_get_cost_centers', array());

            $cost_center_options = array();



            foreach ($cost_centers as $cost_center) {

                $is_active = $cost_center['Active'];

                if ($is_active || wc_string_to_bool(get_option('fortnox_get_inactive_cost_centers'))) {
                    $cost_center_options[$cost_center['Code']] = $cost_center['Description'];
                }
            }

            $cost_center_options = array('' => 'No cost center') + $cost_center_options;

            return $cost_center_options;
        }

        public static function get_projects () {
            $projects = apply_filters('fortnox_get_projects', array());

            $project_options = array();

            $accepted_statuses = array('NOTSTARTED', 'ONGOING');

            if (wc_string_to_bool(get_option('fortnox_get_completed_projects'))) {
                $accepted_statuses[] = 'COMPLETED';
            }
            

            foreach ($projects as $project) {
                $project_status = $project['Status'];

                if (in_array($project_status, $accepted_statuses)) {
                    $project_options[$project['ProjectNumber']] = $project['Description'];
                }
            }

            $project_options = array('' => 'No project') + $project_options;

            return $project_options;
        }

        public static function reset_fortnox_invoice($invoice){

            $read_only_fields = array(
                "@url",
                "@urlTaxReductionList",
                "Url",
                "UrlTaxReductionList",
                "AccountingMethod",
                "AdministrationFeeVAT",
                "Balance",
                "BasisTaxReduction",
                "Booked",
                "Cancelled",
                "Credit",
                "CreditInvoiceReference",
                "ContractReference",
                "ContributionPercent",
                "ContributionValue",
                "DocumentNumber",
                "FinalPayDate",
                "Freight",
                "FreightVAT",
                "Gross",
                "HouseWork",
                "InvoicePeriodStart",
                "InvoicePeriodEnd",
                "InvoiceReference",
                "InvoiceRows",
                "LastRemindDate",
                "Net",
                "NoxFinans",
                "OCR",
                "OfferReference",
                "OrderReference",
                "OrganisationNumber",
                "PaymentWay",
                "Reminders",
                "RoundOff",
                "Sent",
                "TaxReduction",
                "TermsOfDelivery",
                "TermsOfPayment",
                "Total",
                "TotalToPay",
                "TotalVAT",
                "VoucherNumber",
                "VoucherSeries",
                "VoucherYear",
                "WarehouseReady"
            );

            foreach ($read_only_fields as $read_only_field) {
                unset($invoice[$read_only_field]);
            }

            return $invoice;
        }

        public static function checkDocument($document) {
            if ($document instanceof Woo_Fortnox_Hub_Lager) {
                return 'lager';
            } elseif ($document instanceof Woo_Fortnox_Hub_Order) {
                return 'order';
            } elseif ($document instanceof Woo_Fortnox_Hub_Invoice) {
                return 'invoice';
            } else {
                throw new Fortnox_Exception('The object is not an instance of Woo_Fortnox_Hub_Lager, Woo_Fortnox_Hub_Order, or Woo_Fortnox_Hub_Invoice');
            }
        }

        public static function fortnox_get_order (&$order) {
            if (is_numeric($order)) {
               return wc_get_order($order);
            }

            return $order;
        }
    }
}
