<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Admin_Service {

    /**
     * Complete admin dashboard payload.
     *
     * @return array
     */
    public static function dashboard_payload() {

        return [
            'summary'                     => self::dashboard(),
            'recent_activity'             => self::recent_activity(),
            'pending_payments'            => self::pending_payments(),
            'pending_tutor_requests'      => self::pending_tutor_requests(),
            'pending_consulting_requests' => self::pending_consulting_requests(),
            'pending_procurements'        => self::pending_procurements(),
        ];

    }

    /**
     * Dashboard summary.
     *
     * @return array
     */
    public static function dashboard() {

        global $wpdb;

        return [

            'users' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Profiles_Table::table_name()
            ),

            'resources' => [
                'total' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name()
                ),
                'free' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name() . "
                     WHERE price = 0"
                ),
                'paid' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Resources_Table::table_name() . "
                     WHERE price > 0"
                ),
            ],

            'orders' => [
                'awaiting_payment' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'awaiting_payment'"
                ),
                'under_review' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'under_review'"
                ),
                'processing' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'processing'"
                ),
                'completed' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'completed'"
                ),
                'cancelled' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Orders_Table::table_name() . "
                     WHERE order_status = 'cancelled'"
                ),
            ],

            'payments' => [
                'awaiting_verification' => self::awaiting_verification_payment_count(),
                'verified' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Payments_Table::table_name() . "
                     WHERE payment_status = 'verified'"
                ),
                'rejected' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . YAC_Payments_Table::table_name() . "
                     WHERE payment_status = 'rejected'"
                ),
            ],

            'pending_tutor_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Tutor_Requests_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'pending_consulting_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Consulting_Requests_Table::table_name() . "
                 WHERE status = 'pending'"
            ),

            'pending_procurements' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE status = 'pending'"
            ),
        ];

    }

    /**
     * Recent activity.
     *
     * @param int $limit
     * @return array
     */
    public static function recent_activity($limit = 20) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Timeline_Table::table_name() . "
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

    }

    /**
     * Pending payments.
     *
     * @return array
     */
    public static function pending_payments() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Payments_Table::table_name() . "
             WHERE payment_status IN ('pending', 'submitted')
             AND has_pop = 1
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Admin payments list.
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function payments($args = []) {

        global $wpdb;

        $status = !empty($args['status'])
            ? sanitize_key($args['status'])
            : 'awaiting_verification';

        $allowed_statuses = [
            'all',
            'awaiting_verification',
            'verified',
            'rejected',
        ];

        if (!in_array($status, $allowed_statuses, true)) {
            return new WP_Error('yac_invalid_payment_filter', 'Invalid payment status filter.');
        }

        $page = !empty($args['page'])
            ? max(1, absint($args['page']))
            : 1;

        $per_page = !empty($args['per_page'])
            ? max(1, absint($args['per_page']))
            : 20;

        if ($per_page > 100) {
            $per_page = 100;
        }

        $offset = ($page - 1) * $per_page;

        $where = [];
        $params = [];

        if ($status === 'all') {
            $where[] = "(
                (
                    p.payment_status IN ('pending', 'submitted')
                    AND p.has_pop = 1
                )
                OR p.payment_status = 'verified'
                OR p.payment_status = 'rejected'
            )";
        } elseif ($status === 'awaiting_verification') {
            $where[] = "p.payment_status IN ('pending', 'submitted')";
            $where[] = 'p.has_pop = 1';
        } elseif ($status === 'verified') {
            $where[] = 'p.payment_status = %s';
            $params[] = 'verified';
        } elseif ($status === 'rejected') {
            $where[] = 'p.payment_status = %s';
            $params[] = 'rejected';
        }

        $search = !empty($args['search'])
            ? sanitize_text_field($args['search'])
            : '';

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';

            $where[] = '(
                p.payment_reference LIKE %s
                OR o.order_number LIKE %s
                OR CAST(p.order_id AS CHAR) LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $from_sql = "
            FROM " . YAC_Payments_Table::table_name() . " p
            LEFT JOIN " . YAC_Orders_Table::table_name() . " o
                ON o.id = p.order_id
            LEFT JOIN {$wpdb->users} u
                ON u.ID = p.user_id
        ";

        $query = "
            SELECT
                p.id,
                p.payment_reference,
                p.order_id,
                p.user_id,
                p.payment_method,
                p.amount_paid,
                p.currency,
                p.has_pop,
                p.payment_status,
                p.submitted_at,
                p.verified_at,
                p.rejected_at,
                p.rejection_reason,
                p.created_at,
                o.order_number,
                o.order_status,
                o.payment_status AS order_payment_status,
                o.product_name_snapshot,
                u.display_name AS customer_display_name
            {$from_sql}
            {$where_sql}
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT %d OFFSET %d
        ";

        $query_params = array_merge($params, [$per_page, $offset]);

        $payments = $wpdb->get_results(
            $wpdb->prepare($query, ...$query_params),
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
            'payments' => array_map([self::class, 'format_admin_payment'], $payments),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ];

    }

    /**
     * Admin payment detail.
     *
     * @param int $payment_id
     * @return array|WP_Error
     */
    public static function payment_detail($payment_id) {

        global $wpdb;

        $payment_id = absint($payment_id);

        if (!$payment_id) {
            return new WP_Error('yac_invalid_payment_id', 'Invalid payment ID.');
        }

        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    payment_reference,
                    order_id,
                    user_id,
                    payment_method,
                    amount_paid,
                    currency,
                    has_pop,
                    payment_status,
                    user_note,
                    submitted_at,
                    verified_at,
                    rejected_at,
                    rejection_reason,
                    created_at,
                    updated_at
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE id = %d",
                $payment_id
            ),
            ARRAY_A
        );

        if (!$payment) {
            return new WP_Error('yac_payment_not_found', 'Payment not found.');
        }

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    order_number,
                    user_id,
                    order_source,
                    resource_id,
                    product_name_snapshot,
                    sku_snapshot,
                    quantity,
                    unit_price,
                    total_price,
                    currency,
                    order_status,
                    payment_status,
                    fulfillment_status,
                    customer_note,
                    created_at,
                    updated_at
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d",
                (int) $payment['order_id']
            ),
            ARRAY_A
        );

        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    ID AS user_id,
                    display_name,
                    user_email
                 FROM {$wpdb->users}
                 WHERE ID = %d",
                (int) $payment['user_id']
            ),
            ARRAY_A
        );

        return [
            'payment'  => self::format_admin_payment($payment),
            'order'    => $order ? self::format_admin_order($order) : null,
            'customer' => $customer ? self::format_admin_customer($customer) : null,
            'proof'    => self::latest_payment_proof($payment_id),
            'activity' => YAC_Timeline_Service::payment_activity($payment_id),
        ];

    }

    /**
     * Latest proof of payment metadata.
     *
     * @param int $payment_id
     * @return array|null
     */
    private static function latest_payment_proof($payment_id) {

        global $wpdb;

        $proof = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    file_name,
                    original_name,
                    mime_type,
                    file_size,
                    created_at
                 FROM " . YAC_Files_Table::table_name() . "
                 WHERE related_type = %s
                 AND related_id = %d
                 AND file_type = %s
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1",
                'payment',
                $payment_id,
                'proof_of_payment'
            ),
            ARRAY_A
        );

        if (!$proof) {
            return null;
        }

        return [
            'file_id'       => (int) $proof['id'],
            'file_name'     => $proof['file_name'],
            'original_name' => $proof['original_name'],
            'mime_type'     => $proof['mime_type'],
            'file_size'     => (int) $proof['file_size'],
            'created_at'    => $proof['created_at'],
            'download_url'  => rest_url('yac/v1/files/' . (int) $proof['id'] . '/download'),
        ];

    }

    /**
     * Format admin payment payload.
     *
     * @param array $payment
     * @return array
     */
    private static function format_admin_payment($payment) {

        $formatted = [
            'id'                => (int) $payment['id'],
            'payment_reference' => $payment['payment_reference'],
            'order_id'          => (int) $payment['order_id'],
            'user_id'           => (int) $payment['user_id'],
            'payment_method'    => $payment['payment_method'],
            'amount_paid'       => (float) $payment['amount_paid'],
            'currency'          => $payment['currency'],
            'has_pop'           => (int) $payment['has_pop'],
            'payment_status'    => $payment['payment_status'],
            'submitted_at'      => $payment['submitted_at'],
            'verified_at'       => $payment['verified_at'],
            'rejected_at'       => $payment['rejected_at'],
            'rejection_reason'  => $payment['rejection_reason'],
            'created_at'        => $payment['created_at'],
        ];

        foreach (['updated_at', 'user_note'] as $field) {
            if (array_key_exists($field, $payment)) {
                $formatted[$field] = $payment[$field];
            }
        }

        foreach (
            [
                'order_number',
                'order_status',
                'order_payment_status',
                'product_name_snapshot',
                'customer_display_name',
            ] as $field
        ) {
            if (array_key_exists($field, $payment)) {
                $formatted[$field] = $payment[$field];
            }
        }

        return $formatted;

    }

    /**
     * Format admin order payload.
     *
     * @param array $order
     * @return array
     */
    private static function format_admin_order($order) {

        return [
            'id'                    => (int) $order['id'],
            'order_number'          => $order['order_number'],
            'user_id'               => (int) $order['user_id'],
            'order_source'          => $order['order_source'],
            'resource_id'           => !empty($order['resource_id']) ? (int) $order['resource_id'] : null,
            'product_name_snapshot' => $order['product_name_snapshot'],
            'sku_snapshot'          => $order['sku_snapshot'],
            'quantity'              => (int) $order['quantity'],
            'unit_price'            => (float) $order['unit_price'],
            'total_price'           => (float) $order['total_price'],
            'currency'              => $order['currency'],
            'order_status'          => $order['order_status'],
            'payment_status'        => $order['payment_status'],
            'fulfillment_status'    => $order['fulfillment_status'],
            'customer_note'         => $order['customer_note'],
            'created_at'            => $order['created_at'],
            'updated_at'            => $order['updated_at'],
        ];

    }

    /**
     * Format admin customer payload.
     *
     * @param array $customer
     * @return array
     */
    private static function format_admin_customer($customer) {

        return [
            'user_id'      => (int) $customer['user_id'],
            'display_name' => $customer['display_name'],
            'email'        => $customer['user_email'],
        ];

    }

    /**
     * Admin orders list.
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function orders($args = []) {

        global $wpdb;

        $status = !empty($args['status'])
            ? sanitize_key($args['status'])
            : 'all';

        $allowed_statuses = [
            'all',
            'awaiting_payment',
            'paid',
            'completed',
        ];

        if (!in_array($status, $allowed_statuses, true)) {
            return new WP_Error('yac_invalid_order_filter', 'Invalid order status filter.');
        }

        $pagination = self::pagination_args($args);
        $where = [];
        $params = [];

        if ($status === 'awaiting_payment') {
            $where[] = 'COALESCE(p.has_pop, 0) = 0';
            $where[] = "o.order_status != 'completed'";
        } elseif ($status === 'paid') {
            $where[] = 'p.has_pop = 1';
            $where[] = "o.order_status != 'completed'";
        } elseif ($status === 'completed') {
            $where[] = "o.order_status = 'completed'";
        }

        $search = !empty($args['search'])
            ? sanitize_text_field($args['search'])
            : '';

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(
                o.order_number LIKE %s
                OR o.product_name_snapshot LIKE %s
                OR o.sku_snapshot LIKE %s
                OR u.display_name LIKE %s
                OR u.user_email LIKE %s
                OR CAST(o.id AS CHAR) LIKE %s
            )';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $orders_table   = YAC_Orders_Table::table_name();
        $payments_table = YAC_Payments_Table::table_name();

        $from_sql = "
            FROM {$orders_table} o
            LEFT JOIN {$wpdb->users} u
                ON u.ID = o.user_id
            LEFT JOIN {$payments_table} p
                ON p.id = (
                    SELECT p2.id
                    FROM {$payments_table} p2
                    WHERE p2.order_id = o.id
                    AND p2.user_id = o.user_id
                    ORDER BY p2.created_at DESC, p2.id DESC
                    LIMIT 1
                )
        ";

        $query = "
            SELECT
                o.id,
                o.order_number,
                o.user_id,
                o.order_source,
                o.woo_product_id,
                o.woo_variation_id,
                o.resource_id,
                o.product_name_snapshot,
                o.sku_snapshot,
                o.quantity,
                o.total_price,
                o.currency,
                o.order_status,
                o.payment_status,
                o.fulfillment_status,
                o.created_at,
                p.id AS payment_id,
                p.payment_status AS related_payment_status,
                p.has_pop,
                u.display_name AS customer_display_name,
                u.user_email AS customer_email
            {$from_sql}
            {$where_sql}
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT %d OFFSET %d
        ";

        $orders = $wpdb->get_results(
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
            'orders' => array_map([self::class, 'format_admin_order_row'], $orders),
            'pagination' => self::pagination_payload($pagination, $total),
        ];

    }

    /**
     * Admin order detail.
     *
     * @param int $order_id
     * @return array|WP_Error
     */
    public static function order_detail($order_id) {

        global $wpdb;

        $order_id = absint($order_id);

        if (!$order_id) {
            return new WP_Error('yac_invalid_order_id', 'Invalid order ID.');
        }

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d",
                $order_id
            ),
            ARRAY_A
        );

        if (!$order) {
            return new WP_Error('yac_order_not_found', 'Order not found.');
        }

        $customer = self::customer_for_user((int) $order['user_id']);
        $payment = self::latest_payment_for_order($order_id, (int) $order['user_id']);
        $proof = $payment && !empty($payment['has_pop'])
            ? self::latest_order_payment_proof((int) $payment['id'])
            : null;

        return [
            'order'    => self::format_admin_order_detail($order, $payment),
            'customer' => $customer,
            'payment'  => $payment ? self::format_admin_payment($payment) : null,
            'proof'    => $proof,
            'item'     => self::order_item($order),
            'timeline' => self::timeline_for(
                ['order'],
                $order_id,
                $payment ? ['payment' => (int) $payment['id']] : []
            ),
        ];

    }

    /**
     * Admin tutor requests list.
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function tutor_requests($args = []) {

        return self::request_list(
            [
                'table'        => YAC_Tutor_Requests_Table::table_name(),
                'statuses'     => YAC_Status_Service::tutor_request_statuses(),
                'default'      => 'pending',
                'result_key'   => 'tutor_requests',
                'type'         => 'tutor_request',
                'search_cols'  => ['r.exam_type', 'r.exam_level', 'r.preferred_timezone', 'r.preferred_language', 'r.additional_notes'],
                'select_extra' => 'r.exam_type, r.exam_level, r.preferred_timezone, r.preferred_language, r.assigned_tutor_id, r.matched_at',
                'formatter'    => [self::class, 'format_tutor_request_row'],
            ],
            $args
        );

    }

    /**
     * Admin tutor request detail.
     *
     * @param int $request_id
     * @return array|WP_Error
     */
    public static function tutor_request_detail($request_id) {

        $detail = self::request_detail(
            YAC_Tutor_Requests_Table::table_name(),
            'tutor_request',
            $request_id,
            [self::class, 'format_tutor_request_detail']
        );

        if (is_wp_error($detail)) {
            return $detail;
        }

        $detail['tutor'] = !empty($detail['request']['assigned_tutor_id'])
            ? YAC_Tutor_Service::format_admin(YAC_Tutor_Service::find((int) $detail['request']['assigned_tutor_id']))
            : null;

        return $detail;

    }

    /**
     * Admin consulting requests list.
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function consulting_requests($args = []) {

        return self::request_list(
            [
                'table'        => YAC_Consulting_Requests_Table::table_name(),
                'statuses'     => YAC_Status_Service::consulting_request_statuses(),
                'default'      => 'pending',
                'result_key'   => 'consulting_requests',
                'type'         => 'consulting_request',
                'search_cols'  => ['r.service_type', 'r.organization_name', 'r.contact_person', 'r.contact_email', 'r.contact_phone', 'r.project_summary'],
                'select_extra' => 'r.service_type, r.organization_name, r.contact_person, r.contact_email, r.budget, r.preferred_date',
                'formatter'    => [self::class, 'format_consulting_request_row'],
            ],
            $args
        );

    }

    /**
     * Admin consulting request detail.
     *
     * @param int $request_id
     * @return array|WP_Error
     */
    public static function consulting_request_detail($request_id) {

        return self::request_detail(
            YAC_Consulting_Requests_Table::table_name(),
            'consulting_request',
            $request_id,
            [self::class, 'format_consulting_request_detail']
        );

    }

    /**
     * Admin procurements list.
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function procurements($args = []) {

        global $wpdb;

        $status = !empty($args['status'])
            ? sanitize_key($args['status'])
            : 'pending';

        $allowed_statuses = array_merge(['all'], YAC_Status_Service::procurement_statuses());

        if (!in_array($status, $allowed_statuses, true)) {
            return new WP_Error('yac_invalid_procurement_filter', 'Invalid procurement status filter.');
        }

        $pagination = self::pagination_args($args);
        $where = [];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'pr.status = %s';
            $params[] = $status;
        }

        $search = !empty($args['search'])
            ? sanitize_text_field($args['search'])
            : '';

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(
                pr.procurement_reference LIKE %s
                OR pr.supplier_name LIKE %s
                OR pr.tracking_number LIKE %s
                OR pr.courier LIKE %s
                OR o.order_number LIKE %s
                OR u.display_name LIKE %s
                OR u.user_email LIKE %s
                OR CAST(pr.id AS CHAR) LIKE %s
            )';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $query = "
            SELECT
                pr.id,
                pr.order_id,
                pr.user_id,
                pr.procurement_reference,
                pr.supplier_name,
                pr.tracking_number,
                pr.courier,
                pr.status,
                pr.expected_delivery_date,
                pr.created_at,
                pr.updated_at,
                o.order_number,
                o.product_name_snapshot,
                u.display_name AS customer_display_name,
                u.user_email AS customer_email
            FROM " . YAC_Procurements_Table::table_name() . " pr
            LEFT JOIN " . YAC_Orders_Table::table_name() . " o
                ON o.id = pr.order_id
            LEFT JOIN {$wpdb->users} u
                ON u.ID = pr.user_id
            {$where_sql}
            ORDER BY pr.created_at DESC, pr.id DESC
            LIMIT %d OFFSET %d
        ";

        $procurements = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                ...array_merge($params, [$pagination['per_page'], $pagination['offset']])
            ),
            ARRAY_A
        );

        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Procurements_Table::table_name() . " pr
            LEFT JOIN " . YAC_Orders_Table::table_name() . " o
                ON o.id = pr.order_id
            LEFT JOIN {$wpdb->users} u
                ON u.ID = pr.user_id
            {$where_sql}
        ";

        $total = !empty($params)
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params))
            : (int) $wpdb->get_var($count_query);

        return [
            'procurements' => array_map([self::class, 'format_procurement_row'], $procurements),
            'pagination' => self::pagination_payload($pagination, $total),
        ];

    }

    /**
     * Admin procurement detail.
     *
     * @param int $procurement_id
     * @return array|WP_Error
     */
    public static function procurement_detail($procurement_id) {

        global $wpdb;

        $procurement_id = absint($procurement_id);

        if (!$procurement_id) {
            return new WP_Error('yac_invalid_procurement_id', 'Invalid procurement ID.');
        }

        $procurement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Procurements_Table::table_name() . "
                 WHERE id = %d",
                $procurement_id
            ),
            ARRAY_A
        );

        if (!$procurement) {
            return new WP_Error('yac_procurement_not_found', 'Procurement not found.');
        }

        return [
            'procurement' => self::format_procurement_detail($procurement),
            'customer'    => self::customer_for_user((int) $procurement['user_id']),
            'order'       => self::order_summary((int) $procurement['order_id']),
            'timeline'    => self::timeline_for(['procurement'], $procurement_id),
        ];

    }

    /**
     * Generic admin request list.
     *
     * @param array $config
     * @param array $args
     * @return array|WP_Error
     */
    private static function request_list($config, $args) {

        global $wpdb;

        $status = !empty($args['status'])
            ? sanitize_key($args['status'])
            : $config['default'];

        $allowed_statuses = array_merge(['all'], $config['statuses']);

        if (!in_array($status, $allowed_statuses, true)) {
            return new WP_Error('yac_invalid_request_filter', 'Invalid request status filter.');
        }

        $pagination = self::pagination_args($args);
        $where = [];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'r.status = %s';
            $params[] = $status;
        }

        $search = !empty($args['search'])
            ? sanitize_text_field($args['search'])
            : '';

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_clauses = [];

            foreach ($config['search_cols'] as $column) {
                $search_clauses[] = "{$column} LIKE %s";
                $params[] = $like;
            }

            $search_clauses[] = 'u.display_name LIKE %s';
            $search_clauses[] = 'u.user_email LIKE %s';
            $search_clauses[] = 'CAST(r.id AS CHAR) LIKE %s';
            $params = array_merge($params, [$like, $like, $like]);

            $where[] = '(' . implode(' OR ', $search_clauses) . ')';
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $query = "
            SELECT
                r.id,
                r.user_id,
                r.status,
                {$config['select_extra']},
                r.created_at,
                r.updated_at,
                u.display_name AS customer_display_name,
                u.user_email AS customer_email
            FROM {$config['table']} r
            LEFT JOIN {$wpdb->users} u
                ON u.ID = r.user_id
            {$where_sql}
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT %d OFFSET %d
        ";

        $requests = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                ...array_merge($params, [$pagination['per_page'], $pagination['offset']])
            ),
            ARRAY_A
        );

        $count_query = "
            SELECT COUNT(*)
            FROM {$config['table']} r
            LEFT JOIN {$wpdb->users} u
                ON u.ID = r.user_id
            {$where_sql}
        ";

        $total = !empty($params)
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params))
            : (int) $wpdb->get_var($count_query);

        return [
            $config['result_key'] => array_map($config['formatter'], $requests),
            'pagination' => self::pagination_payload($pagination, $total),
        ];

    }

    /**
     * Generic admin request detail.
     *
     * @param string $table
     * @param string $related_type
     * @param int $request_id
     * @param callable $formatter
     * @return array|WP_Error
     */
    private static function request_detail($table, $related_type, $request_id, $formatter) {

        global $wpdb;

        $request_id = absint($request_id);

        if (!$request_id) {
            return new WP_Error('yac_invalid_request_id', 'Invalid request ID.');
        }

        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE id = %d",
                $request_id
            ),
            ARRAY_A
        );

        if (!$request) {
            return new WP_Error('yac_request_not_found', 'Request not found.');
        }

        return [
            'request'  => call_user_func($formatter, $request),
            'customer' => self::customer_for_user((int) $request['user_id']),
            'timeline' => self::timeline_for([$related_type], $request_id),
        ];

    }

    /**
     * Pagination args.
     *
     * @param array $args
     * @return array
     */
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

    /**
     * Pagination response payload.
     *
     * @param array $pagination
     * @param int $total
     * @return array
     */
    private static function pagination_payload($pagination, $total) {

        return [
            'page'        => $pagination['page'],
            'per_page'    => $pagination['per_page'],
            'total'       => $total,
            'total_pages' => (int) ceil($total / $pagination['per_page']),
        ];

    }

    /**
     * Customer payload from a WordPress user.
     *
     * @param int $user_id
     * @return array|null
     */
    private static function customer_for_user($user_id) {

        global $wpdb;

        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    ID AS user_id,
                    display_name,
                    user_email
                 FROM {$wpdb->users}
                 WHERE ID = %d",
                $user_id
            ),
            ARRAY_A
        );

        return $customer ? self::format_admin_customer($customer) : null;

    }

    /**
     * Latest payment for an order.
     *
     * @param int $order_id
     * @param int $user_id
     * @return array|null
     */
    private static function latest_payment_for_order($order_id, $user_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    payment_reference,
                    order_id,
                    user_id,
                    payment_method,
                    amount_paid,
                    currency,
                    has_pop,
                    payment_status,
                    user_note,
                    submitted_at,
                    verified_at,
                    rejected_at,
                    rejection_reason,
                    created_at,
                    updated_at
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE order_id = %d
                 AND user_id = %d
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1",
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

    }

    /**
     * Latest proof metadata for the order's current payment.
     *
     * @param int $payment_id
     * @return array|null
     */
    private static function latest_order_payment_proof($payment_id) {

        global $wpdb;

        $proof = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    file_name,
                    original_name,
                    mime_type,
                    file_size,
                    created_at
                 FROM " . YAC_Files_Table::table_name() . "
                 WHERE related_type = %s
                 AND related_id = %d
                 AND file_type = %s
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1",
                'payment',
                $payment_id,
                'proof_of_payment'
            ),
            ARRAY_A
        );

        if (!$proof) {
            return null;
        }

        return [
            'file_id'       => (int) $proof['id'],
            'file_name'     => $proof['file_name'],
            'original_name' => $proof['original_name'],
            'mime_type'     => $proof['mime_type'],
            'file_size'     => (int) $proof['file_size'],
            'created_at'    => $proof['created_at'],
        ];

    }

    /**
     * Admin-facing order status derived from POP submission.
     *
     * @param array $order
     * @param array|null $payment
     * @return string
     */
    private static function admin_order_status($order, $payment = null) {

        if (($order['order_status'] ?? null) === 'completed') {
            return 'completed';
        }

        $payment_context = $payment ?: $order;

        return !empty($payment_context['has_pop'])
            ? 'paid'
            : 'awaiting_payment';

    }

    /**
     * Timeline entries for related records.
     *
     * @param array $primary_types
     * @param int $primary_id
     * @param array $extra
     * @return array
     */
    private static function timeline_for($primary_types, $primary_id, $extra = []) {

        global $wpdb;

        $clauses = [];
        $params = [];

        foreach ($primary_types as $type) {
            $clauses[] = '(related_type = %s AND related_id = %d)';
            $params[] = $type;
            $params[] = $primary_id;
        }

        foreach ($extra as $type => $id) {
            $clauses[] = '(related_type = %s AND related_id = %d)';
            $params[] = $type;
            $params[] = absint($id);
        }

        if (empty($clauses)) {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    user_id,
                    actor_id,
                    event,
                    title,
                    description,
                    related_type,
                    related_id,
                    visibility,
                    created_at
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE " . implode(' OR ', $clauses) . "
                 ORDER BY created_at DESC, id DESC
                 LIMIT 50",
                ...$params
            ),
            ARRAY_A
        );

    }

    /**
     * Admin order table row.
     *
     * @param array $order
     * @return array
     */
    private static function format_admin_order_row($order) {

        return [
            'id'                    => (int) $order['id'],
            'order_number'          => $order['order_number'],
            'user_id'               => (int) $order['user_id'],
            'customer_display_name' => $order['customer_display_name'],
            'customer_email'        => $order['customer_email'],
            'order_source'          => $order['order_source'],
            'woo_product_id'        => !empty($order['woo_product_id']) ? (int) $order['woo_product_id'] : null,
            'woo_variation_id'      => !empty($order['woo_variation_id']) ? (int) $order['woo_variation_id'] : null,
            'resource_id'           => !empty($order['resource_id']) ? (int) $order['resource_id'] : null,
            'product_name_snapshot' => $order['product_name_snapshot'],
            'sku_snapshot'          => $order['sku_snapshot'],
            'quantity'              => (int) $order['quantity'],
            'total_price'           => (float) $order['total_price'],
            'currency'              => $order['currency'],
            'order_status'          => $order['order_status'],
            'admin_order_status'    => self::admin_order_status($order),
            'payment_status'        => $order['payment_status'],
            'fulfillment_status'    => $order['fulfillment_status'],
            'payment_id'            => !empty($order['payment_id']) ? (int) $order['payment_id'] : null,
            'related_payment_status'=> $order['related_payment_status'],
            'has_pop'               => !empty($order['has_pop']) ? (int) $order['has_pop'] : 0,
            'created_at'            => $order['created_at'],
        ];

    }

    /**
     * Admin order detail payload.
     *
     * @param array $order
     * @return array
     */
    private static function format_admin_order_detail($order, $payment = null) {

        return [
            'id'                    => (int) $order['id'],
            'order_number'          => $order['order_number'],
            'user_id'               => (int) $order['user_id'],
            'order_source'          => $order['order_source'],
            'woo_product_id'        => !empty($order['woo_product_id']) ? (int) $order['woo_product_id'] : null,
            'woo_variation_id'      => !empty($order['woo_variation_id']) ? (int) $order['woo_variation_id'] : null,
            'resource_id'           => !empty($order['resource_id']) ? (int) $order['resource_id'] : null,
            'product_name_snapshot' => $order['product_name_snapshot'],
            'sku_snapshot'          => $order['sku_snapshot'],
            'quantity'              => (int) $order['quantity'],
            'unit_price'            => (float) $order['unit_price'],
            'total_price'           => (float) $order['total_price'],
            'currency'              => $order['currency'],
            'order_status'          => $order['order_status'],
            'admin_order_status'    => self::admin_order_status($order, $payment),
            'payment_status'        => $order['payment_status'],
            'fulfillment_status'    => $order['fulfillment_status'],
            'customer_note'         => $order['customer_note'],
            'admin_note'            => $order['admin_note'],
            'created_at'            => $order['created_at'],
            'updated_at'            => $order['updated_at'],
        ];

    }

    /**
     * Order item context.
     *
     * @param array $order
     * @return array
     */
    private static function order_item($order) {

        $item = [
            'type'                  => $order['order_source'],
            'product_name_snapshot' => $order['product_name_snapshot'],
            'sku_snapshot'          => $order['sku_snapshot'],
            'quantity'              => (int) $order['quantity'],
            'unit_price'            => (float) $order['unit_price'],
            'total_price'           => (float) $order['total_price'],
            'currency'              => $order['currency'],
        ];

        if (($order['order_source'] ?? '') === 'resource' && !empty($order['resource_id'])) {
            $item['resource'] = self::resource_summary((int) $order['resource_id']);
        }

        if (($order['order_source'] ?? '') === 'woocommerce_product' && !empty($order['woo_product_id'])) {
            $item['woocommerce_product'] = self::woocommerce_product_summary((int) $order['woo_product_id']);
        }

        return $item;

    }

    /**
     * Resource summary.
     *
     * @param int $resource_id
     * @return array|null
     */
    private static function resource_summary($resource_id) {

        global $wpdb;

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    title,
                    category,
                    source_type,
                    price,
                    currency,
                    is_public
                 FROM " . YAC_Resources_Table::table_name() . "
                 WHERE id = %d",
                $resource_id
            ),
            ARRAY_A
        );

        if (!$resource) {
            return null;
        }

        return [
            'id'          => (int) $resource['id'],
            'title'       => $resource['title'],
            'category'    => $resource['category'],
            'source_type' => $resource['source_type'],
            'price'       => (float) $resource['price'],
            'currency'    => $resource['currency'],
            'is_public'   => (int) $resource['is_public'],
        ];

    }

    /**
     * WooCommerce product summary when WooCommerce is available.
     *
     * @param int $product_id
     * @return array|null
     */
    private static function woocommerce_product_summary($product_id) {

        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->exists()) {
            return null;
        }

        return [
            'id'    => (int) $product->get_id(),
            'name'  => $product->get_name(),
            'sku'   => $product->get_sku() ?: null,
            'price' => (float) $product->get_price(),
        ];

    }

    /**
     * Order summary for procurement detail.
     *
     * @param int $order_id
     * @return array|null
     */
    private static function order_summary($order_id) {

        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    id,
                    order_number,
                    order_source,
                    product_name_snapshot,
                    total_price,
                    currency,
                    order_status,
                    payment_status,
                    fulfillment_status,
                    created_at
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d",
                $order_id
            ),
            ARRAY_A
        );

        if (!$order) {
            return null;
        }

        return [
            'id'                    => (int) $order['id'],
            'order_number'          => $order['order_number'],
            'order_source'          => $order['order_source'],
            'product_name_snapshot' => $order['product_name_snapshot'],
            'total_price'           => (float) $order['total_price'],
            'currency'              => $order['currency'],
            'order_status'          => $order['order_status'],
            'payment_status'        => $order['payment_status'],
            'fulfillment_status'    => $order['fulfillment_status'],
            'created_at'            => $order['created_at'],
        ];

    }

    /**
     * Tutor request list row.
     *
     * @param array $request
     * @return array
     */
    private static function format_tutor_request_row($request) {

        return [
            'id'                    => (int) $request['id'],
            'user_id'               => (int) $request['user_id'],
            'customer_display_name' => $request['customer_display_name'],
            'customer_email'        => $request['customer_email'],
            'request_type'          => 'tutor_request',
            'status'                => $request['status'],
            'exam_type'             => $request['exam_type'],
            'exam_level'            => $request['exam_level'],
            'preferred_timezone'    => $request['preferred_timezone'],
            'preferred_language'    => $request['preferred_language'],
            'assigned_tutor_id'     => !empty($request['assigned_tutor_id']) ? (int) $request['assigned_tutor_id'] : null,
            'tutor_id'              => !empty($request['assigned_tutor_id']) ? (int) $request['assigned_tutor_id'] : null,
            'matched_at'            => $request['matched_at'],
            'created_at'            => $request['created_at'],
            'updated_at'            => $request['updated_at'],
        ];

    }

    /**
     * Tutor request detail payload.
     *
     * @param array $request
     * @return array
     */
    private static function format_tutor_request_detail($request) {

        $request['id'] = (int) $request['id'];
        $request['user_id'] = (int) $request['user_id'];
        $request['assigned_tutor_id'] = !empty($request['assigned_tutor_id']) ? (int) $request['assigned_tutor_id'] : null;
        $request['tutor_id'] = $request['assigned_tutor_id'];
        $request['matched_by'] = !empty($request['matched_by']) ? (int) $request['matched_by'] : null;
        $request['session_started_by'] = !empty($request['session_started_by']) ? (int) $request['session_started_by'] : null;
        $request['completed_by'] = !empty($request['completed_by']) ? (int) $request['completed_by'] : null;
        $request['request_type'] = 'tutor_request';

        return $request;

    }

    /**
     * Consulting request list row.
     *
     * @param array $request
     * @return array
     */
    private static function format_consulting_request_row($request) {

        return [
            'id'                    => (int) $request['id'],
            'user_id'               => (int) $request['user_id'],
            'customer_display_name' => $request['customer_display_name'],
            'customer_email'        => $request['customer_email'],
            'request_type'          => 'consulting_request',
            'status'                => $request['status'],
            'service_type'          => $request['service_type'],
            'organization_name'     => $request['organization_name'],
            'contact_person'        => $request['contact_person'],
            'contact_email'         => $request['contact_email'],
            'budget'                => $request['budget'] !== null ? (float) $request['budget'] : null,
            'preferred_date'        => $request['preferred_date'],
            'created_at'            => $request['created_at'],
            'updated_at'            => $request['updated_at'],
        ];

    }

    /**
     * Consulting request detail payload.
     *
     * @param array $request
     * @return array
     */
    private static function format_consulting_request_detail($request) {

        $request['id'] = (int) $request['id'];
        $request['user_id'] = (int) $request['user_id'];
        $request['budget'] = $request['budget'] !== null ? (float) $request['budget'] : null;
        $request['assigned_to'] = !empty($request['assigned_to']) ? (int) $request['assigned_to'] : null;
        $request['assigned_by'] = !empty($request['assigned_by']) ? (int) $request['assigned_by'] : null;
        $request['started_by'] = !empty($request['started_by']) ? (int) $request['started_by'] : null;
        $request['completed_by'] = !empty($request['completed_by']) ? (int) $request['completed_by'] : null;
        $request['request_type'] = 'consulting_request';

        return $request;

    }

    /**
     * Procurement request list row.
     *
     * @param array $procurement
     * @return array
     */
    private static function format_procurement_row($procurement) {

        return [
            'id'                    => (int) $procurement['id'],
            'order_id'              => (int) $procurement['order_id'],
            'order_number'          => $procurement['order_number'],
            'user_id'               => (int) $procurement['user_id'],
            'customer_display_name' => $procurement['customer_display_name'],
            'customer_email'        => $procurement['customer_email'],
            'request_type'          => 'procurement',
            'procurement_reference' => $procurement['procurement_reference'],
            'product_name_snapshot' => $procurement['product_name_snapshot'],
            'supplier_name'         => $procurement['supplier_name'],
            'tracking_number'       => $procurement['tracking_number'],
            'courier'               => $procurement['courier'],
            'status'                => $procurement['status'],
            'expected_delivery_date'=> $procurement['expected_delivery_date'],
            'created_at'            => $procurement['created_at'],
            'updated_at'            => $procurement['updated_at'],
        ];

    }

    /**
     * Procurement request detail payload.
     *
     * @param array $procurement
     * @return array
     */
    private static function format_procurement_detail($procurement) {

        $procurement['id'] = (int) $procurement['id'];
        $procurement['order_id'] = (int) $procurement['order_id'];
        $procurement['user_id'] = (int) $procurement['user_id'];
        $procurement['ordered_by'] = !empty($procurement['ordered_by']) ? (int) $procurement['ordered_by'] : null;
        $procurement['shipped_by'] = !empty($procurement['shipped_by']) ? (int) $procurement['shipped_by'] : null;
        $procurement['delivered_by'] = !empty($procurement['delivered_by']) ? (int) $procurement['delivered_by'] : null;
        $procurement['request_type'] = 'procurement';

        return $procurement;

    }

    /**
     * Count payments awaiting admin verification.
     *
     * @return int
     */
    private static function awaiting_verification_payment_count() {

        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM " . YAC_Payments_Table::table_name() . "
             WHERE payment_status IN ('pending', 'submitted')
             AND has_pop = 1"
        );

    }

    /**
     * Pending procurements.
     *
     * @return array
     */
    public static function pending_procurements() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Procurements_Table::table_name() . "
             WHERE status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Pending tutor requests.
     *
     * @return array
     */
    public static function pending_tutor_requests() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Tutor_Requests_Table::table_name() . "
             WHERE status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

    /**
     * Pending consulting requests.
     *
     * @return array
     */
    public static function pending_consulting_requests() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT *
             FROM " . YAC_Consulting_Requests_Table::table_name() . "
             WHERE status='pending'
             ORDER BY created_at DESC",
            ARRAY_A
        );

    }

}
