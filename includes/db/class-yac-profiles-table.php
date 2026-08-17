<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Profiles_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_profiles';

    /**
     * Create the database table.
     */
    public static function create() {

        $table_name = self::table_name();

        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            profile_type VARCHAR(50) NOT NULL,

            phone VARCHAR(50) DEFAULT NULL,

            organization_name VARCHAR(255) DEFAULT NULL,

            exam_type VARCHAR(100) DEFAULT NULL,

            exam_level VARCHAR(100) DEFAULT NULL,

            institution VARCHAR(255) DEFAULT NULL,

            area_of_interest VARCHAR(255) DEFAULT NULL,

            country VARCHAR(100) DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),

            KEY profile_type (profile_type)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }

}
