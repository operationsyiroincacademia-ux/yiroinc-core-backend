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

        $profile_data = [
            'user_id'           => $data['user_id'],
            'profile_type'      => sanitize_text_field($data['profile_type']),
        ];

        $optional_data = $this->prepare_profile_updates($data);

        if (is_wp_error($optional_data)) {
            return $optional_data;
        }

        $profile_id = YAC_Profile_Service::create(
            array_merge($profile_data, $optional_data)
        );

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

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        if (!$profile) {
            return $this->error('Profile not found.', 404);
        }

        $updates = $this->prepare_profile_updates($data, $profile);

        if (is_wp_error($updates)) {
            return $updates;
        }

        if (empty($updates)) {
            return $this->error('No valid profile fields provided.', 400);
        }

        $updated = YAC_Profile_Service::update($user_id, $updates);

        if (!$updated) {
            return $this->error('Unable to update profile.');
        }

        return $this->success([
            'message' => 'Profile updated successfully.',
        ]);

    }

    /**
     * Prepare whitelisted profile updates.
     */
    private function prepare_profile_updates(array $data, ?array $existing_profile = null) {

        $fields = [
            'phone'             => 50,
            'organization_name' => 255,
            'exam_type'         => 100,
            'exam_level'        => 100,
            'institution'       => 255,
            'area_of_interest'  => 255,
            'country'           => 100,
        ];

        $updates = [];

        foreach ($fields as $field => $max_length) {

            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === null) {
                $updates[$field] = null;
                continue;
            }

            $value = sanitize_text_field((string) $data[$field]);

            $validation = YAC_Validation_Service::max_length($value, $max_length);

            if (is_wp_error($validation)) {
                return $validation;
            }

            if ($field === 'phone' && !preg_match('/^[0-9+\s().-]*$/', $value)) {
                return new WP_Error(
                    'yac_validation_error',
                    'Phone number contains invalid characters.',
                    [
                        'status' => 400,
                    ]
                );
            }

            $updates[$field] = $value;

        }

        if ($existing_profile) {
            $candidate_validation = $this->apply_candidate_exam_rules(
                $updates,
                $data,
                $existing_profile
            );

            if (is_wp_error($candidate_validation)) {
                return $candidate_validation;
            }
        }

        return $updates;

    }

    private function apply_candidate_exam_rules(array &$updates, array $data, array $profile) {

        $profile_type = $profile['profile_type'] ?? '';
        $exam_type = $this->canonical_candidate_exam_type($profile_type);

        if (!$exam_type) {
            return true;
        }

        $updates['exam_type'] = $exam_type;

        if (!array_key_exists('exam_level', $data)) {
            return true;
        }

        if ($data['exam_level'] === null) {
            $updates['exam_level'] = null;
            return true;
        }

        $level = $this->normalize_candidate_exam_level($data['exam_level'], $exam_type);

        if ($level === '') {
            return new WP_Error(
                'yac_validation_error',
                $exam_type === 'CFA'
                    ? 'CFA candidates must use level_1, level_2, or level_3.'
                    : 'FRM candidates must use part_1 or part_2.',
                [
                    'status' => 400,
                ]
            );
        }

        $updates['exam_level'] = $level;

        return true;

    }

    private function canonical_candidate_exam_type($profile_type) {

        if ($profile_type === 'cfa_candidate') {
            return 'CFA';
        }

        if ($profile_type === 'frm_candidate') {
            return 'FRM';
        }

        return '';

    }

    private function normalize_candidate_exam_level($value, $exam_type) {

        $raw = strtolower(sanitize_text_field((string) $value));
        $raw = str_replace(['-', ' '], '_', $raw);
        $raw = preg_replace('/_+/', '_', $raw);

        if ($exam_type === 'CFA' && strpos($raw, 'part_') !== false) {
            return '';
        }

        if ($exam_type === 'FRM' && strpos($raw, 'level_') !== false) {
            return '';
        }

        $level = class_exists('YAC_Tutor_Service')
            ? YAC_Tutor_Service::normalize_level($value, $exam_type)
            : sanitize_key($value);

        if ($exam_type === 'CFA' && in_array($level, ['level_1', 'level_2', 'level_3'], true)) {
            return $level;
        }

        if ($exam_type === 'FRM' && in_array($level, ['part_1', 'part_2'], true)) {
            return $level;
        }

        return '';

    }

}
