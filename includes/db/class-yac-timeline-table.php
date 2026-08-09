<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Timeline_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_timeline';

    /**
     * Create the database table.
     */
    public static function create() {

        $table_name = self::table_name();

        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            actor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

            event VARCHAR(100) NOT NULL,

            title VARCHAR(255) NOT NULL,

            description TEXT DEFAULT NULL,

            related_type VARCHAR(50) DEFAULT NULL,

            related_id BIGINT(20) UNSIGNED DEFAULT NULL,

            metadata LONGTEXT DEFAULT NULL,

            visibility VARCHAR(20) NOT NULL DEFAULT 'user',

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),

            KEY actor_id (actor_id),

            KEY event (event),

            KEY related_type (related_type),

            KEY related_id (related_id),

            KEY visibility (visibility)

        ) {$charset_collate};";

        self::run_dbdelta($sql);
    }
}