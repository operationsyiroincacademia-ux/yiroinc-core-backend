<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Profile_Service {

    /**
     * Get profile table name.
     *
     * @return string
     */
    private static function table() {
        return YAC_Profiles_Table::table_name();
    }

    /**
     * Get profile by user ID.
     *
     * @param int $user_id
     * @return array|null
     */
    public static function get_by_user_id($user_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

    }
    
        /**
     * Create profile.
     *
     * @param array $data
     * @return int|false
     */
    public static function create(array $data) {

        global $wpdb;

        $inserted = $wpdb->insert(
            self::table(),
            $data
        );

        if (!$inserted) {
            return false;
        }

        return $wpdb->insert_id;

    }

    /**
     * Update profile.
     *
     * @param int   $user_id
     * @param array $data
     * @return bool
     */
    public static function update($user_id, array $data) {

        global $wpdb;

        $updated = $wpdb->update(
            self::table(),
            $data,
            [
                'user_id' => $user_id,
            ]
        );

        return $updated !== false;

    }

}