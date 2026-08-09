<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Tutor_Requests_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_tutor_requests';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            exam_type VARCHAR(100) NOT NULL,

            exam_level VARCHAR(100) DEFAULT NULL,

            preferred_timezone VARCHAR(100) DEFAULT NULL,

            preferred_language VARCHAR(100) DEFAULT NULL,

            additional_notes TEXT DEFAULT NULL,

            status VARCHAR(30) NOT NULL DEFAULT 'pending',

            assigned_tutor_id BIGINT(20) UNSIGNED DEFAULT NULL,

            matched_at DATETIME DEFAULT NULL,

            completed_at DATETIME DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),

            KEY status (status),

            KEY assigned_tutor_id (assigned_tutor_id),

            KEY exam_type (exam_type)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}