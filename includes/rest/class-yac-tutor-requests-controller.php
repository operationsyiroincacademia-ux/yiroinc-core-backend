<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Tutor_Requests_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Tutor requests.
         *
         * POST /tutor-requests
         * GET  /tutor-requests
         */
        register_rest_route(
            $this->namespace,
            '/tutor-requests',
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
         * Single tutor request.
         *
         * GET /tutor-requests/{id}
         */
        register_rest_route(
            $this->namespace,
            '/tutor-requests/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_request'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Match tutor.
         *
         * PATCH /tutor-requests/{id}/match
         */
        register_rest_route(
            $this->namespace,
            '/tutor-requests/(?P<id>\d+)/match',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'match_tutor'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        /**
         * Start session.
         *
         * PATCH /tutor-requests/{id}/start
         */
        register_rest_route(
            $this->namespace,
            '/tutor-requests/(?P<id>\d+)/start',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'start_session'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        /**
         * Complete session.
         *
         * PATCH /tutor-requests/{id}/complete
         */
        register_rest_route(
            $this->namespace,
            '/tutor-requests/(?P<id>\d+)/complete',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'complete_session'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

    /**
     * Create tutor request.
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
            'exam_type',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $data = [
            'user_id'            => $data['user_id'],
            'exam_type'          => sanitize_text_field($data['exam_type']),
            'exam_level'         => isset($data['exam_level']) ? sanitize_text_field($data['exam_level']) : null,
            'preferred_timezone' => isset($data['preferred_timezone']) ? sanitize_text_field($data['preferred_timezone']) : null,
            'preferred_language' => isset($data['preferred_language']) ? sanitize_text_field($data['preferred_language']) : null,
            'additional_notes'   => isset($data['additional_notes']) ? sanitize_textarea_field($data['additional_notes']) : null,
        ];

        $validation = YAC_Validation_Service::required($data, 'exam_type');

        if (is_wp_error($validation)) {
            return $validation;
        }

        $validation = YAC_Validation_Service::max_length($data['exam_type'], 100);

        if (is_wp_error($validation)) {
            return $validation;
        }

        foreach (['exam_level', 'preferred_timezone', 'preferred_language'] as $field) {
            if ($data[$field] !== null) {
                $validation = YAC_Validation_Service::max_length($data[$field], 100);

                if (is_wp_error($validation)) {
                    return $validation;
                }
            }
        }

        if ($data['additional_notes'] !== null) {
            $validation = YAC_Validation_Service::max_length($data['additional_notes'], 2000);

            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        $inserted = $wpdb->insert(
            YAC_Tutor_Requests_Table::table_name(),
            [
                'user_id'            => $data['user_id'],
                'exam_type'          => $data['exam_type'],
                'exam_level'         => $data['exam_level'] ?? null,
                'preferred_timezone' => $data['preferred_timezone'] ?? null,
                'preferred_language' => $data['preferred_language'] ?? null,
                'additional_notes'   => $data['additional_notes'] ?? null,
            ]
        );

        if (!$inserted) {
            return $this->error('Unable to create tutor request.');
        }

        $request_id = (int) $wpdb->insert_id;

        YAC_Notification_Service::notify_admins([
            'sender_id'    => $user_id,
            'related_type' => 'tutor_request',
            'related_id'   => $request_id,
            'title'        => 'New Tutor Request',
            'message'      => 'A new tutor request is awaiting review.',
            'type'         => 'info',
            'action_url'   => '/admin/tutor-requests/' . $request_id,
        ], $user_id);

        return $this->success([
            'request_id' => $request_id,
        ]);

    }

    /**
     * Get all tutor requests.
     */
    public function get_requests(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();
        
        $allowed_sort_columns = [
            'created_at',
            'status',
            'exam_type',
            'exam_level',
            'matched_at',
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
            FROM " . YAC_Tutor_Requests_Table::table_name() . "
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
            FROM " . YAC_Tutor_Requests_Table::table_name() . "
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
     * Get single tutor request.
     */
    public function get_request(WP_REST_Request $request) {

        global $wpdb;
                $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $is_admin = user_can($user_id, 'manage_options');
        $query = "SELECT * FROM " . YAC_Tutor_Requests_Table::table_name() . " WHERE id = %d";
        $params = [$request['id']];

        if (!$is_admin) {
            $query .= " AND user_id = %d";
            $params[] = $user_id;
        }

        $tutor_request = $wpdb->get_row(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );

        if (!$tutor_request) {
            return $this->error('Tutor request not found.', 404);
        }

        $tutor = !empty($tutor_request['assigned_tutor_id'])
            ? YAC_Tutor_Service::find((int) $tutor_request['assigned_tutor_id'])
            : null;

        return $this->success([
            'request' => $tutor_request,
            'tutor'   => YAC_Tutor_Service::format_candidate($tutor),
        ]);

    }
    
        /**
     * Match tutor.
     */
    public function match_tutor(WP_REST_Request $request) {

        global $wpdb;

        $tutor_request = $this->get_request_record($request['id']);

        if (!$tutor_request) {
            return $this->error('Tutor request not found.', 404);
        }

        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];

        $allowed_statuses = ['pending', 'matched', 'in_progress'];

        if (!in_array($tutor_request['status'], $allowed_statuses, true)) {
            return $this->error('Tutor can only be matched while the request is pending, matched, or in progress.', 409);
        }

        $tutor_id = isset($data['tutor_id'])
            ? $data['tutor_id']
            : ($data['assigned_tutor_id'] ?? null);

        $validation = YAC_Validation_Service::required(['tutor_id' => $tutor_id], 'tutor_id');

        if (is_wp_error($validation)) {
            return $validation;
        }

        $validation = YAC_Validation_Service::numeric($tutor_id);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $assigned_tutor_id = (int) $tutor_id;
        $tutor = YAC_Tutor_Service::find($assigned_tutor_id);
        $match_validation = YAC_Tutor_Service::validate_match($tutor, $tutor_request);

        if (is_wp_error($match_validation)) {
            return $this->error($match_validation->get_error_message(), 422);
        }

        $is_reassignment = !empty($tutor_request['assigned_tutor_id'])
            && (int) $tutor_request['assigned_tutor_id'] !== $assigned_tutor_id;
        $next_status = $tutor_request['status'] === 'in_progress' ? 'in_progress' : 'matched';

        if (!$is_reassignment && !empty($tutor_request['assigned_tutor_id']) && in_array($tutor_request['status'], ['matched', 'in_progress'], true)) {
            return $this->success([
                'message'  => 'Tutor is already matched to this request.',
                'tutor_id' => $assigned_tutor_id,
                'status'   => $next_status,
                'tutor'    => YAC_Tutor_Service::format_admin($tutor),
            ]);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Tutor_Requests_Table::table_name(),
            [
                'status'             => $next_status,
                'assigned_tutor_id'  => $assigned_tutor_id,
                'matched_by'         => $admin_id,
                'matched_at'         => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to match tutor.');
        }

        /**
         * Timeline.
         */
        $event = $is_reassignment ? 'tutor_reassigned' : 'tutor_matched';
        $title = $is_reassignment ? 'Tutor Reassigned' : 'Tutor Assigned';
        $description = $is_reassignment
            ? 'A tutor has been reassigned to your request.'
            : 'A tutor has been assigned to your request.';

        YAC_Timeline_Service::record([
            'user_id'      => $tutor_request['user_id'],
            'actor_id'     => $admin_id,
            'event'        => $event,
            'title'        => $title,
            'description'  => $description,
            'related_type' => 'tutor_request',
            'related_id'   => $request['id'],
            'metadata'     => [
                'tutor_id' => $assigned_tutor_id,
            ],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $tutor_request['user_id'],
            'related_type' => 'tutor_request',
            'related_id'   => $request['id'],
            'title'        => $title,
            'message'      => $description,
            'type'         => 'info',
            'action_url'   => '/tutor-requests/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $tutor_request['user_id'],
            'actor_id'     => $admin_id,
            'action'       => $event,
            'entity_type'  => 'tutor_request',
            'entity_id'    => $request['id'],
            'description'  => $description,
        ]);

        YAC_Email_Service::send_tutor_assignment($tutor_request, $tutor, $is_reassignment);

        return $this->success([
            'message'  => $is_reassignment ? 'Tutor reassigned successfully.' : 'Tutor matched successfully.',
            'tutor_id' => $assigned_tutor_id,
            'status'   => $next_status,
            'tutor'    => YAC_Tutor_Service::format_admin($tutor),
        ]);

    }

        /**
     * Start tutoring session.
     */
    public function start_session(WP_REST_Request $request) {

        global $wpdb;

        $tutor_request = $this->get_request_record($request['id']);

        if (!$tutor_request) {
            return $this->error('Tutor request not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Tutor_Requests_Table::table_name(),
            [
                'status'             => 'in_progress',
                'session_started_by' => $admin_id,
                'session_started_at' => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to start session.');
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $tutor_request['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'tutor_session_started',
            'title'        => 'Tutoring Session Started',
            'description'  => 'Your tutoring session has started.',
            'related_type' => 'tutor_request',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $tutor_request['user_id'],
            'related_type' => 'tutor_request',
            'related_id'   => $request['id'],
            'title'        => 'Tutoring Session Started',
            'message'      => 'Your tutoring session has started.',
            'type'         => 'info',
            'action_url'   => '/tutor-requests/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $tutor_request['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'tutor_session_started',
            'entity_type'  => 'tutor_request',
            'entity_id'    => $request['id'],
            'description'  => 'Tutoring session started.',
        ]);

        return $this->success([
            'message' => 'Tutoring session started successfully.',
        ]);

    }

        /**
     * Complete tutoring session.
     */
    public function complete_session(WP_REST_Request $request) {

        global $wpdb;

        $tutor_request = $this->get_request_record($request['id']);

        if (!$tutor_request) {
            return $this->error('Tutor request not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Tutor_Requests_Table::table_name(),
            [
                'status'        => 'completed',
                'completed_by'  => $admin_id,
                'completed_at'  => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to complete session.');
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $tutor_request['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'tutor_session_completed',
            'title'        => 'Tutoring Session Completed',
            'description'  => 'Your tutoring session has been completed.',
            'related_type' => 'tutor_request',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $tutor_request['user_id'],
            'related_type' => 'tutor_request',
            'related_id'   => $request['id'],
            'title'        => 'Tutoring Session Completed',
            'message'      => 'Your tutoring session has been completed.',
            'type'         => 'success',
            'action_url'   => '/tutor-requests/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $tutor_request['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'tutor_session_completed',
            'entity_type'  => 'tutor_request',
            'entity_id'    => $request['id'],
            'description'  => 'Tutoring session completed.',
        ]);

        return $this->success([
            'message' => 'Tutoring session completed successfully.',
        ]);

    }
    
    /**
     * Get tutor request record.
     */
    private function get_request_record($request_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . YAC_Tutor_Requests_Table::table_name() . " WHERE id = %d",
                $request_id
            ),
            ARRAY_A
        );

    }

}
