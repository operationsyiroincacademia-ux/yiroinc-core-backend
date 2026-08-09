<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Timeline_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/timeline',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_event'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_events'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/timeline/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_event'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

    }

    /**
     * Create timeline event.
     */
    public function create_event(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $required = [
            'user_id',
            'event',
            'title',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $event_id = YAC_Timeline_Service::record($data);

        if (!$event_id) {
            return $this->error('Unable to create timeline event.');
        }

        return $this->success([
            'event_id' => $event_id,
        ]);

    }

    /**
     * Get timeline events.
     */
    public function get_events(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'events' => YAC_Timeline_Service::all($user_id),
        ]);

    }

    /**
     * Get single timeline event.
     */
    public function get_event(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $event = YAC_Timeline_Service::find($request['id'], $user_id);

        if (!$event) {
            return $this->error('Timeline event not found.', 404);
        }

        return $this->success([
            'event' => $event,
        ]);

    }

}