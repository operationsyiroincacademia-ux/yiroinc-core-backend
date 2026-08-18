<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resource_Entitlements_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_resource_entitlements';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            resource_id BIGINT(20) UNSIGNED NOT NULL,

            order_id BIGINT(20) UNSIGNED NOT NULL,

            payment_id BIGINT(20) UNSIGNED NOT NULL,

            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY user_resource (user_id, resource_id),

            KEY user_id (user_id),

            KEY resource_id (resource_id),

            KEY order_id (order_id),

            KEY payment_id (payment_id)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}
