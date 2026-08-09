<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Notifications_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_notifications';

    /**
     * Create the database table.
     */
    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            sender_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

            related_type VARCHAR(50) DEFAULT NULL,

            related_id BIGINT(20) UNSIGNED DEFAULT NULL,

            title VARCHAR(255) NOT NULL,

            message TEXT NOT NULL,

            type VARCHAR(20) NOT NULL DEFAULT 'info',

            is_read TINYINT(1) NOT NULL DEFAULT 0,

            is_dismissed TINYINT(1) NOT NULL DEFAULT 0,

            action_url VARCHAR(255) DEFAULT NULL,

            delivery_channel VARCHAR(20) NOT NULL DEFAULT 'portal',

            read_at DATETIME DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),

            KEY sender_id (sender_id),

            KEY related_type (related_type),

            KEY related_id (related_id),

            KEY type (type),

            KEY is_read (is_read),

            KEY is_dismissed (is_dismissed),

            KEY delivery_channel (delivery_channel)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }

}