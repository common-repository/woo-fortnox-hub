<?php

/**
 * This class handles Fortnox Warehouse specific functions.
 *
 * @package   BjornTech_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2022 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_Warehouse_Handler', false)) {

    class Woo_Fortnox_Warehouse_Handler
    {

        /**
         * Constructor.
         *
         * @since  5.3.0
         */
        public function __construct()
        {
            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 60);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 60, 2);
            add_filter('woocommerce_save_settings_fortnox_hub_updates_from_fortnox', array($this, 'save_settings_section'));
            add_filter('woocommerce_get_stock_html', array($this, 'get_stock_html'), 10, 2);
            add_filter('fortnox_update_woocommerce_product_from_article', array($this, 'update_warehouse_stocklevel'), 10, 4);

            $warehouse_activated = apply_filters('fortnox_warehouse_activated', false);

            if ($warehouse_activated && get_option('fortnox_warehouse_primary_stockplace')) {
                add_filter('fortnox_hub_article_stocklevel', array($this, 'get_article_stockplace_stock_level'), 10, 2);
                add_filter('fortnox_after_get_order_item', array($this, 'warehouse_after_get_order_item'), 3, 3);
                add_filter('fortnox_before_processing_order', array($this, 'warehouse_after_get_details'), 20, 2);
            }
        }

        /**
         * Add settings sections.
         *
         * @since  5.3.0
         * @param  array $sections Sections added by previous filters.
         * @return array
         */
        public function add_settings_section($sections)
        {

            $warehouse_activated = apply_filters('fortnox_warehouse_activated', false);
            if ('stockchange' != get_option('fortnox_woo_order_creates') && $warehouse_activated && !array_key_exists('fortnox_lager', $sections)) {
                $sections = array_merge($sections, array('fortnox_lager' => __('Fortnox Warehouse', 'woo-fortnox-hub')));
            }

            return $sections;
        }

        /**
         * Save settings sections.
         *
         * @since  5.3.0
         * @param  bool $true Save settings bool from previous filters.
         * @return bool
         */
        public function save_settings_section($true)
        {
            return $true;
        }

        /**
         * Format the stock amount ready for display based on settings.
         *
         * @since  5.3.0
         * @param  array $settings Settings array.
         * @param  string $current_section Name of current section.
         * @return array
         */
        public function get_settings($settings, $current_section)
        {
            if ('fortnox_lager' === $current_section) {

                $stockpoints = array(
                    '' => __('No stockplace selected', 'woo-fortnox-hub'),
                );

                foreach ($this->get_warehouse_stockpoints() as $stockpoint) {
                    $stockpoints[$stockpoint['code']] = $stockpoint['name'];
                }

                $settings[] = [
                    'title' => __('Fortnox Warehouse', 'woo-fortnox-hub'),
                    'type' => 'title',
                    'id' => 'fortnox_lager',
                ];

                $settings[] = array(
                    'title' => __('Primary stock place', 'woo-fortnox-hub'),
                    'type' => 'select',
                    'desc' => __('The stockplace that will be used by Fortnox Hub for syncing stocklevels. Note that stocklevels that are changed manually inside the product in WooCommerce will not be synced to Fortnox.', 'woo-fortnox-hub'),
                    'default' => '',
                    'options' => $stockpoints,
                    'id' => 'fortnox_warehouse_primary_stockplace',
                );

                unset($stockpoints['']);

                $settings[] = [
                    'title' => __('Show stockplace stocklevels', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'desc' => __('Stockplaces and their stocklevels are shown to the shop visitor regardless of primary stock place', 'woo-fortnox-hub'),
                    'default' => '',
                    'id' => 'fortnox_warehouse_show_stockplaces',
                ];
                
                if (wc_string_to_bool(get_option('fortnox_warehouse_show_stockplaces'))) {

                    $settings[] = [
                        'title' => __('Include stockplaces', 'woo-fortnox-hub'),
                        'type' => 'multiselect',
                        'class' => 'wc-enhanced-select',
                        'css' => 'width: 400px;',
                        'id' => 'fortnox_warehouse_show_stockplaces_filter',
                        'default' => '',
                        'description' => __('If you only want to display stock from certain stockplaces, select them here. Leave blank to enable for all stockplaces.', 'woo-fortnox-hub'),
                        'options' => $stockpoints,
                        'custom_attributes' => array(
                            'data-placeholder' => __('Select stockplaces to include or leave empty for all', 'woo-fortnox-hub'),
                        ),
                    ];

                    $settings[] = [
                        'title' => __('Show Woo stock', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'desc' => __('Show the stocklevel in WooCommerce as one stocklevel row', 'woo-fortnox-hub'),
                        'default' => '',
                        'id' => 'fortnox_warehouse_show_wc_stock',
                    ];

                    $settings[] = [
                        'title' => __('Show total stock', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'desc' => __('Show the total stocklevel as one stocklevel row', 'woo-fortnox-hub'),
                        'default' => '',
                        'id' => 'fortnox_warehouse_show_total_stock',
                    ];
                }

                $settings[] = [
                    'type' => 'sectionend',
                    'id' => 'fortnox_lager',
                ];

            }
            return $settings;
        }

        /**
         * Updates the warehouse stocklevel on a product.
         *
         * @since  5.3.0
         * @param  bool $do_update Update info from previous filters.
         * @param  int $product_id WooCommerce product id.
         * @param  array $article Article from Fortnox.
         * @param  bool $sync_all Set to true if the update is part of a full update cycle.
         * @return bool
         */
        public function update_warehouse_stocklevel($do_update, $product_id, $article, $sync_all)
        {

            $warehouse_activated = apply_filters('fortnox_warehouse_activated', false);
            if (!$warehouse_activated) {
                return $do_update;
            }

            $show_stockplaces = wc_string_to_bool(get_option('fortnox_warehouse_show_stockplaces'));
            if (!$show_stockplaces) {
                return $do_update;
            }

            $stock_goods = rest_sanitize_boolean($article['StockGoods']);
            if (!$stock_goods) {
                delete_post_meta($product_id, '_fortnox_warehouse_summary');
                return $do_update;
            }

            try {
                $item_summary = WC_FH()->fortnox->warehouse_item_summary($article['ArticleNumber']);
                update_post_meta($product_id, '_fortnox_warehouse_summary', json_encode($item_summary));
            } catch (Fortnox_API_Exception $e) {
                WC_FH()->logger->add(sprintf('update_warehouse_stocklevel: Error when fetching item summary for Article %s', $article['ArticleNumber']));
            }

            return $do_update;
        }

        /**
         * Constructs the stock html code to be shown in the webshop.
         *
         * @since  5.3.0
         * @param  string $html Original html built by WooCommerce.
         * @param  WC_Product $product The WooCommerce product being displayed.
         * @return string
         */
        public function get_stock_html($html, $product)
        {

            $warehouse_activated = apply_filters('fortnox_warehouse_activated', false);
            if (!$warehouse_activated) {
                return $html;
            }

            $show_stockplaces = wc_string_to_bool(get_option('fortnox_warehouse_show_stockplaces'));
            if (!$show_stockplaces) {
                return $html;
            }

            $item_summary = $product->get_meta('_fortnox_warehouse_summary', true);
            if (empty($item_summary)) {
                return $html;
            }

            $show_wc_stock = wc_string_to_bool(get_option('fortnox_warehouse_show_wc_stock'));
            $wc_stock_place_name = get_option('fortnox_warehouse_woo_stock_place_name', 'Total');
            $item_summary = json_decode($item_summary, true);
            $item_summary = $this->filter_stock_places($item_summary);
            $show_total_stock = wc_string_to_bool(get_option('fortnox_warehouse_show_total_stock'));

            ob_start();

            ?>
                <table>
                    <tr>
                        <th><?=__('Stock place', 'woocommerce')?></th>
                        <th><?=__('Status', 'woocommerce')?></th>
                    </tr>

                    <?php if ($show_total_stock): ?>
                        <tr>
                            <td><?=$wc_stock_place_name?></td>
                            <td><p class="stock <?php echo esc_attr($this->get_availability_class($item_summary)); ?>"><?php echo wp_kses_post($this->get_total_availability_text($item_summary, $product)); ?></p></td>
                        </tr>
                    <?php else: ?>

                        <?php if ($show_wc_stock): ?>
                            <tr>
                                <td><?=$wc_stock_place_name?></td>
                                <td><?=$html;?></td>
                            </tr>
                        <?php endif;?>

                        <?php foreach ($item_summary as $item): ?>
                            <tr>
                                <td><?=$item['stockPointName'];?></td>
                                <td><p class="stock <?php echo esc_attr($this->get_availability_class($item)); ?>"><?php echo wp_kses_post($this->get_availability_text($item, $product)); ?></p></td>
                            </tr>
                        <?php endforeach;?>

                    <?php endif;?>

                </table>
            <?php

            $html = ob_get_clean();

            return $html;
        }

        public function get_article_stockplace_stock_level($stock, $article) {
            try {
                $stockPointCode = get_option('fortnox_warehouse_primary_stockplace');
                $item_summary = WC_FH()->fortnox->warehouse_item_summary($article['ArticleNumber'], $stockPointCode);
        
                $item = null;

                $show_total_stock = wc_string_to_bool(get_option('fortnox_warehouse_show_total_stock'));

                if ($show_total_stock) {
                    $stock = 0;

                    $item_summary = $this->filter_stock_places($item_summary);

                    foreach ($item_summary as $item) {
                        $stock += $this->get_avaliable_quantity($item);
                    }
                    return $stock;
                }

                foreach ($item_summary as $summary_item) {
                    if (isset($summary_item['stockPointCode']) && $summary_item['stockPointCode'] == $stockPointCode) {
                        $item = $summary_item;
                        break;
                    }
                }
        
                if ($item === null) {
                    throw new Fortnox_Exception('No item found with given stockPointCode');
                }
        
                return intval($item['availableQuantity']);
            } catch (Fortnox_Exception $e) {
                WC_FH()->logger->add(sprintf('get_article_stockplace_stock_level: Error when fetching item summary for Article %s: %s', $article['ArticleNumber'], $e->getMessage()));
            } catch (Fortnox_API_Exception $e) {
                WC_FH()->logger->add(sprintf('get_article_stockplace_stock_level: Error when fetching item summary for Article %s', $article['ArticleNumber']));
            }
        }

        public function warehouse_after_get_details($document,$order){



            $document['StockPointCode'] = get_option('fortnox_warehouse_primary_stockplace');

            WC_FH()->logger->add(sprintf('warehouse_after_get_details (%s): StockPointCode set to %s', $order->get_id() ,$document['StockPointCode']));

            return $document;

        }

        public function warehouse_after_get_order_item($row, $item, $order) {
            if (!array_key_exists('ArticleNumber',$row)){
                return $row;
            }

            if ($row['ArticleNumber'] == 'API_BLANK') {
                return $row;
            }

            $row['StockPointCode'] = get_option('fortnox_warehouse_primary_stockplace');

            return $row;
        }

        public function filter_stock_places($item_summary) {
            if (count(get_option('fortnox_warehouse_show_stockplaces_filter',[])) == 0){
                return $item_summary;
            }

            return array_filter($item_summary, function ($item) {
                return in_array($item['stockPointCode'], get_option('fortnox_warehouse_show_stockplaces_filter', [])); 
            });
        }

        /**
         * Get the quantity avaliable for the current article in a stockplace
         *
         * @since  5.3.0
         * @param  array $item The warehouse item linked to the current product and stock place
         * @return int
         */
        protected function get_avaliable_quantity($item)
        {
            if (!isset($item['availableQuantity'])) {
                return 0;
            }

            return $item['availableQuantity'];
        }

        /**
         * Checks if the current article has stock in the stockplace
         *
         * @since  5.3.0
         * @param  array $item The warehouse item linked to the current product and stock place
         * @return int
         */
        protected function is_in_stock($item)
        {
            return $this->get_avaliable_quantity($item) > 0;
        }

        /**
         * Get availability text based on stock status.
         *
         * @since  5.3.0
         * @param  array $item The warehouse item linked to the current product and stock place
         * @return string
         */
        protected function get_availability_text($item, $product)
        {
            if (!$this->is_in_stock($item)) {
                $availability = __('Out of stock', 'woocommerce');
            } else {
                $availability = $this->format_stock_for_display($item, $product);
            }
            return apply_filters('fortnox_warehouse_get_availability_text', $availability, $item);
        }

        protected function get_total_availability_text($item_summary, $product)
        {
            $stocklevel = 0;

            foreach ($item_summary as $item) {
                $stocklevel += $this->get_avaliable_quantity($item);
            }

            if ($stocklevel <= 0) {
                $availability = __('Out of stock', 'woocommerce');
            } else {
                $availability = $this->format_stock_for_display(['availableQuantity' => $stocklevel], $product);
            }

            return apply_filters('fortnox_warehouse_get_availability_text', $availability, $item);
        }

        /**
         * Get availability classname based on stock status.
         *
         * @since  5.3.0
         * @param  array $item The warehouse item linked to the current product and stock place
         * @return string
         */
        protected function get_availability_class($item)
        {
            if (!$this->is_in_stock($item)) {
                $class = 'out-of-stock';
            } else {
                $class = 'in-stock';
            }
            return apply_filters('fortnox_warehouse_get_availability_class', $class, $item);
        }

        /**
         * Format the stock amount ready for display based on settings.
         *
         * @since  5.3.0
         * @param  array $item The warehouse item linked to the current product and stock place
         * @return string
         */
        protected function format_stock_for_display($item, $product)
        {
            $display = __('In stock', 'woocommerce');
            $stock_amount = $this->get_avaliable_quantity($item);

            switch (get_option('woocommerce_stock_format')) {
                case 'low_amount':
                    if ($stock_amount <= wc_get_low_stock_amount($product)) {
                        /* translators: %s: stock amount */
                        $display = sprintf(__('Only %s left in stock', 'woocommerce'), $this->format_stock_quantity_for_display($stock_amount, $item));
                    }
                    break;
                case '':
                    /* translators: %s: stock amount */
                    $display = sprintf(__('%s in stock', 'woocommerce'), $this->format_stock_quantity_for_display($stock_amount, $item));
                    break;
            }

            return $display;
        }

        /**
         * Format the stock quantity ready for display.
         *
         * @since  5.3.0
         * @param  int $stock_quantity Stock quantity.
         * @param  array $item The warehouse item linked to the current product and stock place
         * @return string
         */
        protected function format_stock_quantity_for_display($stock_quantity, $item)
        {
            return apply_filters('fortnox_warehouse_format_stock_quantity', $stock_quantity, $item);
        }

        public function get_warehouse_stockpoints(){
            if (($stockpoints = get_fortnox_hub_transient('fortnox_warehouse_stockpoints'))) {
                return $stockpoints;
            }

            try{
                $stockpoints = WC_FH()->fortnox->warehouse_stockpoints();

                set_fortnox_hub_transient('fortnox_warehouse_stockpoints',$stockpoints, DAY_IN_SECONDS);
    
                return $stockpoints;
            }catch (Fortnox_API_Exception $e) {
                WC_FH()->logger->add(sprintf('get_warehouse_stockpoints: Error when fetching stockpoints'));
            }
            
        }

    }

    new Woo_Fortnox_Warehouse_Handler();
}