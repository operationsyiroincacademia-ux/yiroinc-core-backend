<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Payments_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Payments collection.
         *
         * POST /payments
         * GET  /payments
         */
        register_rest_route(
            $this->namespace,
            '/payments',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_payment'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_payments'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Single payment.
         *
         * GET /payments/{id}
         */
        register_rest_route(
            $this->namespace,
            '/payments/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_payment'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Verify payment.
         *
         * PATCH /payments/{id}/verify
         */
        register_rest_route(
            $this->namespace,
            '/payments/(?P<id>\d+)/verify',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'verify_payment'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        /**
         * Reject payment.
         *
         * PATCH /payments/{id}/reject
         */
        register_rest_route(
            $this->namespace,
            '/payments/(?P<id>\d+)/reject',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'reject_payment'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

    }

    /**
     * Create payment.
     */
    public function create_payment(WP_REST_Request $request) {
    
        global $wpdb;
    
        $data = $request->get_json_params();
    
        $user_id = YAC_Auth_Helper::user_id();
    
        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }
    
        $validation = YAC_Validation_Service::required($data, 'order_id');
    
        if (is_wp_error($validation)) {
            return $validation;
        }
    
        $order_id = absint($data['order_id']);
    
        if (!$order_id) {
            return $this->error('Invalid order ID.', 422);
        }
    
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, order_number, total_price, currency
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $order_id,
                $user_id
            ),
            ARRAY_A
        );
    
        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        $existing_payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE order_id = %d
                 AND user_id = %d
                 ORDER BY created_at DESC
                 LIMIT 1",
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

        if ($existing_payment) {
            return $this->success([
                'payment_id'        => (int) $existing_payment['id'],
                'order_id'          => (int) $order['id'],
                'payment_reference' => $existing_payment['payment_reference'],
                'amount_paid'       => (float) $existing_payment['amount_paid'],
                'currency'          => $existing_payment['currency'],
                'payment_status'    => $existing_payment['payment_status'],
                'has_pop'           => (int) $existing_payment['has_pop'],
            ]);
        }
    
        $data = [
            'user_id'           => $user_id,
            'order_id'          => (int) $order['id'],
            'payment_reference' => $order['order_number'],
            'amount_paid'       => (float) $order['total_price'],
            'currency'          => $order['currency'],
        ];
    
        $payment_id = YAC_Payment_Service::create($data);
    
        if (!$payment_id) {
            return $this->error('Unable to create payment.');
        }

        YAC_Timeline_Service::record([
            'user_id'      => $user_id,
            'actor_id'     => $user_id,
            'event'        => 'payment_created',
            'title'        => 'Payment Created',
            'description'  => 'Payment record created for this order.',
            'related_type' => 'payment',
            'related_id'   => $payment_id,
            'visibility'   => 'user',
        ]);

        $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'payment_status' => 'pending',
            ],
            [
                'id'      => (int) $order['id'],
                'user_id' => $user_id,
            ],
            [
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );
    
        return $this->success([
            'payment_id'        => $payment_id,
            'order_id'          => (int) $order['id'],
            'payment_reference' => $order['order_number'],
            'amount_paid'       => (float) $order['total_price'],
            'currency'          => $order['currency'],
        ]);
    
    }
    
    /**
     * Get all payments.
     */
    public function get_payments(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();
        
        $allowed_sort_columns = [
            'created_at',
            'amount_paid',
            'payment_status',
        ];
    
        $sort = $request->get_param('sort') ?: 'created_at';
        
        if (!in_array($sort, $allowed_sort_columns, true)) {
            $sort = 'created_at';
        }
        
        $order = strtoupper($request->get_param('order') ?: 'DESC');
        
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }
    
    $status = $request->get_param('payment_status');
        
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, (int) $request->get_param('per_page'));
        
        if ($per_page > 100) {
            $per_page = 100;
        }
        
        $offset = ($page - 1) * $per_page;

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }
        
        $where = ["user_id = %d"];
        $params = [$user_id];
        
        if (!empty($status)) {
            $status = sanitize_key($status);

            if (!in_array($status, YAC_Status_Service::payment_statuses(), true)) {
                return $this->error('Invalid payment status.', 422);
            }

            $where[] = "payment_status = %s";
            $params[] = $status;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $query = "
            SELECT *
            FROM " . YAC_Payments_Table::table_name() . "
            WHERE {$where_sql}
            ORDER BY {$sort} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $payments = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );
        
        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Payments_Table::table_name() . "
            WHERE {$where_sql}
        ";
        
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                $count_query,
                ...array_slice($params, 0, count($params) - 2)
            )
        );

        return $this->success([
            'payments' => $payments,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);

    }

    /**
     * Get single payment.
     */
    public function get_payment(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $request['id'],
                $user_id
            ),
            ARRAY_A
        );

        if (!$payment) {
            return $this->error('Payment not found.', 404);
        }

        return $this->success([
            'payment'  => $payment,
            'activity' => YAC_Timeline_Service::payment_activity((int) $payment['id']),
        ]);

    }
    
        /**
     * Verify payment.
     */
    public function verify_payment(WP_REST_Request $request) {

        global $wpdb;

        $payment = $this->get_payment_record($request['id']);

        if (!$payment) {
            return $this->error('Payment not found.', 404);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Payments_Table::table_name(),
            [
                'payment_status' => 'verified',
                'verified_by'    => $admin_id,
                'verified_at'    => current_time('mysql'),
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to verify payment.');
        }

        $order_updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'payment_status' => 'verified',
                'order_status'   => 'processing',
            ],
            [
                'id'      => (int) $payment['order_id'],
                'user_id' => (int) $payment['user_id'],
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

        if ($order_updated === false) {
            return $this->error('Payment verified, but order status could not be updated.', 500);
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $payment['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'payment_approved',
            'title'        => 'Payment Approved',
            'description'  => 'Your payment has been approved successfully.',
            'related_type' => 'payment',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $payment['user_id'],
            'related_type' => 'payment',
            'related_id'   => $request['id'],
            'title'        => 'Payment Verified',
            'message'      => 'Your payment has been verified successfully.',
            'type'         => 'success',
            'action_url'   => '/payments/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $payment['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'payment_verified',
            'entity_type'  => 'payment',
            'entity_id'    => $request['id'],
            'description'  => 'Payment verified by administrator.',
        ]);

        return $this->success([
            'message' => 'Payment verified successfully.',
        ]);

    }
    
        /**
     * Reject payment.
     */
    public function reject_payment(WP_REST_Request $request) {

        global $wpdb;

        $payment = $this->get_payment_record($request['id']);

        if (!$payment) {
            return $this->error('Payment not found.', 404);
        }

        $data = $request->get_json_params();

        $validation = YAC_Validation_Service::required($data, 'rejection_reason');

        if (is_wp_error($validation)) {
            return $validation;
        }

        $rejection_reason = sanitize_textarea_field($data['rejection_reason']);

        if ($rejection_reason === '') {
            return $this->error('Rejection reason is required.', 422);
        }

        $admin_id = YAC_Auth_Helper::user_id();

        $updated = $wpdb->update(
            YAC_Payments_Table::table_name(),
            [
                'payment_status'   => 'rejected',
                'rejected_by'      => $admin_id,
                'rejected_at'      => current_time('mysql'),
                'rejection_reason' => $rejection_reason,
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to reject payment.');
        }

        $order_updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'payment_status' => 'rejected',
                'order_status'   => 'awaiting_payment',
            ],
            [
                'id'      => (int) $payment['order_id'],
                'user_id' => (int) $payment['user_id'],
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

        if ($order_updated === false) {
            return $this->error('Payment rejected, but order status could not be updated.', 500);
        }

        /**
         * Timeline.
         */
        YAC_Timeline_Service::record([
            'user_id'      => $payment['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'payment_rejected',
            'title'        => 'Payment Rejected',
            'description'  => $rejection_reason,
            'related_type' => 'payment',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        /**
         * Notification.
         */
        YAC_Notification_Service::create([
            'user_id'      => $payment['user_id'],
            'related_type' => 'payment',
            'related_id'   => $request['id'],
            'title'        => 'Payment Rejected',
            'message'      => $rejection_reason,
            'type'         => 'warning',
            'action_url'   => '/payments/' . $request['id'],
        ]);

        /**
         * Audit Log.
         */
        YAC_Audit_Service::record([
            'user_id'      => $payment['user_id'],
            'actor_id'     => $admin_id,
            'action'       => 'payment_rejected',
            'entity_type'  => 'payment',
            'entity_id'    => $request['id'],
            'description'  => 'Payment rejected by administrator.',
        ]);

        return $this->success([
            'message' => 'Payment rejected successfully.',
        ]);

    }

    /**
     * Get payment record.
     */
    private function get_payment_record($payment_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE id = %d",
                $payment_id
            ),
            ARRAY_A
        );

    }

}
