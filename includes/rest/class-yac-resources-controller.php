<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resources_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/resources',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_resources'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/resources/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

    }

    /**
     * Create resource.
     */
    public function create_resource(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $required = [
            'title',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $validation = YAC_Validation_Service::max_length($data['title'], 255);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $resource_id = YAC_Resource_Service::create([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'category'     => $data['category'] ?? null,
            'file_id'      => $data['file_id'] ?? null,
            'profile_type' => $data['profile_type'] ?? null,
            'exam_type'    => $data['exam_type'] ?? null,
            'is_public'    => !empty($data['is_public']) ? 1 : 0,
        ]);

        if (!$resource_id) {
            return $this->error('Unable to create resource.');
        }

        return $this->success([
            'resource_id' => $resource_id,
        ]);

    }

    /**
     * Get all resources.
     */
    public function get_resources(WP_REST_Request $request) {

        return $this->success([
            'resources' => YAC_Resource_Service::all(),
        ]);

    }

    /**
     * Get single resource.
     */
    public function get_resource(WP_REST_Request $request) {

        $resource = YAC_Resource_Service::find($request['id']);

        if (!$resource) {
            return $this->error('Resource not found.', 404);
        }

        return $this->success([
            'resource' => $resource,
        ]);

    }

}