<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Support_Tickets_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_support_tickets';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            subject VARCHAR(255) NOT NULL,

            category VARCHAR(100) NOT NULL,

            status VARCHAR(30) NOT NULL DEFAULT 'open',

            priority VARCHAR(20) NOT NULL DEFAULT 'medium',

            last_message_at DATETIME NOT NULL,

            last_message_by BIGINT(20) UNSIGNED DEFAULT NULL,

            resolved_by BIGINT(20) UNSIGNED DEFAULT NULL,

            resolved_at DATETIME DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),

            KEY status (status),

            KEY category (category),

            KEY priority (priority),

            KEY last_message_at (last_message_at)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

        self::normalize_legacy_rows();

    }

    private static function normalize_legacy_rows() {

        global $wpdb;

        $table_name = self::table_name();

        $wpdb->query(
            "UPDATE {$table_name}
             SET status = 'open'
             WHERE status IN ('awaiting_admin', 'awaiting_user')"
        );

        $wpdb->query(
            "UPDATE {$table_name}
             SET priority = 'medium'
             WHERE priority IS NULL OR priority = ''"
        );

    }
}
