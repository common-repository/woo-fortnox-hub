<?php

/**
 * This class contains functions for around the user area in WooCommerce
 *
 * @package   Woo_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_Hub_User_Area', false)) {

    class Woo_Fortnox_Hub_User_Area 
    {

        public function __construct()
        {
            add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_custom_order_action'), 10, 2 );
            add_action('rest_api_init', array($this, 'register_rest_route'));
        }

        public function create_nonce($order_id, $user_id){  
            $nonce_action = 'fortnox_invoice_download_' . $order_id . '_' . $user_id;
            
            $nonce = wp_create_nonce($nonce_action);

            set_fortnox_hub_transient($nonce_action, $nonce, DAY_IN_SECONDS);
            
            return $nonce;
        }

        public function verify_nonce($nonce, $order_id, $user_id){

            $nonce_action = 'fortnox_invoice_download_' . $order_id . '_' . $user_id;
            
            $stored_nonce = get_fortnox_hub_transient($nonce_action);

            //If nonce is false return false
            if ($nonce === false) {
                return false;
            }
            
            if ($nonce === $stored_nonce) {
                return true;
            } else {
                return false;
            }
        }

        public function register_rest_route() {

            register_rest_route('woo_fortnox_hub', '/fortnox_invoice_download/(?P<nonce>[a-z0-9-]+)/(?P<order_id>\d+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'handle_fortnox_invoice_download'),
                'permission_callback' => array($this, 'verify_request'),
            ));
        }

        public function verify_request (WP_REST_Request $request): bool {

            $nonce = $request->get_param('nonce');
            $order_id = $request->get_param('order_id');
            $user_id = apply_filters('determine_current_user', get_current_user_id());

            
            if (!$nonce || !$order_id || !$user_id) {
                return false;
            }

            $nonce = $this->verify_nonce($nonce, $order_id, $user_id);
            
            if ($nonce === false) {
                return false;
            }

            $order = wc_get_order($order_id);

            $order_user_id = $order->get_user_id();

            if ($order_user_id !== $user_id) {
                return false;
            }
            

            return true;
        }

        public function add_custom_order_action($actions, $order) {

            
            if (!$order) {
                return $actions;
            }

            $order_id = $order->get_id();

            //Get invoice number for order
            $invoice_number = WCFH_Util::get_fortnox_invoice_number($order_id);

            if (!$invoice_number) {
                return $actions;
            }

            $actions['fortnox_hub_download_invoice'] = array(
                'url'  => rest_url('woo_fortnox_hub/fortnox_invoice_download/' . $this->create_nonce($order_id, get_current_user_id()) . '/' . $order_id),
                'name' => __('Invoice', 'woo-fortnox-hub'),
            );

            return $actions;
        }

        public function handle_fortnox_invoice_download(WP_REST_Request $request) {
            // Get the order id from the request
            $order_id = $request->get_param('order_id');
        
            WC_FH()->logger->add('Fortnox invoice download request for order id: ' . $order_id);
        
            $invoice_number = WCFH_Util::get_fortnox_invoice_number($order_id);

            //Fetch invoice from Fortnox
            $invoice = WC_FH()->fortnox->get_invoice($invoice_number);

            //Check if invoice has credit invoice
            if (!wc_string_to_bool(get_option('fortnox_hub_disable_credit_invoice_download'))) {
                if ($invoice['CreditInvoiceReference']) {
                    WC_FH()->logger->add('Fortnox invoice has credit invoice: ' . $invoice['CreditInvoiceReference']);
                    $invoice_number = $invoice['CreditInvoiceReference'];
                }
            }
        
            try {
                $pdf_contents = WC_FH()->fortnox->getInvoicePDF($invoice_number);
        
                // Set the headers to indicate that the content is a PDF
                header("Content-Description: File Transfer");
                header("Content-Transfer-Encoding: binary");
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $invoice_number . '.pdf"');
        
                // Output the PDF contents
                echo $pdf_contents;
        
                // Make sure to stop further script execution
                exit();
            } catch (Exception $e) {
                WC_FH()->logger->add('Fortnox invoice download failed: ' . $e->getMessage());
                //redirect to /my-account/orders/ page
                return ['status' => 301 , 'redirect' => get_permalink( get_option('woocommerce_myaccount_page_id') ) . 'orders/'];
            }
        }
    }



    new Woo_Fortnox_Hub_User_Area();
}