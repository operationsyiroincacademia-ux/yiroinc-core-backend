<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Audit_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Audit logs.
         *
         * GET /audit-logs
         */
        register_rest_route(
            $this->namespace,
            '/audit-logs',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_logs'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        /**
         * Single audit log.
         *
         * GET /audit-logs/{id}
         */
        register_rest_route(
            $this->namespace,
            '/audit-logs/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_log'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }
    
        /**
     * Get all audit logs.
     */
    public function get_logs(WP_REST_Request $request) {

        global $wpdb;
        
        
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, (int) $request->get_param('per_page'));
        
        if ($per_page > 100) {
            $per_page = 100;
        }
        
        $offset = ($page - 1) * $per_page;
        
        $allowed_sort_columns = [
            'created_at',
            'action',
            'entity_type',
            'actor_id',
        ];
        
        $sort = $request->get_param('sort') ?: 'created_at';
        
        if (!in_array($sort, $allowed_sort_columns, true)) {
            $sort = 'created_at';
        }
        
        $order = strtoupper($request->get_param('order') ?: 'DESC');
        
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }
        
        $action = $request->get_param('action');
        $entity_type = $request->get_param('entity_type');
        
        $where = [];
        $params = [];
        
        if (!empty($action)) {
            $where[] = "action = %s";
            $params[] = $action;
        }
        
        if (!empty($entity_type)) {
            $where[] = "entity_type = %s";
            $params[] = $entity_type;
        }
        
        $where_sql = '';
        
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }
        
        $query = "
            SELECT *
            FROM " . YAC_Audit_Logs_Table::table_name() . "
            {$where_sql}
            ORDER BY {$sort} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $logs = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );

        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Audit_Logs_Table::table_name() . "
            {$where_sql}
        ";
        
        $total = empty($where)
            ? (int) $wpdb->get_var($count_query)
            : (int) $wpdb->get_var(
                $wpdb->prepare(
                    $count_query,
                    ...array_slice($params, 0, count($params) - 2)
                )
            );
        
        return $this->success([
            'logs' => $logs,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);

    }

    /**
     * Get single audit log.
     */
    public function get_log(WP_REST_Request $request) {

        global $wpdb;

        $log = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Audit_Logs_Table::table_name() . "
                 WHERE id = %d",
                $request['id']
            ),
            ARRAY_A
        );

        if (!$log) {
            return $this->error('Audit log not found.', 404);
        }

        return $this->success([
            'log' => $log,
        ]);

    }
    
}