<?php

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Klarna_Support', false)) {

class Fortnox_Klarna_Support
{

private $klarna;

public function __construct()
{
    if (wc_string_to_bool(get_option('fortnox_update_klarna_merchant_reference'))) {
        add_action('fortnox_before_process_changed_invoices_action_all', array($this, 'update_klarna_merchant_reference'), 10, 2);
        $this->klarna = new Fortnox_Hub_Klarna_API();
    }

}

public function update_klarna_merchant_reference($fn_invoice, $order){

    $invoice_number = $fn_invoice['DocumentNumber'];

    $klarna_order_id = $order->get_meta('_wc_klarna_order_id');

    if(!$klarna_order_id){
        WC_FH()->logger->add(sprintf('update_klarna_merchant_reference (%s) - Klarna order id not found', $order->get_id()));
        return;
    }

    try {

        $klarna_order = $this->klarna->get_order($klarna_order_id);

        if(!$klarna_order){
            WC_FH()->logger->add(sprintf('update_klarna_merchant_reference (%s) - Klarna order not found', $order->get_id()));
            return;
        }

        $merchant_reference2 = $klarna_order['merchant_reference2'];

        if($merchant_reference2 == $invoice_number){
            WC_FH()->logger->add(sprintf('update_klarna_merchant_reference (%s) - Klarna merchant reference already updated', $order->get_id()));
            return;
        }

        $this->klarna->update_merchant_reference($klarna_order_id, array(
            'merchant_reference2' => $invoice_number
        ));

        WC_FH()->logger->add(sprintf('update_klarna_merchant_reference (%s) - Updated Klarna merchant reference', $order->get_id()));
    } catch (Fortnox_API_Exception $e) {
        WC_FH()->logger->add(sprintf('update_klarna_merchant_reference (%s) - Failed to update Klarna merchant reference', $order->get_id()));
    } finally {
        return;
    }

}

}

new Fortnox_Klarna_Support();
}