<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Notifications_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Notifications collection.
         *
         * GET /notifications
         */
        register_rest_route(
            $this->namespace,
            '/notifications',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_notifications'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Single notification.
         *
         * GET /notifications/{id}
         */
        register_rest_route(
            $this->namespace,
            '/notifications/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_notification'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );
        
                /**
         * Unread notification count.
         *
         * GET /notifications/unread-count
         */
        register_rest_route(
            $this->namespace,
            '/notifications/unread-count',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_unread_count'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );
        
        /**
         * Mark all notifications as read.
         *
         * PATCH /notifications/read-all
         */
        register_rest_route(
            $this->namespace,
            '/notifications/read-all',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'mark_all_as_read'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Mark notification as read.
         */
        register_rest_route(
            $this->namespace,
            '/notifications/(?P<id>\d+)/read',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'mark_as_read'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Dismiss notification.
         */
        register_rest_route(
            $this->namespace,
            '/notifications/(?P<id>\d+)/dismiss',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'dismiss_notification'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

    }

        /**
     * Get notifications.
     */
    public function get_notifications(WP_REST_Request $request) {

        global $wpdb;

        $table = YAC_Notifications_Table::table_name();

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

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page = max(1, (int) ($request->get_param('per_page') ?: 20));

        if ($per_page > 100) {
            $per_page = 100;
        }

        $offset = ($page - 1) * $per_page;
        
        $where = ["user_id = %d"];
        $params = [$user_id];
        
        if (!empty($status)) {
            $where[] = "status = %s";
            $params[] = $status;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $query = "
            SELECT *
            FROM " . YAC_Notifications_Table::table_name() . "
            WHERE {$where_sql}
            ORDER BY {$sort} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $notifications = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );
        
        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Notifications_Table::table_name() . "
            WHERE {$where_sql}
        ";
        
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                $count_query,
                ...array_slice($params, 0, count($params) - 2)
            )
        );

        return $this->success([
            'notifications' => $notifications,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);

    }

    /**
     * Get single notification.
     */
    public function get_notification(WP_REST_Request $request) {

        global $wpdb;
                $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $notification = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Notifications_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $request['id'],
                $user_id
            ),
            ARRAY_A
        );

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        return $this->success([
            'notification' => $notification,
        ]);

    }
    
        /**
     * Get unread notification count.
     */
    public function get_unread_count(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM " . YAC_Notifications_Table::table_name() . "
                 WHERE user_id = %d
                 AND is_read = 0
                 AND is_dismissed = 0",
                $user_id
            )
        );

        return $this->success([
            'count' => $count,
        ]);

    }
    
        /**
     * Mark all notifications as read.
     */
    public function mark_all_as_read(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . YAC_Notifications_Table::table_name() . "
                 SET is_read = 1,
                     read_at = %s
                 WHERE user_id = %d
                 AND is_read = 0",
                current_time('mysql'),
                $user_id
            )
        );

        if ($updated === false) {
            return $this->error('Unable to mark notifications as read.');
        }

        return $this->success([
            'message' => 'All notifications marked as read.',
        ]);

    }

    /**
     * Mark notification as read.
     */
    public function mark_as_read(WP_REST_Request $request) {

        global $wpdb;
        
        $user_id = YAC_Auth_Helper::user_id();
        
        $updated = $wpdb->update(
            YAC_Notifications_Table::table_name(),
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
                'user_id' => $user_id,
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to mark notification as read.');
        }

        return $this->success([
            'message' => 'Notification marked as read.',
        ]);

    }

    /**
     * Dismiss notification.
     */
    public function dismiss_notification(WP_REST_Request $request) {

        global $wpdb;
        
        $user_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Notifications_Table::table_name(),
            [
                'is_dismissed' => 1,
            ],
            [
                'id' => $request['id'],
                'user_id' => $user_id,
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to dismiss notification.');
        }

        return $this->success([
            'message' => 'Notification dismissed.',
        ]);

    }

}