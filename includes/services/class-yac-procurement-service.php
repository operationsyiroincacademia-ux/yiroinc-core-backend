<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Procurement_Service extends YAC_Base_Service {

    /**
     * Get all procurements for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

    }

}