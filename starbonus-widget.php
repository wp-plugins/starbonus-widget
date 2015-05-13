<?php

/**
 * Plugin Name: Starbonus Widget
 * Plugin URI: kontakt@starbonus.pl
 * Description: Starbonus widget.
 * Author: Varya
 * Version: 1.0.2
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
            ],
                10,
                3
            ); // params $priority, $args amount
            add_action('starbonus_widget_update_status_concrete', [
                $this,
                'starbonus_widget_update_status_concrete'
            ],
                10,
                3
            );
            add_action('woocommerce_checkout_update_order_meta',
                [$this, 'starbonus_widget_woocommerce_checkout_update_order_meta']);
            add_action('woocommerce_order_status_pending',
                [$this, 'starbonus_widget_woocommerce_order_status_pending']);
            add_action('woocommerce_order_status_on-hold',
                [$this, 'starbonus_widget_woocommerce_order_status_on_hold']);
            add_action('woocommerce_order_status_processing',
                [$this, 'starbonus_widget_woocommerce_order_status_processing']);
            add_action('woocommerce_order_status_completed',
                [$this, 'starbonus_widget_woocommerce_order_status_completed']);
            add_action('woocommerce_order_status_cancelled',
                [$this, 'starbonus_widget_woocommerce_order_status_cancelled']);
            add_action('woocommerce_order_status_failed',
                [$this, 'starbonus_widget_woocommerce_order_status_failed']);
            add_action('woocommerce_order_status_refunded',
                [$this, 'starbonus_widget_woocommerce_order_status_refunded']);
            add_action('starbonus_widget_cron_event_hook', [$this, 'starbonus_widget_cron_do_event']);
            add_action('starbonus_widget_cron_retry_transaction_event_hook',
                [$this, 'starbonus_widget_cron_retry_transaction_do_event']);
            add_action('starbonus_widget_cron_retry_older_transaction_event_hook',
                [$this, 'starbonus_widget_cron_retry_older_transaction_do_event']);

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
            if (get_option('starbonus_open_widget_ever') ||
                (get_option('starbonus_open_widget_when_redirect') && $_COOKIE['starbonus_redirect'])
            ) {
                echo '<script type="text/javascript">
                        <!-- // <![CDATA[

                        var urlApi = "' . get_option('starbonus_url_api') . '",
                            urlJs = "' . get_option('starbonus_url_js') . '",
                            programId = ' . get_option('starbonus_program_id') . ',
                            source = "' . get_option('starbonus_source') . '",
                            cookieExpire = parseInt(' . (get_option('starbonus_cookie_expire') * 24 * 60 * 60) . ' || 2592000, 10);

                        (function(i,s,o,g,r,a,m){i[\'StarbonusWidgetObject\']=r;i[r]=i[r]||function(){
                            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                        })(window,document,\'script\',urlJs,\'_sbwidget\');

                        _sbwidget(\'config\', {
                            \'api\': {\'url\': urlApi},
                            \'widget\': {\'program\': programId, \'source\': source},
                            \'cookie\': {\'defaults\': {\'expires\': cookieExpire}}
                        });

                        // ]]> -->
                    </script>';
            }
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
                    $order_config = $wpdb->get_results(
                        'SELECT * FROM ' . $this->table_name . ' WHERE order_id =  ' . $order->id . ' LIMIT 1'
                    );
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
            $sync = true;
            try {
                $transaction = $this->getStarbonusApi()->serviceTransactionCashback()->patch($transactionId, $entity);
            } catch (Exception $e) {
                error_log($e->getMessage(), 0);
                $sync = false;
            }

            $wpdb->update(
                $this->table_name,
                [
                    'order_status' => $order->status,
                    'sync' => $sync,
                    'sync_try' => $sync ? 0 : $wpdb->get_var('SELECT sync_try FROM ' . $this->table_name . ' WHERE order_id=' . $order->id) + 1
                ],
                [
                    'order_id' => $order->id
                ],
                [
                    '%s',
                    '%d',
                    '%d'
                ]
            );

            return $transaction;
        }

        /**
         * Create transaction cachback
         *
         * @param WC_Order $order
         * @param string $clickId
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
            $entity
                ->setTransaction('wordpress-' . $order->get_order_number())
                ->setClick($clickId)
                ->setAmountPurchase(($order->get_total() - (get_option('starbonus_shipping_cost') ? $order->get_total_shipping() : 0)) * 100)
                ->setCurrency($order->get_order_currency())
                ->setState('pending');
//            ->setCategory('');

            $transaction = null;
            $sync = true;
            try {
                $transaction = $this->getStarbonusApi()->serviceTransactionCashback()->create($entity);
            } catch (Exception $e) {
                error_log($e->getMessage());
                $sync = false;
            }

            $wpdb->update(
                $this->table_name,
                [
                    'transaction_id' => $transaction ? $transaction->getId() : null,
                    'click_id' => $clickId,
                    'sync' => $sync,
                    'sync_try' => $sync ? 0 : $wpdb->get_var('SELECT sync_try FROM ' . $this->table_name . ' WHERE order_id=' . $order->id) + 1
                ],
                [
                    'order_id' => $order->id
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%d'
                ]
            );

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

            if (isset($_COOKIE['starbonus']) && !empty($_COOKIE['starbonus'])) {
                $wpdb->insert(
                    $this->table_name,
                    [
                        'order_id' => $order_id,
                        'order_status' => $order->status,
                        'change_status_date' => current_time('mysql')
                    ],
                    [
                        '%d',
                        '%s',
                        '%s'
                    ]
                );
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

            $result = $wpdb->update(
                $this->table_name,
                [
                    'order_status' => $order->status,
                    'change_status_date' => current_time('mysql')
                ],
                // where
                [
                    'order_id' => $order_id
                ],
                // formatting
                [
                    '%s',
                    '%s'
                ]
            );

            if ($result > 0) {
                $order_config = $wpdb->get_results(
                    'SELECT * FROM ' . $this->table_name . ' WHERE order_id =  ' . $order->id . ' LIMIT 1'
                );
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

            $order_configs = $wpdb->get_results(
                'SELECT * FROM ' . $this->table_name . ' WHERE order_status = \'completed\' and TIMESTAMPDIFF(DAY, change_status_date, NOW()) > ' . get_option('starbonus_order_timestamp_limit') . ' LIMIT 50'
            );

            foreach ($order_configs as $order_conf) {
                $order = new WC_Order($order_conf->order_id);
                try {
                    do_action('starbonus_widget_update_status_concrete', $order, true, $order_conf->transaction_id);
                    $wpdb->delete(
                        $this->table_name,
                        [
                            'order_id' => $order->id
                        ],
                        [
                            '%d'
                        ]
                    );
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

            $order_configs = $wpdb->get_results(
                'SELECT * FROM ' . $this->table_name . ' WHERE sync=false and sync_try <= 4'
            );

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

            $order_configs = $wpdb->get_results(
                'SELECT * FROM ' . $this->table_name . ' WHERE sync=false and sync_try > 4 and sync_try <= 10 '
            );

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
            add_menu_page('StarBonus', 'StarBonus', 'manage_options', 'starbonus-menu', [$this, 'starbonus_menu_main'],
                plugins_url('/starbonus-widget/assets/images/logo.png'));
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
         * @param $clientPassword
         * @param $programId
         * @param $offerId
         * @param $bonusId
         * @param $source
         * @param $cookieExpire
         * @param $urlApi
         * @param $urlJs
         * @param $orderTimestampLimit
         * @param $shippingCost
         * @param $openWidgetExpired
         * @param $openWidgetEver
         * @param $openWidgetWhenRedirect
         */
        public function client_details_form(
            $clientId,
            $clientPassword,
            $programId,
            $offerId,
            $bonusId,
            $source,
            $cookieExpire,
            $urlApi,
            $urlJs,
            $orderTimestampLimit,
            $shippingCost,
            $openWidgetExpired,
            $openWidgetEver,
            $openWidgetWhenRedirect
        ) {
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
						    <label for="client_id">ID klienta</label>
						    <input type="text" name="client_id" value="' . (isset($_POST['client_id']) ? $clientId : (get_option('starbonus_client_id') ? get_option('starbonus_client_id') : null)) . '" size="40" aria-required="true">
					    </div>
					    <div>
						    <label for="client_password">Hasło</label>
						    <input type="text" name="client_password" value="' . (isset($_POST['client_password']) ? $clientPassword : (get_option('starbonus_client_password') ? get_option('starbonus_client_password') : null)) . '" size="40" aria-required="true">
					    </div>
					    <div>
						    <label for="program_id">ID programu</label>
						    <input type="number" name="program_id" value="' . (isset($_POST['program_id']) ? $programId : (get_option('starbonus_program_id') ? get_option('starbonus_program_id') : null)) . '" size="40" aria-required="true">
					    </div>
					    <div>
						    <label for="offer_id">ID oferty</label>
						    <input type="number" name="offer_id" value="' . (isset($_POST['offer_id']) ? $offerId : (get_option('starbonus_offer_id') ? get_option('starbonus_offer_id') : null)) . '" size="40" aria-required="true">
					    </div>
					    <div>
						    <label for="bonus_id">ID bonusu</label>
						    <input type="number" name="bonus_id" value="' . (isset($_POST['bonus_id']) ? $bonusId : (get_option('starbonus_bonus_id') ? get_option('starbonus_bonus_id') : null)) . '" size="40" aria-required="true">
					    </div>
					    <div>
						    <label for="source">Source</label>
						    <input type="text" name="source" value="' . (isset($_POST['source']) ? $source : (get_option('starbonus_source') ? get_option('starbonus_source') : null)) . '" size="40">
					    </div>
					    <div>
						    <label for="order_timestamp_limit">Limit dni akceptacji transakcji</label>
						    <input type="number" name="order_timestamp_limit" value="' . (isset($_POST['order_timestamp_limit']) ? $orderTimestampLimit : (get_option('starbonus_order_timestamp_limit') ? get_option('starbonus_order_timestamp_limit') : null)) . '" size="40">
					    </div>
					    <div>
						    <label for="url_api">API url</label>
						    <input type="text" name="url_api" value="' . (isset($_POST['url_api']) ? $urlApi : (get_option('starbonus_url_api') ? get_option('starbonus_url_api') : null)) . '" size="40">
					    </div>
					    <div>
						    <label for="url_js">Javascript url</label>
						    <input type="text" name="url_js" value="' . (isset($_POST['url_js']) ? $urlJs : (get_option('starbonus_url_js') ? get_option('starbonus_url_js') : null)) . '" size="40">
					    </div>
					    <div>
						    <label for="shipping_cost" style="line-height:40px">Wliczyć koszt przesyłki?</label>
						    <input type="checkbox" name="shipping_cost" ' . (((isset($_POST['shipping_cost']) && $shippingCost) || (!isset($_POST['shipping_cost']) && get_option('starbonus_shipping_cost'))) ? 'checked="checked"' : (get_option('starbonus_shipping_cost') ? get_option('starbonus_shipping_cost') : false)) . '>
					    </div>
					    <div>
						    <label for="open_widget_ever" style="line-height:40px">Widget pokazywać zawsze na stronie zawsze?</label>
						    <input type="checkbox" name="open_widget_ever" ' . (((isset($_POST['open_widget_ever']) && $openWidgetEver) || (!isset($_POST['open_widget_ever']) && get_option('starbonus_open_widget_ever'))) ? 'checked="checked"' : (get_option('starbonus_open_widget_ever') ? get_option('starbonus_open_widget_ever') : false)) . '">
					    </div>
					    <div>
						    <label for="open_widget_when_redirect" style="line-height:40px">Widget pokazywać po przekierowaniu z serwisu StarBonus?</label>
						    <input type="checkbox" name="open_widget_when_redirect" ' . (((isset($_POST['open_widget_when_redirect']) && $openWidgetWhenRedirect) || (!isset($_POST['open_widget_when_redirect']) && get_option('starbonus_open_widget_when_redirect'))) ? 'checked="checked"' : (get_option('starbonus_open_widget_when_redirect') ? get_option('starbonus_open_widget_when_redirect') : false)) . '">
					    </div>
					    <div>
						    <label for="open_widget_expired">Czas wygaśnięcia ciasteczka otwierania widgetu (w dniach)</label>
						    <input type="number" name="open_widget_expired" value="' . (isset($_POST['open_widget_expired']) ? $openWidgetExpired : (get_option('starbonus_open_widget_expired') ? get_option('starbonus_open_widget_expired') : null)) . '" size="40">
					    </div>
					    <div>
						    <label for="cookie_expire">Czas wygaśnięcia ciasteczka starbonus (w dniach)</label>
						    <input type="number" name="cookie_expire" value="' . (isset($_POST['cookie_expire']) ? $cookieExpire : (get_option('starbonus_cookie_expire') ? get_option('starbonus_cookie_expire') : null)) . '" size="40">
					    </div>
					    <input type="submit" name="submit" value="Submit" class="button button-primary"/>
				    </form>
				</div>
			</div>
		  </div>
    ';
        }

        /**
         * Client form validation
         *
         * @param $clientId
         * @param $clientPassword
         * @param $programId
         * @param $source
         * @param $cookieExpire
         * @param $openWidgetExpired
         */
        public function client_details_validation(
            $clientId,
            $clientPassword,
            $programId,
            $source,
            $cookieExpire,
            $openWidgetExpired
        ) {
            global $reg_errors;
            $reg_errors = new WP_Error();

            if (empty($clientId) || empty($clientPassword) || empty($programId) || empty($source) || empty($openWidgetExpired) || empty($cookieExpire)) {
                $reg_errors->add('field', 'Required form field is missing');
            }

            if (is_wp_error($reg_errors)) {
                foreach ($reg_errors->get_error_messages() as $error) {
                    echo '<div>
				    <strong>Error</strong>:' . $error . '<br/>
				 </div>';
                }
            }
        }

        /**
         * Update or create client options
         */
        public function update_cilent_options()
        {
            global $reg_errors, $clientId, $clientPassword, $programId, $offerId, $bonusId, $openWidgetEver, $cookieExpire, $urlApi, $urlJs, $orderTimestampLimit, $source, $shippingCost, $openWidgetExpired, $openWidgetWhenRedirect;
            if (1 > count($reg_errors->get_error_messages())) {
                update_option('starbonus_client_id', $clientId);
                update_option('starbonus_client_password', $clientPassword);
                update_option('starbonus_program_id', $programId);
                update_option('starbonus_offer_id', $offerId);
                update_option('starbonus_bonus_id', $bonusId);
                update_option('starbonus_source', $source);
                update_option('starbonus_open_widget_ever', $openWidgetEver);
                update_option('starbonus_order_timestamp_limit', $orderTimestampLimit);
                update_option('starbonus_cookie_expire', $cookieExpire);
                update_option('starbonus_url_api', $urlApi);
                update_option('starbonus_url_js', $urlJs);
                update_option('starbonus_shipping_cost', $shippingCost);
                update_option('starbonus_open_widget_expired', $openWidgetExpired);
                update_option('starbonus_open_widget_when_redirect', $openWidgetWhenRedirect);
            }
        }

        /**
         * Render form if method GET, or validate and set global variables if POST
         */
        public function edit_client_details()
        {
            global $clientId, $clientPassword, $programId, $offerId, $bonusId, $openWidgetEver, $cookieExpire, $urlApi, $urlJs, $orderTimestampLimit, $source, $shippingCost, $openWidgetExpired, $openWidgetWhenRedirect;
            if (isset($_POST['submit'])) {
                $this->client_details_validation($_POST['client_id'], $_POST['client_password'], $_POST['program_id'],
                    $_POST['source'], $_POST['cookie_expire'],
                    $_POST['open_widget_expired']);

                $clientId = sanitize_text_field($_POST['client_id']);
                $clientPassword = sanitize_text_field($_POST['client_password']);
                $programId = sanitize_text_field($_POST['program_id']);
                $offerId = sanitize_text_field($_POST['offer_id']);
                $bonusId = sanitize_text_field($_POST['bonus_id']);
                $source = sanitize_text_field($_POST['source']);
                $openWidgetEver = !empty($_POST['open_widget_ever']);
                $cookieExpire = sanitize_text_field($_POST['cookie_expire']);
                $urlApi = sanitize_text_field($_POST['url_api']);
                $urlJs = sanitize_text_field($_POST['url_js']);
                $orderTimestampLimit = sanitize_text_field($_POST['order_timestamp_limit']);
                $shippingCost = !empty($_POST['shipping_cost']);
                $openWidgetWhenRedirect = !empty($_POST['open_widget_when_redirect']);
                $openWidgetExpired = sanitize_text_field($_POST['open_widget_expired']);

                $this->update_cilent_options();
            }

            $this->client_details_form($clientId, $clientPassword, $programId, $offerId, $bonusId, $source,
                $cookieExpire,
                $urlApi, $urlJs, $orderTimestampLimit, $shippingCost, $openWidgetExpired, $openWidgetEver,
                $openWidgetWhenRedirect);
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
         * Init redirection route
         */
        public function init_starbonus_redirect()
        {

            add_rewrite_tag(
                '%starbonus_click_id%',
                '([^/]+)'
            );
            add_rewrite_tag(
                '%starbonus_url_domain%',
                '(?:/(.*))'
            );
            add_rewrite_rule(
                '^starbonus/([^/]+)(?:/(.*))?$',
                'index.php?&starbonus_click_id=$matches[1]&starbonus_url_domain=$matches[2]',
                'top'
            );
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
                setcookie('starbonus', $wp->query_vars['starbonus_click_id'],
                    time() + (60 * 60 * 24 * get_option('starbonus_cookie_expire')), '/');
                setcookie('starbonus_redirect', 1,
                    time() + (60 * 60 * 24 * get_option('starbonus_open_widget_expired')), '/');

                $url = home_url();

                $parsed = parse_url($url);
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
                $credentials = new \OAuth\Common\Consumer\Credentials(
                    get_option('starbonus_client_id'),
                    get_option('starbonus_client_password'),
                    null
                );

                // dev server
                $starbonusWidgetUri = new \OAuth\Common\Http\Uri\Uri(get_option('starbonus_url_api'));
                $this->starbonusApi = new \Starbonus\Api\Api($credentials, $starbonusWidgetUri);
                // production
                // $starbonusApi = new \Starbonus\Api\Api($credentials);
            }

            return $this->starbonusApi;
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
