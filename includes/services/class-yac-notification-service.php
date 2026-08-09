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

        return self::insert(
            YAC_Notifications_Table::table_name(),
            [
                'user_id'          => $data['user_id'],
                'sender_id'        => $data['sender_id'] ?? 0,
                'related_type'     => $data['related_type'] ?? null,
                'related_id'       => $data['related_id'] ?? null,
                'title'            => $data['title'],
                'message'          => $data['message'],
                'type'             => $data['type'] ?? 'info',
                'action_url'       => $data['action_url'] ?? null,
                'delivery_channel' => $data['delivery_channel'] ?? 'portal',
            ]
        );

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