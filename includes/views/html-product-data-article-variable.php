<?php
/**
 * Adds a barcode-field.
 *
 * @package WooCommerce\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_manufacturer_{$loop}",
        'value' => WCFH_Util::get_metadata($variation->ID, 'Manufacturer'),
        'label' => '<abbr title="' . esc_attr__('Manufacturer', 'woo-fortnox-hub') . '">' . esc_html__('Manufacturer', 'woo-fortnox-hub') . '</abbr>',
        'desc_tip' => true,
        'description' => __('Product manufacturer from Fortnox.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_manufacturer_article_number_{$loop}",
        'value' => WCFH_Util::get_metadata($variation->ID, 'ManufacturerArticleNumber'),
        'label' => __('Manufacturer article', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product Manufacturer article from Fortnox.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_purchase_price_{$loop}",
        'value' => WCFH_Util::get_metadata($variation->ID, 'PurchasePrice'),
        'label' => __('Purchase price', 'woo-fortnox-hub') . ' (' . get_woocommerce_currency_symbol() . ')',
        'desc_tip' => true,
        'description' => __('Purchase price from Fortnox.', 'woo-fortnox-hub'),
        'data_type' => 'price',
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_stock_place_{$loop}",
        'value' => WCFH_Util::get_metadata($variation->ID, 'StockPlace'),
        'label' => __('Stock place', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product stock place from Fortnox.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_unit_{$loop}",
        'value' => WCFH_Util::get_metadata($variation->ID, 'Unit'),
        'label' => __('Unit', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product unit from Fortnox.', 'woo-fortnox-hub'),
    )
);

if ('_fortnox_ean' == get_option('fortnox_metadata_mapping_ean', '_fortnox_ean')) {
    woocommerce_wp_text_input(
        array(
            'id' => "fortnox_barcode_{$loop}",
            'value' => WCFH_Util::get_metadata($variation->ID, 'EAN'),
            'label' => __('Barcode', 'woo-fortnox-hub'),
            'desc_tip' => true,
            'description' => __('Product barcode from Fortnox.', 'woo-fortnox-hub'),
        )
    );
}
