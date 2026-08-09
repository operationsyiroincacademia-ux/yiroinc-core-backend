<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Admin_Service {

    /**
     * Dashboard summary.
     *
     * @return array
     */
    public static function dashboard() {

        global $wpdb;

        return [

            'pending_payments' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Payments_Table::table_name() . "
                 WHERE payment_status = 'pending'"
            ),

            'pending_procurements' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'pending_tutor_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Tutor_Requests_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'pending_consulting_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Consulting_Requests_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'resources' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name()
            ),

            'users' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Profiles_Table::table_name()
            ),

        ];

    }

    /**
     * Recent activity.
     *
     * @param int $limit
     * @return array
     */
    public static function recent_activity($limit = 20) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Timeline_Table::table_name() . "
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

    }

    /**
     * Pending payments.
     *
     * @return array
     */
    public static function pending_payments() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Payments_Table::table_name() . "
             WHERE payment_status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Pending procurements.
     *
     * @return array
     */
    public static function pending_procurements() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Procurements_Table::table_name() . "
             WHERE status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Pending tutor requests.
     *
     * @return array
     */
    public static function pending_tutor_requests() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Tutor_Requests_Table::table_name() . "
             WHERE status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Pending consulting requests.
     *
     * @return array
     */
    public static function pending_consulting_requests() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Consulting_Requests_Table::table_name() . "
             WHERE status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

}