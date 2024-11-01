<?php

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Svea_Support', false)) {

    class Fortnox_Svea_Support
    {

        private $svea_order_id_meta_key = '_svea_co_order_id';

        public function __construct()
        {
            add_filter('fortnox_after_get_details', array($this, 'after_get_details'), 20, 2);
            add_filter('fortnox_hub_filter_partially_refunded_invoice', array($this, 'handle_refund'), 20, 3);
            add_filter('fortnox_hub_filter_fully_refunded_invoice', array($this, 'handle_refund'), 20, 3);
            add_action('fortnox_before_process_changed_invoices_action_all', array($this, 'update_svea_reference'), 15, 2);

        }

        public function update_svea_reference ($fn_invoice, $order) {
            $is_credit = rest_sanitize_boolean($fn_invoice['Credit']);
            
            if ($is_credit) {
                //Get parent
                $parent_id = $order->get_parent_id();

                if (!$parent_id) {
                    WC_FH()->logger->add(sprintf('update_svea_reference (%s) - No parent id found', $order->get_id()));
                    return;
                }

                $order = wc_get_order($parent_id);
            }

            if (!($svea_order_id = $order->get_meta($this->svea_order_id_meta_key))) {
                return;
            }

            WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Maybe update Svea reference with Svea order id %s', $order->get_id(), $svea_order_id));
        
            if (!wc_string_to_bool(get_option('fortnox_do_not_use_external_refs'))) {
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - External refs are not disabled - skipping', $order->get_id()));
                return;
            }

            //Check if invoice is booked - abort if it is
            if (rest_sanitize_boolean($fn_invoice['Booked'])) {
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Invoice is booked - skipping', $order->get_id()));
                return;
            }

            //Check if invoice is warehouseready
            if (rest_sanitize_boolean($fn_invoice['WarehouseReady'])) {
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Invoice is warehouseready - skipping', $order->get_id()));
                return;
            }
        
            $is_credit = rest_sanitize_boolean($fn_invoice['Credit']);

            //Check if svea reference is already set
            if ($is_credit && $fn_invoice['ExternalInvoiceReference1'] == $svea_order_id) {
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Svea reference already set - skipping', $order->get_id()));
                return;
            } elseif (!$is_credit && $fn_invoice['ExternalInvoiceReference2'] == $svea_order_id) {
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Svea reference already set - skipping', $order->get_id()));
                return;
            }
        
            // Prepare the data to be updated
            $data = $is_credit ? ['ExternalInvoiceReference1' => $svea_order_id] : ['ExternalInvoiceReference2' => $svea_order_id];
        
            // Update the invoice in Fortnox
            try {
                WC_FH()->fortnox->updateInvoice($fn_invoice['DocumentNumber'], $data);
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Updated Svea reference with Svea order id %s', $order->get_id(), $svea_order_id));
            } catch (Fortnox_API_Exception $e) {
                WC_FH()->logger->add(sprintf('update_svea_reference (%s) - Failed to update Svea reference', $order->get_id()));
            } finally {
                return;
            }
        }

        public function after_get_details($document, $order) {
            if (!($svea_order_id = $order->get_meta($this->svea_order_id_meta_key))) {
                return $document;
            }

            if (!wc_string_to_bool(get_option('fortnox_do_not_use_external_refs'))) {
                return $document;
            }

            WC_FH()->logger->add(sprintf('after_get_details (%s) - Updating ExternalInvoiceReference2 to %s', $order->get_id(), $svea_order_id));
            
            $document['ExternalInvoiceReference2'] = $svea_order_id;

            return $document;
        }

        public function handle_refund($document, $order_id, $refund_id) {
            $order = wc_get_order($order_id);

            if (!($svea_order_id = $order->get_meta($this->svea_order_id_meta_key))) {
                return $document;
            }

            if (!wc_string_to_bool(get_option('fortnox_do_not_use_external_refs'))) {
                return $document;
            }

            WC_FH()->logger->add(sprintf('handle_refund (%s) - Updating ExternalInvoiceReference1 to %s', $order_id, $svea_order_id));

            $document['ExternalInvoiceReference1'] = $svea_order_id;

            return $document;
        }


    }

    new Fortnox_Svea_Support();
}