<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resource_Service {

    /**
     * Table name.
     *
     * @return string
     */
    private static function table() {

        return YAC_Resources_Table::table_name();

    }

    /**
     * Create resource.
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
     * Get all resources.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id = null) {

        global $wpdb;

        $where = self::access_where_sql($user_id);

        $query = "SELECT " . self::select_sql() . "
            FROM " . self::table() . " r
            LEFT JOIN " . YAC_Files_Table::table_name() . " f
                ON f.id = r.file_id
            {$where['sql']}
            ORDER BY r.created_at DESC";

        if (!empty($where['params'])) {
            $query = $wpdb->prepare($query, ...$where['params']);
        }

        $resources = $wpdb->get_results($query, ARRAY_A);

        return array_map([self::class, 'format'], $resources);

    }

    /**
     * Get resource by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find($id, $user_id = null) {

        global $wpdb;

        $where = self::access_where_sql($user_id);

        $params = [(int) $id];

        if (!empty($where['params'])) {
            $params = array_merge($params, $where['params']);
        }

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT " . self::select_sql() . "
                 FROM " . self::table() . " r
                 LEFT JOIN " . YAC_Files_Table::table_name() . " f
                    ON f.id = r.file_id
                 WHERE r.id = %d
                 {$where['and_sql']}",
                ...$params
            ),
            ARRAY_A
        );

        if (!$resource) {
            return null;
        }

        return self::format($resource);

    }

    public static function find_file_resource($file_id, $user_id = null) {

        global $wpdb;

        $where = self::access_where_sql($user_id);

        $params = [(int) $file_id];

        if (!empty($where['params'])) {
            $params = array_merge($params, $where['params']);
        }

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT " . self::select_sql() . "
                 FROM " . self::table() . " r
                 LEFT JOIN " . YAC_Files_Table::table_name() . " f
                    ON f.id = r.file_id
                 WHERE r.file_id = %d
                 AND r.source_type = 'file'
                 {$where['and_sql']}
                 LIMIT 1",
                ...$params
            ),
            ARRAY_A
        );

        if (!$resource) {
            return null;
        }

        return self::format($resource);

    }

    public static function get_resource_file($file_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Files_Table::table_name() . "
                 WHERE id = %d
                 AND related_type = %s
                 AND file_type = %s",
                (int) $file_id,
                'resource',
                'resource_file'
            ),
            ARRAY_A
        );

    }

    public static function link_file($file_id, $resource_id) {

        global $wpdb;

        $updated = $wpdb->update(
            YAC_Files_Table::table_name(),
            [
                'related_id' => (int) $resource_id,
            ],
            [
                'id'           => (int) $file_id,
                'related_type' => 'resource',
                'file_type'    => 'resource_file',
                'related_id'   => 0,
            ],
            [
                '%d',
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
            ]
        );

        return $updated === 1;

    }

    private static function select_sql() {

        return "r.*,
            f.original_name AS file_original_name,
            f.file_name AS file_name,
            f.mime_type AS file_mime_type,
            f.file_size AS file_size,
            f.file_type AS file_type";

    }

    private static function access_where_sql($user_id) {

        if (!$user_id || user_can($user_id, 'manage_options')) {
            return [
                'sql'     => '',
                'and_sql' => '',
                'params'  => [],
            ];
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        if (!$profile) {
            return [
                'sql'     => "WHERE r.is_public = 1
                    AND (
                        r.source_type != 'file'
                        OR r.file_id IS NOT NULL
                    )",
                'and_sql' => "AND r.is_public = 1
                    AND (
                        r.source_type != 'file'
                        OR r.file_id IS NOT NULL
                    )",
                'params'  => [],
            ];
        }

        return [
            'sql'     => "WHERE (
                r.is_public = 1
                OR (
                    r.profile_type = %s
                    AND (
                        r.exam_type IS NULL
                        OR r.exam_type = ''
                        OR r.exam_type = %s
                    )
                )
            )
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'and_sql' => "AND (
                r.is_public = 1
                OR (
                    r.profile_type = %s
                    AND (
                        r.exam_type IS NULL
                        OR r.exam_type = ''
                        OR r.exam_type = %s
                    )
                )
            )
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'params'  => [
                $profile['profile_type'],
                $profile['exam_type'] ?? '',
            ],
        ];

    }

    private static function format($resource) {

        return [
            'id'            => (int) $resource['id'],
            'title'         => $resource['title'],
            'description'   => $resource['description'],
            'category'      => $resource['category'],
            'source_type'   => $resource['source_type'],
            'file_id'       => !empty($resource['file_id'])
                ? (int) $resource['file_id']
                : null,
            'file_name'     => $resource['file_original_name'] ?? null,
            'file_format'   => !empty($resource['file_original_name'])
                ? strtoupper(pathinfo($resource['file_original_name'], PATHINFO_EXTENSION))
                : null,
            'mime_type'     => $resource['file_mime_type'] ?? null,
            'file_size'     => isset($resource['file_size']) && $resource['file_size'] !== null
                ? (int) $resource['file_size']
                : null,
            'external_url'  => $resource['external_url'],
            'profile_type'  => $resource['profile_type'],
            'exam_type'     => $resource['exam_type'],
            'is_public'     => (int) $resource['is_public'],
            'created_at'    => $resource['created_at'],
            'updated_at'    => $resource['updated_at'],
        ];

    }

}
