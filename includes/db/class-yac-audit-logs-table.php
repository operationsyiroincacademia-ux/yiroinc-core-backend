<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Audit_Logs_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_audit_logs';

    /**
     * Create the database table.
     */
    public static function create() {

        $table_name = self::table_name();

        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            actor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

            action VARCHAR(100) NOT NULL,

            entity_type VARCHAR(50) NOT NULL,

            entity_id BIGINT(20) UNSIGNED NOT NULL,

            old_values LONGTEXT DEFAULT NULL,

            new_values LONGTEXT DEFAULT NULL,

            ip_address VARCHAR(45) DEFAULT NULL,

            user_agent TEXT DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY actor_id (actor_id),

            KEY action (action),

            KEY entity_type (entity_type),

            KEY entity_id (entity_id),

            KEY created_at (created_at)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}