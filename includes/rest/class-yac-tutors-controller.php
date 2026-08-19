<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Tutors_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/admin/tutors',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_tutors'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_tutor'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/tutors/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_tutor'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_tutor'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

    /**
     * Admin tutor list.
     */
    public function get_tutors(WP_REST_Request $request) {

        $result = YAC_Tutor_Service::all([
            'search'         => $request->get_param('search') ?: $request->get_param('q'),
            'status'         => $request->get_param('status'),
            'availability'   => $request->get_param('availability'),
            'exam_expertise' => $request->get_param('exam_expertise') ?: $request->get_param('exam'),
            'level'          => $request->get_param('level'),
            'page'           => $request->get_param('page'),
            'per_page'       => $request->get_param('per_page'),
        ]);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    /**
     * Create tutor.
     */
    public function create_tutor(WP_REST_Request $request) {

        $result = YAC_Tutor_Service::create($request->get_json_params());

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success([
            'tutor' => YAC_Tutor_Service::format_admin($result),
        ], 201);

    }

    /**
     * Get tutor.
     */
    public function get_tutor(WP_REST_Request $request) {

        $tutor = YAC_Tutor_Service::find($request['id']);

        if (!$tutor) {
            return $this->error('Tutor not found.', 404);
        }

        return $this->success([
            'tutor' => YAC_Tutor_Service::format_admin($tutor),
        ]);

    }

    /**
     * Update tutor.
     */
    public function update_tutor(WP_REST_Request $request) {

        $result = YAC_Tutor_Service::update($request['id'], $request->get_json_params());

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'yac_tutor_not_found' ? 404 : 422;
            return $this->error($result->get_error_message(), $status);
        }

        return $this->success([
            'tutor' => YAC_Tutor_Service::format_admin($result),
        ]);

    }
}
