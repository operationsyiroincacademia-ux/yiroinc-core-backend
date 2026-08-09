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

            woo_product_id BIGINT(20) UNSIGNED NOT NULL,

            woo_variation_id BIGINT(20) UNSIGNED DEFAULT NULL,

            product_name_snapshot VARCHAR(255) NOT NULL,

            sku_snapshot VARCHAR(100) DEFAULT NULL,

            quantity INT(11) UNSIGNED NOT NULL DEFAULT 1,

            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

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

            KEY woo_product_id (woo_product_id),

            KEY woo_variation_id (woo_variation_id),

            KEY order_status (order_status),

            KEY payment_status (payment_status),

            KEY fulfillment_status (fulfillment_status)

        ) {$charset_collate};";

        self::run_dbdelta($sql);
    }
}