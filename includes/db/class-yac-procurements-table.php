<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Procurements_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_procurements';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            order_id BIGINT(20) UNSIGNED NOT NULL,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            procurement_reference VARCHAR(100) NOT NULL,

            supplier_name VARCHAR(255) DEFAULT NULL,

            tracking_number VARCHAR(255) DEFAULT NULL,

            courier VARCHAR(255) DEFAULT NULL,

            status VARCHAR(30) NOT NULL DEFAULT 'pending',

            expected_delivery_date DATETIME DEFAULT NULL,

            delivered_at DATETIME DEFAULT NULL,

            admin_note TEXT DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY procurement_reference (procurement_reference),

            KEY order_id (order_id),

            KEY user_id (user_id),

            KEY status (status)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}