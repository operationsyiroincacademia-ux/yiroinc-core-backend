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

        $audiences = $data['_audiences'] ?? [];
        unset($data['_audiences']);

        $inserted = $wpdb->insert(
            self::table(),
            $data
        );

        if (!$inserted) {
            return false;
        }

        $resource_id = (int) $wpdb->insert_id;

        if (!self::sync_audiences($resource_id, $audiences)) {
            return false;
        }

        return $resource_id;

    }

    public static function update($id, array $data) {

        global $wpdb;

        $id = absint($id);
        $audiences = $data['_audiences'] ?? null;
        unset($data['_audiences']);

        $updated = $wpdb->update(
            self::table(),
            $data,
            [
                'id' => $id,
            ]
        );

        if ($updated === false) {
            return false;
        }

        if ($audiences !== null && !self::sync_audiences($id, $audiences)) {
            return false;
        }

        return true;

    }

    public static function sync_audiences($resource_id, array $audiences) {

        global $wpdb;

        $resource_id = absint($resource_id);
        $audiences = self::normalize_audiences($audiences);

        if (!$resource_id || empty($audiences)) {
            return false;
        }

        $table = YAC_Resource_Audiences_Table::table_name();

        $deleted = $wpdb->delete(
            $table,
            [
                'resource_id' => $resource_id,
            ],
            [
                '%d',
            ]
        );

        if ($deleted === false) {
            return false;
        }

        foreach ($audiences as $audience) {
            $inserted = $wpdb->insert(
                $table,
                [
                    'resource_id' => $resource_id,
                    'audience'    => $audience,
                ],
                [
                    '%d',
                    '%s',
                ]
            );

            if ($inserted === false) {
                return false;
            }
        }

        return true;

    }

    /**
     * Get all resources.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id = null) {

        global $wpdb;

        $where = self::catalog_discovery_where_sql($user_id);

        $query = "SELECT " . self::select_sql() . "
            FROM " . self::table() . " r
            LEFT JOIN " . YAC_Files_Table::table_name() . " f
                ON f.id = r.file_id
            " . self::audience_join_sql() . "
            " . self::entitlement_join_sql($user_id) . "
            {$where['sql']}
            ORDER BY r.created_at DESC";

        if (!empty($where['params'])) {
            $query = $wpdb->prepare($query, ...$where['params']);
        }

        $resources = $wpdb->get_results($query, ARRAY_A);

        return array_map(
            function ($resource) use ($user_id) {
                return self::format($resource, $user_id);
            },
            $resources
        );

    }

    public static function purchased($user_id) {

        global $wpdb;

        $resources = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT " . self::select_sql() . "
                 FROM " . self::table() . " r
                 INNER JOIN " . YAC_Resource_Entitlements_Table::table_name() . " e
                    ON e.resource_id = r.id
                    AND e.user_id = %d
                 LEFT JOIN " . YAC_Files_Table::table_name() . " f
                    ON f.id = r.file_id
                 " . self::audience_join_sql() . "
                 WHERE (
                    r.source_type != 'file'
                    OR r.file_id IS NOT NULL
                 )
                 ORDER BY e.granted_at DESC",
                (int) $user_id
            ),
            ARRAY_A
        );

        return array_map(
            function ($resource) use ($user_id) {
                return self::format($resource, $user_id);
            },
            $resources
        );

    }

    /**
     * Get resource by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find($id, $user_id = null) {

        global $wpdb;

        $where = self::catalog_where_sql($user_id);

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
                 " . self::audience_join_sql() . "
                 " . self::entitlement_join_sql($user_id) . "
                 WHERE r.id = %d
                 {$where['and_sql']}",
                ...$params
            ),
            ARRAY_A
        );

        if (!$resource) {
            return null;
        }

        return self::format($resource, $user_id);

    }

    public static function find_unrestricted($id, $user_id = null) {

        global $wpdb;

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT " . self::select_sql() . "
                 FROM " . self::table() . " r
                 LEFT JOIN " . YAC_Files_Table::table_name() . " f
                    ON f.id = r.file_id
                 " . self::audience_join_sql() . "
                 " . self::entitlement_join_sql($user_id) . "
                 WHERE r.id = %d
                 LIMIT 1",
                (int) $id
            ),
            ARRAY_A
        );

        if (!$resource) {
            return null;
        }

        return self::format($resource, $user_id);

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
                 " . self::audience_join_sql() . "
                 " . self::entitlement_join_sql($user_id) . "
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

        return self::format($resource, $user_id);

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

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . YAC_Files_Table::table_name() . "
                 SET related_id = %d
                 WHERE id = %d
                 AND related_type = %s
                 AND file_type = %s
                 AND related_id IN (0, %d)",
                (int) $resource_id,
                (int) $file_id,
                'resource',
                'resource_file',
                (int) $resource_id
            )
        );

        return $updated !== false;

    }

    public static function find_raw($id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . self::table() . "
                 WHERE id = %d
                 LIMIT 1",
                (int) $id
            ),
            ARRAY_A
        );

    }

    public static function admin_all($args = []) {

        global $wpdb;

        $pagination = self::pagination_args($args);
        $where = [];
        $params = [];

        $search = !empty($args['search'])
            ? sanitize_text_field($args['search'])
            : '';

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(
                r.title LIKE %s
                OR r.description LIKE %s
                OR CAST(r.id AS CHAR) LIKE %s
            )';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $exam_value = $args['exam'] ?? ($args['exam_expertise'] ?? '');
        $exam = self::normalize_exam_filter($exam_value);
        if ($exam_value !== null && $exam_value !== '' && $exam === '') {
            return new WP_Error('yac_invalid_resource_exam_filter', 'Invalid resource exam filter.');
        }

        if ($exam !== '') {
            $where[] = 'r.exam_type = %s';
            $params[] = $exam;
        }

        $level = self::normalize_level_filter($args['level'] ?? '', $exam);
        if (is_wp_error($level)) {
            return $level;
        }

        if ($level !== '') {
            $where[] = 'r.exam_level = %s';
            $params[] = $level;
        }

        $pricing = !empty($args['pricing'])
            ? sanitize_key($args['pricing'])
            : '';

        if ($pricing === 'free') {
            $where[] = 'r.price <= 0';
        } elseif ($pricing === 'paid') {
            $where[] = 'r.price > 0';
        } elseif ($pricing !== '') {
            return new WP_Error('yac_invalid_resource_pricing_filter', 'Invalid resource pricing filter.');
        }

        $visibility = !empty($args['visibility'])
            ? sanitize_key($args['visibility'])
            : '';

        if (in_array($visibility, ['public', 'published', 'visible'], true)) {
            $where[] = 'r.is_public = 1';
        } elseif (in_array($visibility, ['private', 'hidden', 'unpublished'], true)) {
            $where[] = 'r.is_public = 0';
        } elseif ($visibility !== '' && $visibility !== 'all') {
            return new WP_Error('yac_invalid_resource_visibility_filter', 'Invalid resource visibility filter.');
        }

        $source_type = !empty($args['source_type'])
            ? sanitize_key($args['source_type'])
            : '';

        if ($source_type !== '') {
            if (!in_array($source_type, ['file', 'external'], true)) {
                return new WP_Error('yac_invalid_resource_source_filter', 'Invalid resource source filter.');
            }

            $where[] = 'r.source_type = %s';
            $params[] = $source_type;
        }

        $audience = !empty($args['audience'])
            ? sanitize_key($args['audience'])
            : '';

        if ($audience !== '') {
            if (!in_array($audience, self::allowed_audiences(), true)) {
                return new WP_Error('yac_invalid_resource_audience_filter', 'Invalid resource audience filter.');
            }

            $where[] = 'EXISTS (
                SELECT 1
                FROM ' . YAC_Resource_Audiences_Table::table_name() . ' raf
                WHERE raf.resource_id = r.id
                AND raf.audience = %s
            )';
            $params[] = $audience;
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $from_sql = "
            FROM " . self::table() . " r
            LEFT JOIN " . YAC_Files_Table::table_name() . " f
                ON f.id = r.file_id
            " . self::audience_join_sql() . "
            LEFT JOIN (
                SELECT resource_id, COUNT(*) AS entitlement_count, COUNT(DISTINCT user_id) AS purchaser_count
                FROM " . YAC_Resource_Entitlements_Table::table_name() . "
                GROUP BY resource_id
            ) ent ON ent.resource_id = r.id
            LEFT JOIN (
                SELECT resource_id, COUNT(*) AS order_count
                FROM " . YAC_Orders_Table::table_name() . "
                WHERE resource_id IS NOT NULL
                GROUP BY resource_id
            ) ord ON ord.resource_id = r.id
        ";

        $query = "
            SELECT " . self::admin_select_sql() . "
            {$from_sql}
            {$where_sql}
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT %d OFFSET %d
        ";

        $resources = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                ...array_merge($params, [$pagination['per_page'], $pagination['offset']])
            ),
            ARRAY_A
        );

        $count_query = "
            SELECT COUNT(*)
            {$from_sql}
            {$where_sql}
        ";

        $total = !empty($params)
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params))
            : (int) $wpdb->get_var($count_query);

        return [
            'resources'  => array_map([self::class, 'format_admin'], $resources),
            'pagination' => self::pagination_payload($pagination, $total),
        ];

    }

    public static function admin_detail($id) {

        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return new WP_Error('yac_invalid_resource_id', 'Invalid resource ID.');
        }

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT " . self::admin_select_sql() . "
                 FROM " . self::table() . " r
                 LEFT JOIN " . YAC_Files_Table::table_name() . " f
                    ON f.id = r.file_id
                 " . self::audience_join_sql() . "
                 LEFT JOIN (
                    SELECT resource_id, COUNT(*) AS entitlement_count, COUNT(DISTINCT user_id) AS purchaser_count
                    FROM " . YAC_Resource_Entitlements_Table::table_name() . "
                    GROUP BY resource_id
                 ) ent ON ent.resource_id = r.id
                 LEFT JOIN (
                    SELECT resource_id, COUNT(*) AS order_count
                    FROM " . YAC_Orders_Table::table_name() . "
                    WHERE resource_id IS NOT NULL
                    GROUP BY resource_id
                 ) ord ON ord.resource_id = r.id
                 WHERE r.id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if (!$resource) {
            return new WP_Error('yac_resource_not_found', 'Resource not found.');
        }

        return [
            'resource'      => self::format_admin($resource),
            'relationships' => self::relationship_counts($id),
        ];

    }

    public static function deactivate($id) {

        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return new WP_Error('yac_invalid_resource_id', 'Invalid resource ID.');
        }

        $resource = self::find_raw($id);

        if (!$resource) {
            return new WP_Error('yac_resource_not_found', 'Resource not found.');
        }

        $updated = $wpdb->update(
            self::table(),
            [
                'is_public'    => 0,
                'profile_type' => null,
                'exam_type'    => null,
                'exam_level'   => null,
            ],
            [
                'id' => $id,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        if ($updated === false) {
            return new WP_Error('yac_resource_deactivate_failed', 'Unable to remove resource from availability.');
        }

        $wpdb->delete(
            YAC_Resource_Audiences_Table::table_name(),
            [
                'resource_id' => $id,
            ],
            [
                '%d',
            ]
        );

        return [
            'message'       => 'Resource removed from availability.',
            'resource_id'   => $id,
            'deactivated'   => 1,
            'relationships' => self::relationship_counts($id),
        ];

    }

    public static function relationship_counts($resource_id) {

        global $wpdb;

        $resource_id = absint($resource_id);

        return [
            'orders'       => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM " . YAC_Orders_Table::table_name() . "
                     WHERE resource_id = %d",
                    $resource_id
                )
            ),
            'entitlements' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM " . YAC_Resource_Entitlements_Table::table_name() . "
                     WHERE resource_id = %d",
                    $resource_id
                )
            ),
            'purchasers'   => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id)
                     FROM " . YAC_Resource_Entitlements_Table::table_name() . "
                     WHERE resource_id = %d",
                    $resource_id
                )
            ),
        ];

    }

    public static function audiences_for_resource($resource_id) {

        global $wpdb;

        $audiences = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT audience
                 FROM " . YAC_Resource_Audiences_Table::table_name() . "
                 WHERE resource_id = %d
                 ORDER BY audience ASC",
                absint($resource_id)
            )
        );

        return self::normalize_audiences($audiences);

    }

    public static function verified_payment_for_order($order_id, $user_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE order_id = %d
                 AND user_id = %d
                 AND payment_status = %s
                 ORDER BY created_at DESC
                 LIMIT 1",
                (int) $order_id,
                (int) $user_id,
                'verified'
            ),
            ARRAY_A
        );

    }

    public static function grant_entitlement($user_id, $resource_id, $order_id, $payment_id) {

        global $wpdb;

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM " . YAC_Resource_Entitlements_Table::table_name() . "
                 WHERE user_id = %d
                 AND resource_id = %d
                 LIMIT 1",
                (int) $user_id,
                (int) $resource_id
            )
        );

        if ($existing) {
            return (int) $existing;
        }

        $inserted = $wpdb->insert(
            YAC_Resource_Entitlements_Table::table_name(),
            [
                'user_id'     => (int) $user_id,
                'resource_id' => (int) $resource_id,
                'order_id'    => (int) $order_id,
                'payment_id'  => (int) $payment_id,
                'granted_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
            ]
        );

        if (!$inserted) {
            return false;
        }

        return (int) $wpdb->insert_id;

    }

    public static function grant_entitlement_for_order($order) {

        if (
            ($order['order_source'] ?? 'woocommerce_product') !== 'resource' ||
            empty($order['resource_id'])
        ) {
            return null;
        }

        $resource = self::find_raw($order['resource_id']);

        if (!$resource) {
            return null;
        }

        $payment = self::verified_payment_for_order($order['id'], $order['user_id']);

        if (!$payment) {
            return new WP_Error(
                'yac_payment_not_verified',
                'Cannot fulfil a digital resource order before payment is verified.',
                [
                    'status' => 422,
                ]
            );
        }

        return self::grant_entitlement(
            $order['user_id'],
            $order['resource_id'],
            $order['id'],
            $payment['id']
        );

    }

    private static function select_sql() {

        return "r.*,
            f.original_name AS file_original_name,
            f.file_name AS file_name,
            f.mime_type AS file_mime_type,
            f.file_size AS file_size,
            f.file_type AS file_type,
            aud.audiences_csv AS audiences_csv,
            e.id AS entitlement_id,
            e.order_id AS entitlement_order_id,
            e.payment_id AS entitlement_payment_id,
            e.granted_at AS entitlement_granted_at";

    }

    private static function admin_select_sql() {

        return "r.*,
            f.original_name AS file_original_name,
            f.file_name AS file_name,
            f.mime_type AS file_mime_type,
            f.file_size AS file_size,
            f.file_type AS file_type,
            aud.audiences_csv AS audiences_csv,
            COALESCE(ent.entitlement_count, 0) AS entitlement_count,
            COALESCE(ent.purchaser_count, 0) AS purchaser_count,
            COALESCE(ord.order_count, 0) AS order_count";

    }

    private static function audience_join_sql() {

        return "LEFT JOIN (
            SELECT resource_id, GROUP_CONCAT(audience ORDER BY audience SEPARATOR ',') AS audiences_csv
            FROM " . YAC_Resource_Audiences_Table::table_name() . "
            GROUP BY resource_id
        ) aud ON aud.resource_id = r.id";

    }

    private static function entitlement_join_sql($user_id) {

        if (!$user_id) {
            return "LEFT JOIN " . YAC_Resource_Entitlements_Table::table_name() . " e ON 1 = 0";
        }

        global $wpdb;

        return $wpdb->prepare(
            "LEFT JOIN " . YAC_Resource_Entitlements_Table::table_name() . " e
                ON e.resource_id = r.id
                AND e.user_id = %d",
            (int) $user_id
        );

    }

    private static function catalog_where_sql($user_id) {

        if (!$user_id || user_can($user_id, 'manage_options')) {
            return [
                'sql'     => '',
                'and_sql' => '',
                'params'  => [],
            ];
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        $visibility = self::combined_visibility_sql($profile);

        return [
            'sql'     => "WHERE (
                {$visibility['sql']}
                OR e.id IS NOT NULL
            )
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'and_sql' => "AND (
                {$visibility['sql']}
                OR e.id IS NOT NULL
            )
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'params'  => $visibility['params'],
        ];

    }

    private static function catalog_discovery_where_sql($user_id) {

        if (!$user_id || user_can($user_id, 'manage_options')) {
            return [
                'sql'     => '',
                'and_sql' => '',
                'params'  => [],
            ];
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);
        $visibility = self::combined_visibility_sql($profile);

        return [
            'sql'     => "WHERE {$visibility['sql']}
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'and_sql' => "AND {$visibility['sql']}
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'params'  => $visibility['params'],
        ];

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
        $free_access = self::free_access_sql($profile);

        return [
            'sql'     => "WHERE (
                {$free_access['sql']}
                OR e.id IS NOT NULL
            )
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'and_sql' => "AND (
                {$free_access['sql']}
                OR e.id IS NOT NULL
            )
            AND (
                r.source_type != 'file'
                OR r.file_id IS NOT NULL
            )",
            'params'  => $free_access['params'],
        ];

    }

    private static function visibility_sql($profile) {

        if (!$profile) {
            return [
                'sql'    => "(
                    r.is_public = 1
                    AND NOT EXISTS (
                        SELECT 1
                        FROM " . YAC_Resource_Audiences_Table::table_name() . " ran
                        WHERE ran.resource_id = r.id
                    )
                )",
                'params' => [],
            ];
        }

        $profile_type = $profile['profile_type'];
        $exam_type = self::profile_exam_type($profile);
        $audience = self::profile_audience($profile_type);

        if ($audience === 'exam_candidate') {
            return [
                'sql'    => "(
                    r.is_public = 1
                    AND
                    EXISTS (
                        SELECT 1
                        FROM " . YAC_Resource_Audiences_Table::table_name() . " ra
                        WHERE ra.resource_id = r.id
                        AND ra.audience = 'exam_candidate'
                    )
                    AND (
                        (
                            r.exam_type IS NULL
                            OR r.exam_type = ''
                            OR r.exam_type = %s
                        )
                        AND (
                            r.exam_level IS NULL
                            OR r.exam_level = ''
                            OR r.exam_level = %s
                        )
                    )
                )",
                'params' => [
                    $exam_type,
                    self::profile_exam_level($profile),
                ],
            ];
        }

        if ($audience !== '') {
            return [
                'sql'    => "(
                    r.is_public = 1
                    AND EXISTS (
                        SELECT 1
                        FROM " . YAC_Resource_Audiences_Table::table_name() . " ra
                        WHERE ra.resource_id = r.id
                        AND ra.audience = %s
                    )
                )",
                'params' => [
                    $audience,
                ],
            ];
        }

        return [
            'sql'    => "r.is_public = 1",
            'params' => [],
        ];

    }

    private static function legacy_visibility_sql($profile) {

        if (!$profile) {
            return [
                'sql'    => "(
                    r.is_public = 1
                    AND NOT EXISTS (
                        SELECT 1
                        FROM " . YAC_Resource_Audiences_Table::table_name() . " ranl
                        WHERE ranl.resource_id = r.id
                    )
                )",
                'params' => [],
            ];
        }

        $profile_type = $profile['profile_type'];
        $exam_type = self::profile_exam_type($profile);

        if (in_array($profile_type, ['cfa_candidate', 'frm_candidate'], true)) {
            return [
                'sql'    => "(
                    (
                        r.is_public = 1
                        AND r.profile_type = %s
                        AND NOT EXISTS (
                            SELECT 1
                            FROM " . YAC_Resource_Audiences_Table::table_name() . " ra0
                            WHERE ra0.resource_id = r.id
                        )
                        AND (
                            (
                                r.exam_type IS NULL
                                OR r.exam_type = ''
                                OR r.exam_type = %s
                            )
                            AND (
                                r.exam_level IS NULL
                                OR r.exam_level = ''
                                OR r.exam_level = %s
                            )
                        )
                    )
                    OR (
                        r.is_public = 1
                        AND r.profile_type = 'exam_candidate'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM " . YAC_Resource_Audiences_Table::table_name() . " ra1
                            WHERE ra1.resource_id = r.id
                        )
                        AND (
                            (
                                r.exam_type IS NULL
                                OR r.exam_type = ''
                                OR r.exam_type = %s
                            )
                            AND (
                                r.exam_level IS NULL
                                OR r.exam_level = ''
                                OR r.exam_level = %s
                            )
                        )
                    )
                )",
                'params' => [
                    $profile_type,
                    $exam_type,
                    self::profile_exam_level($profile),
                    $exam_type,
                    self::profile_exam_level($profile),
                ],
            ];
        }

        return [
            'sql'    => "(
                r.is_public = 1
                AND r.profile_type = %s
                AND NOT EXISTS (
                    SELECT 1
                    FROM " . YAC_Resource_Audiences_Table::table_name() . " ra2
                    WHERE ra2.resource_id = r.id
                )
                AND (
                    (
                        r.exam_type IS NULL
                        OR r.exam_type = ''
                        OR r.exam_type = %s
                    )
                    AND (
                        r.exam_level IS NULL
                        OR r.exam_level = ''
                        OR r.exam_level = %s
                    )
                )
            )",
            'params' => [
                $profile_type,
                $exam_type,
                self::profile_exam_level($profile),
            ],
        ];

    }

    private static function free_access_sql($profile) {

        $visibility = self::combined_visibility_sql($profile);

        return [
            'sql'    => "(
                {$visibility['sql']}
                AND r.price <= 0
            )",
            'params' => $visibility['params'],
        ];

    }

    private static function combined_visibility_sql($profile) {

        $visibility = self::visibility_sql($profile);
        $legacy = self::legacy_visibility_sql($profile);

        return [
            'sql'    => "(
                {$visibility['sql']}
                OR {$legacy['sql']}
            )",
            'params' => array_merge($visibility['params'], $legacy['params']),
        ];

    }

    private static function format($resource, $user_id = null) {

        $is_purchased = !empty($resource['entitlement_id']);
        $price = isset($resource['price']) ? (float) $resource['price'] : 0.0;
        $is_paid = $price > 0;
        $is_buyable = $is_paid && !$is_purchased;
        $has_direct_access = self::resource_has_direct_access($resource, $user_id);
        $is_accessible = $has_direct_access || $is_purchased;
        $access_state = $is_accessible
            ? ($is_purchased ? 'purchased' : 'accessible')
            : ($is_buyable ? 'buyable' : 'restricted');

        return [
            'id'            => (int) $resource['id'],
            'title'         => $resource['title'],
            'description'   => $resource['description'],
            'category'      => $resource['category'],
            'source_type'   => $resource['source_type'],
            'file_id'       => $is_accessible && !empty($resource['file_id'])
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
            'external_url'  => $is_accessible ? $resource['external_url'] : null,
            'price'         => $price,
            'currency'      => $resource['currency'] ?? get_option('yac_bank_currency', 'NGN'),
            'is_paid'       => $is_paid ? 1 : 0,
            'is_buyable'    => $is_buyable ? 1 : 0,
            'is_purchased'  => $is_purchased ? 1 : 0,
            'is_accessible' => $is_accessible ? 1 : 0,
            'access_state'  => $access_state,
            'entitlement'   => $is_purchased
                ? [
                    'id'         => (int) $resource['entitlement_id'],
                    'order_id'   => (int) $resource['entitlement_order_id'],
                    'payment_id' => (int) $resource['entitlement_payment_id'],
                    'granted_at' => $resource['entitlement_granted_at'],
                ]
                : null,
            'profile_type'  => $resource['profile_type'],
            'audiences'     => self::resource_audiences($resource),
            'exam_type'     => $resource['exam_type'],
            'exam_level'    => $resource['exam_level'] ?? null,
            'is_public'     => (int) $resource['is_public'],
            'created_at'    => $resource['created_at'],
            'updated_at'    => $resource['updated_at'],
        ];

    }

    private static function resource_has_direct_access($resource, $user_id = null) {

        if ($user_id && user_can($user_id, 'manage_options')) {
            return true;
        }

        if (isset($resource['price']) && (float) $resource['price'] > 0) {
            return false;
        }

        if (!$user_id) {
            return !empty($resource['is_public']) && empty(self::resource_audiences($resource));
        }

        if (empty($resource['is_public'])) {
            return false;
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        return self::profile_matches_resource($profile, $resource);

    }

    private static function profile_matches_resource($profile, $resource) {

        if (!$profile) {
            return false;
        }

        $audiences = self::resource_audiences($resource);
        $audience = self::profile_audience($profile['profile_type']);

        if (!empty($audiences)) {
            if (!in_array($audience, $audiences, true)) {
                return false;
            }

            if ($audience === 'exam_candidate') {
                return self::resource_exam_matches_profile($resource, $profile);
            }

            return true;
        }

        $profile_type = $profile['profile_type'];
        $resource_profile_type = $resource['profile_type'] ?? '';

        if ($profile_type === $resource_profile_type) {
            return self::resource_exam_matches_profile($resource, $profile);
        }

        if (
            $resource_profile_type === 'exam_candidate' &&
            in_array($profile_type, ['cfa_candidate', 'frm_candidate'], true)
        ) {
            return self::resource_exam_matches_profile($resource, $profile);
        }

        return false;

    }

    private static function resource_exam_matches_profile($resource, $profile) {

        if (empty($resource['exam_type']) && empty($resource['exam_level'])) {
            return true;
        }

        if (!empty($resource['exam_type']) && $resource['exam_type'] !== self::profile_exam_type($profile)) {
            return false;
        }

        if (!empty($resource['exam_level']) && $resource['exam_level'] !== self::profile_exam_level($profile)) {
            return false;
        }

        return true;

    }

    private static function profile_exam_type($profile) {

        if (($profile['profile_type'] ?? '') === 'cfa_candidate') {
            return 'CFA';
        }

        if (($profile['profile_type'] ?? '') === 'frm_candidate') {
            return 'FRM';
        }

        return $profile['exam_type'] ?? '';

    }

    private static function profile_exam_level($profile) {

        if (empty($profile['exam_level'])) {
            return '';
        }

        $exam_type = self::profile_exam_type($profile);

        if (class_exists('YAC_Tutor_Service')) {
            return YAC_Tutor_Service::normalize_level($profile['exam_level'], $exam_type);
        }

        return sanitize_key($profile['exam_level']);

    }

    public static function allowed_audiences() {

        return [
            'academic',
            'exam_candidate',
            'corporate',
        ];

    }

    public static function normalize_audiences($audiences) {

        if (!is_array($audiences)) {
            $audiences = [$audiences];
        }

        $normalized = [];

        foreach ($audiences as $audience) {
            $audience = sanitize_key((string) $audience);

            if ($audience === 'academic_user') {
                $audience = 'academic';
            } elseif ($audience === 'corporate_client') {
                $audience = 'corporate';
            } elseif (in_array($audience, ['cfa_candidate', 'frm_candidate'], true)) {
                $audience = 'exam_candidate';
            }

            if (in_array($audience, self::allowed_audiences(), true)) {
                $normalized[] = $audience;
            }
        }

        return array_values(array_unique($normalized));

    }

    private static function resource_audiences($resource) {

        if (empty($resource['audiences_csv'])) {
            return [];
        }

        return self::normalize_audiences(explode(',', $resource['audiences_csv']));

    }

    private static function profile_audience($profile_type) {

        if ($profile_type === 'academic_user') {
            return 'academic';
        }

        if (in_array($profile_type, ['exam_candidate', 'cfa_candidate', 'frm_candidate'], true)) {
            return 'exam_candidate';
        }

        if ($profile_type === 'corporate_client') {
            return 'corporate';
        }

        return '';

    }

    private static function format_admin($resource) {

        $price = isset($resource['price']) ? (float) $resource['price'] : 0.0;
        $file_id = !empty($resource['file_id']) ? (int) $resource['file_id'] : null;

        return [
            'id'                => (int) $resource['id'],
            'title'             => $resource['title'],
            'description'       => $resource['description'],
            'category'          => $resource['category'],
            'source_type'       => $resource['source_type'],
            'file_id'           => $file_id,
            'file'              => $file_id
                ? [
                    'file_id'       => $file_id,
                    'file_name'     => $resource['file_name'] ?? null,
                    'original_name' => $resource['file_original_name'] ?? null,
                    'mime_type'     => $resource['file_mime_type'] ?? null,
                    'file_size'     => isset($resource['file_size']) && $resource['file_size'] !== null
                        ? (int) $resource['file_size']
                        : null,
                    'download_url'  => rest_url('yac/v1/files/' . $file_id . '/download'),
                ]
                : null,
            'external_url'      => $resource['external_url'],
            'price'             => $price,
            'currency'          => $resource['currency'] ?? get_option('yac_bank_currency', 'NGN'),
            'is_paid'           => $price > 0 ? 1 : 0,
            'profile_type'      => $resource['profile_type'],
            'audiences'         => self::resource_audiences($resource),
            'exam_type'         => $resource['exam_type'],
            'exam_level'        => $resource['exam_level'] ?? null,
            'is_public'         => (int) $resource['is_public'],
            'visibility'        => !empty($resource['is_public']) ? 'public' : 'private',
            'created_at'        => $resource['created_at'],
            'updated_at'        => $resource['updated_at'],
            'entitlement_count' => isset($resource['entitlement_count'])
                ? (int) $resource['entitlement_count']
                : 0,
            'purchaser_count'   => isset($resource['purchaser_count'])
                ? (int) $resource['purchaser_count']
                : 0,
            'order_count'       => isset($resource['order_count'])
                ? (int) $resource['order_count']
                : 0,
        ];

    }

    private static function pagination_args($args) {

        $page = !empty($args['page'])
            ? max(1, absint($args['page']))
            : 1;

        $per_page = !empty($args['per_page'])
            ? max(1, absint($args['per_page']))
            : 20;

        if ($per_page > 100) {
            $per_page = 100;
        }

        return [
            'page'     => $page,
            'per_page' => $per_page,
            'offset'   => ($page - 1) * $per_page,
        ];

    }

    private static function pagination_payload($pagination, $total) {

        return [
            'page'        => $pagination['page'],
            'per_page'    => $pagination['per_page'],
            'total'       => $total,
            'total_pages' => (int) ceil($total / $pagination['per_page']),
        ];

    }

    private static function normalize_exam_filter($value) {

        if ($value === null || $value === '') {
            return '';
        }

        if (class_exists('YAC_Tutor_Service')) {
            return YAC_Tutor_Service::normalize_exam($value);
        }

        $value = strtoupper(sanitize_text_field((string) $value));

        if (strpos($value, 'CFA') !== false) {
            return 'CFA';
        }

        if (strpos($value, 'FRM') !== false) {
            return 'FRM';
        }

        return '';

    }

    private static function normalize_level_filter($value, $exam = '') {

        if ($value === null || $value === '') {
            return '';
        }

        $level = class_exists('YAC_Tutor_Service')
            ? YAC_Tutor_Service::normalize_level($value, $exam ?: null)
            : sanitize_key($value);

        if ($level === '') {
            return new WP_Error('yac_invalid_resource_level_filter', 'Invalid resource level filter.');
        }

        if ($exam === 'CFA' && !in_array($level, ['level_1', 'level_2', 'level_3'], true)) {
            return new WP_Error('yac_invalid_resource_level_filter', 'Level filter is incompatible with CFA.');
        }

        if ($exam === 'FRM' && !in_array($level, ['part_1', 'part_2'], true)) {
            return new WP_Error('yac_invalid_resource_level_filter', 'Level filter is incompatible with FRM.');
        }

        return $level;

    }

}
