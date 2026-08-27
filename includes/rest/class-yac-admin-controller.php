<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Admin_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/admin/dashboard',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'dashboard'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/activity',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'activity'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/users',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'users'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/users/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'user'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/users/(?P<id>\d+)/close',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'close_user'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/payments',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'payments'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/payments/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'payment'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/orders',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'orders'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/orders/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'order'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/procurements',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'procurements'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/procurements/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'procurement'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/tutor-requests',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'tutor_requests'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/tutor-requests/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'tutor_request'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/consulting-requests',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'consulting_requests'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/consulting-requests/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'consulting_request'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

    /**
     * Dashboard summary.
     */
    public function dashboard(WP_REST_Request $request) {

        return $this->success(YAC_Admin_Service::dashboard_payload());

    }

    /**
     * Recent activity.
     */
    public function activity(WP_REST_Request $request) {

        return $this->success([
            'activity' => YAC_Admin_Service::recent_activity(),
        ]);

    }

    /**
     * Admin customer directory.
     */
    public function users(WP_REST_Request $request) {

        $result = YAC_Admin_Service::users([
            'profile_type' => $request->get_param('profile_type') ?: $request->get_param('type'),
            'search'       => $request->get_param('search') ?: $request->get_param('q'),
            'page'         => $request->get_param('page'),
            'per_page'     => $request->get_param('per_page'),
        ]);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Admin customer detail.
     */
    public function user(WP_REST_Request $request) {

        $result = YAC_Admin_Service::user_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    /**
     * Close a customer account.
     */
    public function close_user(WP_REST_Request $request) {

        $result = YAC_Admin_Service::close_user_account(
            $request['id'],
            $request->get_json_params()
        );

        if (is_wp_error($result)) {
            return $this->error(
                $result->get_error_message(),
                (int) ($result->get_error_data()['status'] ?? 400)
            );
        }

        return $this->success($result);

    }

    /**
     * Pending payments.
     */
    public function payments(WP_REST_Request $request) {

        $result = YAC_Admin_Service::payments([
            'status'   => $request->get_param('status'),
            'search'   => $request->get_param('search') ?: $request->get_param('q'),
            'page'     => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
        ]);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Admin payment detail.
     */
    public function payment(WP_REST_Request $request) {

        $result = YAC_Admin_Service::payment_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    /**
     * Admin orders.
     */
    public function orders(WP_REST_Request $request) {

        $result = YAC_Admin_Service::orders($this->list_args($request));

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Admin order detail.
     */
    public function order(WP_REST_Request $request) {

        $result = YAC_Admin_Service::order_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    /**
     * Admin procurements.
     */
    public function procurements(WP_REST_Request $request) {

        $result = YAC_Admin_Service::procurements($this->list_args($request));

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Admin procurement detail.
     */
    public function procurement(WP_REST_Request $request) {

        $result = YAC_Admin_Service::procurement_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    /**
     * Admin tutor requests.
     */
    public function tutor_requests(WP_REST_Request $request) {

        $result = YAC_Admin_Service::tutor_requests($this->list_args($request));

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Admin tutor request detail.
     */
    public function tutor_request(WP_REST_Request $request) {

        $result = YAC_Admin_Service::tutor_request_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    /**
     * Admin consulting requests.
     */
    public function consulting_requests(WP_REST_Request $request) {

        $result = YAC_Admin_Service::consulting_requests($this->list_args($request));

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Admin consulting request detail.
     */
    public function consulting_request(WP_REST_Request $request) {

        $result = YAC_Admin_Service::consulting_request_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    /**
     * Shared list arguments.
     */
    private function list_args(WP_REST_Request $request) {

        return [
            'status'   => $request->get_param('status'),
            'search'   => $request->get_param('search') ?: $request->get_param('q'),
            'page'     => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
        ];

    }

}
