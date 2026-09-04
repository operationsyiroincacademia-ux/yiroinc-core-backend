<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Support_Messages_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_support_messages';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            ticket_id BIGINT(20) UNSIGNED NOT NULL,

            sender_user_id BIGINT(20) UNSIGNED NOT NULL,

            sender_type VARCHAR(20) NOT NULL,

            message LONGTEXT NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY ticket_id (ticket_id),

            KEY sender_user_id (sender_user_id),

            KEY sender_type (sender_type),

            KEY created_at (created_at)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}
