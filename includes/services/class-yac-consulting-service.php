<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Consulting_Service extends YAC_Base_Service {

    /**
     * Get all consulting requests for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Consulting_Requests_Table::table_name() . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

    }

}