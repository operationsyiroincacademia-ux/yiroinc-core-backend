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

        if (!$profile) {
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
            ],
            'profile' => [
                'id'           => (int) $profile['id'],
                'profile_type' => $profile['profile_type'],
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

        $profile_id = YAC_Profile_Service::create([
            'user_id'      => $user_id,
            'profile_type' => $data['profile_type'],
        ]);

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

        return [
            'token' => $token,
            'user'  => [
                'id'            => $user->ID,
                'name'          => $user->display_name,
                'email'         => $user->user_email,
                'registered_at' => $user->user_registered,
            ],
            'profile' => [
                'id'           => (int) $profile_id,
                'profile_type' => $data['profile_type'],
                'completed'    => false,
            ],
        ];

    }

}