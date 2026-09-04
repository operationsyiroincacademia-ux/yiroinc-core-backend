<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Support_Controller extends YAC_REST_Controller {

    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/support/tickets',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_tickets'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_ticket'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/support/tickets/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_ticket'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/support/tickets/(?P<id>\d+)/messages',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'add_message'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/support/tickets/(?P<id>\d+)/resolve',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'resolve_ticket'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/support/tickets/(?P<id>\d+)/reopen',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'reopen_ticket'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/support/tickets',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'admin_get_tickets'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/support/tickets/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'admin_get_ticket'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/support/tickets/(?P<id>\d+)/messages',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'admin_add_message'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/support/tickets/(?P<id>\d+)/status',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'admin_change_status'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

    public function get_tickets(WP_REST_Request $request) {

        $result = YAC_Support_Service::user_tickets(
            YAC_Auth_Helper::user_id(),
            $this->list_args($request)
        );

        return $this->response_from_result($result);

    }

    public function create_ticket(WP_REST_Request $request) {

        $result = YAC_Support_Service::create_ticket(
            YAC_Auth_Helper::user_id(),
            $this->request_data($request),
            $this->attachment($request)
        );

        return $this->response_from_result($result, 201);

    }

    public function get_ticket(WP_REST_Request $request) {

        $result = YAC_Support_Service::get_ticket_for_user(
            $request['id'],
            YAC_Auth_Helper::user_id()
        );

        if (!$result) {
            return $this->error('Support ticket not found.', 404);
        }

        return $this->success($result);

    }

    public function add_message(WP_REST_Request $request) {

        $result = YAC_Support_Service::add_user_reply(
            $request['id'],
            YAC_Auth_Helper::user_id(),
            $this->request_data($request),
            $this->attachment($request)
        );

        return $this->response_from_result($result, 201);

    }

    public function resolve_ticket(WP_REST_Request $request) {

        return $this->error(
            'Support ticket status is managed by support.',
            403,
            'yac_support_status_admin_only'
        );

    }

    public function reopen_ticket(WP_REST_Request $request) {

        return $this->error(
            'Support ticket status is managed by support.',
            403,
            'yac_support_status_admin_only'
        );

    }

    public function admin_get_tickets(WP_REST_Request $request) {

        $result = YAC_Support_Service::admin_tickets($this->list_args($request));

        return $this->response_from_result($result);

    }

    public function admin_get_ticket(WP_REST_Request $request) {

        $result = YAC_Support_Service::admin_ticket($request['id']);

        if (!$result) {
            return $this->error('Support ticket not found.', 404);
        }

        return $this->success($result);

    }

    public function admin_add_message(WP_REST_Request $request) {

        $result = YAC_Support_Service::add_admin_reply(
            $request['id'],
            YAC_Auth_Helper::user_id(),
            $this->request_data($request),
            $this->attachment($request)
        );

        return $this->response_from_result($result, 201);

    }

    public function admin_change_status(WP_REST_Request $request) {

        $data = $this->request_data($request);
        $result = YAC_Support_Service::admin_change_status(
            $request['id'],
            $data['status'] ?? '',
            YAC_Auth_Helper::user_id()
        );

        return $this->response_from_result($result);

    }

    private function list_args(WP_REST_Request $request) {

        return [
            'status'   => $request->get_param('status'),
            'category' => $request->get_param('category'),
            'page'     => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
        ];

    }

    private function request_data(WP_REST_Request $request) {

        $json = $request->get_json_params();

        if (is_array($json) && !empty($json)) {
            return $json;
        }

        return $request->get_params();

    }

    private function attachment(WP_REST_Request $request) {

        $files = $request->get_file_params();

        return $files['attachment'] ?? null;

    }

    private function response_from_result($result, $success_status = 200) {

        if (is_wp_error($result)) {
            return $this->error(
                $result->get_error_message(),
                (int) ($result->get_error_data()['status'] ?? 400),
                $result->get_error_code()
            );
        }

        return $this->success($result, $success_status);

    }
}
