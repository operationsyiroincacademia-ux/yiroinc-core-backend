<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Admin_Invitations_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_admin_invitations';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            email VARCHAR(255) NOT NULL,

            full_name VARCHAR(255) NOT NULL,

            invitation_token VARCHAR(255) NOT NULL,

            invited_by BIGINT(20) UNSIGNED NOT NULL,

            status VARCHAR(30) NOT NULL DEFAULT 'pending',

            expires_at DATETIME NOT NULL,

            accepted_at DATETIME DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY invitation_token (invitation_token),

            KEY email (email),

            KEY invited_by (invited_by),

            KEY status (status)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }
}