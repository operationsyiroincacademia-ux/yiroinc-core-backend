<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Auth_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Register.
         *
         * POST /auth/register
         */
        register_rest_route(
            $this->namespace,
            '/auth/register',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'register'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        /**
         * Login.
         *
         * POST /auth/login
         */
        register_rest_route(
            $this->namespace,
            '/auth/login',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'login'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        /**
         * Current authenticated user.
         *
         * GET /auth/me
         */
        register_rest_route(
            $this->namespace,
            '/auth/me',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'me'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
            ]
        );

        /**
         * Logout.
         *
         * POST /auth/logout
         */
        register_rest_route(
            $this->namespace,
            '/auth/logout',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'logout'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
            ]
        );

    }

    /**
     * Register.
     */
    public function register(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $required = [
            'first_name',
            'last_name',
            'email',
            'password',
            'profile_type',
        ];

        foreach ($required as $field) {

            if (empty($data[$field])) {
                return $this->error("Missing field: {$field}");
            }

        }

        $result = YAC_Auth_Service::register($data);

        if (is_wp_error($result)) {
            return $this->error(
                $result->get_error_message(),
                400
            );
        }

        return $this->success($result);

    }

    /**
     * Login.
     */
    public function login(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $required = [
            'email',
            'password',
        ];

        foreach ($required as $field) {

            if (empty($data[$field])) {
                return $this->error("Missing field: {$field}");
            }

        }

        $result = YAC_Auth_Service::login(
            $data['email'],
            $data['password']
        );

        if (!$result) {
            return $this->error(
                'Invalid email or password.',
                401
            );
        }

        return $this->success($result);

    }

    /**
     * Get current authenticated user.
     */
    public function me(WP_REST_Request $request) {

        $user = YAC_Auth_Helper::user();

        if (!$user) {
            return $this->error('Unauthenticated.', 401);
        }

        $profile = YAC_Profile_Service::get_by_user_id(
            $user['id']
        );

        $is_admin = user_can($user['id'], 'manage_options');

        if (!$profile) {
            if (!$is_admin) {
                return $this->error('Profile not found.', 404);
            }

            $profile = null;
        }

        return $this->success([
            'user' => array_merge(
                $user,
                [
                    'is_admin'     => $is_admin ? 1 : 0,
                    'roles'        => $this->user_roles($user['id']),
                    'capabilities' => [
                        'manage_options' => $is_admin ? 1 : 0,
                    ],
                ]
            ),
            'profile' => $profile
                ? [
                    'id'           => (int) $profile['id'],
                    'profile_type' => $profile['profile_type'],
                ]
                : null,
            'auth' => [
                'is_admin'     => $is_admin ? 1 : 0,
                'capabilities' => [
                    'manage_options' => $is_admin ? 1 : 0,
                ],
            ],
        ]);

    }

    private function user_roles($user_id) {

        $user = get_user_by('id', $user_id);

        if (!$user) {
            return [];
        }

        return array_values((array) $user->roles);

    }

    /**
     * Logout current user.
     */
    public function logout(WP_REST_Request $request) {

        return $this->success([
            'message' => 'Logged out successfully.',
        ]);

    }

}
