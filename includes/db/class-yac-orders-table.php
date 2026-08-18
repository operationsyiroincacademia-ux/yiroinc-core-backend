<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Orders_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_orders';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            order_number VARCHAR(100) NOT NULL,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            order_source VARCHAR(50) NOT NULL DEFAULT 'woocommerce_product',

            woo_product_id BIGINT(20) UNSIGNED DEFAULT NULL,

            woo_variation_id BIGINT(20) UNSIGNED DEFAULT NULL,

            resource_id BIGINT(20) UNSIGNED DEFAULT NULL,

            product_name_snapshot VARCHAR(255) NOT NULL,

            sku_snapshot VARCHAR(100) DEFAULT NULL,

            quantity INT(11) UNSIGNED NOT NULL DEFAULT 1,

            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            currency VARCHAR(10) NOT NULL DEFAULT 'NGN',

            order_status VARCHAR(50) NOT NULL DEFAULT 'awaiting_payment',

            payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',

            fulfillment_status VARCHAR(50) NOT NULL DEFAULT 'not_started',

            customer_note TEXT DEFAULT NULL,

            admin_note TEXT DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY order_number (order_number),

            KEY user_id (user_id),

            KEY order_source (order_source),

            KEY woo_product_id (woo_product_id),

            KEY woo_variation_id (woo_variation_id),

            KEY resource_id (resource_id),

            KEY order_status (order_status),

            KEY payment_status (payment_status),

            KEY fulfillment_status (fulfillment_status)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

        self::normalize_existing_schema();
    }

    private static function normalize_existing_schema() {

        global $wpdb;

        $table_name = self::table_name();

        $wpdb->query(
            "ALTER TABLE {$table_name}
             MODIFY woo_product_id BIGINT(20) UNSIGNED DEFAULT NULL"
        );

        $wpdb->query(
            "UPDATE {$table_name}
             SET order_source = 'woocommerce_product'
             WHERE order_source IS NULL
             OR order_source = ''"
        );

        $currency = function_exists('get_woocommerce_currency')
            ? get_woocommerce_currency()
            : get_option('yac_bank_currency', 'NGN');

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name}
                 SET currency = %s
                 WHERE currency IS NULL
                 OR currency = ''",
                $currency
            )
        );
    }
}
