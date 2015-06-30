<?php

/**
 * Plugin Name: Wtyczka StarBonus
 * Plugin URI: kontakt@starbonus.pl
 * Description: Wtyczka StarBonus.
 * Author: StarBonus Sp. z o.o.
 * Version: 1.0.8
 */

if (!class_exists('StarBonusPlugin')) :

    /**
     * Class StarBonusPlugin
     */
    final class StarBonusPlugin
    {

        /**
         * @var StarBonusPlugin instance of the class
         */
        static protected $instance = null;

        /**
         * @var string table_prefix
         */
        protected $table_name;

        /**
         * @var \Starbonus\Api\Api starbonusApi
         */
        protected $starbonusApi;

        /**
         * @var array
         */
        private static $functionNames = [
            'is_account_page',
            'is_view_order_page',
            'is_order_received_page',
            'is_shop',
            'is_cart',
            'is_checkout',
            'is_product',
            'is_product_taxonomy',
            'is_product_category',
            'is_checkout_pay_page',
            'is_add_payment_method_page'
        ];

        /**
         * Main StarBonusPlugin Instance
         *
         * @return StarBonusPlugin
         */
        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Construct
         */
        private function __construct()
        {
            global $wpdb;

            $this->includes();
            $this->init_hooks();

            $this->table_name = $wpdb->prefix . 'starbonus_order_status_config';
        }

        /**
         * Includes
         */
        private function includes()
        {
            include_once('includes/class-sb-install.php');
            require_once('vendor/autoload.php');
        }

        /**
         * Init hooks
         */
        private function init_hooks()
        {
            /**
             * Register hooks
             */
            register_activation_hook(__FILE__, ['SB_Install', 'install']);
            register_activation_hook(__FILE__, [$this, 'starbonus_widget_cron_complete_status_activation']);
            register_activation_hook(__FILE__, [$this, 'starbonus_widget_cron_retry_transaction_activation']);
            register_activation_hook(__FILE__, [$this, 'starbonus_widget_cron_retry_older_transaction_activation']);
            register_deactivation_hook(__FILE__, [$this, 'starbonus_widget_cron_complete_status_deactivation']);
            register_deactivation_hook(__FILE__, [$this, 'starbonus_widget_cron_retry_transaction_deactivation']);
            register_deactivation_hook(__FILE__, [$this, 'starbonus_widget_cron_retry_older_transaction_deactivation']);

            /**
             * Register actions
             */
            add_action('wp_footer', [$this, 'load_starbonus_js'], 9999);
            add_action('init', [$this, 'init_starbonus_redirect']);
            add_action('parse_request', [$this, 'custom_requests']);
            add_action('admin_menu', [$this, 'starbonus_menu_page']);
            add_action('starbonus_widget_update_status', [
                $this,
                'starbonus_widget_update_status'
            ], 10, 3); // params $priority, $args amount
            add_action('starbonus_widget_update_status_concrete', [
                $this,
                'starbonus_widget_update_status_concrete'
            ], 10, 3);
            add_action('woocommerce_checkout_update_order_meta', [
                $this,
                'starbonus_widget_woocommerce_checkout_update_order_meta'
            ]);
            add_action('woocommerce_order_status_pending', [
                $this,
                'starbonus_widget_woocommerce_order_status_pending'
            ]);
            add_action('woocommerce_order_status_on-hold', [
                $this,
                'starbonus_widget_woocommerce_order_status_on_hold'
            ]);
            add_action('woocommerce_order_status_processing', [
                $this,
                'starbonus_widget_woocommerce_order_status_processing'
            ]);
            add_action('woocommerce_order_status_completed', [
                $this,
                'starbonus_widget_woocommerce_order_status_completed'
            ]);
            add_action('woocommerce_order_status_cancelled', [
                $this,
                'starbonus_widget_woocommerce_order_status_cancelled'
            ]);
            add_action('woocommerce_order_status_failed', [$this, 'starbonus_widget_woocommerce_order_status_failed']);
            add_action('woocommerce_order_status_refunded', [
                $this,
                'starbonus_widget_woocommerce_order_status_refunded'
            ]);
            add_action('starbonus_widget_cron_event_hook', [$this, 'starbonus_widget_cron_do_event']);
            add_action('starbonus_widget_cron_retry_transaction_event_hook', [
                $this,
                'starbonus_widget_cron_retry_transaction_do_event'
            ]);
            add_action('starbonus_widget_cron_retry_older_transaction_event_hook', [
                $this,
                'starbonus_widget_cron_retry_older_transaction_do_event'
            ]);

            /**
             * Shortcodes
             */
            add_shortcode('starbonus_client_details_shortcode', [$this, 'client_details_shortcode']);

            /**
             * Filters
             */
            add_filter('query_vars', [$this, 'starbonus_query_vars']);
        }

        /**
         * Add script tags to head on each application page
         */
        public function load_starbonus_js()
        {
            if (get_option('starbonus_open_widget') === 'ever' || (get_option('starbonus_open_widget') === 'redirect' && $_COOKIE['starbonus_redirect'])) {

                if (get_option('starbonus_show_on_shop')) {
                    foreach ($this->getFunctionNames() as $function) {
                        if (function_exists($function) && $function()) {
                            $this->showScript();
                            break;
                        }
                    }
                } else {
                    $this->showScript();
                }
            } else {
                return false;
            }
        }

        private function showScript()
        {

            echo '<script type="text/javascript">
                    <!-- // <![CDATA[
                    ;(function () {
                        var urlApi = "' . (get_option('starbonus_url_api') === 'production' ? 'https://api.starbonus.pl'
                    : 'http://api.starbonus.kusmierz.be') . '",
                            urlJs = "' . (get_option('starbonus_url_api') === 'production'
                    ? 'https://static.starbonus.pl/widget/build/widget.min.js?v=1.1.2'
                    : 'http://starbonus.kusmierz.be/widget/build/widget.min.js') . '",
                            programId = ' . get_option('starbonus_program_id') . ',
                            bonusId = "' . get_option('starbonus_bonus_id') . '",
                            source = "' . get_option('starbonus_source') . '",
                            isExpanded = ' . (get_option('starbonus_expanded') ? 1 : 0) . ',
                            minWidth = ' . (get_option('starbonus_min_width') ? : 600) . ',
                            fromTop = ' . (get_option('starbonus_widget_below') ? : 150) . ',
                            orientation = "' . (get_option('starbonus_widget_side') ? : 'left') . '",
                            color = "' . (get_option('starbonus_skin') ? : null) . '",
                            animation = ' . (get_option('starbonus_animation') ? 1 : 0) . ';


                        (function(i,s,o,g,r,a,m){i[\'StarbonusWidgetObject\']=r;i[r]=i[r]||function(){
                            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                        })(window,document,\'script\',urlJs,\'_sbwidget\');

                        _sbwidget(\'config\', {
                            \'api\': {\'url\': urlApi},
                            \'widget\': {\'settings\': {\'isExpanded\': isExpanded}, \'program\': programId, \'bonus\': bonusId, \'source\': source, \'minWidth\': minWidth, \'fromTop\': fromTop, \'orientation\': orientation, \'color\': color, \'animations\': animation}
                        });
                        _sbwidget(\'show\');
                    })();

                    // ]]> -->
                </script>';
        }

        /**
         * Update order status to get cashback
         *
         * @param WC_Order $order
         * @param bool $isCompleted
         * @param int $transactionId
         *
         * @return bool
         */
        public function starbonus_widget_update_status($order, $isCompleted = false, $transactionId = null)
        {
            try {
                /**
                 * If transactionId is not null do updateTransactionCaschback.
                 * Else do createTransactionCashback with null transactionId (when order are implementing)
                 * or not (when order are updating)
                 */
                if (!$transactionId) {
                    global $wpdb;
                    $order_config = $wpdb->get_results('SELECT * FROM ' . $this->table_name . ' WHERE order_id =  ' . $order->id . ' LIMIT 1');
                    if ($order_config && is_array($order_config) && (count($order_config) > 0)) {
                        $order_config = reset($order_config);
                        if ($order_config->transaction_id) {
                            $transactionId = $order_config->transaction_id;
                        }
                    } else {
                        return false;
                    }
                }
                $this->starbonus_widget_update_status_concrete($order, $isCompleted, $transactionId);
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        /**
         * Update order status on StarBonus server to get cashback
         *
         * @param WC_Order $order
         * @param bool $isCompleted
         * @param int $transactionId
         *
         * @return bool
         */
        public function starbonus_widget_update_status_concrete($order, $isCompleted = false, $transactionId = null)
        {
            $clickId = null;

            if (isset($_COOKIE['starbonus'])) {
                $clickId = $_COOKIE['starbonus'];
            }

            if ($transactionId) {
                $this->updateTransactionCashback($order, $isCompleted, $transactionId);
            } elseif ($clickId) {
                $this->createTransactionCashback($order, $clickId);
            }
        }

        /**
         * Update transaction cachback
         *
         * @param WC_Order $order
         * @param bool $isCompleted
         * @param int $transactionId
         *
         * @return \Starbonus\Api\Entity\TransactionCashback
         *
         */
        public function updateTransactionCashback($order, $isCompleted, $transactionId)
        {
            global $wpdb;

            $entity = new \Starbonus\Api\Entity\TransactionCashback();

            switch ($order->status) {
                case 'on-hold':
                case 'pending':
                case 'processing':
                    $entity->setState('pending');
                    break;
                case 'completed':
                    if ($isCompleted === true) {
                        $entity->setState('accepted');
                    } else {
                        $entity->setState('pending');
                    }
                    break;
                case 'cancelled':
                case 'failed':
                case 'refunded':
                    $entity->setState('deleted');
                    break;
            }
//        $entity->setCategory('');

            $transaction = null;
            $sync        = true;
            try {
                $transaction = $this->getStarbonusApi()->serviceTransactionCashback()->patch($transactionId, $entity);
            } catch (Exception $e) {
                error_log($e->getMessage(), 0);
                $sync = false;
            }

            $wpdb->update($this->table_name, [
                'order_status' => $order->status,
                'sync'         => $sync,
                'sync_try'     => $sync ? 0
                    : $wpdb->get_var('SELECT sync_try FROM ' . $this->table_name . ' WHERE order_id=' . $order->id) + 1
            ], [
                              'order_id' => $order->id
                          ], [
                              '%s',
                              '%d',
                              '%d'
                          ]);

            return $transaction;
        }

        /**
         * Create transaction cachback
         *
         * @param WC_Order $order
         * @param string $clickId
         *
         * @return \Starbonus\Api\Entity\TransactionCashback
         *
         */
        public function createTransactionCashback($order, $clickId)
        {
            global $wpdb;

            // if transaction_id exist do update
            $order_config = $wpdb->get_row('SELECT * FROM ' . $this->table_name . ' WHERE order_id=' . $order->id);
            if ($order_config->transaction_id > 0) {
                $transaction = $this->updateTransactionCashback($order, false, $order_config->transaction_id);

                return $transaction;
            }

            $entity = new \Starbonus\Api\Entity\TransactionCashback();
            $entity->setTransaction('wordpress-' . $order->get_order_number())->setClick($clickId)->setAmountPurchase(($order->get_total() - (get_option('starbonus_shipping_cost')
                                                                                                                              ? $order->get_total_shipping()
                                                                                                                              : 0)) * 100)->setCurrency($order->get_order_currency())->setState('pending');
//            ->setCategory('');

            $transaction = null;
            $sync        = true;
            try {
                $transaction = $this->getStarbonusApi()->serviceTransactionCashback()->create($entity);
            } catch (Exception $e) {
                error_log($e->getMessage());
                $sync = false;
            }

            $wpdb->update($this->table_name, [
                'transaction_id' => $transaction ? $transaction->getId() : null,
                'click_id'       => $clickId,
                'sync'           => $sync,
                'sync_try'       => $sync ? 0
                    : $wpdb->get_var('SELECT sync_try FROM ' . $this->table_name . ' WHERE order_id=' . $order->id) + 1
            ], [
                              'order_id' => $order->id
                          ], [
                              '%d',
                              '%s',
                              '%d',
                              '%d'
                          ]);

            return $transaction;
        }

        /**
         * Update order status to pending after create order
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_checkout_update_order_meta($order_id)
        {
            global $wpdb;

            $order = new WC_Order($order_id);

            $cookie = $_COOKIE['starbonus'];
            if (isset($_COOKIE['starbonus']) && !empty($cookie)) {
                $wpdb->insert($this->table_name, [
                    'order_id'           => $order_id,
                    'order_status'       => $order->status,
                    'change_status_date' => current_time('mysql')
                ], [
                                  '%d',
                                  '%s',
                                  '%s'
                              ]);
                do_action('starbonus_widget_update_status', $order);
            }
        }

        /**
         * Update order status to pending
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_pending($order_id)
        {
            $order = new WC_Order($order_id);
            do_action('starbonus_widget_update_status', $order);
        }

        /**
         * Update order status to pending
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_on_hold($order_id)
        {
            $order = new WC_Order($order_id);

            do_action('starbonus_widget_update_status', $order);
        }

        /**
         * Update order status to pending
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_processing($order_id)
        {
            $order = new WC_Order($order_id);
            do_action('starbonus_widget_update_status', $order);
        }

        /**
         * Update order status to accepted
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_completed($order_id)
        {
            global $wpdb;

            $order = new WC_Order($order_id);

            $result = $wpdb->update($this->table_name, [
                'order_status'       => $order->status,
                'change_status_date' => current_time('mysql')
            ], // where
                                    [
                                        'order_id' => $order_id
                                    ], // formatting
                                    [
                                        '%s',
                                        '%s'
                                    ]);

            if ($result > 0) {
                $order_config = $wpdb->get_results('SELECT * FROM ' . $this->table_name . ' WHERE order_id =  ' . $order->id . ' LIMIT 1');
                if ($order_config && is_array($order_config) && count($order_config) > 0) {
                    $order_config = reset($order_config);
                    do_action('starbonus_widget_update_status', $order, false, $order_config->transaction_id);
                }
            }
        }

        /**
         * Update order status to deleted
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_cancelled($order_id)
        {
            $order = new WC_Order($order_id);
            do_action('starbonus_widget_update_status', $order);
        }

        /**
         * Update order status to deleted
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_failed($order_id)
        {
            $order = new WC_Order($order_id);
            do_action('starbonus_widget_update_status', $order);
        }

        /**
         * Update order status to deleted
         *
         * @param $order_id
         */
        public function starbonus_widget_woocommerce_order_status_refunded($order_id)
        {
            $order = new WC_Order($order_id);
            do_action('starbonus_widget_update_status', $order);
        }

        /**
         * Activate cron to update order status
         */
        public function starbonus_widget_cron_complete_status_activation()
        {
            if (!wp_next_scheduled('starbonus_widget_cron_event_hook')) {
                wp_schedule_event(time(), 'hourly', 'starbonus_widget_cron_event_hook');
            }
        }

        /**
         * Deactivate cron
         */
        public function starbonus_widget_cron_complete_status_deactivation()
        {
            wp_clear_scheduled_hook('starbonus_widget_cron_event_hook');
        }

        /**
         * Do cron
         *
         * Changing order status on StarBonus site
         */
        public function starbonus_widget_cron_do_event()
        {
            global $wpdb;

            $order_configs = $wpdb->get_results('SELECT * FROM ' . $this->table_name . ' WHERE order_status = \'completed\' and TIMESTAMPDIFF(DAY, change_status_date, NOW()) > ' . get_option('starbonus_order_timestamp_limit') . ' LIMIT 50');

            foreach ($order_configs as $order_conf) {
                $order = new WC_Order($order_conf->order_id);
                try {
                    do_action('starbonus_widget_update_status_concrete', $order, true, $order_conf->transaction_id);
                    $wpdb->delete($this->table_name, [
                        'order_id' => $order->id
                    ], [
                                      '%d'
                                  ]);
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        /**
         * Activate cron to retry transaction hourly
         */
        public function starbonus_widget_cron_retry_transaction_activation()
        {
            if (!wp_next_scheduled('starbonus_widget_cron_retry_transaction_event_hook')) {
                wp_schedule_event(time(), 'hourly', 'starbonus_widget_cron_retry_transaction_event_hook');
            }
        }

        /**
         * Deactivate cron
         */
        public function starbonus_widget_cron_retry_transaction_deactivation()
        {
            wp_clear_scheduled_hook('starbonus_widget_cron_retry_transaction_event_hook');
        }

        /**
         * Do cron
         *
         * Retry new transaction when sync is false
         */
        public function starbonus_widget_cron_retry_transaction_do_event()
        {
            global $wpdb;

            $order_configs = $wpdb->get_results('SELECT * FROM ' . $this->table_name . ' WHERE sync=false and sync_try <= 4');

            if ($order_configs && is_array($order_configs) && count($order_configs) > 0) {
                foreach ($order_configs as $order_config) {
                    $order = new \WC_Order($order_config->order_id);
                    $this->createTransactionCashback($order, $order_config->click_id);
                }
            }
        }

        /**
         * Activate cron to retry transaction daily
         */
        public function starbonus_widget_cron_retry_older_transaction_activation()
        {
            if (!wp_next_scheduled('starbonus_widget_cron_retry_older_transaction_event_hook')) {
                wp_schedule_event(time(), 'daily', 'starbonus_widget_cron_retry_older_transaction_event_hook');
            }
        }

        /**
         * Deactivate cron
         */
        public function starbonus_widget_cron_retry_older_transaction_deactivation()
        {
            wp_clear_scheduled_hook('starbonus_widget_cron_retry_older_transaction_event_hook');
        }

        /**
         * Do cron
         *
         * Retry oldest transaction when sync is false
         */
        public function starbonus_widget_cron_retry_older_transaction_do_event()
        {
            global $wpdb;

            $order_configs = $wpdb->get_results('SELECT * FROM ' . $this->table_name . ' WHERE sync=false and sync_try > 4 and sync_try <= 10 ');

            if ($order_configs && is_array($order_configs) && count($order_configs) > 0) {
                foreach ($order_configs as $order_config) {
                    $order = new \WC_Order($order_config->order_id);
                    $this->createTransactionCashback($order, $order_config->click_id);
                }
            }
        }

        /**
         * Add StarBonus menu bar to admin panel
         */
        public function starbonus_menu_page()
        {
            add_menu_page('StarBonus', 'StarBonus', 'manage_options', 'starbonus-menu', [
                $this,
                'starbonus_menu_main'
            ], plugins_url('/starbonus-widget/assets/images/logo.png'));
        }

        /**
         * Add submenu option
         */
        public function starbonus_menu_main()
        {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            // Rendering StarBonus form
            echo do_shortcode('[starbonus_client_details_shortcode]');
        }

        /**
         * Client html form
         *
         * @param $clientId
         * @param $programId
         * @param $bonusId
         * @param $source
         * @param $orderTimestampLimit
         * @param $shippingCost
         * @param $expanded
         * @param $minWidth
         * @param $widgetBelow
         * @param $animation
         * @param $showOnShop
         *
         */
        public function client_details_form(
            $clientId,
            $programId,
            $bonusId,
            $source,
            $orderTimestampLimit,
            $shippingCost,
            $expanded,
            $minWidth,
            $widgetBelow,
            $animation,
            $showOnShop
        )
        {
            echo '
    <style>
	    div {
	        margin-bottom:2px;
	    }

	    input{
	        margin-bottom:4px;
	    }
	</style>
	';

            echo '<div id="col-container">
			<div id="col-left">
				<div class="col-wrap">
					<p>W tym miejscu możesz uzupełnić swoje dane seriwsu StarBonus</p>
				</div>
				<div class="form-field form-required term-name-wrap">
				    <form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
					    <div>
						    <label for="client_id"><strong>ID Partnera<i style="color:red">*</i></strong></label>
						    <input type="text" placeholder="np. nazwasklepu" style="width:99%" name="client_id" value="' . (isset($_POST['client_id'])
                    ? $clientId : (get_option('starbonus_client_id') ? get_option('starbonus_client_id') : null)) . '" size="40" aria-required="true">
					        <span class="description"><small>jednoznaczna identyfikacja Partnera w Programie Starbonus</small></span>
					        <span title="dostaniesz go e-mailem od StarBonus po podpisaniu umowy" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="client_password"><strong>Hasło Partnera<i style="color:red">*</i></strong></label>
						    <input type="password" placeholder="np. TajneHaslo123" style="width:99%" name="client_password" value="' . (get_option('starbonus_client_password')
                    ? '******' : '') . '" size="40" aria-required="true">
					    	<span class="description"><small>indywidualne hasło zabezpieczające</small></span>
					        <span title="dostaniesz go e-mailem od StarBonus po podpisaniu umowy" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="program_id"><strong>ID Sklepu<i style="color:red">*</i></strong></label>
						    <input type="number" placeholder="np. 123" style="width:99%" name="program_id" value="' . (isset($_POST['program_id'])
                    ? $programId : (get_option('starbonus_program_id') ? get_option('starbonus_program_id') : null)) . '" size="40" aria-required="true">
					        <span class="description"><small>jednoznaczna identyfikacja Sklepu w Programie Starbonus</small></span>
					        <span title="dostaniesz go e-mailem od StarBonus po podpisaniu umowy" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="bonus_id"><strong>ID Bonusu</strong></label>
						    <input type="number" placeholder="np. 99" style="width:99%" name="bonus_id" value="' . (isset($_POST['bonus_id'])
                    ? $bonusId : (get_option('starbonus_bonus_id') ? get_option('starbonus_bonus_id') : null)) . '" size="40" aria-required="true">
					        <span class="description"><small>jednoznaczna identyfikacja Bonusu w Programie Starbonus</small></span>
					        <span title="dostaniesz go e-mailem od StarBonus po podpisaniu umowy" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="source"><strong>Adres sklepu</strong></label>
						    <input type="text" placeholder="np. nazwasklepu.pl" style="width:99%" name="source" value="' . (isset($_POST['source'])
                    ? $source : (get_option('starbonus_source') ? get_option('starbonus_source') : null)) . '" size="40">
					        <span class="description"><small>adres URL Sklepu, bez subdomeny</small></span>
					    </div>
					    <br/>
					    <div>
						    <label for="order_timestamp_limit"><strong>Limit Akceptacji Transakcji (15-30 dni)</strong><i style="color:red">*</i></label>
						    <input type="number" placeholder="np. 17" style="width:99%" name="order_timestamp_limit" value="' . (isset($_POST['order_timestamp_limit'])
                    ? $orderTimestampLimit
                    : (get_option('starbonus_order_timestamp_limit') ? get_option('starbonus_order_timestamp_limit')
                        : null)) . '" size="40">
					        <span class="description"><small>liczba dni, po której zakup dokonany z użyciem Wtyczki StarBonus zostanie automatycznie zaakceptowany, jeżeli wcześniej Klient nie dokonał zwrotu zamówienia.</small></span>
					        <span title="uniknięcie nieuczciwych Klientów robiących zakupy wyłącznie w celu odebrania Cashbacku; uwzględnienie prawa Klienta do odstąpienia od umowy sprzedaży przez 14 dni." style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="shipping_cost" style="line-height:40px"><strong>Wlicz koszt przesyłki do kwoty Transakcji</strong></label>
						    <input type="checkbox" name="shipping_cost" ' . (((isset($_POST['shipping_cost']) && $shippingCost) || (!isset($_POST['shipping_cost']) && get_option('starbonus_shipping_cost')))
                    ? 'checked="checked"'
                    : (get_option('starbonus_shipping_cost') ? get_option('starbonus_shipping_cost') : false)) . '>
					        <br/><span class="description"><small>możesz użyć tej opcji, jeżeli chcesz bardziej nagradzać Klientów - naliczać Cashbacki od wyższej kwoty Transakcji</small></span>
					    </div>
					    <br/>
					    <div>
						    <label for="open_widget"><strong>Widoczność wtyczki</strong></label>
                            <select id="open_widget" name="open_widget" style="width:99%" onchange="checkChoice()">
                                <option value="disable" ' . (get_option('starbonus_open_widget') === 'disable'
                    ? 'selected' : false) . '>Wyłącz</option>
                                <option value="ever" ' . (get_option('starbonus_open_widget') === 'ever' ? 'selected'
                    : false) . '>Włącz dla wszystkich</option>
                                <option value="redirect" ' . (get_option('starbonus_open_widget') === 'redirect'
                    ? 'selected' : false) . '>Włącz tylko dla Klientów przekierowanych ze StarBonus</option>
                            </select>
                            <span class="description"><small>możesz ustawić widoczność Wtyczki: w ogóle włączyć/wyłączyć wyświetlanie w Sklepie lub ograniczyć wyświetlanie tylko dla Klientów przekierowanych ze StarBonus</small></span>
					    </div>
					    <br/>
					    <div>
						    <label for="show_on_shop" style="line-height:40px"><strong>Widoczna tylko w sklepie</strong></label>
						    <input id="show_on_shop" type="checkbox" name="show_on_shop" ' . (((isset($_POST['show_on_shop']) && $showOnShop) || (!isset($_POST['show_on_shop']) && get_option('starbonus_show_on_shop')))
                    ? 'checked="checked"'
                    : (get_option('starbonus_show_on_shop') ? get_option('starbonus_show_on_shop') : false)) . '>
					        <br/><span class="description"><small>ogranicza widoczność tylko do podstron związanych bezpośrednio z zakupem</small></span>
					    </div>
					    <br/>
					    <div>
						    <label for="url_api"><strong>Wersja [Produkcyjna/Testowa]</strong></label>
                            <select name="url_api" style="width:99%">
                                <option value="production" ' . (get_option('starbonus_url_api') === 'production'
                    ? 'selected' : false) . '>Produkcja</option>
                                <option value="test" ' . (get_option('starbonus_url_api') === 'test' ? 'selected'
                    : false) . '>Testowa</option>
                            </select>
                            <span class="description"><small>umożliwia testową instalację Wtyczki, jeżeli posiadasz wersję testową Sklepu</small></span>
					    </div>
					    <br/>
					    <div>
						    <label for="expanded" style="line-height:40px"><strong>Domyślnie rozwinięta</strong></label>
						    <input type="checkbox" name="expanded" ' . (((isset($_POST['expanded']) && $expanded) || (!isset($_POST['expanded']) && get_option('starbonus_expanded')))
                    ? 'checked="checked"'
                    : (get_option('starbonus_expanded') ? get_option('starbonus_expanded') : false)) . '>
					        <br/><span class="description"><small>domyślne ustawienie Wtyczki, jako rozwiniętej, ma na celu wzrost zainteresowania Klientów Cashbackiem</small></span>
					    	<span title="opcja wyboru: T - Klient zobaczy rozwiniętą Wtyczkę przy pierwszej wizycie; N - Klient zobaczy zwiniętą Wtyczkę przy pierwszej wizycie" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="min_width"><strong>Minimalna szerokość Wtyczki (domyślnie 600px)</strong><i style="color:red">*</i></label>
						    <input type="number" placeholder="np. 600" style="width:99%" name="min_width" value="' . (isset($_POST['min_width'])
                    ? $minWidth : (get_option('starbonus_min_width') ? get_option('starbonus_min_width') : null)) . '" size="40">
					        <span class="description"><small>ustawienie od jakiej szerokości okna przeglądarki Wtyczka jest niewidoczna</small></span>
					        <span title="ukrycie Wtyczki na urządzeniach mobilnych" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="widget_below"><strong>Obniż Wtyczkę (domyślnie 150px)</strong><i style="color:red">*</i></label>
						    <input type="number" placeholder="np. 150" style="width:99%" name="widget_below" value="' . (isset($_POST['widget_below'])
                    ? $widgetBelow
                    : (get_option('starbonus_widget_below') ? get_option('starbonus_widget_below') : null)) . '" size="40">
					        <span class="description"><small>ustawienie o ile Wtyczka zostanie obniżona względem górnej krawędzi przeglądarki</small></span>
					        <span title="optymalne ustawienie Wtyczki względem layoutu Sklepu, np. niezasłanianie logo" style="cursor:pointer"> (?)</span>
					    </div>
					    <br/>
					    <div>
						    <label for="widget_side"><strong>Strona wyświetlania</strong></label>
                            <select name="widget_side" style="width:99%">
                                <option value="left" ' . (get_option('starbonus_widget_side') === 'left' ? 'selected'
                    : false) . '>Lewa</option>
                                <option value="right" ' . (get_option('starbonus_widget_side') === 'right' ? 'selected'
                    : false) . '>Prawa</option>
                            </select>
                            <span class="description"><small>optymalne ustawienie Wtyczki względem layoutu Sklepu</small></span>
					    </div>
					    <br/>
					    <br/>
					    <div>
						    <label for="skin"><strong>Skórka</strong></label>
                            <select name="skin" style="width:99%">
                                <option value="violet" ' . (get_option('starbonus_skin') === 'violet' ? 'selected'
                    : false) . '>Fiolet</option>
                                <option value="orange" ' . (get_option('starbonus_skin') === 'orange' ? 'selected'
                    : false) . '>Pomarańcz</option>
                                <option value="red" ' . (get_option('starbonus_skin') === 'red' ? 'selected' : false) . '>Czerwień</option>
                                <option value="green" ' . (get_option('starbonus_skin') === 'green' ? 'selected'
                    : false) . '>Zieleń</option>
                                <option value="grey" ' . (get_option('starbonus_skin') === 'grey' ? 'selected'
                    : false) . '>Szarość</option>
                            </select>
                            <span class="description"><small>opcja wyboru kolorystyki Wtyczki</small></span>
					    </div>
					    <br/>
					    <div>
						    <label for="animation" style="line-height:40px"><strong>Animacje</strong></label>
						    <input type="checkbox" name="animation" ' . (((isset($_POST['animation']) && $animation) || (!isset($_POST['animation']) && get_option('starbonus_animation')))
                    ? 'checked="checked"'
                    : (get_option('starbonus_animation') ? get_option('starbonus_animation') : false)) . '>
					        <br/><span class="description"><small>włącza/wyłącza animacje wyświetalania Wtyczki</small></span>
					    </div>
					    <br/>
					    <input type="submit" name="submit" value="Submit" class="button button-primary"/>
				    </form>
				</div>
			</div>
		  </div>
		  <script type="text/javascript">
              function checkChoice() {
                 var choice = document.getElementById("open_widget").value;
                 var showOnShop = document.getElementById("show_on_shop");
                 if (choice == "disable") {
                    showOnShop.enabled = false;
                    showOnShop.disabled = true;
                 } else {
                    showOnShop.disabled = false
                    showOnShop.enable = true;
                 }
              }
		  </script>
    ';
        }

        /**
         * Client form validation
         *
         * @param $clientId
         * @param $clientPassword
         * @param $programId
         * @param $urlApi
         * @param $orderTimestampLimit
         * @param $openWidget
         * @param $minWidth
         * @param $widgetBelow
         */
        public function client_details_validation(
            $clientId,
            $clientPassword,
            $programId,
            $urlApi,
            $orderTimestampLimit,
            $openWidget,
            $minWidth,
            $widgetBelow
        )
        {
            global $reg_errors;
            $reg_errors = new WP_Error();

            if (empty($clientId) || empty($programId) || empty($urlApi) || empty($orderTimestampLimit) || empty($openWidget) || empty($minWidth) || empty($widgetBelow)) {
                $reg_errors->add('field', 'Wypełnij wszystkie wymagane pola');
            }
            if ((empty($clientPassword) || $clientPassword === '******') && !get_option('starbonus_client_password')) {
                $reg_errors->add('field', 'Aby zatwierdzić zmiany musisz podać hasło');
            }
            if ($orderTimestampLimit < 15 || $orderTimestampLimit > 30) {
                $reg_errors->add('field', 'Limit akceptacji transakcji musi zawierać się w przedziale 15-30 dni');
            }

            if (is_wp_error($reg_errors)) {
                foreach ($reg_errors->get_error_messages() as $error) {
                    echo '<div>
                            <p style="color:red; font-size:20px"><strong>Błąd</strong>:' . $error . '</p><br/>
                         </div>';
                }
            }
        }

        /**
         * Update or create client options
         */
        public function update_cilent_options()
        {
            global $reg_errors, $clientId, $clientPassword, $programId, $bonusId, $openWidget, $urlApi, $orderTimestampLimit, $source, $shippingCost, $expanded, $minWidth, $widgetBelow, $widgetSide, $skin, $animation, $showOnShop;
            if (1 > count($reg_errors->get_error_messages())) {
                update_option('starbonus_client_id', $clientId);
                if (($clientPassword !== '******') && !empty($clientPassword)) {
                    update_option('starbonus_client_password', $clientPassword);
                }
                update_option('starbonus_program_id', $programId);
                update_option('starbonus_bonus_id', $bonusId);
                update_option('starbonus_source', $source);
                update_option('starbonus_open_widget', $openWidget);
                update_option('starbonus_order_timestamp_limit', $orderTimestampLimit);
                update_option('starbonus_url_api', $urlApi);
                update_option('starbonus_shipping_cost', $shippingCost);
                update_option('starbonus_expanded', $expanded);
                update_option('starbonus_min_width', $minWidth);
                update_option('starbonus_widget_below', $widgetBelow);
                update_option('starbonus_widget_side', $widgetSide);
                update_option('starbonus_skin', $skin);
                update_option('starbonus_animation', $animation);
                update_option('starbonus_show_on_shop', $showOnShop);
            }
        }

        /**
         * Render form if method GET, or validate and set global variables if POST
         */
        public function edit_client_details()
        {
            global $clientId, $clientPassword, $programId, $bonusId, $openWidget, $urlApi, $orderTimestampLimit, $source, $shippingCost, $expanded, $minWidth, $widgetBelow, $widgetSide, $skin, $animation, $showOnShop;
            if (isset($_POST['submit'])) {
                $this->client_details_validation($_POST['client_id'], $_POST['client_password'], $_POST['program_id'], $_POST['url_api'], $_POST['order_timestamp_limit'], $_POST['open_widget'], $_POST['min_width'], $_POST['widget_below']);

                $clientId            = sanitize_text_field($_POST['client_id']);
                $clientPassword      = sanitize_text_field($_POST['client_password']);
                $programId           = sanitize_text_field($_POST['program_id']);
                $bonusId             = sanitize_text_field($_POST['bonus_id']);
                $source              = sanitize_text_field($_POST['source']);
                $openWidget          = sanitize_text_field($_POST['open_widget']);
                $urlApi              = sanitize_text_field($_POST['url_api']);
                $orderTimestampLimit = sanitize_text_field($_POST['order_timestamp_limit']);
                $shippingCost        = !empty($_POST['shipping_cost']);
                $expanded            = !empty($_POST['expanded']);
                $minWidth            = sanitize_text_field($_POST['min_width']);
                $widgetBelow         = sanitize_text_field($_POST['widget_below']);
                $widgetSide          = sanitize_text_field($_POST['widget_side']);
                $skin                = sanitize_text_field($_POST['skin']);
                $animation           = !empty($_POST['animation']);
                $showOnShop          = !empty($_POST['show_on_shop']);

                $this->update_cilent_options();
            }

            $this->client_details_form($clientId, $programId, $bonusId, $source, $orderTimestampLimit, $shippingCost, $expanded, $minWidth, $widgetBelow, $animation, $showOnShop);
        }

        /**
         * Call shortcode
         *
         * @return string
         */
        public function client_details_shortcode()
        {
            ob_start();
            $this->edit_client_details();

            return ob_get_clean();
        }

        /**
         * http://example.com/index.php?&starbonus_click_id={clickId}&starbonus_url_domain={urlDomain}
         *
         * Init redirection route
         */
        public function init_starbonus_redirect()
        {

            add_rewrite_tag('%starbonus_click_id%', '([^/]+)');
            add_rewrite_tag('%starbonus_url_domain%', '(?:/(.*))');
            add_rewrite_rule('^starbonus/([^/]+)(?:/(.*))?$', 'index.php?&starbonus_click_id=$matches[1]&starbonus_url_domain=$matches[2]', 'top');
        }

        /**
         * Add request variables to wp variables
         *
         * @param $vars
         *
         * @return array
         */
        public function starbonus_query_vars($vars)
        {
            $vars[] = 'starbonus_click_id';
            $vars[] = 'starbonus_url_domain';

            return $vars;
        }

        /**
         * Get request, set cookie and redirect like a StarBonus client
         *
         * @param $wp
         */
        public function custom_requests($wp)
        {
            if (!empty($wp->query_vars['starbonus_click_id'])) {
                setcookie('starbonus', $wp->query_vars['starbonus_click_id'], time() + (60 * 60 * 24 * 14), '/');
                setcookie('starbonus_redirect', 1, time() + (60 * 60 * 24 * 30), '/');

                $url = home_url();

                $parsed        = parse_url($url);
                $relativeparts = array_intersect_key($parsed, array_flip(['path', 'query', 'fragment']));

                $url = http_build_url($relativeparts);
                wp_redirect($url, 302);
                exit();
            }
        }

        /**
         * Get starbonusApi
         *
         * @return \Starbonus\Api\Api
         */
        public function getStarbonusApi()
        {
            if ($this->starbonusApi === null) {
                // Setup the credentials for the requests
                $credentials = new \OAuth\Common\Consumer\Credentials(get_option('starbonus_client_id'), get_option('starbonus_client_password'), null);

                // dev server
                $starbonusWidgetUri = new \OAuth\Common\Http\Uri\Uri((get_option('starbonus_url_api') === 'production'
                    ? 'https://api.starbonus.pl' : 'http://api.starbonus.kusmierz.be'));
                $this->starbonusApi = new \Starbonus\Api\Api($credentials, $starbonusWidgetUri);
                // production
//                 $this->starbonusApi = new \Starbonus\Api\Api($credentials);
            }

            return $this->starbonusApi;
        }

        /**
         * Get functionNames
         *
         * @return static array
         */
        public function getFunctionNames() {
            return self::$functionNames;
        }
    }

endif;


/**
 * Returns the main instance of StarBonus to prevent the need to use globals.
 *
 * @return StarBonusPlugin
 */
function SB()
{
    return StarBonusPlugin::instance();
}

$GLOBALS['starbonus'] = SB();
