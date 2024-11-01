<?php

/**
 * Handle settings for Zettle Gift Cards
 *
 * @package   BjornTech_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Hub_Zettle_GC', false)) {

    class Fortnox_Hub_Zettle_GC
    {

        public function __construct()
        {

            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 200);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 70, 2);
            add_filter('fortnox_after_get_order_item', array($this, 'maybe_enrich_zettle_gc_order_item'), 10, 3);
            add_filter('fortnox_after_get_order_item', array($this, 'maybe_enrich_zettle_gc_purchase'), 10, 3);        
        }

        public function add_settings_section($sections)
        {
            if (!array_key_exists('zettle_gc_options', $sections)) {
                $sections = array_merge($sections, array('zettle_gc_options' => __('Zettle Gift Cards', 'woo-fortnox-hub')));
            }
            return $sections;
        }

        public function get_settings($settings, $current_section)
        {
            if ('zettle_gc_options' === $current_section) {

                $account_selection = apply_filters('fortnox_get_account_selection', array());

                $settings[] = [
                    'title' => __('Gift card options', 'woo-fortnox-hub'),
                    'type' => 'title',
                    'desc' => __('', 'woo-fortnox-hub'),
                    'id' => 'fortnox_zettle_gc_options',
                ];
                $settings[] = [
                    'title' => __('Gift card sales account', 'woo-fortnox-hub'),
                    'type' => 'select',
                    'default' => '',
                    'options' => $account_selection,
                    'id' => 'fortnox_zettle_gc_giftcard_sales_account',
                ];
                $settings[] = [
                    'type' => 'sectionend',
                    'id' => 'fortnox_zettle_gc_options',
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
        public function maybe_enrich_zettle_gc_order_item($row, $order_item, $order)
        {

            if ($order_item->meta_exists('izettle_gift_card')) {
                $row["AccountNumber"] = get_option('fortnox_zettle_gc_giftcard_sales_account');
            }

            return WCFH_Util::remove_blanks($row);

        }

        public function maybe_enrich_zettle_gc_purchase($row, $order_item, $order)
        {

            if ($order_item->meta_exists('izettle_gift_card_used_id')) {
                $row["AccountNumber"] = get_option('fortnox_zettle_gc_giftcard_sales_account');
            }

            return WCFH_Util::remove_blanks($row);

        }

    }

    new Fortnox_Hub_Zettle_GC();
}
