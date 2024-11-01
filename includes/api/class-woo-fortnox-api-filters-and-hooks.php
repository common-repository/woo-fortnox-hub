<?php

/**
 * class Woo_Fortnox_API_Hooks_And_Filters
 *
 * Version 1.0
 *
 */

defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_API_Hooks_And_Filters', false)) {

    class Woo_Fortnox_API_Hooks_And_Filters
    {
        private $fortnox;

        public function __construct()
        {
            add_filter('fortnox_is_connected', array($this, 'is_connected'));
            add_filter('fortnox_get_article', array($this, 'get_article'), 10, 2);
            add_filter('fortnox_update_article', array($this, 'update_article'), 10, 3);
            add_filter('fortnox_inbound_delivery', array($this, 'inbound_delivery'), 10, 4);
            add_filter('fortnox_outbound_delivery', array($this, 'outbound_delivery'), 10, 4);
            add_filter('fortnox_get_pricelist', array($this, 'get_pricelist'));
            add_filter('fortnox_get_price', array($this, 'get_price'), 10, 3);
            add_filter('fortnox_get_cost_centers', array($this, 'get_cost_centers'));
            add_filter('fortnox_get_projects', array($this, 'get_projects'));
            add_filter('fortnox_get_account_selection', array($this, 'get_account_selection'), 10, 2);
            add_filter('fortnox_get_modes_of_payments', array($this, 'get_modes_of_payments'), 10, 1);
            add_filter('fortnox_get_terms_of_payments', array($this, 'get_terms_of_payments'), 10, 1);
            add_filter('fortnox_get_terms_of_deliveries', array($this, 'get_terms_of_deliveries'), 10, 1);
            add_filter('fortnox_get_way_of_deliveries', array($this, 'get_way_of_deliveries'), 10, 1);
            add_filter('fortnox_get_print_templates', array($this, 'get_print_templates'), 10, 2);
            add_filter('fortnox_get_financial_years', array($this, 'get_financial_years'));
            add_filter('fortnox_get_financial_year', array($this, 'get_financial_year'), 10, 2);
            add_filter('fortnox_get_company_information', array($this, 'get_company_information'), 10, 1);
            add_filter('fortnox_get_units', array($this, 'get_units'), 10, 1);
            add_filter('fortnox_get_first_customer_by_organisation_number', array($this, 'get_first_customer_by_organisation_number'), 10, 2);
            add_filter('fortnox_get_voucher_series', array($this, 'get_voucher_series'), 10, 1);
            add_filter('fortnox_warehouse_activated', array($this, 'warehouse_activated'), 10, 1);
            add_filter('fortnox_check_order_warehouse_ready', array($this, 'check_order_warehouse_ready'), 10, 2);
            
            add_action('fortnox_clear_cache', array($this, 'clear_cache'));
            add_action('fortnox_trigger_warehouse_ready', array($this, 'trigger_warehouse_ready'), 10, 3);
            add_action('fortnox_set_order_delivery_status', array($this, 'set_order_delivery_status'), 10, 1);

            add_action("update_option_fortnox_hub_use_v2_api", array($this, "update_option_fortnox_hub_use_v2_api"), 10, 3);
            add_action("add_option_fortnox_hub_use_v2_api", array($this, "add_option_fortnox_hub_use_v2_api"), 10, 2);


        }

        public function is_connected($is_connected)
        {
            return get_option('fortnox_refresh_token') ? true : false;
        }

        public function add_option_fortnox_hub_use_v2_api($option, $value) {
            WC_FH()->logger->add('add_option_fortnox_hub_use_v2_api: ' . $option . ' - ' . $value);

            if ($option != 'fortnox_hub_use_v2_api') {
                return;
            }

            if ($value == 'yes') {
                try {
                    WC_FH()->logger->add('add_option_fortnox_hub_use_v2_api: Switching to new API - Updating access token');
                    WC_FH()->initAPIClient();
                    delete_fortnox_hub_transient('fortnox_accesstoken');
                    WC_FH()->fortnox->get_access_token();
                } catch (Fortnox_API_Exception $e) {
                    WC_FH()->logger->add(print_r($e, true));
                    $e->write_to_logs();
                    $error = $e->getMessage();
                } finally {
                    return;
                }
            }

            if ($value == 'no') {
                WC_FH()->logger->add('add_option_fortnox_hub_use_v2_api: Switching to old API - Clearing refresh token');
                update_option('fortnox_refresh_token', '');
                return;
            }

            return;
        }

        public function update_option_fortnox_hub_use_v2_api($old_value, $new_value, $option)
        {
            WC_FH()->logger->add('update_option_fortnox_hub_use_v2_api: ' . $option . ' - ' . $old_value . ' - ' . $new_value);

            if ($option != 'fortnox_hub_use_v2_api') {
                return;
            }

            if ($old_value == $new_value) {
                return;
            }

            if ($new_value == 'yes' && ($old_value == 'no' || $old_value == '' || $old_value == false)) {

                try {
                    WC_FH()->logger->add('update_option_fortnox_hub_use_v2_api: Switching to new API - Updating access token');
                    WC_FH()->initAPIClient();
                    delete_fortnox_hub_transient('fortnox_accesstoken');
                    WC_FH()->fortnox->get_access_token();
                } catch (Fortnox_API_Exception $e) {
                    WC_FH()->logger->add(print_r($e, true));
                    $e->write_to_logs();
                    $error = $e->getMessage();
                } finally {
                    return;
                }
            }

            if ($new_value == 'no' && $old_value == 'yes') {
                WC_FH()->logger->add('update_option_fortnox_hub_use_v2_api: Switching to old API - Clearing refresh token');
                update_option('fortnox_refresh_token', '');
                return;
            }

            return;
        }

        public function get_price($price, $product, $pricelist)
        {
            $article_number = WCFH_Util::get_fortnox_article_number($product);
            try {
                $prices = WC_FH()->fortnox->get_prices($article_number, $pricelist);
                $price = $prices['Price'];
            } catch (Fortnox_API_Exception $e) {
                if (404 != $e->getCode()) {
                    throw new $e($e->getMessage(), $e->getCode(), $e);
                }
            }
            return $price;
        }

        public function get_article($response, $article_number)
        {

            if (!$article_number) {
                return false;
            }

            try {
                return WC_FH()->fortnox->get_article($article_number);
            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs('get_article');
            }
            return false;
        }

        public function update_article($response, $article_number, $article)
        {

            if (!$article_number) {
                return false;
            }

            try {
                return WC_FH()->fortnox->update_article($article_number, $article);
            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs('update_article');
            }
            return false;
        }

        public function check_order_warehouse_ready($response, $order_id) {
            if (!$order_id) {
                return false;
            }

            try {
                $fn_order = WC_FH()->fortnox->getOrder($order_id);

                if ((!is_null($warehouseReady = $fn_order['WarehouseReady'])) && !empty($warehouseReady)) {
                    return rest_sanitize_boolean($warehouseReady);
                } else {
                    return false;
                }


            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs('get_order_warehouseready');
            }
            return false;
        }

        public function set_order_delivery_status($order_id) {
            if (!$order_id) {
                return false;
            }

            $fn_order = WC_FH()->fortnox->getOrder($order_id);

            $rows = WCFH_Util::transform_order_rows($fn_order['OrderRows']);

            $order_data = array();

            if ($rows) {
                $order_data['OrderRows'] = $rows;
            }

            $order_data['DeliveryState'] = "delivery";

            WC_FH()->logger->add(sprintf('set_order_delivery_status - %s', json_encode($order_data)));

            try {
                return WC_FH()->fortnox->update_order($order_id, $order_data);
            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs('get_order_warehouseready');
            }
            return false;


        }

        public function inbound_delivery($response, $data, $type = false, $id = false)
        {
            try {
                return WC_FH()->fortnox->inbound_delivery($data, $type, $id);
            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs('inbound_delivery');
            }
            return $response;
        }

        public function outbound_delivery($response, $data, $type = false, $id = false)
        {
            try {
                return WC_FH()->fortnox->outbound_delivery($data, $type, $id);
            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs('outbound_delivery');
            }
            return $response;
        }

        public function get_pricelist()
        {
            try {
                $pricelists = get_fortnox_hub_transient('fortnox_pricelists');
                if (!is_array($pricelists)) {
                    $pricelists = WC_FH()->fortnox->get_pricelists();
                    set_fortnox_hub_transient('fortnox_pricelists', $pricelists, HOUR_IN_SECONDS);
                }
                return $pricelists;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return false;
        }

        public function get_cost_centers($cost_centers)
        {
            try {
                $cost_centers = get_fortnox_hub_transient('fortnox_cost_centers');
                if (!is_array($cost_centers)) {
                    $cost_centers = WC_FH()->fortnox->get_cost_centers();
                    set_fortnox_hub_transient('fortnox_cost_centers', $cost_centers, HOUR_IN_SECONDS);
                }
                return $cost_centers;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $cost_centers;
        }

        public function get_projects($projects)
        {
            try {
                $projects = get_fortnox_hub_transient('fortnox_projects');
                if (!is_array($projects)) {
                    $projects = WC_FH()->fortnox->get_projects();
                    set_fortnox_hub_transient('fortnox_projects', $projects, HOUR_IN_SECONDS);
                }
                return $projects;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $projects;
        }

        public function get_account_selection($account_selection, $use_default = true)
        {
            try {

                if (true === $use_default) {
                    $account_selection[''] = __('Use Fortnox default', 'woo-fortnox-hub');
                } else {
                    $account_selection[''] = __('No account selected', 'woo-fortnox-hub');
                }

                $accounts = get_fortnox_hub_transient('fortnox_all_accounts');
                if (!is_array($accounts)) {
                    $accounts = WC_FH()->fortnox->getAllAccounts();
                    set_fortnox_hub_transient('fortnox_all_accounts', $accounts, HOUR_IN_SECONDS);
                }
                foreach ($accounts as $account) {
                    if ($account['Active'] == true) {
                        $account_selection[$account['Number']] = ($account['Number'] . ' - ' . $account['Description']);
                    }
                }
                ksort($account_selection);
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $account_selection;
        }

        public function get_modes_of_payments($modes_of_payments)
        {
            try {
                $modes_of_payments = get_fortnox_hub_transient('fortnox_modes_of_payments');
                if (!is_array($modes_of_payments)) {
                    $modes_of_payments = WC_FH()->fortnox->get_modes_of_payments();
                    set_fortnox_hub_transient('fortnox_modes_of_payments', $modes_of_payments, HOUR_IN_SECONDS);
                }
                return $modes_of_payments;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $modes_of_payments;
        }

        public function get_terms_of_payments($terms_of_payments)
        {
            try {
                $terms_of_payments = get_fortnox_hub_transient('fortnox_terms_of_payments');
                if (!is_array($terms_of_payments)) {
                    $terms_of_payments = WC_FH()->fortnox->get_terms_of_payments();
                    set_fortnox_hub_transient('fortnox_terms_of_payments', $terms_of_payments, HOUR_IN_SECONDS);
                }
                return $terms_of_payments;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $terms_of_payments;
        }

        public function get_terms_of_deliveries($terms_of_deliveries)
        {
            try {
                $terms_of_deliveries = get_fortnox_hub_transient('fortnox_terms_of_deliveries');
                if (!is_array($terms_of_deliveries)) {
                    $terms_of_deliveries = WC_FH()->fortnox->get_terms_of_deliveries();
                    set_fortnox_hub_transient('fortnox_terms_of_deliveries', $terms_of_deliveries, HOUR_IN_SECONDS);
                }
                return $terms_of_deliveries;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $terms_of_deliveries;
        }

        public function get_way_of_deliveries($way_of_deliveries)
        {
            try {
                $way_of_deliveries = get_fortnox_hub_transient('fortnox_way_of_deliveries');
                if (!is_array($way_of_deliveries)) {
                    $way_of_deliveries = WC_FH()->fortnox->get_way_of_deliveries();
                    set_fortnox_hub_transient('fortnox_way_of_deliveries', $way_of_deliveries, HOUR_IN_SECONDS);
                }
                return $way_of_deliveries;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $way_of_deliveries;
        }

        public function get_print_templates($print_templates, $type = '')
        {
            try {
                $print_templates = get_fortnox_hub_transient('fortnox_print_templates_' . $type);
                if (!is_array($print_templates)) {
                    $print_templates = WC_FH()->fortnox->get_print_templates($type);
                    set_fortnox_hub_transient('fortnox_print_templates_' . $type, $print_templates, HOUR_IN_SECONDS);
                }
                return $print_templates;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $print_templates;
        }

        public function get_financial_years($financial_years)
        {
            try {
                $financial_years = get_fortnox_hub_transient('fortnox_financial_years');
                if (!is_array($financial_years)) {
                    $financial_years = WC_FH()->fortnox->get_financial_years();
                    set_fortnox_hub_transient('fortnox_financial_years', $financial_years, DAY_IN_SECONDS);
                }
                return $financial_years;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $financial_years;
        }

        public function get_financial_year($financial_year, $date)
        {
            try {
                $financial_year = get_fortnox_hub_transient('fortnox_financial_year');
                if (!is_array($financial_year)) {
                    $datestring = date('Y-m-d', $date);
                    $financial_year = WC_FH()->fortnox->get_financial_years(null, $datestring);
                    set_fortnox_hub_transient('fortnox_financial_year', $financial_year, DAY_IN_SECONDS);
                }
                return $financial_year;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $financial_year;
        }

        public function get_company_information($company)
        {
            try {
                $company = WC_FH()->fortnox->get_company_information();
                return $company;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $company;
        }

        public function get_units($units)
        {

            $units = get_fortnox_hub_transient('fortnox_units');
            if (!is_array($units)) {
                $units = WC_FH()->fortnox->get_units();
                set_fortnox_hub_transient('fortnox_units', $units, DAY_IN_SECONDS);
            }

            return $units;

        }

        public function get_first_customer_by_organisation_number($customer, $org_number)
        {
            try {
                $customer = WC_FH()->fortnox->get_first_customer_by_organisation_number($org_number);
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $customer;
        }

        public function get_voucher_series($voucher_series)
        {
            try {
                $voucher_series = get_fortnox_hub_transient('fortnox_voucher_series');
                if (!is_array($voucher_series)) {
                    $voucher_series = WC_FH()->fortnox->get_voucher_series();
                    set_fortnox_hub_transient('fortnox_voucher_series', $voucher_series, DAY_IN_SECONDS);
                }

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $voucher_series;
        }

        public function trigger_warehouse_ready($order_id, $fn_document_number, $is_order = false)
        {
            try {

                if (wc_string_to_bool(get_option('fortnox_set_warehouseready'))) {

                    if ($is_order) {
                        WC_FH()->logger->add(sprintf(__('trigger_order_warehouse_ready (%s): Changing Fortnox order %s to warehouseready', 'woo-fortnox-hub'), $order_id, $fn_document_number));
                        WC_FH()->fortnox->set_order_warehouseready($fn_document_number);
                    } else {
                        WC_FH()->logger->add(sprintf(__('trigger_order_warehouse_ready (%s): Changing Fortnox invoice %s to warehouseready', 'woo-fortnox-hub'), $order_id, $fn_document_number));
                        WC_FH()->fortnox->set_invoice_warehouseready($fn_document_number);
                    }
                }
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
        }
        

        public function warehouse_activated($warehouse_activated)
        {
            try {
                $warehouse_status = get_fortnox_hub_transient('fortnox_warehouse_status');
                if (!is_array($warehouse_status)) {
                    $warehouse_status = WC_FH()->fortnox->warehouse_activated();
                    set_fortnox_hub_transient('fortnox_warehouse_status', $warehouse_status, DAY_IN_SECONDS);
                }
                $warehouse_activated = rest_sanitize_boolean($warehouse_status['activated']);
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
            return $warehouse_activated;
        }

        public function clear_cache()
        {
            delete_fortnox_hub_transient('fortnox_all_accounts');
            delete_fortnox_hub_transient('fortnox_pricelists');
            delete_fortnox_hub_transient('fortnox_projects');
            delete_fortnox_hub_transient('fortnox_cost_centers');
            delete_fortnox_hub_transient('fortnox_modes_of_payments');
            delete_fortnox_hub_transient('fortnox_terms_of_payments');
            delete_fortnox_hub_transient('fortnox_terms_of_deliveries');
            delete_fortnox_hub_transient('fortnox_way_of_deliveries');
            delete_fortnox_hub_transient('fortnox_print_templates_order');
            delete_fortnox_hub_transient('fortnox_print_templates_invoice');
            delete_fortnox_hub_transient('fortnox_print_templates_');
            delete_fortnox_hub_transient('fortnox_financial_years');
            delete_fortnox_hub_transient('fortnox_financial_year');
            delete_fortnox_hub_transient('fortnox_units');
            delete_fortnox_hub_transient('fortnox_voucher_series');
            delete_fortnox_hub_transient('fortnox_warehouse_status');

        }
    }

    new Woo_Fortnox_API_Hooks_And_Filters();
}
