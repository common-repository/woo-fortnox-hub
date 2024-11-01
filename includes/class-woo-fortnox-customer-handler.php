<?php

/**
 * This class contains function to handle the customer data interaction with Fortnox
 *
 * @package   Woo_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2021 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_Hub_Customer_Handler', false)) {
    class Woo_Fortnox_Hub_Customer_Handler
    {
        public function __construct()
        {
            add_action('woo_fortnox_hub_create_customer_invoice', array($this, 'create_customer'));
            add_action('woo_fortnox_hub_create_customer_order', array($this, 'create_customer'));
            add_action('woo_fortnox_hub_create_customer', array($this, 'create_customer'));
            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 100);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 100, 2);
            add_filter('woocommerce_save_settings_fortnox_hub_customers', array($this, 'save_settings_section'));
            add_filter('fortnox_after_get_details', array($this, 'add_delivery_details_to_document'), 10, 2);
            add_filter('fortnox_after_get_details', array($this, 'add_billing_details_to_document'), 10, 2);
        }

        /**
         * Add setting section for customer settings
         *
         * @param array $sections The incoming array of sections for this plugin settings
         *
         * @since 1.0.0
         *
         * @return array The outgoing array of sections for this plugin settings
         */
        public function add_settings_section($sections)
        {
            if (!array_key_exists('customers', $sections)) {
                $sections = array_merge($sections, array('customers' => __('Customers', 'woo-fortnox-hub')));
            }
            return $sections;
        }

        /**
         * Save setting section for customer settings
         *
         * @param bool $true
         *
         * @since 1.0.0
         *
         * @return bool The result of the saving of this section.
         */
        public function save_settings_section($true)
        {
            return $true;
        }

        /**
         * Get the settings for customers
         *
         * @param array $settings The incoming settings array
         * @param string $current_section The current setting used
         *
         * @since 1.0.0
         *
         * @return array The outgoing settings array
         */
        public function get_settings($settings, $current_section)
        {
            if ('customers' === $current_section) {
                $settings[] = [
                    'title' => __('Customer options', 'woo-fortnox-hub'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'fortnox_customers_options',
                ];

                $settings[] = [
                    'title' => __('Do not update billing', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'default' => '',
                    'desc' => __('Check if you do not want customer billing details to be updated on existing Fortnox customers. The billing details will be updated only when the customer does not exist and is created by the plugin.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_do_not_update_customer_billing',
                ];

                $settings[] = [
                    'title' => __('Do not update delivery', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'default' => '',
                    'desc' => __('Check if you do not want customer delivery details to be updated on existing Fortnox customers. The delivery details will be updated only when the customer does not exist and is created by the plugin.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_do_not_update_customer_delivery',
                ];

                $settings[] = [
                    'title' => __('Delivery on document only', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'default' => '',
                    'desc' => __('Check if you do want Fortnox customer delivery details to update the order/invoice only. No delivery details will be updated on the customer card.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_delivery_details_on_document_only',
                ];

                $settings[] = [
                    'title' => __('Billing on document only', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'default' => '',
                    'desc' => __('Check if you do want Fortnox customer billing details to update the order/invoice only. No billing details will be updated on the customer card.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_billing_details_on_document_only',
                ];

                $settings[] = [
                    'title' => __('Default delivery type', 'woo-fortnox-hub'),
                    'type' => 'select',
                    'desc' => __('Set the preferred default delivery option for new customers created by the plugin', 'woo-fortnox-hub'),
                    'default' => '',
                    'options' => array(
                        '' => __('Use Fortnox default', 'woo-fortnox-hub'),
                        'PRINT' => __('Use print', 'woo-fortnox-hub'),
                        'EMAIL' => __('Use e-mail.', 'woo-fortnox-hub'),
                        'PRINTSERVICE' => __('Use external printservice.', 'woo-fortnox-hub'),
                    ),
                    'id' => 'wfh_customer_default_delivery_types',
                ];

                $settings[] = [
                    'title' => __('Do not update invoice email', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'default' => '',
                    'desc' => __('Check if you do not want to update an existing customer card invoice email with the email from the WooCommerce order.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_do_not_update_customercard_invoice_email',
                ];

                $settings[] = [
                    'title' => __('Do not update order email', 'woo-fortnox-hub'),
                    'type' => 'checkbox',
                    'default' => '',
                    'desc' => __('Check if you do not want to update an existing customer card order email with the email from the WooCommerce order.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_do_not_update_customercard_order_email',
                ];

                $settings[] = [
                    'title' => __('Identify customers by', 'woo-fortnox-hub'),
                    'type' => 'select',
                    'default' => '',
                    'desc' => __('The plugin needs to find out if the customer already exists in Fortnox. The first customer with a matching email will be selected. The mapping can also be done by organisation number. In this a metadatafield named "_organisation_number" has to be present on the order', 'woo-fortnox-hub'),
                    'options' => array(
                        '' => __('Customer email', 'woo-fortnox-hub'),
                        'organisation_number' => __('Organisation number (_organisation_number)', 'woo-fortnox-hub'),
                        '_meta' => __('Use a configurable metadata field', 'woo-fortnox-hub'),
                    ),
                    'id' => 'fortnox_identify_customers_by',
                ];

                if ('_meta' == get_option('fortnox_identify_customers_by')) {
                    $settings[] = [
                        'title' => __('Organisation number meta', 'woo-fortnox-hub'),
                        'type' => 'text',
                        'desc' => __('Enter the name of the product metadata field that should be used.', 'woo-fortnox-hub'),
                        'default' => '',
                        'id' => 'fortnox_organisation_number_meta',
                    ];
                }

                if (get_option('fortnox_identify_customers_by')) {
                    $settings[] = [
                        'title' => __('Require organisation number', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'desc' => __('Stop the order processing if the organisation number can not be found.', 'woo-fortnox-hub'),
                        'default' => '',
                        'id' => 'fortnox_organisation_number_only',
                    ];
                }

                if (get_option('fortnox_identify_customers_by')) {
                    $settings[] = [
                        'title' => __('Organisation number means company', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'desc' => __('Will set the customer as Company on the customer card in Fortnox if organisation number is present.', 'woo-fortnox-hub'),
                        'default' => '',
                        'id' => 'fortnox_set_company_if_organisation_number_present',
                    ];
                }

                if (!(empty($payment_gateways = WCFH_Util::get_available_payment_gateways()))) {
                    foreach ($payment_gateways as $key => $payment_gateway) {
                        $settings[] = [
                            'title' => sprintf(__('Send invoice for %s', 'woo-fortnox-hub'), $payment_gateway->get_title()),
                            'default' => get_option('fortnox_send_customer_email_invoice'),
                            'type' => 'checkbox',
                            'desc' => sprintf(__('Check if you do want the invoice in Fortnox to be emailed to the customer when the WooCommerce order using %s as payment method is set to completed.', 'woo-fortnox-hub'), $payment_gateway->get_title()),
                            'id' => 'fortnox_send_customer_email_invoice_' . $key,
                        ];

                        if (wc_string_to_bool(get_option('fortnox_send_customer_email_invoice_payment_method_specific')) && wc_string_to_bool(get_option('fortnox_send_customer_email_invoice_' . $key))) {
                            $settings[] = [
                                'title' => __('Reply-adress for ' . $payment_gateway->get_title(), 'woo-fortnox-hub'),
                                'type' => 'email',
                                'default' => '',
                                'id' => 'fornox_invoice_email_from_' . $key,
                            ];
        
                            $settings[] = [
                                'title' => __('E-mail subject for ' . $payment_gateway->get_title(), 'woo-fortnox-hub'),
                                'type' => 'text',
                                'desc' => __('Subject text on the Fortnox mail containing the invoice. The variable {no} = document number. The variable {name} =  customer name', 'woo-fortnox-hub'),
                                'id' => 'fornox_invoice_email_subject_' . $key,
                            ];
        
                            $settings[] = [
                                'title' => __('E-mail body for ' . $payment_gateway->get_title(), 'woo-fortnox-hub'),
                                'desc' => __('Body text on the Fortnox mail containing the invoice.', 'woo-fortnox-hub'),
                                'id' => 'fornox_invoice_email_body_' . $key,
                                'css' => 'width:100%; height: 65px;',
                                'type' => 'textarea',
                            ];
                        }
                    }

                    if (!wc_string_to_bool(get_option('fortnox_send_customer_email_invoice_payment_method_specific'))) {
                        $settings[] = [
                            'title' => __('Reply-adress', 'woo-fortnox-hub'),
                            'type' => 'email',
                            'default' => '',
                            'id' => 'fornox_invoice_email_from',
                        ];
    
                        $settings[] = [
                            'title' => __('E-mail subject', 'woo-fortnox-hub'),
                            'type' => 'text',
                            'desc' => __('Subject text on the Fortnox mail containing the invoice. The variable {no} = document number. The variable {name} =  customer name', 'woo-fortnox-hub'),
                            'id' => 'fornox_invoice_email_subject',
                        ];
    
                        $settings[] = [
                            'title' => __('E-mail body', 'woo-fortnox-hub'),
                            'desc' => __('Body text on the Fortnox mail containing the invoice.', 'woo-fortnox-hub'),
                            'id' => 'fornox_invoice_email_body',
                            'css' => 'width:100%; height: 65px;',
                            'type' => 'textarea',
                        ];
                    }

                }

                $settings[] = [
                    'type' => 'sectionend',
                    'id' => 'fortnox_customers_options',
                ];
            }

            return $settings;
        }

        /**
         * Get the VAT-type for a customer on an order
         * The type can be "SEVAT", "SEREVERSEDVAT", "EUREVERSEDVAT", "EUVAT", or "EXPORT".
         *
         * @param WC_Order $order The order containing the customer
         *
         * @since 1.0.0
         *
         * @return string the VAT-type
         */
        private function get_vat_type($order)
        {
            $country = WCFN_Accounts::get_billing_country($order);
            if ('SE' == $country) {
                $vat_type = 'SEVAT';
            } elseif (WCFH_Util::is_european_country($country)) {
                if (WCFH_Util::eu_number_is_validated($order)) {
                    $vat_type = 'EUREVERSEDVAT';
                } else {
                    $vat_type = 'EUVAT';
                }
            } else {
                $vat_type = 'EXPORT';
            }
            return $vat_type;
        }

        /**
         * create billing details array to be sent to fortnox
         *
         * @param WC_Order $order
         * @param string $email
         * @param mixed $current_customer
         * @param string $organisation_number
         * @param boolean $customer_card is set to true if the billing details are to be used for a customer card, false if used in a document
         * @return array
         */
        public function billing_details(&$order, $email, $current_customer, $organisation_number, $customer_card = true)
        {

            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $company_name = $order->get_billing_company();

            $is_company = $company_name ? true : false;

            if ($this->get_vat_number($order, $current_customer)) {
                $is_company = true;
            }

            if (wc_string_to_bool(get_option('fortnox_set_company_if_organisation_number_present'))) {
                WC_FH()->logger->add(sprintf('create_customer (%s): Checking if customer is company - organisation number is %s', $order->get_id(), $organisation_number));

                if ($organisation_number) {
                    WC_FH()->logger->add(sprintf('create_customer (%s): Organisation number present. Customer is company', $order->get_id()));
                    $is_company = true;
                } else {
                    WC_FH()->logger->add(sprintf('create_customer (%s): Organisation number not present.', $order->get_id()));
                }
            }

            WC_FH()->logger->add(sprintf('create_customer (%s): Customer is company: %s', $order->get_id(), ($is_company ? 'true' : 'false')));

            $default_delivery_type = ($current_customer && isset($current_customer['DefaultDeliveryTypes'])) ? $current_customer['DefaultDeliveryTypes'] : get_option('wfh_customer_default_delivery_types'); //PRINT EMAIL or PRINTSERVICE

            $data = array(
                "Address1" => WCFH_Util::clean_fortnox_text($order->get_billing_address_1(), 1024, 'API_BLANK'),
                "Address2" => WCFH_Util::clean_fortnox_text($order->get_billing_address_2(), 1024, 'API_BLANK'),
                "City" => WCFH_Util::clean_fortnox_text($order->get_billing_city(), 1024),
                "ZipCode" => WCFH_Util::clean_fortnox_text($order->get_billing_postcode(), 10),
                "Phone1" => WCFH_Util::clean_fortnox_text($order->get_billing_phone(), 1024),
                "YourReference" => WCFH_Util::clean_fortnox_text($customer_name, 50),
            );

            if ($customer_card) {

                $show_price_vat_included = !$is_company;

                if (wc_string_to_bool(get_option('fortnox_always_show_price_vat_included'))) {
                    $show_price_vat_included = true;
                    WC_FH()->logger->add(sprintf('create_customer (%s): Always show price vat included - %s', $order->get_id(), $show_price_vat_included ? 'true' : 'false'));
                } elseif (wc_string_to_bool(get_option('fortnox_never_show_price_vat_included'))) {
                    $show_price_vat_included = false;
                    WC_FH()->logger->add(sprintf('create_customer (%s): Never show price vat included - %s', $order->get_id(), $show_price_vat_included ? 'true' : 'false'));
                } else {
                    WC_FH()->logger->add(sprintf('create_customer (%s): Use default show price vat included - %s', $order->get_id(), $show_price_vat_included ? 'true' : 'false'));
                }

                $data = array_merge($data, array(
                    "Name" => WCFH_Util::clean_fortnox_text($company_name ? $company_name : $customer_name, 1024),
                    "CountryCode" => WCFN_Accounts::get_billing_country($order),
                    "DefaultDeliveryTypes" => array(
                        "Invoice" => $default_delivery_type,
                        "Order" => $default_delivery_type,
                        "Offer" => $default_delivery_type,
                    ),
                    "ShowPriceVATIncluded" => $show_price_vat_included,
                    "Type" => ($is_company ? 'COMPANY' : 'PRIVATE'),
                    "Currency" => WCFH_Util::clean_fortnox_text($order->get_currency(), 3),
                    "OrganisationNumber" => WCFH_Util::clean_fortnox_text($organisation_number, 30),
                    "Email" => WCFH_Util::clean_fortnox_text($email, 1024),
                    "VATType" => $this->get_vat_type($order),
                    "VATNumber" => $this->get_vat_number($order, $current_customer),
                ));

                if (!($current_customer && wc_string_to_bool(get_option('fortnox_do_not_update_customercard_invoice_email')))) {
                    $data["EmailInvoice"] = apply_filters('fortnox_customercard_invoice_email', WCFH_Util::clean_fortnox_text($email, 1024));
                }

                if (!($current_customer && wc_string_to_bool(get_option('fortnox_do_not_update_customercard_order_email')))) {
                    $data["EmailOrder"] = apply_filters('fortnox_customercard_order_email', WCFH_Util::clean_fortnox_text($email, 1024));
                }

            } else {
                $data["CustomerName"] = WCFH_Util::clean_fortnox_text($company_name ? $company_name : $customer_name, 1024);
                $data["Country"] = Fortnox_Countries::get_country(WCFN_Accounts::get_billing_country($order));
            }

            return $data;

        }

        /**
         * Add delivery details to a Fortnox document
         *
         * @param array $document An array to update a Fortnox document
         * @param WC_Order $order The WooCommerce order to use as basis when updating the Fortnox document
         *
         * @since 4.4.0
         *
         * @return array An array to update a Fortnox document
         */
        public function add_delivery_details_to_document($document, $order)
        {
            if (!WCFH_Util::is_izettle($order)) {
                if (wc_string_to_bool(get_option('fortnox_delivery_details_on_document_only'))) {
                    return array_merge($document, $this->delivery_details($order, false));
                }
            }

            return $document;
        }

        /**
         * Add billing details to a Fortnox document
         *
         * @param array $document An array to update a Fortnox document
         * @param WC_Order $order The WooCommerce order to use as basis when updating the Fortnox document
         *
         * @since 4.4.0
         *
         * @return array An array to update a Fortnox document
         */
        public function add_billing_details_to_document($document, &$order)
        {

            if (wc_string_to_bool(get_option('fortnox_billing_details_on_document_only'))) {
                if (!WCFH_Util::is_izettle($order)) {
                    $email = $this->get_billing_email($order);
                    $organisation_number = $this->get_organisation_number($order);
                    $customer = $this->get_fortnox_customer($order, $email, $organisation_number);
                    return array_merge($document, $this->billing_details($order, $email, $customer, $organisation_number, false));
                }
            }

            return $document;

        }

        /**
         * Create delivery details array to send to Fortnox
         *
         * @param WC_Order $order
         * @param boolean $customer_card is set to true if the billing details are to be used for a customer card, false if used in a document
         * @return array
         */
        public function delivery_details($order, $customer_card = true)
        {
            $data = array();

            if ($order->get_formatted_shipping_address()) {

                $shipping_person = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();

                if ($shipping_company = $order->get_shipping_company()) {
                    $shipping_to = $shipping_company . ', ' . __('Att:', 'woo-fortnox-hub') . ' ' . $shipping_person;
                } else {
                    $shipping_to = $shipping_person;
                }

                $data = array(
                    "DeliveryName" => WCFH_Util::clean_fortnox_text($shipping_to, 1024),
                    "DeliveryAddress1" => WCFH_Util::clean_fortnox_text($order->get_shipping_address_1(), 1024, 'API_BLANK'),
                    "DeliveryAddress2" => WCFH_Util::clean_fortnox_text($order->get_shipping_address_2(), 1024, 'API_BLANK'),
                    "DeliveryCity" => WCFH_Util::clean_fortnox_text($order->get_shipping_city(), 1024),
                    "DeliveryZipCode" => WCFH_Util::clean_fortnox_text($order->get_shipping_postcode(), 10),
                );

                if ($customer_card) {
                    $data["DeliveryCountryCode"] = $order->get_shipping_country();
                } else {
                    $data["DeliveryCountry"] = Fortnox_Countries::get_country($order->get_shipping_country());
                }

            }

            return $data;
        }

        /**
         * Get organisation number
         *
         * @since 4.6.0
         * @param string|int $order_id
         *
         * The option 'fortnox_identify_customers_by' can have three values
         *
         * '' : No organisation number, the email adress will be used ad identifier
         * 'organisation_number' : Use the metadata '_organisation_number' from the order
         * '_meta' : Use metadata in the option 'fortnox_organisation_number_meta' as the metadata to get the organisation number
         *
         * The filter 'fortnox_organisation_number' can be used to alter the result.
         *
         * @return string|bool Returns the organisation number or blank/false if not found
         */

        public function get_organisation_number(&$order)
        {

            $organisation_number = false;
            $order_id = $order->get_id();
            $identify_customers_by = get_option('fortnox_identify_customers_by');

            if ($identify_customers_by) {
                if ('organisation_number' == $identify_customers_by) {
                    $organisation_number = $order->get_meta('_organisation_number', true);
                } elseif ($organisation_number_meta = get_option('fortnox_organisation_number_meta')) {
                    $organisation_number = $order->get_meta($organisation_number_meta, true);
                }
                if (!$organisation_number && wc_string_to_bool(get_option('fortnox_organisation_number_only'))) {
                    throw new Fortnox_Exception(__('Organisation number not found', 'woo-fortnox-hub'));
                }
            }

            $organisation_number = str_replace('-', '', $organisation_number);

            $organisation_number = trim($organisation_number);

            if (wc_string_to_bool(get_option('fortnox_skip_organisation_number_validation'))) {
                return apply_filters('fortnox_organisation_number', $organisation_number, $order_id);
            }

            if (strlen($organisation_number) > 10) {
                if (!in_array(substr($organisation_number, 0, 2), array('19', '20'))) {
                    WC_FH()->logger->add(sprintf('Organisation number is too long: %s', $organisation_number));
                    throw new Fortnox_Exception(__('Organisation number is too long', 'woo-fortnox-hub'));
                } else {
                    $organisation_number = substr($organisation_number, 2);
                }
            }

            if ($organisation_number != false && strlen($organisation_number) < 10) {
                WC_FH()->logger->add(sprintf('Organisation number is too short: %s', $organisation_number));
                throw new Fortnox_Exception(__('Organisation number is too short', 'woo-fortnox-hub'));
            }

            return apply_filters('fortnox_organisation_number', $organisation_number, $order_id);

        }

        /**
         * Get VAT number
         *
         * @since 5.1.5
         * @param string $vat_number
         * @param array $customer
         *
         * Clean the organisation number having an existing customer using the common used format xxxxxxnnnn with the required format xxxxxx-nnnn.
         * This must be done before saving the customer in order to prevent an error when saving the customer.
         * https://developer.fortnox.se/blog/new-validation-for-vat-number/
         *
         * @return string Returns the organisation number
         */
        public function get_vat_number(&$order, $customer)
        {

            if (wc_string_to_bool(get_option('fortnox_skip_vat_number'))) {
                return '';
            }

            if ($vat_number = $order->get_meta('_billing_vat_number', true)) {
                return $vat_number;
            }

            if ($vat_number = $order->get_meta('_vat_number', true)) {
                return $vat_number;
            }

            if ($vat_number = $order->get_meta('vat_number', true)) {
                return $vat_number;
            }

            if ($vat_number = $order->get_meta('yweu_billing_vat', true)) {
                return $vat_number;
            }

            if (function_exists('alg_wc_eu_vat_get_field_id')) {
                $field_id = alg_wc_eu_vat_get_field_id();
                $vat_number = $order->get_meta('_' . $field_id);
                if ($vat_number) {
                    return $vat_number;
                }
            }

            $clean_vat_number = wc_string_to_bool(get_option('fortnox_clean_vat_number'));

            if ($clean_vat_number && is_array($customer)) {
                if (array_key_exists("CustomerNumber", $customer)) {
                    $customer = WC_FH()->fortnox->getCustomer($customer["CustomerNumber"]);
                }
                if ('SE' === $customer['CountryCode'] && 10 === strlen($customer["VATNumber"]) || 11 === strlen($customer["VATNumber"])) {
                    return 'SE' . str_replace('-', '', $customer["VATNumber"]) . '01';
                }
            }

            return '';

        }

        /**
         * Creates a Fortnox customer array
         *
         * @param string $order_id
         * @return void
         */
        public function create_customer(&$order)
        {

            $order_id = $order->get_id();

            if (!apply_filters('fortnox_hub_filter_woocommerce_order', true, 'create_customer', $order_id)) {
                return;
            }

            if (!WCFH_Util::is_izettle($order)) {

                $customer_data = array();

                $organisation_number = $this->get_organisation_number($order);

                $email = $this->get_billing_email($order);

                $customer = $this->get_fortnox_customer($order, $email, $organisation_number);

                if ((!wc_string_to_bool(get_option('fortnox_billing_details_on_document_only'))) || !$customer ) {
                    if (!$customer || ($customer && !wc_string_to_bool(get_option('fortnox_do_not_update_customer_billing')))) {
                        $customer_data = $this->billing_details($order, $email, $customer, $organisation_number);
                    }
                }

                if ((!wc_string_to_bool(get_option('fortnox_delivery_details_on_document_only'))) || !$customer) {
                    if ((!$customer || ($customer && !wc_string_to_bool(get_option('fortnox_do_not_update_customer_delivery'))))) {
                        $customer_data = array_merge($this->delivery_details($order), $customer_data);
                    }
                }

                $customer_data = WCFH_Util::remove_blanks(apply_filters('fortnox_customer_data_before_processing', $customer_data, $order_id, $customer));

                if (false === $customer) {
                    $customer = WC_FH()->fortnox->addCustomer($customer_data);
                    WC_FH()->logger->add(sprintf('create_customer (%s): Created Fortnox customer %s', $order_id, $customer['CustomerNumber']));
                } elseif ($customer_data) {
                    if (isset($customer_data['Comments'])) {
                        $order->update_meta_data('_fortnox_customer_comments', $customer_data['Comments']);
                        $order->save_meta_data();
                    }
                    WC_FH()->logger->add(sprintf('create_customer (%s): Fortnox customer %s found. Updating customer details', $order_id, $customer['CustomerNumber']));
                    $customer = WC_FH()->fortnox->updateCustomer($customer['CustomerNumber'], $customer_data);
                }

                WC_FH()->logger->add(json_encode($customer_data, JSON_INVALID_UTF8_IGNORE));

                $customer_number = $customer['CustomerNumber'];

            } else {

                $customer_number = get_option('fortnox_izettle_customer_number');

            }

            WCFH_Util::set_fortnox_customer_number($order, $customer_number);

        }

        /**
         * Get the billing email from the order
         *
         * @since 5.4.4
         * @param WC_Order $order
         * @return string
         */
        private function get_billing_email($order)
        {
            return apply_filters('fortnox_customer_email', $order->get_billing_email(), $order->get_id());
        }

        /**
         * Get customer from Fortnox
         *
         * @since 5.4.4
         * @param WC_Order $order
         * @param mixed $email
         * @param mixed $organisation_number
         * @return array
         */
        private function get_fortnox_customer($order, $email = false, $organisation_number = false)
        {

            $order_id = $order->get_id();
            $customer = apply_filters('fortnox_get_customer', false, $order_id);

            if ($customer) {
                WC_FH()->logger->add(sprintf('create_customer (%s): Found customer by organisation number "%s" in Fortnox', $order_id, $organisation_number));
                return $customer;
            } elseif ($organisation_number) {
                WC_FH()->logger->add(sprintf('create_customer (%s): Searching for customer by organisation number "%s" in Fortnox', $order_id, $organisation_number));
                return apply_filters('fortnox_get_customer_by_organisation_number', WC_FH()->fortnox->get_first_customer_by_organisation_number(trim($organisation_number)), $order_id);
            } elseif ($email) {
                WC_FH()->logger->add(sprintf('create_customer (%s): Searching for customer %s by email in Fortnox', $order_id, $email));
                return apply_filters('fortnox_get_customer_by_email', WC_FH()->fortnox->get_first_customer_by_email($email), $order_id);
            } else {
                throw new Fortnox_Exception(__('No customer identifier found', 'woo-fortnox-hub'));
            }

        }

    }

    new Woo_Fortnox_Hub_Customer_Handler();
}
