<?php

/**
 * This class handles syncing invoices with Fortnox.
 *
 * @package   Woo_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2020 BjornTech
 */

// Prevent direct file access
defined('ABSPATH') || exit;

if (!class_exists('Woo_Fortnox_Hub_Invoice_Status_Handler', false)) {

    class Woo_Fortnox_Hub_Invoice_Status_Handler
    {

        static $fn_invoice;

        public function __construct()
        {

            /**
             * Scheduler hooks
             */
            add_action('init', array($this, 'schedule_sync_fortnox_invoices'));
            add_action('action_sync_fortnox_invoices', array($this, 'sync_fortnox_invoices'));
            add_action('fortnox_process_changed_invoices', array($this, 'process_changed_invoices'));

            /**
             * Settings hooks
             */
            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 60);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 60, 2);
            add_filter('woocommerce_save_settings_fortnox_hub_invoice_status', array($this, 'save_settings_section'));
            add_action('woocommerce_settings_fortnox_check_invoices_options', array($this, 'show_check_invoices_button'), 10);

            if (get_option('fortnox_capture_klarna_orders')) {
                if ( class_exists( 'WC_Klarna_Order_Management' ) ) {
                    remove_action( 'woocommerce_order_status_completed', array( WC_Klarna_Order_Management::get_instance(), 'capture_klarna_order' ),1 );
                }
            }

            /**
             * Ajax hooks
             */
            add_action('wp_ajax_fortnox_check_invoices', array($this, 'ajax_check_invoices'));
        }

        /**
         * If set to sync automatically, do set action to check for changed invoices every minute
         *
         * @since 2.0.0
         * @return void
         */
        public function schedule_sync_fortnox_invoices()
        {

            if (wc_string_to_bool(get_option('fortnox_check_invoices_automatically'))) {
                if (false === as_next_scheduled_action('action_sync_fortnox_invoices')) {
                    as_schedule_recurring_action(time(), MINUTE_IN_SECONDS, 'action_sync_fortnox_invoices');
                }

                $actions = as_get_scheduled_actions(
                    array(
                        'hook' => 'action_sync_fortnox_invoices',
                        'status' => ActionScheduler_Store::STATUS_PENDING,
                        'claimed' => false,
                        'per_page' => -1,
                    ),
                    'ids'
                );
                if (count($actions) > 1) {
                    try{
                        as_unschedule_action('action_sync_fortnox_invoices');
                    }catch(\Throwable $throwable){
                        WC_FH()->logger->add(sprintf('schedule_sync_fortnox_invoices - No process to unschedule'));
                    }
                }

            } else {
                if (false !== as_next_scheduled_action('action_sync_fortnox_invoices')) {
                    as_unschedule_all_actions('action_sync_fortnox_invoices');
                }
            }
        }

        /**
         * Filter to add section for invoice status settings
         *
         * @since 1.0.0
         * @param array $sections
         * @return array $sections
         */
        public function add_settings_section($sections)
        {
            if (!array_key_exists('invoice_status', $sections)) {
                $sections = array_merge($sections, array('invoice_status' => __('Invoice status actions', 'woo-fortnox-hub')));
            }
            return $sections;
        }

        /**
         * Filter to save settings
         *
         * @since 1.0.0
         * @param bool $true
         * @return bool $true
         */
        public function save_settings_section($true)
        {
            if (isset($_POST['fortnox_check_invoices_automatically']) && wc_string_to_bool($_POST['fortnox_check_invoices_automatically']) && !wc_string_to_bool(get_option('fortnox_check_invoices_automatically'))) {
                $this_sync_time = date('Y-m-d H:i', current_time('timestamp', true) + get_option('fortnox_gmt_offset', 0));
                update_option('fortnox_hub_sync_last_sync_invoices', $this_sync_time, true);
                WC_FH()->logger->add(sprintf('save_settings_section: Setting invoice last sync time to %s', $this_sync_time));
            }
            return $true;
        }

        /**
         * Settings for invoice status settings
         */
        public function get_settings($settings, $current_section)
        {
            if ('invoice_status' == $current_section) {

                $accounting_method = WCFH_Util::get_accounting_method();

                $settings[] = [
                    'title' => __('Invoice status actions', 'woo-fortnox-hub'),
                    'type' => 'title',
                    'desc' => '<div class=fortnox_infobox>' . __('Select actions to be performed on when the status changes on a Fortnox invoice.</BR></BR>To do a manual update click "Check" end enter the number of days back to check for changed invoices.</BR></BR>If "Automatic check" is checked the plugin will check for changed invoices every minute.</BR>', 'woo-fortnox-hub') . '</div>',
                    'id' => 'fortnox_check_invoices_options',
                ];

                $settings[] = [
                    'title' => __('Automatic check', 'woo-fortnox-hub'),
                    'default' => '',
                    'type' => 'checkbox',
                    'desc' => __('Check for changed invoices automatically. Enable this to allow Fortnox Hub process, bookkeep and set invoices as paid automatically.', 'woo-fortnox-hub'),
                    'id' => 'fortnox_check_invoices_automatically',
                ];

                $settings[] = [
                    'title' => __('Automatic order completion', 'woo-fortnox-hub'),
                    'type' => 'select',
                    'desc' => __('Select if and in what state the invoice in Fortnox should make the WooCommerce order status to be set to "Completed".', 'woo-fortnox-hub'),
                    'default' => '',
                    'options' => array(
                        '' => __('Never change order status based on the Fortnox invoice', 'woo-fortnox-hub'),
                        'created' => __('Change order status when the invoice is created (Recommended)', 'woo-fortnox-hub'),
                        'sent' => __('Change order status when the invoice is sent', 'woo-fortnox-hub'),
                        'booked' => __('Change order status when the invoice is booked.', 'woo-fortnox-hub'),
                        'paid' => __('Change order status when the invoice is paid', 'woo-fortnox-hub'),
                    ),
                    'id' => 'fortnox_fortnox_set_order_status',
                ];

                if ('ACCRUAL' === $accounting_method) {

                    foreach (WCFH_Util::get_available_payment_gateways() as $key => $payment_gateway) {

                        $description = (($title = $payment_gateway->get_title()) ? $title : $payment_gateway->get_method_title());
    
                        $settings[] = [
                            'title' => sprintf(__('Automatically bookkeep %s', 'woo-fortnox-hub'), $description),
                            'type' => 'checkbox',
                            'default' => get_option('fortnox_book_invoice') ? get_option('fortnox_book_invoice') : '',
                            'desc' => sprintf(__('Automatically bookkeep Fortnox invoice created with %s as payment method when WooCommerce order is set to "Completed".', 'woo-fortnox-hub'), $description),
                            'id' => 'fortnox_book_invoice_' . $key,
                        ];
                    }
                    /*
                    $settings[] = [
                        'title' => __('Bookkeep invoices', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'default' => '',
                        'desc' => __('Automatically bookkeep invoices when the order is set to completed.', 'woo-fortnox-hub'),
                        'id' => 'fortnox_book_invoice',
                    ];
                    */

                    $paid_options = array(
                        '' => __('Never set to paid', 'woo-fortnox-hub'),
                        'booked' => __('Set to paid when the invoice is booked.', 'woo-fortnox-hub'),
                    );
                } elseif ('CASH' === $accounting_method) {

                    foreach (WCFH_Util::get_available_payment_gateways() as $key => $payment_gateway) {

                        $description = (($title = $payment_gateway->get_title()) ? $title : $payment_gateway->get_method_title());
    
                        $settings[] = [
                            'title' => sprintf(__('Automatically set as printed %s', 'woo-fortnox-hub'), $description),
                            'type' => 'checkbox',
                            'default' => get_option('fortnox_set_invoice_as_external_printed') ? get_option('fortnox_set_invoice_as_external_printed') : '',
                            'desc' => sprintf(__('Automatically set Fortnox invoice created with %s as payment method as printed when WooCommerce order is set to "Completed".', 'woo-fortnox-hub'), $description),
                            'id' => 'fortnox_set_invoice_as_external_printed_' . $key,
                        ];
                    }

                    /*$settings[] = [
                        'title' => __('Set as printed', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'desc' => __('Set a created invoice to externally printed when it has been created. When using the cash accounting method this is the way to mark the invoice as ready to be paid.', 'woo-fortnox-hub'),
                        'default' => 'yes',
                        'id' => 'fortnox_set_invoice_as_external_printed',
                    ];*/

                    $paid_options = array(
                        '' => __('Never set to paid', 'woo-fortnox-hub'),
                        'sent' => __('Set to paid when the invoice is sent.', 'woo-fortnox-hub'),
                        'created' => __('Set to paid when the invoice is created.', 'woo-fortnox-hub'),
                    );
                }

                foreach (WCFH_Util::get_available_payment_gateways() as $key => $payment_gateway) {

                    $description = (($title = $payment_gateway->get_title()) ? $title : $payment_gateway->get_method_title());

                    $settings[] = [
                        'title' => sprintf(__('Automatically set %s to paid', 'woo-fortnox-hub'), $description),
                        'type' => 'select',
                        'default' => '',
                        'options' => $paid_options,
                        'desc' => sprintf(__('Automatically set a Fortnox invoice created with %s as payment method to paid.', 'woo-fortnox-hub'), $description),
                        'id' => 'fortnox_automatic_payment_' . $key,
                    ];
                }

                if ($accounting_method === 'ACCRUAL') {
                    $klarna_capture_options = array(
                        '' => __('Never capture Klarna orders', 'woo-fortnox-hub'),
                        'booked' => __('Capture Klarna orders when the invoice is booked', 'woo-fortnox-hub'),
                    );
                } else {
                    $klarna_capture_options = array(
                        '' => __('Never capture Klarna orders', 'woo-fortnox-hub'),
                        'sent' => __('Capture Klarna orders when the invoice is sent', 'woo-fortnox-hub'),
                        'created' => __('Capture Klarna orders when the invoice is created', 'woo-fortnox-hub'),
                    );
                }

                if (class_exists('WC_Klarna_Order_Management')) {
                    $settings[] = [
                        'title' => __('Automatically capture Klarna orders', 'woo-fortnox-hub'),
                        'type' => 'select',
                        'default' => '',
                        'options' => $klarna_capture_options,
                        'desc' => __('Automatically capture Klarna orders', 'woo-fortnox-hub'),
                        'id' => 'fortnox_capture_klarna_orders',
                    ];
                }

                $settings[] = [
                    'type' => 'sectionend',
                    'id' => 'fortnox_check_invoices_options',
                ];
            }

            return $settings;
        }

        public function show_check_invoices_button()
        {
            echo '<div id=fortnox_titledesc_check_invoices>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label for="fortnox_check_invoices">' . __('Manual check', 'woo-fortnox-hub') . '<span class="woocommerce-help-tip" data-tip="' . __('Manually check invoice status a selected number of days back in time', 'woo-fortnox-hub') . '"></span></label>';
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            echo '<button name="fortnox_check_invoices" id="fortnox_check_invoices" class="button">' . __('Check', 'woo-fortnox-hub') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        public function ajax_check_invoices()
        {

            if (!wp_verify_nonce($_POST['nonce'], 'ajax-fortnox-hub')) {
                wp_die();
            }

            do_action('action_sync_fortnox_invoices', $_POST['sync_days']);

            $response = array(
                'result' => 'success',
                'message' => __('Invoice status check started.', 'woo-fortnox-hub'),
            );

            wp_send_json($response);
        }

        public function maybe_bookkeep_invoice($order, $order_id)
        {

            try {

                $parent_id = $order->get_parent_id();

                if ($parent_id && ('shop_order_refund' == $order->get_type())) {
                    $order = wc_get_order($parent_id);
                    WC_FH()->logger->add(sprintf('maybe_bookkeep_invoice (%s): Got credit invoice - changing order id from %s to %s', $parent_id,$order_id,$parent_id));
                    $order_id = $parent_id;
                }

                $payment_method = WCFH_Util::get_payment_method($order, 'maybe_bookkeep_invoice');

                $can_bookkeep = get_option('fortnox_book_invoice_' . $payment_method) ? get_option('fortnox_book_invoice_' . $payment_method) : get_option('fortnox_book_invoice');

                if (self::$fn_invoice['Total'] == 0 && !wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders'))) {
                    $can_bookkeep = true;
                }

                if (!wc_string_to_bool($can_bookkeep)) {
                    return;
                }

                if ('ACCRUAL' !== self::$fn_invoice['AccountingMethod']) {
                    WC_FH()->logger->add(sprintf('maybe_bookkeep_invoice (%s): Fortnox invoice %s is using accounting method %s and does not need to be booked', $order_id, self::$fn_invoice['DocumentNumber'], self::$fn_invoice['AccountingMethod']));
                    return;
                }

                $order_status = $order->get_status('edit');
                $accepted_statuses = apply_filters('fortnox_filter_maybe_bookkeep_invoice_on_status', array('completed','refunded'), $order);

                if (!in_array($order_status,$accepted_statuses)) {
                    WC_FH()->logger->add(sprintf('maybe_bookkeep_invoice (%s): Fortnox invoice %s do not book orders with status "%s"', $order_id, self::$fn_invoice['DocumentNumber'], $order_status));
                    return;
                }

                if (rest_sanitize_boolean(self::$fn_invoice['Cancelled'])) {
                    WC_FH()->logger->add(sprintf('maybe_bookkeep_invoice (%s): Fortnox Invoice %s was cancelled', $order_id, self::$fn_invoice['DocumentNumber']));
                    return;
                }

                if (rest_sanitize_boolean(self::$fn_invoice['Booked'])) {
                    WC_FH()->logger->add(sprintf('maybe_bookkeep_invoice (%s): Fortnox invoice %s already booked', $order_id, self::$fn_invoice['DocumentNumber']));
                    return;
                }

                WC_FH()->fortnox->book_invoice(self::$fn_invoice['DocumentNumber']);
                self::$fn_invoice = WC_FH()->fortnox->get_invoice(self::$fn_invoice['DocumentNumber']);

                WC_FH()->logger->add(sprintf('maybe_bookkeep_invoice (%s): Booking Fortnox Invoice %s', $order_id, self::$fn_invoice['DocumentNumber']));

            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
                Fortnox_Notice::add(sprintf(__("%s when processing order %s", 'woo-fortnox-hub'), $e->getMessage(), $order->get_order_number()));
            }

        }

        /**
         * Maybe set the invoice to externally printed.
         *
         * Only valid for invoices in status 'completed' and using the CASH accounting method.
         *
         * @since 2.0.0
         * @param WC_Order $order
         * @param string $order_id
         */
        public function maybe_external_print_invoice($order, $order_id)
        {

            try {

                $parent_id = $order->get_parent_id();

                if ($parent_id  && ('shop_order_refund' == $order->get_type())) {
                    $order = wc_get_order($parent_id);
                    WC_FH()->logger->add(sprintf('maybe_external_print_invoice (%s): Got credit invoice - changing order id from %s to %s', $parent_id,$order_id,$parent_id));
                    $order_id = $parent_id;
                }

                $payment_method = WCFH_Util::get_payment_method($order, 'maybe_external_print_invoice');

                $can_print = get_option('fortnox_set_invoice_as_external_printed_' . $payment_method) ? get_option('fortnox_set_invoice_as_external_printed_' . $payment_method) : get_option('fortnox_set_invoice_as_external_printed');

                if (self::$fn_invoice['Total'] == 0 && !wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders'))) {
                    $can_print = true;
                }

                if (!wc_string_to_bool($can_print)) {
                    return;
                }

                if ('yes' != get_option('fortnox_set_invoice_as_external_printed')) {
                    return;
                }

                $status = $order->get_status('edit');
                $accepted_statuses = apply_filters('fortnox_filter_maybe_external_print_invoice_on_status', array('completed','refunded'), $order);


                if (!in_array($status,$accepted_statuses)) {
                    return;
                }

                if ('CASH' !== self::$fn_invoice['AccountingMethod']) {
                    return;
                }

                if (rest_sanitize_boolean(self::$fn_invoice['Cancelled'])) {
                    WC_FH()->logger->add(sprintf('maybe_external_print_invoice (%s): Fortnox Invoice %s was cancelled', $order_id, self::$fn_invoice['DocumentNumber']));
                    return;
                }

                if (rest_sanitize_boolean(self::$fn_invoice['Sent'])) {
                    WC_FH()->logger->add(sprintf('maybe_external_print_invoice (%s): Fortnox Invoice %s already printed', $order_id, self::$fn_invoice['DocumentNumber']));
                    return;
                }

                WC_FH()->fortnox->external_print_invoice(self::$fn_invoice['DocumentNumber']);
                self::$fn_invoice = WC_FH()->fortnox->get_invoice(self::$fn_invoice['DocumentNumber']);

                WC_FH()->logger->add(sprintf('maybe_external_print_invoice (%s): Did set Fortnox invoice %s as printed externally', $order_id, self::$fn_invoice['DocumentNumber']));

            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
                Fortnox_Notice::add(sprintf(__("%s when processing order %s", 'woo-fortnox-hub'), $e->getMessage(), $order->get_order_number()));
            }

        }

        /**
         * Maybe set the invoice to externally printed.
         *
         * Only valid for invoices in status 'completed'
         *
         * @since 2.0.0
         * @param WC_Order $order
         * @param string $order_id
         */
        public function maybe_email_invoice($order, $order_id)
        {

            try {

                $wc_order_creates = WCFH_Util::fortnox_wc_order_creates($order);

                $parent_id = $order->get_parent_id();

                if ($parent_id  && ('shop_order_refund' == $order->get_type())) {
                    $parent = wc_get_order($parent_id);
                    $payment_method = WCFH_Util::get_payment_method($parent, 'maybe_email_invoice');
                } else {
                    $payment_method = WCFH_Util::get_payment_method($order, 'maybe_email_invoice');
                }

                if ('yes' != get_option('fortnox_send_customer_email_invoice_' . $payment_method, get_option('fortnox_send_customer_email_invoice'))) {
                    return;
                }

                $accepted_statuses = apply_filters('fortnox_filter_maybe_email_invoice_on_status', array('completed'), $order);
                $status = $order->get_status('edit');

                $use_nox_invoice = apply_filters('fortnox_use_nox_invoice', wc_string_to_bool(get_option('fortnox_use_nox_invoice')),$order);

                if (!in_array($status,$accepted_statuses)) {
                    WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Fortnox invoice %s do not send emails with status "%s"', $order_id, self::$fn_invoice['DocumentNumber'], $status));
                    return;
                }

                if (wc_string_to_bool(get_option('fortnox_extra_email_control')) && $order->get_meta('_fortnox_email_is_sent_for_order')) {
                    WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Fortnox Invoice was already sent - catched by internal control', $order_id));
                    return;
                }

                if (rest_sanitize_boolean(self::$fn_invoice['Cancelled'])) {
                    WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Fortnox Invoice was cancelled', $order_id));
                    return;
                }

                if (rest_sanitize_boolean(self::$fn_invoice['Sent'])) {
                    WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Fortnox Invoice was already sent', $order_id));
                    return;
                }

                if ($use_nox_invoice && rest_sanitize_boolean(self::$fn_invoice['NoxFinans'])) {
                    WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Fortnox Invoice was already sent via Fortnox Finans', $order_id));
                    return;
                }

                /**
                 * If the invoice was created via an order we do need to update the invoice mail info
                 */
                if ('order' === $wc_order_creates) {
                    $email_information = WCFH_Util::get_invoice_email_information($order);
                    WC_FH()->fortnox->updateInvoice(self::$fn_invoice['DocumentNumber'], $email_information);
                }

                if ($use_nox_invoice) {

                    WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Maybe sending Fortnox NOX invoice', $order_id));

                    if($order->get_meta('_fortnox_email_is_sent_for_order')) {
                        WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Fortnox Nox Invoice was already sent - catched by internal control for nox invoice', $order_id));
                        return;
                    }

                    $nox_send_method = 'EMAIL';

                    if (wc_string_to_bool(get_option('fortnox_use_customer_default_send_method'))) {
                        WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Using customer default delivery method', $order_id));

                        $nox_send_method = WCFH_Util::map_customer_nox_delivery_method($order);
                    }

                    if ($nox_send_method) {

                        WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Sending Fortnox NOX invoice for invoice %s with method %s', $order_id, self::$fn_invoice['DocumentNumber'], $nox_send_method));

                        $nox_invoice = array(
                            'InvoiceNumber' => self::$fn_invoice['DocumentNumber'],
                            'SendMethod' => $nox_send_method,
                            'Service' => 'LEDGERBASE'
                        );

                        WC_FH()->fortnox->send_nox_invoice($nox_invoice);
                    }

                } else {
                    WC_FH()->fortnox->email_invoice(self::$fn_invoice['DocumentNumber']);
                }

                $order->update_meta_data('_fortnox_email_is_sent_for_order',true);

                self::$fn_invoice = WC_FH()->fortnox->get_invoice(self::$fn_invoice['DocumentNumber']);

                WC_FH()->logger->add(sprintf('maybe_email_invoice (%s): Sent Fortnox Invoice %s to customer', $order_id, self::$fn_invoice['DocumentNumber']));

            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
                Fortnox_Notice::add(sprintf(__("%s when processing order %s", 'woo-fortnox-hub'), $e->getMessage(), $order->get_order_number()));
            }

        }

        /**
         * Set ocr-number as metadata on the WooCommerce order
         *
         * @since 4.0.0
         *
         * @param array $fn_invoice
         * @param WC_Order $order
         * @param string $order_id
         */
        public function set_ocr_on_order($order, $order_id)
        {

            if (!isset(self::$fn_invoice['OCR'])) {
                return;
            }

            if (self::$fn_invoice['OCR'] == $order->get_meta('fortnox_invoice_ocr', true)) {
                return;
            }

            $order->update_meta_data('fortnox_invoice_ocr', self::$fn_invoice['OCR']);
            $order->save();

            WC_FH()->logger->add(sprintf('set_ocr_on_order (%s): Added OCR "%s" on order', $order_id, self::$fn_invoice['OCR']));
        }

        /**
         *
         * Maybe set the WooCommerce order to completed
         *
         * @since 1.0.0
         *
         * @param array $fn_invoice
         * @param WC_Order $order
         * @param string $order_id
         */
        public function maybe_set_wc_order_to_completed($order, $order_id)
        {

            if (!apply_filters('fortnox_filter_set_wc_order_to_completed', true, $order, self::$fn_invoice)) {
                return;
            }

            $set_order_status = apply_filters('fortnox_filter_set_wc_order_to_completed', get_option('fortnox_fortnox_set_order_status'), $order, self::$fn_invoice);

            if (!$set_order_status) {
                WC_FH()->logger->add(sprintf('maybe_set_wc_order_to_completed (%s): Never set WooCommerce Order to "completed"', $order_id));
                return;
            }

            $status = $order->get_status();
            $accepted_statuses = apply_filters('fortnox_filter_set_wc_order_to_completed_on_status', array('processing','completed'), $order);

            if (!in_array($status, $accepted_statuses)) {
                WC_FH()->logger->add(sprintf('maybe_set_wc_order_to_completed (%s): Orderstatus is "%s" but must be "%s"', $order_id, $status, implode('" or "', $accepted_statuses)));
                return;
            }

            switch ($set_order_status) {

                case 'sent':
                    if (!rest_sanitize_boolean(self::$fn_invoice['Sent'])) {
                        return;
                    }
                    $message = __('Fortnox invoice %s was sent.', 'woo-fortnox-hub');
                    break;

                case 'booked':
                    if (!rest_sanitize_boolean(self::$fn_invoice['Booked'])) {
                        return;
                    }
                    $message = __('Fortnox invoice %s was booked.', 'woo-fortnox-hub');
                    break;

                case 'paid':
                    if (strlen(self::$fn_invoice['FinalPayDate']) !== 10) {
                        return;
                    }
                    $message = __('Fortnox invoice %s was paid.', 'woo-fortnox-hub');
                    break;

                case 'created':
                    $message = __('Fortnox invoice %s was created.', 'woo-fortnox-hub');
                    break;

                default:
                    return;
            }

            WC_FH()->logger->add(sprintf('maybe_set_wc_order_to_completed: Order %s set to status completed using invoice %s that was %s', $order_id, self::$fn_invoice['DocumentNumber'], $set_order_status));
            $order->set_status('completed', sprintf($message, self::$fn_invoice['DocumentNumber']));
            $order->save();
        }

        public function set_customer_invoice_to_paid($order, $order_id)
        {

            if ($order->get_parent_id() && ('shop_order_refund' == $order->get_type())) {
                return;
            }

            $payment_method = WCFH_Util::get_payment_method($order, 'set_customer_invoice_to_paid');

            $payment_account = get_option('fortnox_payment_account_' . $payment_method);

            if (self::$fn_invoice['Total'] == 0) {
                $mode_of_payment = !wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders')) ? 'API_BLANK' : false;
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): Using zero order mode of payment %s', $order_id, $mode_of_payment));
                $payment_account = FN_PAYOUT_BANK_ACCOUNT;
            } else {
                $mode_of_payment = get_option('fortnox_mode_of_payment_' . $payment_method);
            }

            if (!$mode_of_payment) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): No mode of payment was set for payment method %s on Fortnox Invoice %s', $order_id, $payment_method, self::$fn_invoice['DocumentNumber']));
                return;
            }

            $automatic_payment = get_option('fortnox_automatic_payment_' . $payment_method);

            if (self::$fn_invoice['Total'] == 0) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): No mode of payment was set for zero order - setting automatic payment to created', $order_id));
                $automatic_payment = 'created';
                return;
            }

            if (!$automatic_payment) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): No mode of payment was set for payment method %s on Fortnox Invoice %s', $order_id, $payment_method, self::$fn_invoice['DocumentNumber']));
                return;
            }

            //Cancelled
            if (rest_sanitize_boolean(self::$fn_invoice['Cancelled'])) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): Fortnox Invoice %s was cancelled', $order_id, self::$fn_invoice['DocumentNumber']));
                return;
            }

            if ($this->invoice_is_paid($order_id)) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): Fortnox Invoice %s was already paid', $order_id, self::$fn_invoice['DocumentNumber']));
                return;
            }

            if ("sent" === $automatic_payment && !rest_sanitize_boolean(self::$fn_invoice['Sent'])) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): Fortnox Invoice %s was not "Sent" yet', $order_id, self::$fn_invoice['DocumentNumber']));
                return;
            }

            if ("booked" === $automatic_payment && !rest_sanitize_boolean(self::$fn_invoice['Booked'])) {
                WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): Fortnox Invoice %s was not "Booked" yet', $order_id, self::$fn_invoice['DocumentNumber']));
                return;
            }

            WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid (%s): Processing %s payment triggered by %s for Fortnox Invoice number %s using %s as payment mode ', $order_id, $payment_method, $automatic_payment, self::$fn_invoice['DocumentNumber'], $mode_of_payment));

            $payment_date_datetime = $order->get_date_paid();
            $payment_date = $payment_date_datetime->date('Y-m-d');
            $invoice_date_datetime = new DateTime(self::$fn_invoice['InvoiceDate']);
            if ($payment_date_datetime < $invoice_date_datetime) {
                $payment_date = self::$fn_invoice['InvoiceDate'];
                WC_FH()->logger->add(sprintf('Changed to payment date %s', $payment_date));
            }
            $payment_request = array(
                'InvoiceNumber' => self::$fn_invoice['DocumentNumber'],
                'Amount' => self::$fn_invoice['Total'] * self::$fn_invoice['CurrencyRate'] * self::$fn_invoice['CurrencyUnit'],
                'AmountCurrency' => self::$fn_invoice['Total'],
                'CurrencyRate' => self::$fn_invoice['CurrencyRate'],
                'PaymentDate' => $payment_date,
                'ModeOfPayment' => $mode_of_payment,
                'ModeOfPaymentAccount' => $payment_account,
            );
            $payment_response = WC_FH()->fortnox->createInvoicePayment($payment_request);
            WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid: Created Fortnox invoice payment %s', $payment_response['Number']));
            $bookkeep_response = WC_FH()->fortnox->bookkeepInvoicePayment($payment_response['Number']);
            WC_FH()->logger->add(sprintf('set_customer_invoice_to_paid: Fortnox invoice payment %s booked', self::$fn_invoice['DocumentNumber']));

            self::$fn_invoice = WC_FH()->fortnox->get_invoice(self::$fn_invoice['DocumentNumber']);
        }

        private function invoice_is_paid($order_id)
        {

            if (strlen(self::$fn_invoice['FinalPayDate']) === 10) {
                WC_FH()->logger->add(sprintf('invoice_is_paid (%s): Fortnox Invoice %s is paid', $order_id, self::$fn_invoice['DocumentNumber']));
                return true;
            }

            $was_paid = false;
            $invoice_payments = WC_FH()->fortnox->getInvoicePaymentsByInvoiceNumber(self::$fn_invoice['DocumentNumber']);

            if ($invoice_payments) {

                WC_FH()->logger->add(sprintf('invoice_is_paid (%s): Found %s invoice payments for invoice %s', $order_id, count($invoice_payments), self::$fn_invoice['DocumentNumber']));

                $delete_file_payments = wc_string_to_bool(get_option('fortnox_delete_invoice_file_payments'));

                foreach ($invoice_payments as $invoice_payment) {              
                    if ($delete_file_payments && $invoice_payment['Source'] === 'file' && !rest_sanitize_boolean($invoice_payment['Booked'])) {

                        WC_FH()->fortnox->delete_invoice_payment($invoice_payment['Number']);

                    } elseif ($invoice_payment['Source'] === 'file') {
                        $was_paid = true;
                        
                    } else {
                        if (!rest_sanitize_boolean($invoice_payment['Booked'])) {
                            $bookkeep_response = WC_FH()->fortnox->bookkeepInvoicePayment($invoice_payment['Number']);
                            WC_FH()->logger->add(sprintf('invoice_is_paid (%s): Invoice payment %s was already created and is now booked on Invoice %s', $order_id, $invoice_payment['Number'], self::$fn_invoice['DocumentNumber']));
                        }
                        $was_paid = true;
                    }
                }
            } else {
                WC_FH()->logger->add(sprintf('invoice_is_paid (%s): Found no invoice payments for invoice %s', $order_id, self::$fn_invoice['DocumentNumber']));
            }

            WC_FH()->logger->add(sprintf('invoice_is_paid (%s): Fortnox Invoice %s is %s', $order_id, self::$fn_invoice['DocumentNumber'], ($was_paid ? 'paid' : 'not paid')));
            return $was_paid;
        }

        public function set_credit_invoice_to_paid($order, $order_id)
        {

            $parent_id = $order->get_parent_id();

            if (!$parent_id) {
                return;
            }

            if (('shop_order_refund' != $order->get_type())){
                return;
            }

            $parent = wc_get_order($parent_id);

            $payment_method = WCFH_Util::get_payment_method($parent, 'set_credit_invoice_to_paid');
            $payment_account = get_option('fortnox_payment_account_' . $payment_method);

            $automatic_payment = get_option('fortnox_automatic_payment_' . $payment_method);

            if (self::$fn_invoice['Total'] == 0 && !wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders'))) {
                $automatic_payment = 'created';
                WC_FH()->logger->add(sprintf('set_credit_invoice_to_paid (%s): Zero order - setting automatic payment to created', $order_id));
            }

            if (!$automatic_payment) {
                return;
            }

            if (self::$fn_invoice['Total'] == 0) {
                $mode_of_payment = !wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders')) ? 'API_BLANK' : false;
                WC_FH()->logger->add(sprintf('set_credit_invoice_to_paid (%s): Using zero order mode of payment %s', $order_id, $mode_of_payment));
                $payment_account = FN_PAYOUT_BANK_ACCOUNT;
            } else {
                $mode_of_payment = get_option('fortnox_mode_of_payment_' . $payment_method);
            }

            if (!$mode_of_payment) {
                WC_FH()->logger->add(sprintf('set_credit_invoice_to_paid (%s): No mode of payment was set on Fortnox Invoice %s', $order_id, self::$fn_invoice['DocumentNumber']));
                return;
            }

            if ($this->invoice_is_paid($order_id)) {
                return;
            }

            if ('sent' == $automatic_payment && !rest_sanitize_boolean(self::$fn_invoice['Sent'])) {
                return;
            }

            if ('booked' == $automatic_payment && !rest_sanitize_boolean(self::$fn_invoice['Booked'])) {
                return;
            }

            WC_FH()->logger->add(sprintf('set_credit_invoice_to_paid (%s): Processing %s payment triggered by "%s" for Fortnox Invoice number %s using "%s" as payment mode ', $order_id, $payment_method, $automatic_payment, self::$fn_invoice['DocumentNumber'], $mode_of_payment));

            $payment_date_datetime = $order->get_date_created();
            $payment_date = $payment_date_datetime->date('Y-m-d');
            $invoice_date_datetime = new DateTime(self::$fn_invoice['InvoiceDate']);
            if ($payment_date_datetime < $invoice_date_datetime) {
                $payment_date = self::$fn_invoice['InvoiceDate'];
                WC_FH()->logger->add(sprintf('Changed to payment date %s', $payment_date));
            }

            $payment_request = array(
                'InvoiceNumber' => self::$fn_invoice['DocumentNumber'],
                'Amount' => self::$fn_invoice['Total'] * self::$fn_invoice['CurrencyRate'] * self::$fn_invoice['CurrencyUnit'],
                'AmountCurrency' => self::$fn_invoice['Total'],
                'CurrencyRate' => self::$fn_invoice['CurrencyRate'],
                'PaymentDate' => $payment_date,
                'ModeOfPayment' => $mode_of_payment,
                'ModeOfPaymentAccount' => $payment_account,
            );
            $payment_response = WC_FH()->fortnox->createInvoicePayment($payment_request);
            WC_FH()->logger->add(sprintf('set_credit_invoice_to_paid: Created Fortnox credit invoice payment %s', $payment_response['Number']));
            $bookkeep_response = WC_FH()->fortnox->bookkeepInvoicePayment($payment_response['Number']);
            WC_FH()->logger->add(sprintf('set_credit_invoice_to_paid: Fortnox credit invoice payment %s booked', self::$fn_invoice['DocumentNumber']));

            self::$fn_invoice = WC_FH()->fortnox->get_invoice(self::$fn_invoice['DocumentNumber']);
        }

        public function maybe_capture_klarna_order($order, $order_id) {

            if (!apply_filters('fortnox_filter_maybe_capture_klarna_order', true, $order, self::$fn_invoice)) {
                return;
            }

            if (!get_option('fortnox_capture_klarna_orders')) {
                return;
            }

            if (!class_exists('WC_Klarna_Order_Management')) {
                return;
            }

            if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
                return;
            }

            if (self::$fn_invoice['Total'] == 0) {
                return;
            }

            $set_order_status = apply_filters('fortnox_filter_set_capture_klarna_order', get_option('fortnox_capture_klarna_orders'), $order, self::$fn_invoice);


            switch ($set_order_status) {

                case 'sent':
                    if (!rest_sanitize_boolean(self::$fn_invoice['Sent'])) {
                        return;
                    }
                    WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Captured Klarna order %s using Fortnox invoice %s that was sent', $order_id, self::$fn_invoice['DocumentNumber']));
                    break;

                case 'booked':
                    if (!rest_sanitize_boolean(self::$fn_invoice['Booked'])) {
                        return;
                    }
                    WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Captured Klarna order %s using Fortnox invoice %s that was booked', $order_id, self::$fn_invoice['DocumentNumber']));
                    break;

                case 'paid':
                    if (strlen(self::$fn_invoice['FinalPayDate']) !== 10) {
                        return;
                    }
                    WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Captured Klarna order %s using Fortnox invoice %s that was paid', $order_id, self::$fn_invoice['DocumentNumber']));
                    break;

                case 'created':
                    WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Captured Klarna order %s using Fortnox invoice %s that was created', $order_id, self::$fn_invoice['DocumentNumber']));
                    break;

                default:
                    return;
            }

            $total = round(self::$fn_invoice['Total'] * 100, 0);
            //Log total
            WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Total amount to capture for Klarna order %s is %s', $order_id, $total));

            add_filter('kom_order_capture_args', function($data, $order_id) use ($total) {

                $data['captured_amount'] = $total;
                WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Captured amount for Klarna order %s is %s', $order_id, $total));

                return $data;
            }, 10, 2);

            WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Capturing Klarna order %s', $order_id));

            $klarna_order_management = WC_Klarna_Order_Management::get_instance();

            $klarna_order_management->capture_klarna_order($order_id, true);

            WC_FH()->logger->add(sprintf('maybe_capture_klarna_order: Klarna order %s captured', $order_id));

        }

        public function maybe_add_invoice_data($order, $order_id) {
            try {

                    if (!apply_filters('fortnox_filter_maybe_add_invoice_data', true, $order, self::$fn_invoice)) {
                        return;
                    }

                    if (!wc_string_to_bool(get_option('fortnox_order_add_invoice_data'))) {
                        return;
                    }
                    
                    $parent_id = $order->get_parent_id();
    
                    if ($parent_id && ('shop_order_refund' == $order->get_type())) {
                        $order = wc_get_order($parent_id);
                        WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): Got credit invoice - changing order id from %s to %s', $parent_id,$order_id,$parent_id));
                        $order_id = $parent_id;
                    }
    
                    $wc_order_creates = WCFH_Util::fortnox_wc_order_creates($order);
    
                    if ('order' === $wc_order_creates) {
    
                        $invoice_reference = WCFH_Util::get_fortnox_invoice_number($order_id);
    
                        if (!$invoice_reference) {
                            WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): No invoice reference found on order %s', $order_id, $order->get_order_number()));
                            return;
                        }
    
                        $invoice = WC_FH()->fortnox->get_invoice($invoice_reference);
    
                        if (!$invoice) {
                            WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): No invoice found on Fortnox with reference %s', $order_id, $invoice_reference));
                            return;
                        }

                        //Cancelled
                        if (rest_sanitize_boolean($invoice['Cancelled'])) {
                            WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): Invoice %s was cancelled', $order_id, $invoice_reference));
                            return;
                        }

                        //Make sure that invoice is not booked
                        if (rest_sanitize_boolean($invoice['Booked'])) {
                            WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): Invoice %s was already booked', $order_id, $invoice_reference));
                            return;
                        }

                         //Make sure that invoice is not sent
                         if (rest_sanitize_boolean($invoice['Sent'])) {
                            WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): Invoice %s was already sent', $order_id, $invoice_reference));
                            return;
                        }

                        $payment_method = WCFH_Util::get_payment_method($order, 'maybe_add_invoice_data');

                        $payment_gateways = WCFH_Util::get_available_payment_gateways();

                        if (($payment_remark = get_option('fortnox_invoice_payment_remark_' . $payment_method, 'yes')) && $order->get_date_paid()) {

                            if ($payment_method) {
                                $payment_text[] = sprintf(__('Payment via %s (%s)', 'woo-fortnox-hub'), isset($payment_gateways[$payment_method]) ? $payment_gateways[$payment_method]->get_title() : ucfirst($payment_method), $order->get_transaction_id());
                            }

                            $payment_text[] = sprintf(
                                __('Paid on %1$s @ %2$s', 'woo-fortnox-hub'),
                                wc_format_datetime($order->get_date_paid()),
                                wc_format_datetime($order->get_date_paid(), get_option('time_format'))
                            );

                            if (false !== strpos($payment_remark, 'yes')) {
                                $remarks = $payment_text;
                            }

                            if (false !== strpos($payment_remark, 'comment')) {
                                $comments = $payment_text;
                            }
                        }
    
                        $invoice_data  = WCFH_Util::get_invoice_email_information($order);

                        if ($invoice_print_template = get_option('fortnox_invoice_print_template_' . $payment_method, get_option('fortnox_invoice_print_template'))) {
                            $invoice_data['PrintTemplate'] = $invoice_print_template;
                        }

                        //Add comment to invoice
                        $invoice_data['Remarks'] = !empty($remarks) ? WCFH_Util::clean_fortnox_text(implode('. ', $remarks), 1024) : 'API_BLANK';
                        if ('order' === $wc_order_creates && ('yes' == get_option('fortnox_order_copy_remarks'))) {
                            $invoice_data['CopyRemarks'] = true;
                        }

                        $invoice_data['Language'] = get_option('fortnox_language', (get_locale() == 'sv_SE') ? 'SV' : 'EN');

                        $invoice_data['Comments'] = !empty($comments) ? WCFH_Util::clean_fortnox_text(implode('. ', $comments), 1024) : 'API_BLANK';
    
                        WC_FH()->fortnox->updateInvoice($invoice_reference, $invoice_data);

                        WC_FH()->logger->add(sprintf('maybe_add_invoice_data (%s): Added invoice data to Fortnox invoice %s: %s', $order_id, $invoice_reference, json_encode($invoice_data)));
    
                    }
    
                } catch (Fortnox_API_Exception $e) {
                    $e->write_to_logs();
            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
            }
        }

        public function sync_fortnox_invoices($sync_days = false)
        {
            try {

                if (true === WC_FH()->do_not_sync) {
                    WC_FH()->logger->add(sprintf('sync_fortnox_invoices: Do not sync is set'));
                    return;
                }

                $current_time_unix = current_time('timestamp', true) + get_option('fortnox_gmt_offset', 0);

                $this_sync_time = date('Y-m-d H:i', $current_time_unix);

                if (false !== $sync_days) {
                    $last_sync_done = date('Y-m-d 00:01', $current_time_unix - (DAY_IN_SECONDS * $sync_days));
                } else {
                    $last_sync_invoices = get_option('fortnox_hub_sync_last_sync_invoices');
                    $last_sync_done = $last_sync_invoices ? $last_sync_invoices : $this_sync_time;
                }

                $invoices = WC_FH()->fortnox->get_all_invoices($last_sync_done);

                if (0 != ($total_to_sync = count($invoices))) {

                    WC_FH()->logger->add(sprintf('Got %s changed Fortnox invoices since %s. Adding them to the queue', $total_to_sync, $last_sync_done));

                    foreach ($invoices as $invoice) {
                        as_schedule_single_action(as_get_datetime_object(), 'fortnox_process_changed_invoices', array($invoice['DocumentNumber']));
                    }
                }

                update_option('fortnox_hub_sync_last_sync_invoices', $this_sync_time, true);
            } catch (Fortnox_API_Exception $e) {

                $e->write_to_logs();
            }
        }

        /**
         * Try to find the WooCommerce order that created the invoice and save the invoice id on the order
         */
        public function get_order_id()
        {
            if (self::$fn_invoice['ExternalInvoiceReference1']) {
                $order_id = WCFH_Util::decode_external_reference(self::$fn_invoice['ExternalInvoiceReference1']);
                return $order_id;
            }
            WC_FH()->logger->add(sprintf('get_order_id: No valid reference found on Fortnox invoice %s', self::$fn_invoice['DocumentNumber']));
            return false;
        }

        /**
         * Try to find the WooCommerce order that created the invoice and save the invoice id on the order
         */
        public function get_fn_order_reference()
        {
            if (self::$fn_invoice['OrderReference']) {
                $order_reference = self::$fn_invoice['OrderReference'];
                return $order_reference;
            }
            WC_FH()->logger->add(sprintf('get_order_id: No valid order reference found on Fortnox invoice %s', self::$fn_invoice['DocumentNumber']));
            return false;
        }

        /**
         * Try to find the WooCommerce order that created the invoice and save the invoice id on the order
         */
        public function get_refund_id()
        {
            if (self::$fn_invoice['ExternalInvoiceReference2']) {
                $order_id = WCFH_Util::decode_external_reference(self::$fn_invoice['ExternalInvoiceReference2']);
                return $order_id;
            }
            WC_FH()->logger->add(sprintf('get_refund_id: No valid reference found on Fortnox invoice %s', self::$fn_invoice['DocumentNumber']));
            return false;
        }

        public function process_changed_invoices($fn_invoice_number)
        {
            try {

                self::$fn_invoice = WC_FH()->fortnox->get_invoice($fn_invoice_number);

                $is_credit = rest_sanitize_boolean(self::$fn_invoice['Credit']);

                if ($is_credit) {
                    $order_id = $this->get_refund_id();
                    WC_FH()->logger->add(sprintf('-> process_changed_invoices (%s): Starting to process Fortnox credit invoice %s', $order_id, $fn_invoice_number));
                } elseif (!wc_string_to_bool(get_option('fortnox_skip_processing_zero_orders')) && self::$fn_invoice['Total'] == 0 && $this->get_refund_id()) {
                    $order_id = $this->get_refund_id();
                    WC_FH()->logger->add(sprintf('-> process_changed_invoices (%s): Starting to process Fortnox credit invoice %s - zero invoice', $order_id, $fn_invoice_number));
                } else {
                    $order_id = $this->get_order_id();
                    WC_FH()->logger->add(sprintf('-> process_changed_invoices (%s): Starting to process Fortnox invoice %s', $order_id, $fn_invoice_number));
                }

                if (!$order_id) {
                    WC_FH()->logger->add(sprintf('process_changed_invoices: Fortnox invoice %s did not contain any order information', $fn_invoice_number));
                    return;
                }

                $order = wc_get_order($order_id);

                if (!$order) {
                    WC_FH()->logger->add(sprintf('process_changed_invoices (%s): The order id on the Fortnox invoice did not exist as order in WooCommerce', $order_id, $fn_invoice_number));
                    return;
                } else {
                    WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox invoice %s matches correctly with WooCommerce order', $order_id, $fn_invoice_number));
                }

                $wc_order_creates = WCFH_Util::fortnox_wc_order_creates($order);

                $our_invoice_number = WCFH_Util::get_fortnox_invoice_number($order_id);


                if (wc_string_to_bool(get_option('fortnox_strict_order_matching')) && $wc_order_creates == 'order') {
                    $fn_order_id = WCFH_Util::get_fortnox_order_documentnumber($order_id);

                    WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox order id %s found', $order_id, $fn_order_id));

                    if ($fn_order_id) {
                        $order_reference = $this->get_fn_order_reference();
                    } else {
                        $order_reference = false;
                    }

                    if (!$order_reference) {
                        WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox order reference was not set on order', $order_id));
                        return;
                    } elseif ($fn_order_id != $order_reference) {
                        WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox order reference %s differs from %s on the WooCommerce order', $order_id, $fn_order_id, $order_reference));
                        return;
                    } else {
                        WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox order reference %s matches correctly with WooCommerce order', $order_id, $fn_order_id));
                    }
                }

                if (!$our_invoice_number) {
                    WCFH_Util::set_fortnox_invoice_number($order, $fn_invoice_number);
                    WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox invoice number %s was not set on order', $order_id, $fn_invoice_number));
                } elseif ($fn_invoice_number != $our_invoice_number) {
                    WC_FH()->logger->add(sprintf('process_changed_invoices (%s): Fortnox invoice number %s differs from %s on the WooCommerce order', $order_id, $fn_invoice_number, $our_invoice_number));
                    return;
                }

                do_action('fortnox_before_process_changed_invoices_action_all', self::$fn_invoice, $order);

                $this->maybe_capture_klarna_order($order, $order_id);

                $this->set_ocr_on_order($order, $order_id);

                $this->maybe_set_wc_order_to_completed($order, $order_id);

                $this->maybe_add_invoice_data($order, $order_id);

                $this->set_customer_invoice_to_paid($order, $order_id);

                //Added this if statement for backward compatibility - invoices created from orders need their email information updated which can't happen when invoice is booked
                if ('order' === $wc_order_creates) {
                    $this->maybe_email_invoice($order, $order_id);

                    $this->maybe_bookkeep_invoice($order, $order_id);

                } else {
                    $this->maybe_bookkeep_invoice($order, $order_id);

                    $this->maybe_email_invoice($order, $order_id);
                }

                $this->maybe_external_print_invoice($order, $order_id);

                $this->set_credit_invoice_to_paid($order, $order_id);

                $this->maybe_set_wc_order_to_completed($order, $order_id);

                do_action('fortnox_process_changed_invoices_action_all', self::$fn_invoice, $order, $order_id);

                $order->save();

                WC_FH()->logger->add(sprintf('<- process_changed_invoices (%s): Finished processing Fortnox %s %s', $order_id, $is_credit ? 'credit invoice' : 'invoice', $fn_invoice_number));

            } catch (Fortnox_API_Exception $e) {
                $e->write_to_logs();
                Fortnox_Notice::add(sprintf(__("%s when processing Fortnox Invoice %s", 'woo-fortnox-hub'), $e->getMessage(), $fn_invoice_number));
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }

        }

    }

    new Woo_Fortnox_Hub_Invoice_Status_Handler();
}
