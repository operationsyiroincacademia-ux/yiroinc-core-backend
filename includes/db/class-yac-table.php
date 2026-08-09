<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class YAC_Table {

    /**
     * Return the full table name.
     *
     * @return string
     */
    public static function table_name() {

        global $wpdb;

        return $wpdb->prefix . static::TABLE_NAME;

    }

    /**
     * Return the database charset and collation.
     *
     * @return string
     */
    protected static function charset_collate() {

        global $wpdb;

        return $wpdb->get_charset_collate();

    }

    /**
     * Execute dbDelta().
     *
     * @param string $sql
     */
    protected static function run_dbdelta($sql) {

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);

    }

    /**
     * Every table must implement this.
     */
    abstract public static function create();

}