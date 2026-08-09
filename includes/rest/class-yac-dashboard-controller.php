<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Dashboard_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/dashboard/general',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'general_dashboard'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/dashboard/exam',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'exam_dashboard'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/dashboard/corporate',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'corporate_dashboard'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/dashboard/admin',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'admin_dashboard'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

        /**
     * General dashboard.
     */
    public function general_dashboard(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'profile'       => YAC_Profile_Service::get_by_user_id($user_id),
            'resources'     => YAC_Resource_Service::all(),
            'notifications' => YAC_Notification_Service::all($user_id),
            'timeline'      => YAC_Timeline_Service::all($user_id),
        ]);

    }

        /**
     * Exam dashboard.
     */
    public function exam_dashboard(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'profile'       => YAC_Profile_Service::get_by_user_id($user_id),
            'payments'      => YAC_Payment_Service::all($user_id),
            'procurements'  => YAC_Procurement_Service::all($user_id),
            'resources'     => YAC_Resource_Service::all(),
            'notifications' => YAC_Notification_Service::all($user_id),
            'timeline'      => YAC_Timeline_Service::all($user_id),
        ]);

    }
    
        /**
     * Corporate dashboard.
     */
    public function corporate_dashboard(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'profile'             => YAC_Profile_Service::get_by_user_id($user_id),
            'consulting_requests' => YAC_Consulting_Service::all($user_id),
            'payments'            => YAC_Payment_Service::all($user_id),
            'resources'           => YAC_Resource_Service::all(),
            'notifications'       => YAC_Notification_Service::all($user_id),
            'timeline'            => YAC_Timeline_Service::all($user_id),
        ]);

    }

        /**
     * Admin dashboard.
     */
    public function admin_dashboard(WP_REST_Request $request) {

        return $this->success([
            'summary'                     => YAC_Admin_Service::dashboard(),
            'recent_activity'             => YAC_Admin_Service::recent_activity(),
            'pending_payments'            => YAC_Admin_Service::pending_payments(),
            'pending_procurements'        => YAC_Admin_Service::pending_procurements(),
            'pending_tutor_requests'      => YAC_Admin_Service::pending_tutor_requests(),
            'pending_consulting_requests' => YAC_Admin_Service::pending_consulting_requests(),
        ]);

    }
}