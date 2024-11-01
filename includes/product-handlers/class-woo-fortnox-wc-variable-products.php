<?php

/**
 * Handle settings for WooCommerce Variable products
 *
 * @package   BjornTech_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2021 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Hub_WC_Variable_Products', false)) {

    class Fortnox_Hub_WC_Variable_Products
    {

        public function __construct()
        {
            if (wc_string_to_bool(get_option('fortnox_wc_product_update_variable_parent'))) {
                add_filter('fortnox_after_get_order_item', array($this, 'maybe_enrich_wc_variable_product'), 10, 3);
            }
        }

        /**
         * Check if the row is a variable product and if so, add the child product name to an additional row
         *
         * @since 5.1.0
         *
         * @param array $row
         * @param WC_Order_Item $order_item
         * @param WC_Order $order
         *
         * @return array
         */
        public function maybe_enrich_wc_variable_product($row_in, $order_item, $order)
        {

            WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): Extra check for variable products', $order->get_id()));

            //Check if order_item has a product associated with it
            if (!($product = $order_item->get_product())) {
                WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): No product associated with order item', $order->get_id()));
                return $row_in;
            }

            //Check if the product is a variant by checking the type
            if (!$product->is_type('variation')) {
                WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): Product is not a variant', $order->get_id()));
                return $row_in;
            }
            

            //Check if row in has article number and is not a blank string or has the value 'API_BLANK'
            if (!isset($row_in['ArticleNumber']) || $row_in['ArticleNumber'] == '' || $row_in['ArticleNumber'] == 'API_BLANK') {
                WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): No article number in row', $order->get_id()));
                return $row_in;
            }

            //Get product from the article number in the row
            $article_product_id = wc_get_product_id_by_sku($row_in['ArticleNumber']);

            //Return if there is no product with the article number
            if (!$article_product_id) {
                WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): No product with article number %s', $order->get_id(), $row_in['ArticleNumber']));
                return $row_in;
            }

            //Check if product is a variable product
            if (!($article_product = wc_get_product($article_product_id)) || !$article_product->is_type('variable')) {
                WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): Product is not a variable product', $order->get_id()));
                return $row_in;
            }
            
            //Make sure that both products are connected
            if ($article_product->get_id() != $product->get_parent_id()) {
                WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): Product %s is not connected to the article product %s', $order->get_id(), $product->get_id(), $article_product->get_id()));
                return $row_in;
            }

            $new_row = [];

            $new_row['Description'] = $product->get_name();

            $new_row = WCFH_Util::clear_row_blanks($new_row);

            $rows = [$row_in, $new_row];

            WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): Successfully added product name to row', $order->get_id()));

            WC_FH()->logger->add(sprintf('maybe_enrich_wc_variable_product (%s): Row before enrichment: %s', $order->get_id(), print_r($rows , true)));

            return $rows;

        }

    }

    new Fortnox_Hub_WC_Variable_Products();
}
