<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Consulting_Requests_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Consulting requests.
         *
         * POST /consulting-requests
         * GET  /consulting-requests
         */
        register_rest_route(
            $this->namespace,
            '/consulting-requests',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_request'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_requests'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Single consulting request.
         *
         * GET /consulting-requests/{id}
         */
        register_rest_route(
            $this->namespace,
            '/consulting-requests/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_request'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Assign consultant.
         *
         * PATCH /consulting-requests/{id}/assign
         */
        register_rest_route(
            $this->namespace,
            '/consulting-requests/(?P<id>\d+)/assign',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'assign_consultant'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );

        /**
         * Start consulting engagement.
         *
         * PATCH /consulting-requests/{id}/start
         */
        register_rest_route(
            $this->namespace,
            '/consulting-requests/(?P<id>\d+)/start',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'start_consulting'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );

        /**
         * Complete consulting engagement.
         *
         * PATCH /consulting-requests/{id}/complete
         */
        register_rest_route(
            $this->namespace,
            '/consulting-requests/(?P<id>\d+)/complete',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'complete_consulting'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );

    }

    /**
     * Create consulting request.
     */
    public function create_request(WP_REST_Request $request) {

        global $wpdb;

        $data = $request->get_json_params();

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $data['user_id'] = $user_id;

        $required = [
            'service_type',
            'contact_person',
            'contact_email',
            'project_summary',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $data = [
            'user_id'           => $data['user_id'],
            'service_type'      => sanitize_text_field($data['service_type']),
            'organization_name' => isset($data['organization_name']) ? sanitize_text_field($data['organization_name']) : null,
            'contact_person'    => sanitize_text_field($data['contact_person']),
            'contact_email'     => sanitize_email($data['contact_email']),
            'contact_phone'     => isset($data['contact_phone']) ? sanitize_text_field($data['contact_phone']) : null,
            'project_summary'   => sanitize_textarea_field($data['project_summary']),
            'budget'            => isset($data['budget']) ? $data['budget'] : null,
            'preferred_date'    => isset($data['preferred_date']) ? sanitize_text_field($data['preferred_date']) : null,
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $validation = YAC_Validation_Service::email($data['contact_email']);

        if (is_wp_error($validation)) {
            return $validation;
        }

        foreach (
            [
                'service_type'      => 100,
                'organization_name' => 255,
                'contact_email'     => 255,
                'contact_phone'     => 50,
            ] as $field => $length
        ) {
            if ($data[$field] !== null) {
                $validation = YAC_Validation_Service::max_length($data[$field], $length);

                if (is_wp_error($validation)) {
                    return $validation;
                }
            }
        }

        $validation = YAC_Validation_Service::max_length($data['project_summary'], 5000);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $validation = YAC_Validation_Service::max_length($data['contact_person'], 150);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!empty($data['budget'])) {

            $validation = YAC_Validation_Service::positive_number($data['budget']);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        if (!empty($data['preferred_date'])) {

            $validation = YAC_Validation_Service::date($data['preferred_date']);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $inserted = $wpdb->insert(
            YAC_Consulting_Requests_Table::table_name(),
            [
                'user_id'           => $data['user_id'],
                'service_type'      => $data['service_type'],
                'organization_name' => $data['organization_name'] ?? null,
                'contact_person'    => $data['contact_person'],
                'contact_email'     => $data['contact_email'],
                'contact_phone'     => $data['contact_phone'] ?? null,
                'project_summary'   => $data['project_summary'],
                'budget'            => $data['budget'] ?? null,
                'preferred_date'    => $data['preferred_date'] ?? null,
            ]
        );

        if (!$inserted) {
            return $this->error('Unable to create consulting request.');
        }

        $request_id = (int) $wpdb->insert_id;

        YAC_Notification_Service::notify_admins([
            'sender_id'    => $user_id,
            'related_type' => 'consulting_request',
            'related_id'   => $request_id,
            'title'        => 'New Consulting Request',
            'message'      => 'A new consulting request is awaiting review.',
            'type'         => 'info',
            'action_url'   => '/admin/consulting-requests/' . $request_id,
        ], $user_id);

        return $this->success([
            'request_id' => $request_id,
        ]);

    }

    /**
     * Get all consulting requests.
     */
    public function get_requests(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();
        
        $allowed_sort_columns = [
            'created_at',
            'status',
            'service_type',
            'preferred_date',
            'completed_at',
            'updated_at',
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
        $status = $status !== null ? sanitize_text_field($status) : null;
        
        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page = max(1, (int) ($request->get_param('per_page') ?: 20));

        if ($per_page > 100) {
            $per_page = 100;
        }

        $offset = ($page - 1) * $per_page;

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $is_admin = user_can($user_id, 'manage_options');

        $where = [];
        $params = [];

        if (!$is_admin) {
            $where[] = "user_id = %d";
            $params[] = $user_id;
        }
        
        if (!empty($status)) {
            $where[] = "status = %s";
            $params[] = $status;
        }
        
        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';
        
        $query = "
            SELECT *
            FROM " . YAC_Consulting_Requests_Table::table_name() . "
            {$where_sql}
            ORDER BY {$sort} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $requests = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );
        
        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Consulting_Requests_Table::table_name() . "
            {$where_sql}
        ";
        
        $count_params = array_slice($params, 0, count($params) - 2);
        $total = empty($count_params)
            ? (int) $wpdb->get_var($count_query)
            : (int) $wpdb->get_var($wpdb->prepare($count_query, ...$count_params));
        
        return $this->success([
            'requests' => $requests,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);
    }

    /**
     * Get single consulting request.
     */
    public function get_request(WP_REST_Request $request) {

        global $wpdb;
                $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $is_admin = user_can($user_id, 'manage_options');
        $query = "SELECT * FROM " . YAC_Consulting_Requests_Table::table_name() . " WHERE id = %d";
        $params = [$request['id']];

        if (!$is_admin) {
            $query .= " AND user_id = %d";
            $params[] = $user_id;
        }

        $consulting_request = $wpdb->get_row(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );

        if (!$consulting_request) {
            return $this->error('Consulting request not found.', 404);
        }

        return $this->success([
            'request' => $consulting_request,
        ]);

    }

        /**
     * Assign consultant.
     */
    public function assign_consultant(WP_REST_Request $request) {

        global $wpdb;

        $consulting_request = $this->get_request_record($request['id']);

        if (!$consulting_request) {
            return $this->error('Consulting request not found.', 404);
        }

        $data = $request->get_json_params();

        $validation = YAC_Validation_Service::required($data, 'assigned_to');

        if (is_wp_error($validation)) {
            return $validation;
        }

        $validation = YAC_Validation_Service::numeric($data['assigned_to']);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $assigned_to = (int) $data['assigned_to'];

        if ($assigned_to < 1 || !get_user_by('id', $assigned_to)) {
            return $this->error('assigned_to must be a valid user ID.', 400);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Consulting_Requests_Table::table_name(),
            [
                'status'      => 'assigned',
                'assigned_to' => $assigned_to,
                'assigned_by' => $admin_id,
                'assigned_at' => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to assign consultant.');
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $consulting_request['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'consultant_assigned',
            'title'        => 'Consultant Assigned',
            'description'  => 'A consultant has been assigned to your request.',
            'related_type' => 'consulting_request',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $consulting_request['user_id'],
            'related_type' => 'consulting_request',
            'related_id'   => $request['id'],
            'title'        => 'Consultant Assigned',
            'message'      => 'A consultant has been assigned to your request.',
            'type'         => 'info',
            'action_url'   => '/consulting-requests/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $consulting_request['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'consultant_assigned',
            'entity_type'  => 'consulting_request',
            'entity_id'    => $request['id'],
            'description'  => 'Consultant assigned.',
        ]);

        return $this->success([
            'message' => 'Consultant assigned successfully.',
        ]);

    }
    
       /**
     * Start consulting engagement.
     */
    public function start_consulting(WP_REST_Request $request) {

        global $wpdb;

        $consulting_request = $this->get_request_record($request['id']);

        if (!$consulting_request) {
            return $this->error('Consulting request not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Consulting_Requests_Table::table_name(),
            [
                'status'     => 'in_progress',
                'started_by' => $admin_id,
                'started_at' => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to start consulting engagement.');
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $consulting_request['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'consulting_started',
            'title'        => 'Consulting Started',
            'description'  => 'Your consulting engagement has started.',
            'related_type' => 'consulting_request',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $consulting_request['user_id'],
            'related_type' => 'consulting_request',
            'related_id'   => $request['id'],
            'title'        => 'Consulting Started',
            'message'      => 'Your consulting engagement has started.',
            'type'         => 'info',
            'action_url'   => '/consulting-requests/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $consulting_request['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'consulting_started',
            'entity_type'  => 'consulting_request',
            'entity_id'    => $request['id'],
            'description'  => 'Consulting engagement started.',
        ]);

        return $this->success([
            'message' => 'Consulting engagement started successfully.',
        ]);

    }

        /**
     * Complete consulting engagement.
     */
    public function complete_consulting(WP_REST_Request $request) {

        global $wpdb;

        $consulting_request = $this->get_request_record($request['id']);

        if (!$consulting_request) {
            return $this->error('Consulting request not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Consulting_Requests_Table::table_name(),
            [
                'status'       => 'completed',
                'completed_by' => $admin_id,
                'completed_at' => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to complete consulting engagement.');
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $consulting_request['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'consulting_completed',
            'title'        => 'Consulting Completed',
            'description'  => 'Your consulting engagement has been completed.',
            'related_type' => 'consulting_request',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $consulting_request['user_id'],
            'related_type' => 'consulting_request',
            'related_id'   => $request['id'],
            'title'        => 'Consulting Completed',
            'message'      => 'Your consulting engagement has been completed.',
            'type'         => 'success',
            'action_url'   => '/consulting-requests/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $consulting_request['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'consulting_completed',
            'entity_type'  => 'consulting_request',
            'entity_id'    => $request['id'],
            'description'  => 'Consulting engagement completed.',
        ]);

        return $this->success([
            'message' => 'Consulting engagement completed successfully.',
        ]);

    }

    /**
     * Get consulting request record.
     */
    private function get_request_record($request_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . YAC_Consulting_Requests_Table::table_name() . " WHERE id = %d",
                $request_id
            ),
            ARRAY_A
        );

    }

}
        
