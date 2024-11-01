<?php

/**
 * Handle settings for YWGC
 *
 * @package   BjornTech_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Hub_YWGC', false)) {

    class Fortnox_Hub_YWGC
    {

        public function __construct()
        {

            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 200);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 70, 2);
            add_filter('fortnox_after_get_order_item', array($this, 'maybe_enrich_ywgc_order_item'), 10, 3);
            add_filter('fortnox_after_get_fee_items', array($this, 'maybe_enrich_ywgc_fee_item'), 10, 3);
            add_filter('fortnox_after_get_order_items', array($this, 'maybe_add_ywgc_item'), 10, 3);


        }

        public function add_settings_section($sections)
        {
            if (!array_key_exists('ywgc_options', $sections)) {
                $sections = array_merge($sections, array('ywgc_options' => __('Gift Cards', 'woo-fortnox-hub')));
            }
            return $sections;
        }

        public function get_settings($settings, $current_section)
        {
            if ('ywgc_options' === $current_section) {

                $account_selection = apply_filters('fortnox_get_account_selection', array());

                $settings = array(
                    array(
                        'title' => __('Gift card options', 'woo-fortnox-hub'),
                        'type' => 'title',
                        'desc' => __('', 'woo-fortnox-hub'),
                        'id' => 'fortnox_ywgc_options',
                    ),
                    array(
                        'title' => __('Gift card sales account', 'woo-fortnox-hub'),
                        'type' => 'select',
                        'default' => '',
                        'options' => $account_selection,
                        'id' => 'fortnox_ywgc_giftcard_sales_account',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'fortnox_ywgc_options',
                    ),
                );
            }
            return $settings;
        }

        public function maybe_add_ywgc_item($rows, $order, $rowtype) {
            if (!($giftcards = $order->get_meta('_ywgc_applied_gift_cards'))) {
                return $rows;
            }

            if ($order->get_meta('ywgc_gift_card_updated_as_fee', true)) {
                return $rows;
            }

            $order_items = $order->get_items();

            foreach ($order_items as $item_id) {
                if (wc_get_order_item_meta($item_id, '_ywgc_gift_card_code')) {
                    return $rows;
                }
            }

            foreach ($giftcards as $gift_card =>$gift_card_value) {
                $gift_card_row["AccountNumber"] = get_option('fortnox_ywgc_giftcard_sales_account');
                $gift_card_row["DeliveredQuantity"] = 1;
                $gift_card_row["Price"] = $gift_card_value * -1;
                $gift_card_row["ArticleNumber"] = 'API_BLANK';
                $gift_card_row["Description"] = WCFH_Util::clean_fortnox_text(sprintf(__('Gift card %s', 'woo-fortnox-hub'), $gift_card), 50); 

                $rows[$rowtype][]  = WCFH_Util::remove_blanks($gift_card_row);
            }


            return $rows;

        }

        /**
         * Check if the order item is a gift card purchase and enrich the order item
         *
         * @since 4.1.0
         *
         * @param array $row
         * @param WC_Order_Item $item
         * @param WC_Order $order
         *
         * @return array
         */
        public function maybe_enrich_ywgc_order_item($row, $item, $order)
        {

            $item_id = $item->get_id();

            if ($gift_ids = wc_get_order_item_meta($item_id, '_ywgc_gift_card_code')) {

                $row["Description"] = $item->get_name() . ' #' . implode('#', $gift_ids);
                $row["AccountNumber"] = get_option('fortnox_ywgc_giftcard_sales_account');

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
        public function maybe_enrich_ywgc_fee_item($row, $item, $order)
        {

            if ($order->get_meta('ywgc_gift_card_updated_as_fee', true)) {

                $item_id = $item->get_id();

                $item_name = $item->get_name();

                if ($item_id === '_ywgc_fee' || (strpos($item_name, 'Gift Card') !== false)){

                    $row["AccountNumber"] = get_option('fortnox_ywgc_giftcard_sales_account');

                }

            }
            return WCFH_Util::remove_blanks($row);

        }

    }

    new Fortnox_Hub_YWGC();
}
