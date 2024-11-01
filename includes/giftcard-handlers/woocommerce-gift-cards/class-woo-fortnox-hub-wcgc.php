<?php

/**
 * Handle settings for WCGC
 *
 * @package   BjornTech_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Hub_WCGC', false)) {

    class Fortnox_Hub_WCGC
    {

        public function __construct()
        {

            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 200);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 70, 2);
            add_filter('fortnox_after_get_order_item', array($this, 'maybe_enrich_wcgc_order_item'), 10, 3);
            add_filter('fortnox_after_get_fee_items', array($this, 'maybe_enrich_wcgc_fee_item'), 10, 3);
            add_filter('fortnox_after_get_order_items', array($this, 'maybe_add_wcgc_item'), 10, 3);

        }

        public function add_settings_section($sections)
        {
            if (!array_key_exists('wcgc_options', $sections)) {
                $sections = array_merge($sections, array('wcgc_options' => __('WooCommerce Gift Cards', 'woo-fortnox-hub')));
            }
            return $sections;
        }

        public function get_settings($settings, $current_section)
        {
            if ('wcgc_options' === $current_section) {

                $account_selection = apply_filters('fortnox_get_account_selection', array());

                $settings[] = [
                    'title' => __('Gift card options', 'woo-fortnox-hub'),
                    'type' => 'title',
                    'desc' => __('', 'woo-fortnox-hub'),
                    'id' => 'fortnox_wcgc_options',
                ];
                $settings[] = [
                    'title' => __('Gift card sales account', 'woo-fortnox-hub'),
                    'type' => 'select',
                    'default' => '',
                    'options' => $account_selection,
                    'id' => 'fortnox_wcgc_giftcard_sales_account',
                ];
                $settings[] = [
                    'type' => 'sectionend',
                    'id' => 'fortnox_wcgc_options',
                ];

            }
            return $settings;
        }

        /**
         * Check if the order item is a gift card purchase and enrich the order item
         *
         * @since 5.1.1
         *
         * @param array $row
         * @param WC_Order_Item $order_item
         * @param WC_Order $order
         *
         * @return array
         */
        public function maybe_enrich_wcgc_order_item($row, $order_item, $order)
        {

            if ($product = $order_item->get_product()) {
                $product_id = !empty($product->get_parent_id()) ? $product->get_parent_id() : $product->get_id();
                $product = wc_get_product($product_id);
                if (class_exists('WC_GC_Gift_Card_Product') && method_exists('WC_GC_Gift_Card_Product','is_gift_card') && WC_GC_Gift_Card_Product::is_gift_card($product)) {
                    $row["AccountNumber"] = get_option('fortnox_wcgc_giftcard_sales_account');
                }
            }
            return WCFH_Util::remove_blanks($row);

        }

        /**
         * Check if the order fee item is a gift card payment and enrich the order item
         *
         * @since 4.1.0
         *
         * @param array $row
         * @param WC_Order_Item $item
         * @param WC_Order $order
         *
         * @return array
         */
        public function maybe_enrich_wcgc_fee_item($row, $item, $order)
        {

            $item_type = $item->get_type();

            if ($item_type == 'gift_card') {

                $row["AccountNumber"] = get_option('fortnox_wcgc_giftcard_sales_account');

            }

            return WCFH_Util::remove_blanks($row);

        }

        /**
         * Check if a gift card is used in the order and add it to the order
         *
         * @since 4.9.3
         *
         * @param array $rows
         * @param WC_Order $order
         *
         * @return array of rows
         */
        public function maybe_add_wcgc_item($rows, $order, $rowtype)
        {    
            foreach ($order->get_items('gift_card') as $line) {
                $gift_card_total = 0;
                $gift_card_total = $line->get_amount('edit');
                
                if (!empty($gift_card_total)) {
                    $price = apply_filters('wcgc_to_order_currency', $gift_card_total * -1, $order);
                    $gift_card_number = $line->get_code();
                    
                    $gift_card_row["AccountNumber"] = get_option('fortnox_wcgc_giftcard_sales_account');
                    $gift_card_row["DeliveredQuantity"] = 1;
                    $gift_card_row["Price"] = $price;
                    $gift_card_row["ArticleNumber"] = 'API_BLANK';
                    $gift_card_row["Description"] = WCFH_Util::clean_fortnox_text(sprintf(__('Gift card %s', 'woocommerce-gift-cards'), $gift_card_number), 50);                
                    
                    $rows[$rowtype][] = $gift_card_row;
                }
            }

            return $rows;
        }

    }

    new Fortnox_Hub_WCGC();
}
