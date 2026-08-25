<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Notification_Service extends YAC_Base_Service {

    /**
     * Create a notification.
     *
     * @param array $data
     * @return int|false
     */
    public static function create($data) {

        $notification = self::prepare($data);

        if (is_wp_error($notification)) {
            return false;
        }

        return self::insert(
            YAC_Notifications_Table::table_name(),
            $notification
        );

    }

    /**
     * Create the same portal notification for every current administrator.
     *
     * @param array $data
     * @param int   $exclude_user_id
     * @return int
     */
    public static function notify_admins($data, $exclude_user_id = 0) {

        $created = 0;

        foreach (self::admin_user_ids() as $admin_id) {
            if ((int) $admin_id === (int) $exclude_user_id) {
                continue;
            }

            $notification = $data;
            $notification['user_id'] = (int) $admin_id;

            if (self::create($notification)) {
                $created++;
            }
        }

        return $created;

    }

    /**
     * Resolve administrators by capability so notification ownership remains user-scoped.
     *
     * @return array
     */
    public static function admin_user_ids() {

        global $wpdb;

        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                 AND (
                    meta_value LIKE %s
                    OR meta_value LIKE %s
                 )",
                $wpdb->prefix . 'capabilities',
                '%' . $wpdb->esc_like('administrator') . '%',
                '%' . $wpdb->esc_like('manage_options') . '%'
            )
        );

        return array_values(array_unique(array_map('absint', $user_ids)));

    }

    private static function prepare($data) {

        if (empty($data['user_id']) || empty($data['title']) || empty($data['message'])) {
            return new WP_Error(
                'yac_invalid_notification',
                'Notification user_id, title and message are required.'
            );
        }

        $type = sanitize_key($data['type'] ?? 'info');

        if (!in_array($type, ['info', 'success', 'warning'], true)) {
            return new WP_Error(
                'yac_invalid_notification_type',
                'Invalid notification type.'
            );
        }

        return [
            'user_id'          => absint($data['user_id']),
            'sender_id'        => absint($data['sender_id'] ?? 0),
            'related_type'     => isset($data['related_type'])
                ? sanitize_key($data['related_type'])
                : null,
            'related_id'       => isset($data['related_id'])
                ? absint($data['related_id'])
                : null,
            'title'            => sanitize_text_field($data['title']),
            'message'          => sanitize_textarea_field($data['message']),
            'type'             => $type,
            'action_url'       => isset($data['action_url'])
                ? esc_url_raw($data['action_url'])
                : null,
            'delivery_channel' => sanitize_key($data['delivery_channel'] ?? 'portal'),
        ];

    }
    
        /**
     * Get all notifications for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Notifications_Table::table_name() . "
                 WHERE user_id = %d
                 AND is_dismissed = 0
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

    }

}
