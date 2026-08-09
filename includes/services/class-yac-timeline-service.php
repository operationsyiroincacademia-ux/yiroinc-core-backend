<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Timeline_Service extends YAC_Base_Service {

    /**
     * Record a timeline event.
     *
     * @param array $data
     * @return int|false
     */
    public static function record($data) {

        return self::insert(
            YAC_Timeline_Table::table_name(),
            [
                'user_id'      => $data['user_id'],
                'actor_id'     => $data['actor_id'] ?? 0,
                'event'        => $data['event'],
                'title'        => $data['title'],
                'description'  => $data['description'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id'   => $data['related_id'] ?? null,
                'metadata'     => isset($data['metadata']) ? wp_json_encode($data['metadata']) : null,
                'visibility'   => $data['visibility'] ?? 'user',
            ]
        );

    }

    /**
     * Get all timeline events for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

    }

    /**
     * Get a single timeline event.
     *
     * @param int $id
     * @param int $user_id
     * @return array|null
     */
    public static function find($id, $user_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $id,
                $user_id
            ),
            ARRAY_A
        );

    }

}