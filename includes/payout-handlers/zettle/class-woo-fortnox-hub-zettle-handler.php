<?php
/**
 * Creating Zettle payout invoices for accounting
 *
 * @package   BjornTech_Fortnox_Hub
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2022 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Fortnox_Hub_Zettle_Handler', false)) {

    class Fortnox_Hub_Zettle_Handler
    {

        private $customer_number;
        private $log_zettle_traffic;

        public function __construct()
        {
            add_action('init', array($this, 'schedule_fortnox_sync_zettle'), 99);
            add_filter('fortnox_get_sections', array($this, 'add_settings_section'), 230);
            add_filter('woocommerce_get_settings_fortnox_hub', array($this, 'get_settings'), 100, 2);
            add_filter('fortnox_payment_gateways', array($this, 'terms_of_payments_options'));
            add_action('wp_ajax_fortnox_sync_izettle', array($this, 'ajax_sync_zettle'));
            add_action('woocommerce_settings_fortnox_zettle_options', array($this, 'show_izettle_button'), 10);
            add_action('fortnox_izettle_sync', array($this, 'fortnox_izettle_sync'));
        }

        /**
         * Schedule automatic sync of Zettle payouts using action scheduler
         * @return void
         */
        public function schedule_fortnox_sync_zettle()
        {

            if ('yes' === get_option('fortnox_create_izettle_payout_invoices')) {
                if (false === as_next_scheduled_action('fortnox_izettle_sync')) {
                    as_schedule_cron_action(strtotime('tomorrow 7 am'), '0 7 * * *', 'fortnox_izettle_sync');
                }
            } else {
                if (false !== as_next_scheduled_action('fortnox_izettle_sync')) {
                    as_unschedule_all_actions('fortnox_izettle_sync');
                }
            }

        }

        /**
         * Ajax function to perform manual sync of Zettle payouts
         * @return void
         */
        public function ajax_sync_zettle()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'ajax-fortnox-hub')) {
                wp_die();
            }

            $days_or_date = sanitize_key($_POST['sync_days']);

            WC_FH()->logger->add(sprintf('ajax_sync_zettle: Sync from "%s" requested', $days_or_date));

            if (is_numeric($days_or_date) || date_create_from_format('Y-m-d', $days_or_date)) {

                as_schedule_single_action(as_get_datetime_object()->getTimestamp(), 'fortnox_izettle_sync', array($days_or_date));

                if (is_numeric($days_or_date)) {

                    $start_date = date('Y-m-d', current_time('timestamp') - (DAY_IN_SECONDS * $days_or_date));
                    $response = array(
                        'result' => 'success',
                        'message' => sprintf(__('Zettle payouts from %s are being processed.', 'woo-fortnox-hub'), $start_date),
                    );

                } else {

                    $response = array(
                        'result' => 'success',
                        'message' => sprintf(__('Zettle payouts for %s are being processed.', 'woo-fortnox-hub'), $days_or_date),
                    );

                }

            } else {

                $response = array(
                    'result' => 'error',
                    'message' => sprintf(__('"%s" is not a valid date format or a numeric value.', 'woo-fortnox-hub'), $days_or_date),
                );

            }

            wp_send_json($response);

        }

        /**
         * Create mock zettle payment gateway
         * @param mixed $choice
         * @return mixed
         */
        public function terms_of_payments_options($choice)
        {
            $izettle_array = array(
                'izettle_card' => new Fortnox_Hub_Mock_Payment_Gateway(__('Zettle card', 'woo-fortnox-hub')),
                'izettle_cash' => new Fortnox_Hub_Mock_Payment_Gateway(__('Zettle cash', 'woo-fortnox-hub')),
                'izettle_swish' => new Fortnox_Hub_Mock_Payment_Gateway(__('Zettle swish', 'woo-fortnox-hub')),
            );
            return array_merge($choice, $izettle_array);
        }

        /**
         * Create a button to manually sync Zettle payouts
         * @return void
         */
        public function show_izettle_button()
        {
            echo '<div id=fortnox_titledesc_sync_izettle>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label for="fortnox_sync_izettle">' . __('Manual sync', 'woo-fortnox-hub') . '<span class="woocommerce-help-tip" data-tip="' . __('Sync WooCommerce products to Fortnox', 'woo-fortnox-hub') . '"></span></label>';
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            echo '<button name="fortnox_sync_izettle" id="fortnox_sync_izettle" class="button">' . __('Syncronize', 'woo-fortnox-hub') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        /**
         * Add Zettle settings section
         * @param mixed $sections
         * @return mixed
         */
        public function add_settings_section($sections)
        {
            if (!array_key_exists('zettle', $sections)) {
                $sections = array_merge($sections, array('zettle' => __('Zettle', 'woo-fortnox-hub')));
            }
            return $sections;
        }

        /**
         * Settings section for Zettle
         * @param mixed $settings
         * @param mixed $current_section
         * @return mixed
         */
        public function get_settings($settings, $current_section)
        {
            if ('zettle' === $current_section) {

                $account_selection = apply_filters('fortnox_get_account_selection', array());

                $settings = array(
                    array(
                        'title' => __('Zettle options', 'woo-fortnox-hub'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'fortnox_zettle_options',
                    ),
                    array(
                        'title' => __('Create invoice to match Zettle card payouts', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'default' => '',
                        'id' => 'fortnox_create_izettle_payout_invoices',
                    ),
                    array(
                        'title' => __('Fortnox customer number for Zettle-invoice', 'woo-fortnox-hub'),
                        'type' => 'text',
                        'default' => '',
                        'id' => 'fortnox_izettle_customer_number',
                    ),
                    array(
                        'title' => __('Fee account for Zettle-sales', 'woo-fortnox-hub'),
                        'type' => 'select',
                        'default' => FN_ZETTLE_FEE_ACCOUNT,
                        'options' => $account_selection,
                        'id' => 'fortnox_izettle_fee_account',
                    ),
                    array(
                        'title' => __('Log Zettle traffic data (used for debugging)', 'woo-fortnox-hub'),
                        'type' => 'checkbox',
                        'default' => '',
                        'id' => 'fortnox_log_zettle_traffic',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'fortnox_zettle_options',
                    ),
                );

            }
            return $settings;
        }

        /**
         * Get transaction based on transaction uuid
         * @param mixed $purchases
         * @param mixed $transaction_uuid
         * @return mixed
         */
        protected function get_transaction_id($purchases, $transaction_uuid)
        {
            WC_FH()->logger->add(sprintf('get_transaction_id: Looking for transaction %s', $transaction_uuid));

            foreach ($purchases->purchases as $purchase) {
                foreach ($purchase->payments as $payment) {
                    if ($payment->uuid == $transaction_uuid) {
                        WC_FH()->logger->add(sprintf('get_transaction_id: Found transaction %s', $purchase->purchaseNumber));
                        return $purchase->purchaseNumber;
                    }
                }
            }

            WC_FH()->logger->add(sprintf('get_transaction_id: Transaction %s not found', $transaction_uuid));
            return false;
        }

        /**
         * Get account number for transaction type
         * @param mixed $type
         * @return mixed
         */
        public function get_account_number($type)
        {
            if (in_array($type, array('PAYMENT_FEE', 'INVOICE_PAYMENT_FEE'))) {
                return get_option('fortnox_izettle_fee_account', FN_ZETTLE_FEE_ACCOUNT);
            }
            return get_option('fortnox_payment_account_izettle_card');
        }

        /**
         * Valid transaction types
         * @return array<string>
         */
        public function valid_transactions()
        {
            return array(
                'ADJUSTMENT' => __('adjustment', 'woo-fortnox-hub'),
                'ADVANCE' => __('advance', 'woo-fortnox-hub'),
                'ADVANCE_DOWNPAYMENT' => __('advance downpayment', 'woo-fortnox-hub'),
                'ADVANCE_FEE_DOWNPAYMENT' => __('advance fee downpayment', 'woo-fortnox-hub'),
                'BANK_ACCOUNT_VERIFICATION' => __('bank account verification', 'woo-fortnox-hub'), //Deprecated can occur in old data
                'CASHBACK' => __('adjustment of card payment fee', 'woo-fortnox-hub'),
                'FAILED_PAYOUT' => __('failed payout', 'woo-fortnox-hub'),
                'FROZEN_FUNDS' => __('frozen funds', 'woo-fortnox-hub'),
                'INVOICE_PAYMENT' => __('invoice payment', 'woo-fortnox-hub'),
                'INVOICE_PAYMENT_FEE' => __('invoice payment fee', 'woo-fortnox-hub'),
                'PAYMENT' => __('payment', 'woo-fortnox-hub'),
                'PAYMENT_FEE' => __('payment fee', 'woo-fortnox-hub'),
            );
        }

        /**
         * Format transaction type
         * @param mixed $type
         * @return string
         */
        public function format_transaction_type($type)
        {
            $transactions = $this->valid_transactions();
            if (array_key_exists($type, $transactions)) {
                return $transactions[$type];
            };

            return '';
        }

        /**
         * Get transactions from Zettle
         *
         * @param string $start
         * @param string $end
         * @return object
         */
        private function get_transactions($start, $end)
        {
            $transactions = izettle_api()->get_liquid_transactions_v2(array(
                'accountTypeGroup' => 'LIQUID',
                'includeTransactionType' => array(
                    array_keys($this->valid_transactions()),
                ),
                'start' => $start,
                'end' => $end,
                'limit' => 10000,
            ));
            if ($this->log_zettle_traffic) {
                WC_FH()->logger->add(sprintf('process_izettle_payout: payout transactions %s', json_encode($transactions, JSON_INVALID_UTF8_IGNORE)), true);
            }

            return $transactions;
        }

        /**
         * Get purchases from Zettle
         *
         * @param string $start
         * @param string $end
         * @return object
         */
        private function get_purchases($start, $end)
        {
            $purchases = izettle_api()->get_purchases(array(
                'startDate' => $start,
                'endDate' => $end,
            ));
            if ($this->log_zettle_traffic) {
                WC_FH()->logger->add(sprintf('process_izettle_payout: purchases %s', json_encode($purchases, JSON_INVALID_UTF8_IGNORE)), true);
            }

            return $purchases;
        }

        /**
         * Get payout transactions from Zettle
         *
         * @param string $start
         * @param string $end
         * @return object
         */
        private function get_payout_transactions($start, $end)
        {
            $payout_transactions = izettle_api()->get_liquid_transactions_v2(array(
                'accountTypeGroup' => 'LIQUID',
                'includeTransactionType' => 'PAYOUT',
                'start' => date('Y-m-d\T00:00:01.000+00:00', $start),
                'end' => date('Y-m-d\T23:59:59.000+00:00', $end),
            ));
            if ($this->log_zettle_traffic) {
                WC_FH()->logger->add(sprintf('process_izettle_payout: payout transactions %s', json_encode($payout_transactions, JSON_INVALID_UTF8_IGNORE)), true);
            }

            return $payout_transactions;
        }

        /**
         * Process payout transactions
         * @param string $startdate
         * @param string $enddate
         * @return void
         */
        private function process_izettle_payout($startdate, $enddate = null)
        {

            try {

                $enddate = $enddate === null ? $startdate : $enddate;

                $payout_info = izettle_api()->get_payout_info_v2();
                if ($this->log_zettle_traffic) {
                    WC_FH()->logger->add(sprintf('process_izettle_payout: payout info %s', json_encode($payout_info, JSON_INVALID_UTF8_IGNORE)), true);
                }

                $payout_transactions = $this->get_payout_transactions($startdate, $enddate);
                if (empty($payout_transactions)) {
                    WC_FH()->logger->add(sprintf('No Zettle payout data for %s', date('Y-m-d', $startdate)));
                    return;
                }
                $payout_transactions = array_reverse($payout_transactions);

                $reference_start = strtotime(reset($payout_transactions)->timestamp) - (DAY_IN_SECONDS * 200);
                $reference_end = strtotime(reset($payout_transactions)->timestamp) - DAY_IN_SECONDS;
                WC_FH()->logger->add($reference_start);
                WC_FH()->logger->add($reference_end);
                $reference_transactions = $this->get_payout_transactions($reference_start, $reference_end);
                WC_FH()->logger->add(print_r(reset($payout_transactions), true));
                WC_FH()->logger->add(print_r($reference_transactions, true));
                if (empty($reference_transactions)) {
                    if (isset($payout_info) && $payout_info->periodicity == 'WEEKLY') {
                        $day_offset = DAY_IN_SECONDS * 7;
                    } else {
                        $day_offset = DAY_IN_SECONDS * (('1' == date('N', $startdate)) ? 3 : 1);
                    }
                    $first_startdate = $startdate - (DAY_IN_SECONDS * $day_offset);
                } else {
                    $first_startdate = strtotime(reset($reference_transactions)->timestamp);
                }

                $transaction_start = date('c', $first_startdate);
                $purchases_start = date('Y-m-d\T00:00:01', $startdate - (DAY_IN_SECONDS * 100));

                foreach ($payout_transactions as $payout_transaction) {

                    $transaction_end = date('c', strtotime($payout_transaction->timestamp));
                    $purchases_end = $payout_transaction->timestamp;

                    $transactions = $this->get_transactions($transaction_start, $transaction_end);
                    $purchases = $this->get_purchases($purchases_start, $purchases_end);

                    $invoice_rows = array();
                    foreach ($transactions as $transaction) {
                        $transaction_id = $this->get_transaction_id($purchases, $transaction->originatingTransactionUuid);
                        if ($transaction_id !== false) {
                            WC_FH()->logger->add(sprintf('process_izettle_payout: Found transaction %s', $transaction_id));
                            $invoice_number = WCFH_Util::get_fortnox_invoice_number($transaction_id);
                            WC_FH()->logger->add(sprintf('process_izettle_payout: Fortnox invoice number %s', $invoice_number));
                            $transaction_id = $invoice_number ? $invoice_number : $transaction_id;
                            $invoice_rows[] = array(
                                "AccountNumber" => $this->get_account_number($transaction->originatorTransactionType),
                                "DeliveredQuantity" => 1,
                                "Price" => $transaction->amount / 100,
                                "Description" => sprintf(__('Zettle %s #%s', 'woo-fortnox-hub'), $this->format_transaction_type($transaction->originatorTransactionType), $transaction_id),
                            );

                            WC_FH()->logger->add(sprintf('process_izettle_payout: Invoice row %s', json_encode($invoice_rows, JSON_INVALID_UTF8_IGNORE)));
                        }
                    }

                    $booking_date = date('Y-m-d', strtotime($payout_transaction->timestamp));
                    $invoice = array(
                        "CustomerNumber" => $this->customer_number,
                        "DueDate" => $booking_date,
                        "InvoiceDate" => $booking_date,
                        "InvoiceRows" => $invoice_rows,
                        "Currency" => strtoupper($payout_info->currencyId),
                        "ExternalInvoiceReference1" => $payout_transaction->originatingTransactionUuid,
                        "TermsOfPayment" => '',
                    );

                    $transaction_start = $transaction_end;
                    $purchases_start = $purchases_end;

                    $fortnox_invoice = WCFH_Util::check_if_invoice_already_created($payout_transaction->originatingTransactionUuid, true);

                    if (!$fortnox_invoice) {
                        $invoice_created = WC_FH()->fortnox->create_invoice($invoice);
                        WC_FH()->logger->add(sprintf('process_izettle_payout: Zettle payout %s created Fortnox invoice %s', $payout_transaction->originatingTransactionUuid, $invoice_created['DocumentNumber']));
                        Fortnox_Notice::add(sprintf(__('Successfully created Fortnox Invoice %s from Zettle payouts on %s', 'woo-fortnox-hub'), $invoice_created['DocumentNumber'], $booking_date), 'success');
                        continue;
                    }

                    if (!rest_sanitize_boolean($fortnox_invoice['Booked']) && !rest_sanitize_boolean($fortnox_invoice['Cancelled']) && !$fortnox_invoice['FinalPayDate']) {
                        $invoice_created = WC_FH()->fortnox->update_invoice($fortnox_invoice['DocumentNumber'], $invoice);
                        WC_FH()->logger->add(sprintf('process_izettle_payout: Zettle payout %s updated Fortnox invoice %s', $payout_transaction->originatingTransactionUuid, $invoice_created['DocumentNumber']));
                        Fortnox_Notice::add(sprintf(__('Successfully updated Fortnox Invoice %s from Zettle payouts on %s', 'woo-fortnox-hub'), $invoice_created['DocumentNumber'], $booking_date), 'success');
                        continue;
                    }

                    if (rest_sanitize_boolean($fortnox_invoice['Cancelled'])) {
                        WC_FH()->logger->add(sprintf('process_izettle_payout: Zettle payout %s already booked on Fortnox Invoice %s that has been cancelled', $payout_transaction->originatingTransactionUuid, $fortnox_invoice['DocumentNumber']));
                        continue;
                    }

                    if (rest_sanitize_boolean($fortnox_invoice['Booked']) || $fortnox_invoice['FinalPayDate']) {
                        WC_FH()->logger->add(sprintf('process_izettle_payout: Zettle payout %s already booked on Fortnox invoice %s', $payout_transaction->originatingTransactionUuid, $fortnox_invoice['DocumentNumber']));
                        continue;
                    }

                    WC_FH()->logger->add(sprintf('process_izettle_payout: Zettle payout %s already created Fortnox Invoice %s, no further processing will be made', $payout_transaction->originatingTransactionUuid, $fortnox_invoice['DocumentNumber']));

                };

            } catch (Fortnox_API_Exception $e) {
                Fortnox_Notice::add(__('Something went wrong when processing Zettle payouts', 'woo-fortnox-hub'), 'error');
                $e->write_to_logs();
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    WC_FH()->logger->add(print_r($t, true));
                }
            }
        }

        /**
         * Start syncing Zettle payouts
         * @param mixed $days_or_date
         * @return void
         */
        public function fortnox_izettle_sync($days_or_date = 1)
        {

            $this->customer_number = get_option('fortnox_izettle_customer_number');
            $this->log_zettle_traffic = wc_string_to_bool(get_option('fortnox_log_zettle_traffic', false));

            if (!$this->customer_number) {
                Fortnox_Notice::add(sprintf(__('In order to process payouts, a (Fortnox) customer number must be present in the %s settings', 'woo-fortnox-hub'), 'Zettle'), 'error');
                WC_FH()->logger->add('fortnox_izettle_sync: Customer number for Zettle invoices must be configured');
                return;
            }

            if (is_numeric($days_or_date)) {

                $current_datetime = current_time('timestamp');
                $from_datetime = $current_datetime - (DAY_IN_SECONDS * $days_or_date);

                WC_FH()->logger->add(sprintf('fortnox_izettle_sync: Starting to process Zettle payouts from %s', date('Y-m-d', $from_datetime)));
                $this->process_izettle_payout($from_datetime, $current_datetime);

            } else {

                WC_FH()->logger->add(sprintf('fortnox_izettle_sync: Starting to process Zettle payouts for %s', $days_or_date));
                $this->process_izettle_payout(strtotime($days_or_date));

            }

        }

    }

    /**
     * Mock payment gateway for Zettle
     */
    class Fortnox_Hub_Mock_Payment_Gateway
    {

        private $description;
        public $enabled = 'yes';

        public function __construct($description)
        {
            $this->description = $description;
        }

        public function is_available()
        {
            return true;
        }

        public function get_title()
        {
            return $this->description;
        }
    }

    new Fortnox_Hub_Zettle_Handler();

}
