<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Profiles_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/profiles',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_profile'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_profile'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_profile'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

    }

    /**
     * Create profile.
     */
    public function create_profile(WP_REST_Request $request) {

        global $wpdb;

        $data = $request->get_json_params();

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $data['user_id'] = $user_id;

        $required = [
            'profile_type',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $validation = YAC_Validation_Service::max_length($data['profile_type'], 100);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $profile_id = YAC_Profile_Service::create([
            'user_id'           => $data['user_id'],
            'profile_type'      => $data['profile_type'],
            'organization_name' => $data['organization_name'] ?? null,
            'exam_type'         => $data['exam_type'] ?? null,
            'exam_level'        => $data['exam_level'] ?? null,
            'institution'       => $data['institution'] ?? null,
            'area_of_interest'  => $data['area_of_interest'] ?? null,
            'country'           => $data['country'] ?? null,
        ]);

        if (!$profile_id) {
            return $this->error('Unable to create profile.');
        }

        return $this->success([
            'profile_id' => $profile_id,
        ]);

    }

    /**
     * Get logged-in user's profile.
     */
    public function get_profile(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        if (!$profile) {
            return $this->error('Profile not found.', 404);
        }

        return $this->success([
            'profile' => $profile,
        ]);

    }
    
        /**
     * Update logged-in user's profile.
     */
    public function update_profile(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        if (isset($data['profile_type'])) {

            $validation = YAC_Validation_Service::max_length($data['profile_type'], 100);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        if (isset($data['country'])) {

            $validation = YAC_Validation_Service::max_length($data['country'], 100);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        if (isset($data['organization_name'])) {

            $validation = YAC_Validation_Service::max_length($data['organization_name'], 255);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $updated = YAC_Profile_Service::update($user_id, $data);

        if (!$updated) {
            return $this->error('Unable to update profile.');
        }

        return $this->success([
            'message' => 'Profile updated successfully.',
        ]);

    }

}