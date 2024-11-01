<?php

/**
 * This class contains common functions for creating invoices and orders
 *
 * @package   Woo_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_Hub_Lager', false)) {

    require_once plugin_dir_path(self::PLUGIN_FILE) . 'includes/class-woo-fortnox-hub-document.php';

    class Woo_Fortnox_Hub_Lager extends Woo_Fortnox_Hub_Document
    {

        public function __construct()
        {

            add_action('woo_fortnox_hub_processing_lager', array($this, 'processing_lager'));
            add_action('woo_fortnox_hub_cancelled_lager', array($this, 'cancelled_lager'));
            add_action('woo_fortnox_hub_completing_lager', array($this, 'completing_lager'));

            parent::__construct();
        }

        public function processing_lager(&$order_id)
        {

            try {

                if (!apply_filters('fortnox_hub_filter_woocommerce_order', true, 'processing_lager', $order_id)) {
                    return;
                }

                $order = WCFH_Util::fortnox_get_order($order_id);

                $document_number = WCFH_Util::get_fortnox_invoice_number($order_id);

                if ($document_number) {
                    $fn_invoice = WC_FH()->fortnox->get_invoice($document_number);
                } elseif (!wc_string_to_bool(get_option('fortnox_do_not_use_external_refs'))) {
                    $fn_invoice = WCFH_Util::check_if_invoice_already_created(WCFH_Util::encode_external_reference($order_id), false);
                    if ($fn_invoice) {
                        WCFH_Util::set_fortnox_invoice_number($order, $fn_invoice['DocumentNumber']);
                    }
                }

                if ($fn_invoice) {

                    if (rest_sanitize_boolean($fn_invoice['Cancelled'])) {
                        WC_FH()->logger->add(sprintf('processing_lager (%s): Fortnox invoice %s already cancelled and can not be updated', $order_id, $fn_invoice['DocumentNumber']));
                        return;
                    }

                    if ('ACCRUAL' === $fn_invoice['AccountingMethod'] && rest_sanitize_boolean($fn_invoice['Booked'])) {
                        WC_FH()->logger->add(sprintf('processing_lager (%s):Fortnox invoice %s is booked and can not be updated', $order_id, $fn_invoice['DocumentNumber']));
                        return;
                    }

                    if ('CASH' === $fn_invoice['AccountingMethod']) {

                        if (rest_sanitize_boolean($fn_invoice['Sent'])) {
                            WC_FH()->logger->add(sprintf('processing_lager (%s):Fortnox invoice %s is sent and can not be updated', $order_id, $fn_invoice['DocumentNumber']));
                            return;
                        }

                        if ($fn_invoice['FinalPayDate']) {
                            WC_FH()->logger->add(sprintf('processing_lager (%s):Fortnox invoice %s was payed %s and can not be updated', $order_id, $fn_invoice['DocumentNumber'], $fn_invoice['FinalPayDate']));
                            return;
                        }
                    }
                }

                $invoice_details = $this->get_details($order);

                $invoice_items = $this->get_items($order);

                if (wc_string_to_bool(get_option('fortnox_document_use_fee_field'))) {
                    $fee_items = array();
                    $invoice_details = array_merge($invoice_details, $this->get_fee_cost($order));
                } else {
                    $fee_items = $this->get_fee_items($order);
                }

                if (wc_string_to_bool(get_option('fortnox_document_use_shipping_field'))) {
                    $shipping_items = array();
                    $invoice_details = array_merge($invoice_details, $this->get_shipping_cost($order));
                } else {
                    $shipping_items = $this->get_shipping_items($order);
                }

                $email_information = WCFH_Util::get_invoice_email_information($order);

                $full_invoice = apply_filters('fortnox_before_processing_lager', array_merge_recursive($invoice_details, $invoice_items, $fee_items, $shipping_items, $email_information), $order);

                if ($fn_invoice) {

                    unset($full_invoice['DocumentNumber']);
                    $fn_invoice = WC_FH()->fortnox->updateInvoice($fn_invoice['DocumentNumber'], $full_invoice);
                    WC_FH()->logger->add(sprintf('processing_lager (%s): WooCommerce order updated Fortnox invoice %s', $order_id, $fn_invoice['DocumentNumber']));
                } else {

                    $fn_invoice = WC_FH()->fortnox->create_invoice($full_invoice);
                    WC_FH()->logger->add(sprintf('processing_lager (%s): Created Fortnox invoice %s', $order_id, $fn_invoice['DocumentNumber']));
                }

                WCFH_Util::set_fortnox_invoice_number($order, $fn_invoice['DocumentNumber']);

                $order->save();

                WC_FH()->logger->add(json_encode($full_invoice, JSON_INVALID_UTF8_IGNORE));

            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
                Fortnox_Notice::add(sprintf(__("%s when processing order %s", 'woo-fortnox-hub'), $e->getMessage(), $order->get_order_number()));
                do_action('fortnox_order_sync_failed', $e);
            }

        }

        public function cancelled_lager(&$order_id)
        {

            try {

                if (!apply_filters('fortnox_hub_filter_woocommerce_order', true, 'cancelled_lager', $order_id)) {
                    return;
                }

                $order = WCFH_Util::fortnox_get_order($order_id);

                WC_FH()->logger->add(sprintf('cancelled_lager (%s): Processing order cancellation', $order_id));

                $document_number = WCFH_Util::get_fortnox_invoice_number($order_id);

                if (!$document_number) {
                    WC_FH()->logger->add(sprintf('cancelled_lager (%s): No reference to a Fortnox Invoice found on order', $order_id));
                    return;
                }

                $fn_invoice = WC_FH()->fortnox->get_invoice($document_number);

                if (rest_sanitize_boolean($fn_invoice['Cancelled'])) {
                    WC_FH()->logger->add(sprintf('cancelled_lager (%s): Fortnox invoice %s is already cancelled and can not be updated', $order_id, $fn_invoice['DocumentNumber']));
                    return;
                }

                if ('ACCRUAL' === $fn_invoice['AccountingMethod'] && rest_sanitize_boolean($fn_invoice['Booked'])) {
                    WC_FH()->logger->add(sprintf('cancelled_lager (%s): Fortnox invoice %s is booked and can not be updated', $order_id, $fn_invoice['DocumentNumber']));
                    return;
                }

                if ('CASH' === $fn_invoice['AccountingMethod'] && rest_sanitize_boolean($fn_invoice['Sent'])) {
                    WC_FH()->logger->add(sprintf('cancelled_lager (%s): Fortnox invoice %s is sent and can not be updated', $order_id, $fn_invoice['DocumentNumber']));
                    return;
                }

                WC_FH()->fortnox->updateInvoice($fn_invoice['DocumentNumber'], array(
                    "Comments" => __('Cancelled from WooCommerce', 'woo-fortnox-hub'),
                ));

                WC_FH()->fortnox->cancel_invoice($fn_invoice['DocumentNumber']);

            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
                Fortnox_Notice::add(sprintf(__("%s when processing order %s", 'woo-fortnox-hub'), $e->getMessage(), $order->get_order_number()));
                do_action('fortnox_order_sync_failed', $e);
            }

        }

        public function completing_lager(&$order_id)
        {

            if (!apply_filters('fortnox_hub_filter_woocommerce_order', true, 'completing_lager', $order_id)) {
                return;
            }

            if (!WCFH_Util::get_fortnox_invoice_number($order_id)) {

                do_action('woo_fortnox_hub_processing_lager', $order_id);
            }

            $fortnox_invoice_number = WCFH_Util::get_fortnox_invoice_number($order_id);

            if ($fortnox_invoice_number) {

                if ('yes' == get_option('fortnox_check_invoices_automatically')) {
                    do_action('fortnox_process_changed_invoices', $fortnox_invoice_number);
                }
            }
        }
    }

    new Woo_Fortnox_Hub_Lager();
}
