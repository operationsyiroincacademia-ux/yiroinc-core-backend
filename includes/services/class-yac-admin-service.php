<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Admin_Service {

    /**
     * Complete admin dashboard payload.
     *
     * @return array
     */
    public static function dashboard_payload() {

        return [
            'summary'                     => self::dashboard(),
            'recent_activity'             => self::recent_activity(),
            'pending_payments'            => self::pending_payments(),
            'pending_tutor_requests'      => self::pending_tutor_requests(),
            'pending_consulting_requests' => self::pending_consulting_requests(),
            'pending_procurements'        => self::pending_procurements(),
        ];

    }

    /**
     * Dashboard summary.
     *
     * @return array
     */
    public static function dashboard() {

        global $wpdb;

        return [

            'users' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Profiles_Table::table_name()
            ),

            'resources' => [
                'total' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name()
                ),
                'free' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name() . "
                     WHERE price = 0"
                ),
                'paid' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name() . "
                     WHERE price > 0"
                ),
            ],

            'orders' => [
                'awaiting_payment' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'awaiting_payment'"
                ),
                'under_review' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'under_review'"
                ),
                'processing' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'processing'"
                ),
                'completed' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'completed'"
                ),
                'cancelled' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'cancelled'"
                ),
            ],

            'payments' => [
                'awaiting_verification' => self::awaiting_verification_payment_count(),
                'verified' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Payments_Table::table_name() . "
                     WHERE payment_status = 'verified'"
                ),
                'rejected' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Payments_Table::table_name() . "
                     WHERE payment_status = 'rejected'"
                ),
            ],

            'pending_tutor_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Tutor_Requests_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'pending_consulting_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Consulting_Requests_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'pending_procurements' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE status = 'pending'"
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
             WHERE payment_status IN ('pending', 'submitted')
             AND has_pop = 1
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Count payments awaiting admin verification.
     *
     * @return int
     */
    private static function awaiting_verification_payment_count() {

        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM " . YAC_Payments_Table::table_name() . "
             WHERE payment_status IN ('pending', 'submitted')
             AND has_pop = 1"
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
