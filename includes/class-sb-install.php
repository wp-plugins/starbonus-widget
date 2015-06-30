<?php

/**
 * Class SB_Install
 */
abstract class SB_Install
{

    /**
     * Create table for order status manage during install or enable plugin
     */
    public static function install()
    {
        self::create_table();
        self::create_options();
    }

    /**
     * Creating StarBonus config table
     */
    public static function create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'starbonus_order_status_config';

        if ( $wpdb->get_var( 'SHOW TABLES LIKE ' . $table_name ) != $table_name ) {
            $charset_collate = $wpdb->charset;

            $query = 'CREATE TABLE ' . $table_name . ' (
	            id BIGINT NOT NULL AUTO_INCREMENT,
	            order_id BIGINT NOT NULL,
	            transaction_id BIGINT,
	            click_id VARCHAR(31),
	            order_status VARCHAR(15),
	            change_status_date TIMESTAMP,
	            sync TINYINT(1) DEFAULT 0,
	            sync_try INT DEFAULT 0,
	            PRIMARY KEY (id),
	            UNIQUE KEY order_id (order_id)
	        ) DEFAULT CHARACTER SET ' . $charset_collate . ';';

            /**
             * The dbDelta() public function isn't available at this stage of the application
             * so you will need to make sure you include the /wp-admin/includes/upgrade.php file
             * before you call the dbDelta() public function.
             */
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $query );
        }
    }

    /**
     * Creating default options
     */
    public static function create_options()
    {
        /**
         * Creating default options
         */
        if ( ! get_option( 'starbonus_order_timestamp_limit' ) ) {
            add_option( 'starbonus_order_timestamp_limit', 15 );
        }
        if ( ! get_option( 'starbonus_open_widget' ) ) {
            add_option( 'starbonus_open_widget', 'ever' );
        }
        if ( ! get_option( 'starbonus_url_api' ) ) {
            add_option( 'starbonus_url_api', 'production' );
        }
        if ( ! get_option( 'starbonus_shipping_cost' ) ) {
            add_option( 'starbonus_shipping_cost', true );
        }
        if ( ! get_option( 'starbonus_expanded' ) ) {
            add_option( 'starbonus_expanded', true );
        }
        if ( ! get_option( 'starbonus_min_width' ) ) {
            add_option( 'starbonus_min_width', 600 );
        }
        if ( ! get_option( 'starbonus_widget_below' ) ) {
            add_option( 'starbonus_widget_below', 150 );
        }
        if ( ! get_option( 'starbonus_widget_side' ) ) {
            add_option( 'starbonus_widget_side', 'left' );
        }
        if ( ! get_option( 'starbonus_skin' ) ) {
            add_option( 'starbonus_skin', 'violet' );
        }
        if ( ! get_option( 'starbonus_animation' ) ) {
            add_option( 'starbonus_animation', true );
        }
        if ( ! get_option( 'starbonus_show_on_shop' ) ) {
            add_option( 'starbonus_show_on_shop', false );
        }
    }
}
