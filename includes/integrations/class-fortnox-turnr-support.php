<?php

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Turnr_Support', false)) {

    class Fortnox_Turnr_Support
    {
        public function __construct()
        {
            add_filter('fortnox_after_get_order_items', array($this, 'after_get_order_items'), 10, 3);
        }

        public function after_get_order_items($rows, $order, $rowtype){

            $row = array();
    
            if (('shop_order_refund' != $order->get_type()) && ($order->get_meta('turnr_exchange_order', true))){

                $turnr_parent_id = ($order->get_meta('turnr_parent_id', true) ? $order->get_meta('turnr_parent_id', true) : $order->get_parent_id());

                WC_FH()->logger->add(sprintf('after_get_order_items ($s): Turnr exchange order identified',$order->get_id()));

                if ($turnr_parent_id) {
                    $row = array(
                        "Description" => WCFH_Util::clean_fortnox_text(sprintf(__('Turnr exchange order for order %s'), $turnr_parent_id), 50),
                        "AccountNumber" => 'API_BLANK'
                    );

                    $rows[$rowtype][] = $row;

                    WC_FH()->logger->add(sprintf('after_get_order_items ($s): Turnr exchange order parent id %s found and added to invoice', $order->get_id(), $turnr_parent_id));

                } else {
                    WC_FH()->logger->add(sprintf('after_get_order_items ($s): Turnr exchange order parent id not identified', $order->get_id()));
                }    
            }
    
            return $rows;
        }

    }

    new Fortnox_Turnr_Support();
}