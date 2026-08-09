<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Payments_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_payments';

    /**
     * Create the database table.
     */
    public static function create() {

        $table_name = self::table_name();

        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            payment_reference VARCHAR(100) NOT NULL,

            order_id BIGINT(20) UNSIGNED NOT NULL,

            user_id BIGINT(20) UNSIGNED NOT NULL,

            payment_method VARCHAR(50) NOT NULL DEFAULT 'bank_transfer',

            amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            currency VARCHAR(10) NOT NULL DEFAULT 'NGN',

            has_pop TINYINT(1) NOT NULL DEFAULT 0,

            payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',

            verified_by BIGINT(20) UNSIGNED DEFAULT NULL,

            user_note TEXT DEFAULT NULL,

            admin_note TEXT DEFAULT NULL,

            submitted_at DATETIME DEFAULT NULL,

            verified_at DATETIME DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY payment_reference (payment_reference),

            KEY order_id (order_id),

            KEY user_id (user_id),

            KEY payment_status (payment_status),

            KEY payment_method (payment_method),

            KEY verified_by (verified_by)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

    }

}