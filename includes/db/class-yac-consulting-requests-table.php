<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Consulting_Requests_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_consulting_requests';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            service_type VARCHAR(100) NOT NULL,

            organization_name VARCHAR(255) DEFAULT NULL,

            contact_person VARCHAR(255) NOT NULL,

            contact_email VARCHAR(255) NOT NULL,

            contact_phone VARCHAR(50) DEFAULT NULL,

            project_summary LONGTEXT NOT NULL,

            budget DECIMAL(12,2) DEFAULT NULL,

            preferred_date DATETIME DEFAULT NULL,

            status VARCHAR(30) NOT NULL DEFAULT 'pending',

            assigned_to BIGINT(20) UNSIGNED DEFAULT NULL,

            admin_note TEXT DEFAULT NULL,

            completed_at DATETIME DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),

            KEY status (status),

            KEY assigned_to (assigned_to),

            KEY service_type (service_type)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}