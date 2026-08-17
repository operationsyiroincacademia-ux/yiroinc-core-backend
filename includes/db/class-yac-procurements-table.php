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

            ordered_by BIGINT(20) UNSIGNED DEFAULT NULL,

            ordered_at DATETIME DEFAULT NULL,

            shipped_by BIGINT(20) UNSIGNED DEFAULT NULL,

            shipped_at DATETIME DEFAULT NULL,

            delivered_by BIGINT(20) UNSIGNED DEFAULT NULL,

            delivered_at DATETIME DEFAULT NULL,

            admin_note TEXT DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY procurement_reference (procurement_reference),

            KEY order_id (order_id),

            KEY user_id (user_id),

            KEY status (status),

            KEY ordered_by (ordered_by),

            KEY shipped_by (shipped_by),

            KEY delivered_by (delivered_by)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}
