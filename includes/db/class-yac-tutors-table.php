<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Tutors_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_tutors';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            name VARCHAR(255) NOT NULL,

            email VARCHAR(255) DEFAULT NULL,

            whatsapp_number VARCHAR(50) NOT NULL,

            exam_expertise VARCHAR(50) NOT NULL,

            levels VARCHAR(255) NOT NULL,

            timezone VARCHAR(100) DEFAULT NULL,

            availability VARCHAR(20) NOT NULL DEFAULT 'available',

            bio TEXT DEFAULT NULL,

            status VARCHAR(20) NOT NULL DEFAULT 'active',

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY status (status),

            KEY availability (availability),

            KEY exam_expertise (exam_expertise),

            KEY name (name),

            KEY email (email)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}
