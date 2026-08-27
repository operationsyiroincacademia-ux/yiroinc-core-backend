<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Account_Deletion_Service {

    const DELETED_AT_META_KEY = 'yac_account_deleted_at';
    const DELETED_EMAIL_HASH_META_KEY = 'yac_deleted_email_hash';
    const DELETED_GOOGLE_SUB_HASH_META_KEY = 'yac_deleted_google_sub_hash';
    const CLOSED_ACCOUNT_LOGIN_MESSAGE = 'This account has been closed and can no longer be accessed.';

    /**
     * Close the currently authenticated customer account.
     *
     * @param int        $user_id
     * @param array|null $data
     * @return array|WP_Error
     */
    public static function delete_customer_account($user_id, $data) {

        $user_id = absint($user_id);

        if (!$user_id) {
            return self::error('yac_account_delete_unauthorized', 'Unauthorized.', 401);
        }

        if (!is_array($data) || ($data['confirmation'] ?? '') !== 'DELETE') {
            return self::error('yac_account_delete_confirmation_required', 'Type DELETE to confirm account deletion.', 422);
        }

        return self::close_customer_account(
            $user_id,
            [
                'success_message' => 'Account deleted successfully.',
                'missing_status'  => 401,
                'missing_message' => 'Unauthorized.',
                'closed_status'   => 401,
                'closed_message'  => 'Unauthorized.',
                'invalid_message' => 'Account deletion is only available for customer accounts.',
            ]
        );

    }

    /**
     * Permanently close and anonymize a customer account.
     *
     * @param int   $user_id
     * @param array $options
     * @return array|WP_Error
     */
    public static function close_customer_account($user_id, array $options = []) {

        $user_id = absint($user_id);
        $user = $user_id ? get_userdata($user_id) : false;

        if (!$user) {
            return self::error(
                'yac_account_close_not_found',
                $options['missing_message'] ?? 'Customer not found.',
                (int) ($options['missing_status'] ?? 404)
            );
        }

        if (user_can($user_id, 'manage_options')) {
            return self::error(
                'yac_account_close_forbidden',
                $options['invalid_message'] ?? 'Invalid customer account.',
                403
            );
        }

        if (self::is_deleted_user($user_id)) {
            return self::error(
                'yac_account_close_already_closed',
                $options['closed_message'] ?? 'Customer account is already closed.',
                (int) ($options['closed_status'] ?? 409)
            );
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        if (!self::is_customer_profile($profile)) {
            return self::error(
                'yac_account_close_forbidden',
                $options['invalid_message'] ?? 'Invalid customer account.',
                403
            );
        }

        if (self::has_active_business_processes($user_id)) {
            return self::error(
                'yac_account_close_active_business',
                $options['active_message'] ?? 'Your account cannot be deleted while you have active requests, orders, or payments.',
                409
            );
        }

        $original_email = self::normalize_email($user->user_email);

        if ($original_email === '') {
            return self::error('yac_account_close_invalid_email', 'Unable to close account.', 500);
        }

        $google_sub = (string) get_user_meta($user_id, YAC_Google_Auth_Service::GOOGLE_SUB_META_KEY, true);
        $had_google_link = $google_sub !== '';
        $deleted_at = gmdate('Y-m-d H:i:s');
        $placeholder_email = self::placeholder_email($user_id);
        $placeholder_login = 'deleted_user_' . $user_id;
        $display_name = 'Deleted Customer';
        $email_context = [
            'email'        => $original_email,
            'first_name'   => trim((string) $user->first_name),
            'display_name' => trim((string) $user->display_name),
            'user_id'      => $user_id,
        ];

        global $wpdb;

        $wpdb->query('START TRANSACTION');

        $updated_identity = wp_update_user([
            'ID'           => $user_id,
            'user_email'   => $placeholder_email,
            'user_pass'    => wp_generate_password(64, true, true),
            'display_name' => $display_name,
            'first_name'   => '',
            'last_name'    => '',
            'nickname'     => $display_name,
            'user_url'     => '',
        ]);

        if (is_wp_error($updated_identity)) {
            self::rollback($user_id);
            return $updated_identity;
        }

        $login_updated = $wpdb->update(
            $wpdb->users,
            [
                'user_login'          => $placeholder_login,
                'user_nicename'       => $placeholder_login,
                'user_activation_key' => '',
            ],
            [
                'ID' => $user_id,
            ],
            [
                '%s',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        if ($login_updated === false) {
            self::rollback($user_id);
            return self::error('yac_account_close_failed', 'Unable to close account.', 500);
        }

        if (!self::anonymize_profile($user_id) || !self::anonymize_consulting_requests($user_id, $placeholder_email)) {
            self::rollback($user_id);
            return self::error('yac_account_close_failed', 'Unable to close account.', 500);
        }

        if (
            !self::set_user_meta($user_id, self::DELETED_AT_META_KEY, $deleted_at) ||
            !self::set_user_meta($user_id, self::DELETED_EMAIL_HASH_META_KEY, self::tombstone_hash($original_email))
        ) {
            self::rollback($user_id);
            return self::error('yac_account_close_failed', 'Unable to close account.', 500);
        }

        if ($google_sub !== '') {
            if (!self::set_user_meta($user_id, self::DELETED_GOOGLE_SUB_HASH_META_KEY, self::tombstone_hash($google_sub))) {
                self::rollback($user_id);
                return self::error('yac_account_close_failed', 'Unable to close account.', 500);
            }
        }

        if ($google_sub !== '' && !delete_user_meta($user_id, YAC_Google_Auth_Service::GOOGLE_SUB_META_KEY)) {
            self::rollback($user_id);
            return self::error('yac_account_close_failed', 'Unable to close account.', 500);
        }

        $notifications_deleted = $wpdb->delete(
            YAC_Notifications_Table::table_name(),
            [
                'user_id' => $user_id,
            ],
            [
                '%d',
            ]
        );

        if ($notifications_deleted === false) {
            self::rollback($user_id);
            return self::error('yac_account_close_failed', 'Unable to close account.', 500);
        }

        if (!empty($options['audit']) && is_array($options['audit'])) {
            $audit_id = YAC_Audit_Service::record([
                'actor_id'    => absint($options['audit']['actor_id'] ?? 0),
                'action'      => 'customer_account_closed',
                'entity_type' => 'user',
                'entity_id'   => $user_id,
                'old_values'  => [
                    'account_status' => 'active',
                    'profile_type'    => $profile['profile_type'],
                    'had_google_link' => $had_google_link,
                ],
                'new_values'  => [
                    'account_status' => 'closed',
                    'closed_by'      => $options['audit']['closed_by'] ?? 'admin',
                    'reason'         => $options['audit']['reason'] ?? '',
                ],
            ]);

            if (!$audit_id) {
                self::rollback($user_id);
                return self::error('yac_account_close_failed', 'Unable to close account.', 500);
            }
        }

        $wpdb->query('COMMIT');
        clean_user_cache($user_id);

        try {
            YAC_CRM_Service::close_deleted_account_contact($original_email, $user_id);
        } catch (\Throwable $e) {
            error_log('[YAC CRM] Account closure cleanup failed for user ID ' . $user_id . '.');
        }

        if (!empty($options['send_closure_email'])) {
            YAC_Email_Service::send_account_closed($email_context);
        }

        return [
            'message' => $options['success_message'] ?? 'Account closed successfully.',
        ];

    }

    public static function is_deleted_user($user_id) {

        return (string) get_user_meta(absint($user_id), self::DELETED_AT_META_KEY, true) !== '';

    }

    public static function email_has_deleted_tombstone($email) {

        $email = self::normalize_email($email);

        if ($email === '') {
            return false;
        }

        return self::hash_exists(self::DELETED_EMAIL_HASH_META_KEY, self::tombstone_hash($email));

    }

    public static function google_identity_has_deleted_tombstone($email, $google_sub) {

        if (self::email_has_deleted_tombstone($email)) {
            return true;
        }

        $google_sub = sanitize_text_field((string) $google_sub);

        if ($google_sub === '') {
            return false;
        }

        return self::hash_exists(self::DELETED_GOOGLE_SUB_HASH_META_KEY, self::tombstone_hash($google_sub));

    }

    public static function cleanup_profile_for_user($user_id) {

        global $wpdb;

        return $wpdb->delete(
            YAC_Profiles_Table::table_name(),
            [
                'user_id' => absint($user_id),
            ],
            [
                '%d',
            ]
        ) !== false;

    }

    private static function is_customer_profile($profile) {

        if (!$profile || empty($profile['profile_type'])) {
            return false;
        }

        return in_array($profile['profile_type'], self::customer_profile_types(), true);

    }

    private static function customer_profile_types() {

        return [
            'academic_user',
            'cfa_candidate',
            'frm_candidate',
            'corporate_client',
        ];

    }

    private static function has_active_business_processes($user_id) {

        return self::has_active_payments($user_id)
            || self::has_active_orders($user_id)
            || self::has_active_tutor_requests($user_id)
            || self::has_active_consulting_requests($user_id)
            || self::has_active_procurements($user_id);

    }

    private static function has_active_payments($user_id) {

        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE user_id = %d
                 AND payment_status IN ('pending', 'submitted')",
                absint($user_id)
            )
        ) > 0;

    }

    private static function has_active_orders($user_id) {

        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE user_id = %d
                 AND order_status != 'cancelled'
                 AND (
                    order_status IN ('awaiting_payment', 'under_review', 'processing')
                    OR (
                        payment_status = 'verified'
                        AND order_status != 'completed'
                        AND fulfillment_status != 'fulfilled'
                    )
                 )",
                absint($user_id)
            )
        ) > 0;

    }

    private static function has_active_tutor_requests($user_id) {

        return self::has_status_rows(
            YAC_Tutor_Requests_Table::table_name(),
            $user_id,
            ['pending', 'matched', 'in_progress']
        );

    }

    private static function has_active_consulting_requests($user_id) {

        return self::has_status_rows(
            YAC_Consulting_Requests_Table::table_name(),
            $user_id,
            ['pending', 'under_review', 'assigned', 'in_progress']
        );

    }

    private static function has_active_procurements($user_id) {

        return self::has_status_rows(
            YAC_Procurements_Table::table_name(),
            $user_id,
            ['pending', 'sourcing', 'ordered', 'shipped']
        );

    }

    private static function has_status_rows($table, $user_id, array $statuses) {

        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE user_id = %d
                 AND status IN ({$placeholders})",
                ...array_merge([absint($user_id)], $statuses)
            )
        ) > 0;

    }

    private static function anonymize_profile($user_id) {

        return YAC_Profile_Service::update(
            $user_id,
            [
                'phone'             => null,
                'organization_name' => null,
                'exam_type'         => null,
                'exam_level'        => null,
                'institution'       => null,
                'area_of_interest'  => null,
                'country'           => null,
            ]
        );

    }

    private static function anonymize_consulting_requests($user_id, $placeholder_email) {

        global $wpdb;

        return $wpdb->update(
            YAC_Consulting_Requests_Table::table_name(),
            [
                'organization_name' => null,
                'contact_person'    => 'Deleted customer',
                'contact_email'     => $placeholder_email,
                'contact_phone'     => null,
            ],
            [
                'user_id' => absint($user_id),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        ) !== false;

    }

    private static function set_user_meta($user_id, $key, $value) {

        $updated = update_user_meta(absint($user_id), $key, $value);

        return $updated !== false || (string) get_user_meta(absint($user_id), $key, true) === (string) $value;

    }

    private static function hash_exists($meta_key, $hash) {

        if ($hash === '') {
            return false;
        }

        $users = get_users([
            'meta_key'   => $meta_key,
            'meta_value' => $hash,
            'fields'     => 'ID',
            'number'     => 1,
        ]);

        return !empty($users);

    }

    private static function rollback($user_id) {

        global $wpdb;

        $wpdb->query('ROLLBACK');
        clean_user_cache(absint($user_id));

    }

    private static function tombstone_hash($value) {

        $value = sanitize_text_field((string) $value);

        if ($value === '') {
            return '';
        }

        return hash_hmac('sha256', $value, wp_salt('auth'));

    }

    private static function normalize_email($email) {

        $email = strtolower(sanitize_email((string) $email));

        return is_email($email) ? $email : '';

    }

    private static function placeholder_email($user_id) {

        return 'deleted-user-' . absint($user_id) . '@accounts.yiroinc.invalid';

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
