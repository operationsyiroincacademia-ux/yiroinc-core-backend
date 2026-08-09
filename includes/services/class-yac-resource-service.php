<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resource_Service {

    /**
     * Table name.
     *
     * @return string
     */
    private static function table() {

        return YAC_Resources_Table::table_name();

    }

    /**
     * Create resource.
     *
     * @param array $data
     * @return int|false
     */
    public static function create(array $data) {

        global $wpdb;

        $inserted = $wpdb->insert(
            self::table(),
            $data
        );

        if (!$inserted) {
            return false;
        }

        return $wpdb->insert_id;

    }

    /**
     * Get all resources.
     *
     * @return array
     */
    public static function all() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM " . self::table() . " ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Get resource by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find($id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

    }

}