<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Search_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Global search.
         *
         * GET /search
         */
        register_rest_route(
            $this->namespace,
            '/search',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'search'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }
    
        /**
     * Global search.
     */
    public function search(WP_REST_Request $request) {

        global $wpdb;

        $query = sanitize_text_field($request->get_param('q'));

        if (empty($query)) {
            return $this->error('Search query is required.', 400);
        }

        $users = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    full_name,
                    email,
                    role
                 FROM " . YAC_Profiles_Table::table_name() . "
                 WHERE full_name LIKE %s
                    OR email LIKE %s
                 ORDER BY full_name ASC
                 LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ),
            ARRAY_A
        );
        
        $payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    payment_reference,
                    payment_status,
                    amount_paid
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE payment_reference LIKE %s
                 ORDER BY created_at DESC
                 LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%'
            ),
            ARRAY_A
        );

        $procurements = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    product_name,
                    status
                 FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE product_name LIKE %s
                 ORDER BY created_at DESC
                 LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%'
            ),
            ARRAY_A
        );
        
        $tutor_requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    subject,
                    status
                 FROM " . YAC_Tutor_Requests_Table::table_name() . "
                 WHERE subject LIKE %s
                 ORDER BY created_at DESC
                 LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%'
            ),
            ARRAY_A
        );

        $consulting_requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    topic,
                    status
                 FROM " . YAC_Consulting_Requests_Table::table_name() . "
                 WHERE topic LIKE %s
                 ORDER BY created_at DESC
                 LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%'
            ),
            ARRAY_A
        );

        $resources = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    title,
                    resource_type
                 FROM " . YAC_Resources_Table::table_name() . "
                 WHERE title LIKE %s
                 ORDER BY created_at DESC
                 LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%'
            ),
            ARRAY_A
        );
        
        return $this->success([
        'query'                 => $query,
        'users'                 => $users,
        'payments'              => $payments,
        'procurements'          => $procurements,
        'tutor_requests'        => $tutor_requests,
        'consulting_requests'   => $consulting_requests,
        'resources'             => $resources,
        ]);
    }
}