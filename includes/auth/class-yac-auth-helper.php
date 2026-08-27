<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Auth_Helper {

    /**
     * Get authenticated user from Bearer token.
     *
     * @return array|false
     */
    public static function user() {

        $headers = function_exists('getallheaders')
            ? getallheaders()
            : [];

        $authorization = '';

        if (isset($headers['Authorization'])) {
            $authorization = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authorization = $headers['authorization'];
        }

        if (empty($authorization)) {
            return false;
        }

        if (!preg_match('/Bearer\s(\S+)/', $authorization, $matches)) {
            return false;
        }

        $payload = YAC_JWT_Service::verify($matches[1]);

        if (!$payload) {
            return false;
        }

        $user = get_user_by('id', $payload['user_id']);

        if (!$user) {
            return false;
        }

        if (YAC_Account_Deletion_Service::is_deleted_user($user->ID)) {
            return false;
        }

        return [
            'id'    => $user->ID,
            'email' => $user->user_email,
            'name'  => $user->display_name,
        ];

    }

    /**
     * Get authenticated user ID.
     *
     * @return int|false
     */
    public static function user_id() {

        $user = self::user();

        if (!$user) {
            return false;
        }

        return $user['id'];

    }

    /**
     * Permission callback for protected routes.
     *
     * @return true|WP_Error
     */
    public static function authorize() {

        if (!self::user()) {

            return new WP_Error(
                'yac_unauthorized',
                'Unauthorized.',
                [
                    'status' => 401,
                ]
            );

        }

        return true;

    }

    /**
     * Permission callback for administrator-only routes.
     *
     * @return true|WP_Error
     */
    public static function authorize_admin() {

        $user_id = self::user_id();

        if (!$user_id) {

            return new WP_Error(
                'yac_unauthorized',
                'Unauthorized.',
                [
                    'status' => 401,
                ]
            );

        }

        if (!user_can($user_id, 'manage_options')) {

            return new WP_Error(
                'yac_forbidden',
                'Administrator access required.',
                [
                    'status' => 403,
                ]
            );

        }

        return true;

    }

    /**
     * Require a specific WordPress role.
     *
     * @param string $role
     * @return true|WP_Error
     */
    public static function require_role($role) {

        $user_id = self::user_id();

        if (!$user_id) {

            return new WP_Error(
                'yac_unauthorized',
                'Unauthorized.',
                [
                    'status' => 401,
                ]
            );

        }

        $user = get_userdata($user_id);

        if (!$user || !in_array($role, $user->roles, true)) {

            return new WP_Error(
                'yac_forbidden',
                'Forbidden.',
                [
                    'status' => 403,
                ]
            );

        }

        return true;

    }

    /**
     * Check ownership of a resource.
     *
     * @param int $resource_user_id
     * @return true|WP_Error
     */
    public static function owns_resource($resource_user_id) {

        $user_id = self::user_id();

        if (!$user_id) {

            return new WP_Error(
                'yac_unauthorized',
                'Unauthorized.',
                [
                    'status' => 401,
                ]
            );

        }

        if ((int) $user_id !== (int) $resource_user_id) {

            return new WP_Error(
                'yac_forbidden',
                'You do not have permission to access this resource.',
                [
                    'status' => 403,
                ]
            );

        }

        return true;

    }

}
