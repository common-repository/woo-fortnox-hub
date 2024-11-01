<?php
/**
 * Adds fortnox specific fields.
 *
 * @package WooCommerce\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

woocommerce_wp_select(
    array(
        'id' => 'fortnox_cost_center',
        'value' => WCFH_Util::get_metadata($product_object, 'CostCenter'),
        'label' => '<abbr title="' . esc_attr__('Cost Center', 'woo-fortnox-hub') . '">' . esc_html__('Cost Center', 'woo-fortnox-hub') . '</abbr>',
        'options' => WCFH_Util::get_cost_centers(),
        'desc_tip' => true,
        'description' => __('Cost Center to use for the Fortnox Article in Orders/Invoices.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_select(
    array(
        'id' => 'fortnox_project',
        'value' => WCFH_Util::get_metadata($product_object, 'Project'),
        'label' => '<abbr title="' . esc_attr__('Project', 'woo-fortnox-hub') . '">' . esc_html__('Project', 'woo-fortnox-hub') . '</abbr>',
        'options' => WCFH_Util::get_projects(),
        'desc_tip' => true,
        'description' => __('Project to use for the Fortnox Article in Orders/Invoices.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_manufacturer",
        'value' => WCFH_Util::get_metadata($product_object, 'Manufacturer'),
        'label' => __('Manufacturer', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product manufacturer from Fortnox.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_manufacturer_article_number",
        'value' => WCFH_Util::get_metadata($product_object, 'ManufacturerArticleNumber'),
        'label' => __('Manufacturer article', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product Manufacturer article from Fortnox.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_stock_place",
        'value' => WCFH_Util::get_metadata($product_object, 'StockPlace'),
        'label' => __('Stock place', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product stock place from Fortnox.', 'woo-fortnox-hub'),
    )
);

woocommerce_wp_text_input(
    array(
        'id' => "fortnox_unit",
        'value' => WCFH_Util::get_metadata($product_object, 'Unit'),
        'label' => __('Unit', 'woo-fortnox-hub'),
        'desc_tip' => true,
        'description' => __('Product unit from Fortnox.', 'woo-fortnox-hub'),
    )
);

if ('_fortnox_ean' == get_option('fortnox_metadata_mapping_ean')) {
    woocommerce_wp_text_input(
        array(
            'id' => "fortnox_barcode",
            'value' => WCFH_Util::get_metadata($product_object, 'EAN'),
            'label' => __('Barcode', 'woo-fortnox-hub'),
            'desc_tip' => true,
            'description' => __('Product barcode from Fortnox.', 'woo-fortnox-hub'),
        )
    );
}
