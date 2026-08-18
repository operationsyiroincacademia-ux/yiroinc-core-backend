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

        if ($status === 'awaiting_verification') {
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
