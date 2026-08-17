<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resources_Table {

    /**
     * Table name.
     *
     * @return string
     */
    public static function table_name() {

        global $wpdb;

        return $wpdb->prefix . 'yiroinc_resources';

    }

    /**
     * Create table.
     */
    public static function create() {

        global $wpdb;

        $table = self::table_name();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            title VARCHAR(255) NOT NULL,
            description TEXT NULL,

            category VARCHAR(100) NULL,

            source_type VARCHAR(20) NOT NULL DEFAULT 'file',

            file_id BIGINT UNSIGNED NULL,

            external_url TEXT NULL,

            profile_type VARCHAR(50) NULL,
            exam_type VARCHAR(100) NULL,

            is_public TINYINT(1) NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY category (category),
            KEY source_type (source_type),
            KEY profile_type (profile_type),
            KEY exam_type (exam_type),
            KEY file_id (file_id)

        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);

    }

}
