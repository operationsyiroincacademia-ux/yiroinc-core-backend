<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Password_Reset_Service {

    const FORGOT_THROTTLE_SECONDS = 15 * MINUTE_IN_SECONDS;

    /**
     * Start a customer password reset.
     *
     * @param array|null $data
     * @return array|WP_Error
     */
    public static function forgot_password($data) {

        if (!is_array($data)) {
            return self::error('yac_invalid_forgot_password_request', 'Invalid forgot password request.', 400);
        }

        $email = sanitize_email((string) ($data['email'] ?? ''));

        if ($email === '' || !is_email($email)) {
            return self::error('yac_invalid_email', 'A valid email address is required.', 400);
        }

        $email = strtolower($email);
        $response = self::generic_forgot_response();

        if (self::forgot_is_throttled($email)) {
            return $response;
        }

        self::mark_forgot_throttled($email);

        $user = get_user_by('email', $email);

        if (!$user || !self::is_customer_user((int) $user->ID)) {
            return $response;
        }

        $frontend_url = self::frontend_url();

        if (!$frontend_url) {
            error_log('[YAC Auth] YAC_FRONTEND_URL is not configured for password reset emails.');
            return $response;
        }

        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            error_log('[YAC Auth] Unable to create password reset key for user ID ' . (int) $user->ID . '.');
            return $response;
        }

        $reset_link = self::reset_link($frontend_url, $key, $user->user_login);

        if (!$reset_link) {
            error_log('[YAC Auth] Unable to build password reset link for user ID ' . (int) $user->ID . '.');
            return $response;
        }

        $sent = wp_mail(
            $user->user_email,
            'Reset your YiroInc Academia password',
            self::reset_email_body($reset_link)
        );

        if (!$sent) {
            error_log('[YAC Auth] Password reset email could not be sent for user ID ' . (int) $user->ID . '.');
        }

        return $response;

    }

    /**
     * Complete a customer password reset.
     *
     * @param array|null $data
     * @return array|WP_Error
     */
    public static function reset_password($data) {

        if (!is_array($data)) {
            return self::error('yac_invalid_reset_password_request', 'Invalid reset password request.', 400);
        }

        $login = sanitize_text_field((string) ($data['login'] ?? ''));
        $key = sanitize_text_field((string) ($data['key'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($login === '' || $key === '' || $password === '') {
            return self::error('yac_missing_reset_fields', 'login, key and password are required.', 400);
        }

        if (strlen($password) < 8) {
            return self::error('yac_invalid_password', 'Password must be at least 8 characters.', 400);
        }

        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user) || !$user instanceof WP_User) {
            return self::error(
                'yac_invalid_reset_link',
                'The password reset link is invalid or has expired.',
                400
            );
        }

        if (!self::is_customer_user((int) $user->ID)) {
            return self::error('yac_customer_reset_forbidden', 'Password reset is not available for this account.', 403);
        }

        reset_password($user, $password);

        return [
            'message' => 'Password reset successfully. Please sign in with your new password.',
        ];

    }

    private static function generic_forgot_response() {

        return [
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ];

    }

    private static function is_customer_user($user_id) {

        if (user_can($user_id, 'manage_options')) {
            return false;
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        if (!$profile) {
            return false;
        }

        return in_array($profile['profile_type'], self::allowed_customer_profile_types(), true);

    }

    private static function allowed_customer_profile_types() {

        return [
            'academic_user',
            'cfa_candidate',
            'frm_candidate',
            'corporate_client',
        ];

    }

    private static function frontend_url() {

        if (!defined('YAC_FRONTEND_URL')) {
            return '';
        }

        $url = trim((string) YAC_FRONTEND_URL);

        if ($url === '') {
            return '';
        }

        return untrailingslashit($url);

    }

    private static function reset_link($frontend_url, $key, $login) {

        return esc_url_raw(
            add_query_arg(
                [
                    'key'   => $key,
                    'login' => $login,
                ],
                $frontend_url . '/reset-password'
            )
        );

    }

    private static function reset_email_body($reset_link) {

        return implode(
            "\n\n",
            [
                'A password reset was requested for your YiroInc Academia account.',
                'Use the link below to choose a new password:',
                $reset_link,
                'For your security, this password reset link will expire after a limited time.',
                'If you did not request this, you can safely ignore this email.',
            ]
        );

    }

    private static function forgot_is_throttled($email) {

        return (bool) get_transient(self::forgot_throttle_key($email));

    }

    private static function mark_forgot_throttled($email) {

        set_transient(
            self::forgot_throttle_key($email),
            1,
            self::FORGOT_THROTTLE_SECONDS
        );

    }

    private static function forgot_throttle_key($email) {

        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR'])
            : '';

        return 'yac_forgot_' . hash_hmac(
            'sha256',
            strtolower($email) . '|' . $ip,
            wp_salt('auth')
        );

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
