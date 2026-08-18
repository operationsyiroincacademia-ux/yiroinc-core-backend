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
            '/admin/consulting-requests',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'consulting_requests'],
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
     * Pending procurements.
     */
    public function procurements(WP_REST_Request $request) {

        return $this->success([
            'procurements' => YAC_Admin_Service::pending_procurements(),
        ]);

    }

    /**
     * Pending tutor requests.
     */
    public function tutor_requests(WP_REST_Request $request) {

        return $this->success([
            'tutor_requests' => YAC_Admin_Service::pending_tutor_requests(),
        ]);

    }

    /**
     * Pending consulting requests.
     */
    public function consulting_requests(WP_REST_Request $request) {

        return $this->success([
            'consulting_requests' => YAC_Admin_Service::pending_consulting_requests(),
        ]);

    }

}
