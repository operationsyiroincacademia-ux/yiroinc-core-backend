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

        $where = self::catalog_where_sql($user_id);

        $query = "SELECT " . self::select_sql() . "
            FROM " . self::table() . " r
            LEFT JOIN " . YAC_Files_Table::table_name() . " f
                ON f.id = r.file_id
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
            e.id AS entitlement_id,
            e.order_id AS entitlement_order_id,
            e.payment_id AS entitlement_payment_id,
            e.granted_at AS entitlement_granted_at";

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

        $visibility = self::visibility_sql($profile);

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
                'sql'    => "r.is_public = 1",
                'params' => [],
            ];
        }

        $profile_type = $profile['profile_type'];
        $exam_type = self::profile_exam_type($profile);

        if (in_array($profile_type, ['cfa_candidate', 'frm_candidate'], true)) {
            return [
                'sql'    => "(
                    r.is_public = 1
                    OR (
                        r.profile_type = %s
                        AND (
                            r.exam_type IS NULL
                            OR r.exam_type = ''
                            OR r.exam_type = %s
                        )
                    )
                    OR (
                        r.profile_type = 'exam_candidate'
                        AND (
                            r.exam_type IS NULL
                            OR r.exam_type = ''
                            OR r.exam_type = %s
                        )
                    )
                )",
                'params' => [
                    $profile_type,
                    $exam_type,
                    $exam_type,
                ],
            ];
        }

        return [
            'sql'    => "(
                r.is_public = 1
                OR (
                    r.profile_type = %s
                    AND (
                        r.exam_type IS NULL
                        OR r.exam_type = ''
                        OR r.exam_type = %s
                    )
                )
            )",
            'params' => [
                $profile_type,
                $exam_type,
            ],
        ];

    }

    private static function free_access_sql($profile) {

        $visibility = self::visibility_sql($profile);

        return [
            'sql'    => "(
                {$visibility['sql']}
                AND r.price <= 0
            )",
            'params' => $visibility['params'],
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
            'exam_type'     => $resource['exam_type'],
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

        if (!empty($resource['is_public'])) {
            return true;
        }

        if (!$user_id || empty($resource['profile_type'])) {
            return false;
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        return self::profile_matches_resource($profile, $resource);

    }

    private static function profile_matches_resource($profile, $resource) {

        if (!$profile || empty($resource['profile_type'])) {
            return false;
        }

        $profile_type = $profile['profile_type'];
        $resource_profile_type = $resource['profile_type'];

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

        if (empty($resource['exam_type'])) {
            return true;
        }

        return $resource['exam_type'] === self::profile_exam_type($profile);

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

}
