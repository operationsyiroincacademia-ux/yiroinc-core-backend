<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Google_Auth_Service {

    const GOOGLE_SUB_META_KEY = 'yac_google_sub';

    /**
     * Authenticate or register a customer with a verified Google ID token.
     *
     * @param array|null $data
     * @return array|WP_Error
     */
    public static function authenticate($data) {

        if (!is_array($data)) {
            return self::error('yac_invalid_google_request', 'Invalid Google authentication request.', 400);
        }

        if (empty($data['credential'])) {
            return self::error('yac_missing_google_credential', 'Google credential is required.', 400);
        }

        $claims = self::verify_id_token((string) $data['credential']);

        if (is_wp_error($claims)) {
            return $claims;
        }

        $sub = $claims['sub'];
        $email = strtolower($claims['email']);

        $linked_user_id = self::user_id_by_google_sub($sub);

        if (is_wp_error($linked_user_id)) {
            return $linked_user_id;
        }

        if ($linked_user_id) {
            return self::authenticate_existing_customer((int) $linked_user_id, $sub, false);
        }

        $email_user = get_user_by('email', $email);

        if ($email_user) {
            return self::authenticate_existing_customer((int) $email_user->ID, $sub, true);
        }

        if (empty($data['profile_type'])) {
            return [
                'requires_profile' => true,
                'email'            => $email,
                'name'             => $claims['name'],
            ];
        }

        return self::register_customer($claims, $data);

    }

    private static function verify_id_token($credential) {

        if (!defined('YAC_GOOGLE_CLIENT_ID') || trim((string) YAC_GOOGLE_CLIENT_ID) === '') {
            return self::error('yac_google_not_configured', 'Google authentication is not configured.', 500);
        }

        if (!class_exists('Google_Client')) {
            return self::error('yac_google_dependency_missing', 'Google authentication dependency is not installed.', 500);
        }

        $client_id = trim((string) YAC_GOOGLE_CLIENT_ID);

        try {
            $client = new Google_Client([
                'client_id' => $client_id,
            ]);

            $payload = $client->verifyIdToken($credential);
        } catch (\Throwable $e) {
            return self::error('yac_invalid_google_credential', 'Invalid Google credential.', 401);
        }

        if (!$payload || !is_array($payload)) {
            return self::error('yac_invalid_google_credential', 'Invalid Google credential.', 401);
        }

        $issuer = $payload['iss'] ?? '';

        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return self::error('yac_invalid_google_issuer', 'Invalid Google credential issuer.', 401);
        }

        if (($payload['aud'] ?? '') !== $client_id) {
            return self::error('yac_invalid_google_audience', 'Invalid Google credential audience.', 401);
        }

        if (empty($payload['exp']) || time() >= (int) $payload['exp']) {
            return self::error('yac_expired_google_credential', 'Google credential has expired.', 401);
        }

        if (!self::claim_is_true($payload['email_verified'] ?? false)) {
            return self::error('yac_unverified_google_email', 'Google email address is not verified.', 401);
        }

        $sub = sanitize_text_field((string) ($payload['sub'] ?? ''));
        $email = sanitize_email((string) ($payload['email'] ?? ''));

        if ($sub === '' || $email === '' || !is_email($email)) {
            return self::error('yac_invalid_google_identity', 'Google credential is missing required identity claims.', 401);
        }

        return [
            'sub'         => $sub,
            'email'       => $email,
            'given_name'  => sanitize_text_field((string) ($payload['given_name'] ?? '')),
            'family_name' => sanitize_text_field((string) ($payload['family_name'] ?? '')),
            'name'        => sanitize_text_field((string) ($payload['name'] ?? $email)),
        ];

    }

    private static function authenticate_existing_customer($user_id, $sub, $link_google_sub) {

        if (user_can($user_id, 'manage_options')) {
            return self::error('yac_google_admin_forbidden', 'Google authentication is not available for administrators.', 403);
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        if (!$profile || !in_array($profile['profile_type'], self::allowed_google_profile_types(), true)) {
            return self::error('yac_google_profile_required', 'A valid customer profile is required.', 403);
        }

        $existing_sub = get_user_meta($user_id, self::GOOGLE_SUB_META_KEY, true);

        if ($existing_sub && !hash_equals((string) $existing_sub, (string) $sub)) {
            return self::error('yac_google_account_mismatch', 'This account is already linked to a different Google identity.', 409);
        }

        if ($link_google_sub && !$existing_sub) {
            $linked = add_user_meta($user_id, self::GOOGLE_SUB_META_KEY, $sub, true);

            if (!$linked) {
                $current_sub = get_user_meta($user_id, self::GOOGLE_SUB_META_KEY, true);

                if (!$current_sub || !hash_equals((string) $current_sub, (string) $sub)) {
                    return self::error('yac_google_link_failed', 'Unable to link Google identity.', 409);
                }
            }
        }

        return self::auth_response($user_id, $profile);

    }

    private static function register_customer(array $claims, array $data) {

        $profile_type = sanitize_key((string) $data['profile_type']);

        if (!in_array($profile_type, self::allowed_google_profile_types(), true)) {
            return self::error('yac_invalid_google_profile_type', 'Invalid Google registration profile type.', 400);
        }

        $profile_data = [
            'profile_type' => $profile_type,
        ];

        if ($profile_type === 'corporate_client') {
            $organization_name = isset($data['organization_name'])
                ? sanitize_text_field(trim((string) $data['organization_name']))
                : '';

            if ($organization_name === '') {
                return self::error(
                    'yac_missing_organization_name',
                    'Organization name is required for corporate clients.',
                    400
                );
            }

            if (strlen($organization_name) > 255) {
                return self::error(
                    'yac_invalid_organization_name',
                    'Organization name must be 255 characters or fewer.',
                    400
                );
            }

            $profile_data['organization_name'] = $organization_name;
        }

        $sub = $claims['sub'];
        $email = strtolower($claims['email']);

        $linked_user_id = self::user_id_by_google_sub($sub);

        if (is_wp_error($linked_user_id)) {
            return $linked_user_id;
        }

        if ($linked_user_id) {
            return self::authenticate_existing_customer((int) $linked_user_id, $sub, false);
        }

        if (get_user_by('email', $email)) {
            return self::error('yac_google_email_exists', 'A user with this email already exists.', 409);
        }

        $first_name = $claims['given_name'];
        $last_name = $claims['family_name'];

        if ($first_name === '' && $last_name === '') {
            [$first_name, $last_name] = self::name_parts($claims['name']);
        }

        $user_id = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(32, true, true),
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim($claims['name']) !== ''
                ? $claims['name']
                : $email,
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $profile_data['user_id'] = $user_id;

        $profile_id = YAC_Profile_Service::create($profile_data);

        if (!$profile_id) {
            wp_delete_user($user_id);

            return self::error('yac_google_profile_creation_failed', 'Unable to create user profile.', 500);
        }

        $meta_id = add_user_meta($user_id, self::GOOGLE_SUB_META_KEY, $sub, true);

        if (!$meta_id) {
            wp_delete_user($user_id);

            return self::error('yac_google_link_failed', 'Unable to link Google identity.', 500);
        }

        try {
            YAC_CRM_Service::sync_user($user_id);
            YAC_CRM_Service::apply_tag($user_id, $profile_type);
        } catch (\Throwable $e) {
            error_log('[YAC CRM] ' . $e->getMessage());
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        return self::auth_response($user_id, $profile);

    }

    private static function auth_response($user_id, array $profile) {

        $user = get_userdata($user_id);

        if (!$user) {
            return self::error('yac_google_user_not_found', 'User account not found.', 404);
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
                'is_admin'      => 0,
                'roles'         => array_values((array) $user->roles),
                'capabilities'  => [
                    'manage_options' => 0,
                ],
            ],
            'profile' => [
                'id'                => (int) $profile['id'],
                'profile_type'      => $profile['profile_type'],
                'organization_name' => $profile['organization_name'] ?? null,
            ],
            'auth' => [
                'is_admin'     => 0,
                'capabilities' => [
                    'manage_options' => 0,
                ],
            ],
        ];

    }

    private static function user_id_by_google_sub($sub) {

        $users = get_users([
            'meta_key'   => self::GOOGLE_SUB_META_KEY,
            'meta_value' => $sub,
            'fields'     => 'ID',
            'number'     => 2,
        ]);

        if (count($users) > 1) {
            return self::error('yac_google_link_conflict', 'Google identity is linked to multiple accounts.', 409);
        }

        return !empty($users) ? (int) $users[0] : 0;

    }

    private static function allowed_google_profile_types() {

        return [
            'academic_user',
            'cfa_candidate',
            'frm_candidate',
            'corporate_client',
        ];

    }

    private static function claim_is_true($value) {

        return $value === true || $value === 'true' || $value === 1 || $value === '1';

    }

    private static function name_parts($name) {

        $parts = preg_split('/\s+/', trim((string) $name), 2);

        return [
            sanitize_text_field($parts[0] ?? ''),
            sanitize_text_field($parts[1] ?? ''),
        ];

    }

    private static function error($code, $message, $status) {

        return new WP_Error(
            $code,
            $message,
            [
                'status' => $status,
            ]
        );

    }

}
