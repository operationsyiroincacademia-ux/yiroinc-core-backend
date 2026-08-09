<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class YAC_Base_Service {

    /**
     * Insert a record.
     *
     * @param string $table
     * @param array  $data
     * @return int|false
     */
    protected static function insert($table, array $data) {

        global $wpdb;

        $inserted = $wpdb->insert($table, $data);

        if (!$inserted) {
            return false;
        }

        return (int) $wpdb->insert_id;

    }

}