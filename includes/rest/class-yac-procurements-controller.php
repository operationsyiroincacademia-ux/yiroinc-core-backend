<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Procurements_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Procurements.
         *
         * POST /procurements
         * GET  /procurements
         */
        register_rest_route(
            $this->namespace,
            '/procurements',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_procurement'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_procurements'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Single procurement.
         *
         * GET /procurements/{id}
         */
        register_rest_route(
            $this->namespace,
            '/procurements/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_procurement'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Mark procurement as ordered.
         *
         * PATCH /procurements/{id}/ordered
         */
        register_rest_route(
            $this->namespace,
            '/procurements/(?P<id>\d+)/ordered',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'mark_ordered'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        /**
         * Mark procurement as shipped.
         *
         * PATCH /procurements/{id}/shipped
         */
        register_rest_route(
            $this->namespace,
            '/procurements/(?P<id>\d+)/shipped',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'mark_shipped'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        /**
         * Mark procurement as delivered.
         *
         * PATCH /procurements/{id}/delivered
         */
        register_rest_route(
            $this->namespace,
            '/procurements/(?P<id>\d+)/delivered',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'mark_delivered'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

    /**
     * Create procurement.
     */
    public function create_procurement(WP_REST_Request $request) {

        global $wpdb;

        $data = $request->get_json_params();

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $data['user_id'] = $user_id;

        $required = [
            'order_id',
            'procurement_reference',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $validation = YAC_Validation_Service::numeric($data['order_id']);

        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $data['order_id'],
                $user_id
            ),
            ARRAY_A
        );
        
        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        $validation = YAC_Validation_Service::max_length($data['procurement_reference'], 100);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!empty($data['expected_delivery_date'])) {

            $validation = YAC_Validation_Service::date($data['expected_delivery_date']);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $inserted = $wpdb->insert(
            YAC_Procurements_Table::table_name(),
            [
                'order_id'               => $data['order_id'],
                'user_id'                => $data['user_id'],
                'procurement_reference'  => $data['procurement_reference'],
                'supplier_name'          => $data['supplier_name'] ?? null,
                'tracking_number'        => $data['tracking_number'] ?? null,
                'courier'                => $data['courier'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'admin_note'             => $data['admin_note'] ?? null,
            ]
        );

        if (!$inserted) {
            return $this->error('Unable to create procurement.');
        }

        return $this->success([
            'procurement_id' => $wpdb->insert_id,
        ]);

    }

    /**
     * Get all procurements.
     */
    public function get_procurements(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();
        
        $allowed_sort_columns = [
            'created_at',
            'status',
            'expected_delivery_date',
        ];
        
        $sort = $request->get_param('sort') ?: 'created_at';
        
        if (!in_array($sort, $allowed_sort_columns, true)) {
            $sort = 'created_at';
        }
        
        $order = strtoupper($request->get_param('order') ?: 'DESC');
        
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }
        
        $status = $request->get_param('status');
        
        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page = max(1, (int) ($request->get_param('per_page') ?: 20));

        if ($per_page > 100) {
            $per_page = 100;
        }

        $offset = ($page - 1) * $per_page;

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }
        
        $where = ["user_id = %d"];
        $params = [$user_id];
        
        if (!empty($status)) {
            $where[] = "status = %s";
            $params[] = $status;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $query = "
            SELECT *
            FROM " . YAC_Procurements_Table::table_name() . "
            WHERE {$where_sql}
            ORDER BY {$sort} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $procurements = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );
        
        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Procurements_Table::table_name() . "
            WHERE {$where_sql}
        ";
        
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                $count_query,
                ...array_slice($params, 0, count($params) - 2)
            )
        );

        return $this->success([
            'procurements' => $procurements,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);
    }

    /**
     * Get single procurement.
     */
    public function get_procurement(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $procurement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . YAC_Procurements_Table::table_name() . " WHERE id = %d AND user_id = %d",
                $request['id'],
                $user_id
            ),
            ARRAY_A
        );

        if (!$procurement) {
            return $this->error('Procurement not found.', 404);
        }

        return $this->success([
            'procurement' => $procurement,
        ]);

    }
    
        /**
     * Mark procurement as ordered.
     */
    public function mark_ordered(WP_REST_Request $request) {

        global $wpdb;

        $procurement_id = absint($request['id']);

        $procurement = $this->get_procurement_record($procurement_id);

        if (!$procurement) {
            return $this->error('Procurement not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        if (!$admin_id) {
            return $this->error('Unable to identify admin user.', 401);
        }

        $updated = $wpdb->update(
            YAC_Procurements_Table::table_name(),
            [
                'status'     => 'ordered',
                'ordered_by' => $admin_id,
                'ordered_at' => current_time('mysql'),
            ],
            [
                'id' => $procurement_id,
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to update procurement.');
        }

        YAC_Timeline_Service::record([
            'user_id'      => $procurement['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'procurement_ordered',
            'title'        => 'Procurement Ordered',
            'description'  => 'Your procurement request has been ordered.',
            'related_type' => 'procurement',
            'related_id'   => $procurement_id,
            'visibility'   => 'user',
        ]);

        YAC_Notification_Service::create([
            'user_id'      => $procurement['user_id'],
            'related_type' => 'procurement',
            'related_id'   => $procurement_id,
            'title'        => 'Procurement Ordered',
            'message'      => 'Your procurement request has been ordered.',
            'type'         => 'info',
            'action_url'   => '/procurements/' . $procurement_id,
        ]);

        YAC_Audit_Service::record([
            'user_id'      => $procurement['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'procurement_ordered',
            'entity_type'  => 'procurement',
            'entity_id'    => $procurement_id,
            'description'  => 'Procurement marked as ordered.',
        ]);

        return $this->success([
            'message' => 'Procurement marked as ordered.',
        ]);

    }

    /**
     * Mark procurement as shipped.
     */
    public function mark_shipped(WP_REST_Request $request) {

        global $wpdb;

        $procurement_id = absint($request['id']);

        $procurement = $this->get_procurement_record($procurement_id);

        if (!$procurement) {
            return $this->error('Procurement not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        if (!$admin_id) {
            return $this->error('Unable to identify admin user.', 401);
        }

        $updated = $wpdb->update(
            YAC_Procurements_Table::table_name(),
            [
                'status'     => 'shipped',
                'shipped_by' => $admin_id,
                'shipped_at' => current_time('mysql'),
            ],
            [
                'id' => $procurement_id,
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to update procurement.');
        }

        YAC_Timeline_Service::record([
            'user_id'      => $procurement['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'procurement_shipped',
            'title'        => 'Procurement Shipped',
            'description'  => 'Your procurement has been shipped.',
            'related_type' => 'procurement',
            'related_id'   => $procurement_id,
            'visibility'   => 'user',
        ]);

        YAC_Notification_Service::create([
            'user_id'      => $procurement['user_id'],
            'related_type' => 'procurement',
            'related_id'   => $procurement_id,
            'title'        => 'Procurement Shipped',
            'message'      => 'Your procurement has been shipped.',
            'type'         => 'info',
            'action_url'   => '/procurements/' . $procurement_id,
        ]);

        YAC_Audit_Service::record([
            'user_id'      => $procurement['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'procurement_shipped',
            'entity_type'  => 'procurement',
            'entity_id'    => $procurement_id,
            'description'  => 'Procurement marked as shipped.',
        ]);

        return $this->success([
            'message' => 'Procurement marked as shipped.',
        ]);

    }

    /**
     * Mark procurement as delivered.
     */
    public function mark_delivered(WP_REST_Request $request) {

        global $wpdb;

        $procurement_id = absint($request['id']);

        $procurement = $this->get_procurement_record($procurement_id);

        if (!$procurement) {
            return $this->error('Procurement not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        if (!$admin_id) {
            return $this->error('Unable to identify admin user.', 401);
        }

        $updated = $wpdb->update(
            YAC_Procurements_Table::table_name(),
            [
                'status'       => 'delivered',
                'delivered_by' => $admin_id,
                'delivered_at' => current_time('mysql'),
            ],
            [
                'id' => $procurement_id,
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to update procurement.');
        }

        YAC_Timeline_Service::record([
            'user_id'      => $procurement['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'procurement_delivered',
            'title'        => 'Procurement Delivered',
            'description'  => 'Your procurement has been delivered.',
            'related_type' => 'procurement',
            'related_id'   => $procurement_id,
            'visibility'   => 'user',
        ]);

        YAC_Notification_Service::create([
            'user_id'      => $procurement['user_id'],
            'related_type' => 'procurement',
            'related_id'   => $procurement_id,
            'title'        => 'Procurement Delivered',
            'message'      => 'Your procurement has been delivered.',
            'type'         => 'success',
            'action_url'   => '/procurements/' . $procurement_id,
        ]);

        YAC_Audit_Service::record([
            'user_id'      => $procurement['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'procurement_delivered',
            'entity_type'  => 'procurement',
            'entity_id'    => $procurement_id,
            'description'  => 'Procurement marked as delivered.',
        ]);

        return $this->success([
            'message' => 'Procurement marked as delivered.',
        ]);

    }

    /**
     * Get procurement record.
     */
    private function get_procurement_record($procurement_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE id = %d",
                $procurement_id
            ),
            ARRAY_A
        );

    }

}