<?php

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Woo_Subscriptions_Support', false)) {

    class Fortnox_Woo_Subscriptions_Support {

        public function __construct() {

            if (wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders'))) {

                add_filter('fortnox_hub_filter_woocommerce_order', array($this, 'fortnox_hub_filter_woocommerce_order'), 10, 3);
            }

        }

        public function fortnox_hub_filter_woocommerce_order ($sync_invoice,$method,$order_id) {
            $order = wc_get_order($order_id);

            if (!$order) {
                return false;
            }

            if ($order->get_total() == 0){
                WC_FH()->logger->add(sprintf('fortnox_hub_filter_woocommerce_order (%s) - Order total is 0 - skipping syncing of order', $order->get_id()));
                return false;
            }

            return $sync_invoice;
        }



    }

    new Fortnox_Woo_Subscriptions_Support();
}