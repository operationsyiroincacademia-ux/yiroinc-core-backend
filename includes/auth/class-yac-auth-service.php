<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Auth_Service {

    /**
     * Login user.
     *
     * @param string $email
     * @param string $password
     * @return array|false
     */
    public static function login($email, $password) {

        $user = get_user_by('email', $email);

        if (!$user) {
            return false;
        }

        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            return false;
        }

        $profile = YAC_Profile_Service::get_by_user_id($user->ID);
        $is_admin = user_can($user->ID, 'manage_options');

        if (!$profile && !$is_admin) {
            return false;
        }

        $token = YAC_JWT_Service::generate([
            'user_id' => $user->ID,
            'email'   => $user->user_email,
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'            => $user->ID,
                'name'          => $user->display_name,
                'email'         => $user->user_email,
                'registered_at' => $user->user_registered,
                'is_admin'      => $is_admin ? 1 : 0,
                'roles'         => array_values((array) $user->roles),
                'capabilities'  => [
                    'manage_options' => $is_admin ? 1 : 0,
                ],
            ],
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
        ];

    }

    /**
     * Register user.
     *
     * @param array $data
     * @return array|WP_Error
     */
    public static function register($data) {

        $allowed_profile_types = [
            'academic_user',
            'exam_candidate',
            'corporate_client',
            'cfa_candidate',
            'frm_candidate',
            'consulting_lead',
        ];

        if (!in_array($data['profile_type'], $allowed_profile_types, true)) {
            return new WP_Error(
                'invalid_profile_type',
                'Invalid profile type.'
            );
        }

        if ($data['profile_type'] === 'corporate_client') {
            $organization_name = isset($data['organization_name'])
                ? sanitize_text_field(trim((string) $data['organization_name']))
                : '';

            if ($organization_name === '') {
                return new WP_Error(
                    'missing_organization_name',
                    'Organization name is required for corporate clients.'
                );
            }

            if (strlen($organization_name) > 255) {
                return new WP_Error(
                    'invalid_organization_name',
                    'Organization name must be 255 characters or fewer.'
                );
            }

            $data['organization_name'] = $organization_name;
        }

        if (email_exists($data['email'])) {
            return new WP_Error(
                'email_exists',
                'Email address already exists.'
            );
        }

        $user_id = wp_insert_user([
            'user_login'   => $data['email'],
            'user_email'   => $data['email'],
            'user_pass'    => $data['password'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'display_name' => trim(
                $data['first_name'] . ' ' . $data['last_name']
            ),
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $profile_data = [
            'user_id'      => $user_id,
            'profile_type' => $data['profile_type'],
        ];

        if ($data['profile_type'] === 'corporate_client') {
            $profile_data['organization_name'] = $data['organization_name'];
        }

        $profile_id = YAC_Profile_Service::create($profile_data);

        if (!$profile_id) {

            wp_delete_user($user_id);

            return new WP_Error(
                'profile_creation_failed',
                'Unable to create user profile.'
            );

        }

        try {

            YAC_CRM_Service::sync_user($user_id);

            YAC_CRM_Service::apply_tag(
                $user_id,
                $data['profile_type']
            );

        } catch (\Throwable $e) {

            error_log(
                '[YAC CRM] ' . $e->getMessage()
            );

        }

        $user = get_userdata($user_id);

        $token = YAC_JWT_Service::generate([
            'user_id' => $user->ID,
            'email'   => $user->user_email,
        ]);

        $is_admin = user_can($user->ID, 'manage_options');

        $profile_response = [
            'id'           => (int) $profile_id,
            'profile_type' => $data['profile_type'],
            'completed'    => false,
        ];

        if ($data['profile_type'] === 'corporate_client') {
            $profile_response['organization_name'] = $data['organization_name'];
        }

        return [
            'token' => $token,
            'user'  => [
                'id'            => $user->ID,
                'name'          => $user->display_name,
                'email'         => $user->user_email,
                'registered_at' => $user->user_registered,
                'is_admin'      => $is_admin ? 1 : 0,
                'roles'         => array_values((array) $user->roles),
                'capabilities'  => [
                    'manage_options' => $is_admin ? 1 : 0,
                ],
            ],
            'profile' => $profile_response,
            'auth' => [
                'is_admin'     => $is_admin ? 1 : 0,
                'capabilities' => [
                    'manage_options' => $is_admin ? 1 : 0,
                ],
            ],
        ];

    }

}
