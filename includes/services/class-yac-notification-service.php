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
