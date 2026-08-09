<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Files_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_files';

    /**
     * Create the database table.
     */
    public static function create() {

        $table_name = self::table_name();

        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            related_type VARCHAR(50) NOT NULL,

            related_id BIGINT(20) UNSIGNED NOT NULL,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            file_name VARCHAR(255) NOT NULL,

            original_name VARCHAR(255) NOT NULL,

            file_path TEXT NOT NULL,

            mime_type VARCHAR(100) NOT NULL,

            file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

            file_type VARCHAR(100) NOT NULL,

            visibility VARCHAR(20) NOT NULL DEFAULT 'private',

            uploaded_by BIGINT(20) UNSIGNED NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY related_type (related_type),

            KEY related_id (related_id),

            KEY user_id (user_id),

            KEY file_type (file_type),

            KEY visibility (visibility),

            KEY uploaded_by (uploaded_by)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}