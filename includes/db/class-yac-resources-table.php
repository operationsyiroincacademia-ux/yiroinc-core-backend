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

            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            currency VARCHAR(10) NOT NULL DEFAULT 'NGN',

            profile_type VARCHAR(50) NULL,
            exam_type VARCHAR(100) NULL,
            exam_level VARCHAR(100) NULL,

            is_public TINYINT(1) NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY category (category),
            KEY source_type (source_type),
            KEY profile_type (profile_type),
            KEY exam_type (exam_type),
            KEY exam_level (exam_level),
            KEY file_id (file_id)

        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);

        self::remove_woocommerce_resource_mapping();

    }

    private static function remove_woocommerce_resource_mapping() {

        global $wpdb;

        $table = self::table_name();

        $index_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = %s
                 AND INDEX_NAME = %s",
                $table,
                'woo_product_id'
            )
        );

        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = %s
                 AND COLUMN_NAME = %s",
                $table,
                'woo_product_id'
            )
        );

        if ($column_exists) {
            self::migrate_legacy_woocommerce_prices();
        }

        if ($index_exists) {
            $wpdb->query("ALTER TABLE {$table} DROP INDEX woo_product_id");
        }

        if ($column_exists) {
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN woo_product_id");
        }

    }

    private static function migrate_legacy_woocommerce_prices() {

        if (!function_exists('wc_get_product')) {
            return;
        }

        global $wpdb;

        $table = self::table_name();
        $currency = function_exists('get_woocommerce_currency')
            ? get_woocommerce_currency()
            : get_option('yac_bank_currency', 'NGN');

        $resources = $wpdb->get_results(
            "SELECT id, woo_product_id
             FROM {$table}
             WHERE woo_product_id IS NOT NULL
             AND woo_product_id > 0",
            ARRAY_A
        );

        foreach ($resources as $resource) {
            $product = wc_get_product((int) $resource['woo_product_id']);

            if (!$product || !$product->exists()) {
                self::hide_unpriced_legacy_resource((int) $resource['id']);
                continue;
            }

            $price = (float) $product->get_price();

            if ($price <= 0) {
                self::hide_unpriced_legacy_resource((int) $resource['id']);
                continue;
            }

            $wpdb->update(
                $table,
                [
                    'price'    => $price,
                    'currency' => $currency,
                ],
                [
                    'id' => (int) $resource['id'],
                ],
                [
                    '%f',
                    '%s',
                ],
                [
                    '%d',
                ]
            );
        }

    }

    private static function hide_unpriced_legacy_resource($resource_id) {

        global $wpdb;

        $wpdb->update(
            self::table_name(),
            [
                'is_public'    => 0,
                'profile_type' => null,
                'exam_type'    => null,
            ],
            [
                'id' => (int) $resource_id,
            ],
            [
                '%d',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

    }

}
