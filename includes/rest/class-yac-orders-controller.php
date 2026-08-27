<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Orders_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Orders collection routes.
         *
         * POST /orders
         * GET  /orders
         */
        register_rest_route(
            $this->namespace,
            '/orders',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_order'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_orders'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
            ]
        );

        /**
         * Single order route.
         *
         * GET /orders/{id}
         */
        register_rest_route(
            $this->namespace,
            '/orders/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_order'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
            ]
        );

        /**
         * Order status update route.
         *
         * PATCH /orders/{id}/status
         */
        register_rest_route(
            $this->namespace,
            '/orders/(?P<id>\d+)/status',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_order_status'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );

        /**
         * Dispatch order route.
         *
         * PATCH /orders/{id}/dispatch
         */
        register_rest_route(
            $this->namespace,
            '/orders/(?P<id>\d+)/dispatch',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'dispatch_order'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );

        /**
         * Fulfil order route.
         *
         * PATCH /orders/{id}/fulfil
         */
        register_rest_route(
            $this->namespace,
            '/orders/(?P<id>\d+)/fulfil',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'fulfil_order'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );

    }

    /**
     * Create order.
     */
    public function create_order(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $data['user_id'] = $user_id;

        $order_source = !empty($data['order_source'])
            ? sanitize_key($data['order_source'])
            : null;

        if (
            $order_source &&
            !in_array($order_source, ['woocommerce_product', 'resource'], true)
        ) {
            return $this->error('Invalid order_source.', 422);
        }

        if (!empty($data['resource_id']) && !empty($data['woo_product_id'])) {
            return $this->error('Provide either resource_id or woo_product_id, not both.', 422);
        }

        if ($order_source === 'resource' || !empty($data['resource_id'])) {
            return $this->create_resource_order($data, $user_id);
        }

        $validation = YAC_Validation_Service::required($data, 'woo_product_id');

        if (is_wp_error($validation)) {
            return $validation;
        }

        $product_id = absint($data['woo_product_id']);

        $quantity = !empty($data['quantity'])
            ? absint($data['quantity'])
            : 1;

        if (!$product_id) {
            return $this->error('Invalid product ID.', 422);
        }

        if ($quantity < 1) {
            return $this->error('Quantity must be at least 1.', 422);
        }

        if (!function_exists('wc_get_product')) {
            return $this->error('WooCommerce is unavailable.', 500);
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->exists()) {
            return $this->error('Product not found.', 404);
        }

        $unit_price = (float) $product->get_price();

        if ($unit_price < 0) {
            return $this->error('Invalid product price.', 500);
        }

        $data['order_number'] = 'YAC-' . strtoupper(
            wp_generate_password(10, false, false)
        );

        $data['order_source']          = 'woocommerce_product';
        $data['product_name_snapshot'] = $product->get_name();
        $data['sku_snapshot']          = $product->get_sku() ?: null;
        $data['quantity']              = $quantity;
        $data['unit_price']            = $unit_price;
        $data['total_price']           = $unit_price * $quantity;
        $data['currency']              = get_woocommerce_currency();

        $order_id = YAC_Order_Service::create($data);

        if (!$order_id) {
            return $this->error('Unable to create order.');
        }

        return $this->success([
            'order_id'        => $order_id,
            'order_reference' => $data['order_number'],
            'total_amount'    => $data['total_price'],
            'currency'        => $data['currency'],
        ]);
    }

    private function create_resource_order(array $data, $user_id) {

        $resource_id = !empty($data['resource_id'])
            ? absint($data['resource_id'])
            : 0;

        if (!$resource_id) {
            return $this->error('Invalid resource ID.', 422);
        }

        $resource = YAC_Resource_Service::find($resource_id, $user_id);

        if (!$resource) {
            return $this->error('Resource not found.', 404);
        }

        if (empty($resource['is_public'])) {
            return $this->error('Resource is not available for purchase.', 403);
        }

        if (empty($resource['is_paid'])) {
            return $this->error('Resource is free and does not require an order.', 422);
        }

        if (!empty($resource['is_purchased'])) {
            return $this->error('Resource has already been purchased.', 409);
        }

        $existing_order = YAC_Order_Service::active_resource_order($user_id, $resource_id);

        if ($existing_order) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'You already have an active order for this resource.',
                    'data'    => [
                        'order_id'     => (int) $existing_order['id'],
                        'order_number' => $existing_order['order_number'],
                    ],
                ],
                409
            );
        }

        $order = [
            'order_number'          => 'YAC-' . strtoupper(
                wp_generate_password(10, false, false)
            ),
            'user_id'               => $user_id,
            'order_source'          => 'resource',
            'woo_product_id'        => null,
            'woo_variation_id'      => null,
            'resource_id'           => $resource_id,
            'product_name_snapshot' => $resource['title'],
            'sku_snapshot'          => null,
            'quantity'              => 1,
            'unit_price'            => (float) $resource['price'],
            'total_price'           => (float) $resource['price'],
            'currency'              => $resource['currency'],
            'customer_note'         => isset($data['customer_note'])
                ? sanitize_textarea_field($data['customer_note'])
                : null,
        ];

        $order_id = YAC_Order_Service::create($order);

        if (!$order_id) {
            return $this->error('Unable to create order.');
        }

        return $this->success([
            'order_id'        => $order_id,
            'order_reference' => $order['order_number'],
            'total_amount'    => $order['total_price'],
            'currency'        => $order['currency'],
        ]);

    }

    /**
     * Get all orders.
     */
    public function get_orders(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'orders' => YAC_Order_Service::all($user_id),
        ]);

    }

    /**
     * Get single order.
     */
    public function get_order(WP_REST_Request $request) {

        global $wpdb;

        $orders_table   = YAC_Orders_Table::table_name();
        $payments_table = YAC_Payments_Table::table_name();

        $user_id  = YAC_Auth_Helper::user_id();
        $order_id = absint($request['id']);

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        if (!$order_id) {
            return $this->error('Invalid order ID.', 422);
        }

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    o.*,
                    p.id AS payment_id,
                    p.has_pop,
                    p.payment_status AS related_payment_status
                 FROM {$orders_table} o
                 LEFT JOIN {$payments_table} p
                    ON p.order_id = o.id
                    AND p.user_id = o.user_id
                 WHERE o.id = %d
                 AND o.user_id = %d
                 ORDER BY p.created_at DESC
                 LIMIT 1",
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        return $this->success([
            'order' => YAC_Order_Service::format_order($order),
        ]);

    }

    /**
     * Update order status.
     */
    public function update_order_status(WP_REST_Request $request) {

        global $wpdb;

        $status = $request->get_param('status');

        if (!$status) {
            return $this->error('Status is required.');
        }

        $status = sanitize_key($status);

        if (!in_array($status, YAC_Status_Service::order_statuses(), true)) {
            return $this->error('Invalid order status.', 422);
        }

        $order = $this->get_order_record($request['id']);

        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        if ($status === 'completed') {
            $validation = $this->validate_digital_resource_payment($order);

            if (is_wp_error($validation)) {
                return $this->error($validation->get_error_message(), 422);
            }
        }

        $updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'order_status' => $status,
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to update order.');
        }

        if ($status === 'completed') {
            $entitlement = YAC_Resource_Service::grant_entitlement_for_order($order);

            if ($entitlement === false || is_wp_error($entitlement)) {
                return $this->error('Order updated, but resource access could not be granted.', 500);
            }
        }

        return $this->success([
            'message' => 'Order updated successfully.',
        ]);

    }

    /**
     * Dispatch order.
     */
    public function dispatch_order(WP_REST_Request $request) {

        global $wpdb;

        $order = $this->get_order_record($request['id']);

        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        $updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'fulfillment_status' => 'dispatched',
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to dispatch order.');
        }

        YAC_Notification_Service::create([
            'user_id'      => $order['user_id'],
            'related_type' => 'order',
            'related_id'   => $request['id'],
            'title'        => 'Order Dispatched',
            'message'      => 'Your order has been dispatched.',
            'type'         => 'info',
            'action_url'   => '/orders/' . $request['id'],
        ]);

        return $this->success([
            'message' => 'Order dispatched successfully.',
        ]);

    }

    /**
     * Fulfil order.
     */
    public function fulfil_order(WP_REST_Request $request) {

        global $wpdb;

        $order = $this->get_order_record($request['id']);

        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        if (
            $order['order_status'] === 'completed' ||
            $order['fulfillment_status'] === 'fulfilled'
        ) {
            return $this->error('Order has already been fulfilled.', 409);
        }

        $validation = $this->validate_order_payment_verified($order);

        if (is_wp_error($validation)) {
            return $this->error($validation->get_error_message(), 422);
        }

        if (($order['order_source'] ?? 'woocommerce_product') === 'resource') {
            $entitlement = YAC_Resource_Service::grant_entitlement_for_order($order);

            if (!$entitlement || is_wp_error($entitlement)) {
                return $this->error('Resource access could not be granted.', 500);
            }
        }

        $updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'order_status'       => 'completed',
                'fulfillment_status' => 'fulfilled',
            ],
            [
                'id' => $request['id'],
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to fulfil order.');
        }

        $admin_id = YAC_Auth_Helper::user_id();

        YAC_Timeline_Service::record([
            'user_id'      => $order['user_id'],
            'actor_id'     => $admin_id,
            'event'        => 'order_fulfilled',
            'title'        => 'Order Fulfilled',
            'description'  => 'Your order has been fulfilled.',
            'related_type' => 'order',
            'related_id'   => $request['id'],
            'visibility'   => 'user',
        ]);

        YAC_Notification_Service::create([
            'user_id'      => $order['user_id'],
            'related_type' => 'order',
            'related_id'   => $request['id'],
            'title'        => 'Order Fulfilled',
            'message'      => 'Your order has been fulfilled.',
            'type'         => 'success',
            'action_url'   => '/orders/' . $request['id'],
        ]);

        YAC_Email_Service::send_order_ready($order);

        return $this->success([
            'message' => 'Order fulfilled successfully.',
        ]);

    }

    private function validate_order_payment_verified($order) {

        $payment = YAC_Resource_Service::verified_payment_for_order(
            $order['id'],
            $order['user_id']
        );

        if (!$payment) {
            return new WP_Error(
                'yac_payment_not_verified',
                'Cannot fulfil an order before payment is verified.'
            );
        }

        return true;

    }

    private function validate_digital_resource_payment($order) {

        if (($order['order_source'] ?? 'woocommerce_product') !== 'resource') {
            return true;
        }

        if (empty($order['resource_id'])) {
            return new WP_Error(
                'yac_invalid_resource_order',
                'Resource order is missing a resource.'
            );
        }

        $payment = YAC_Resource_Service::verified_payment_for_order(
            $order['id'],
            $order['user_id']
        );

        if (!$payment) {
            return new WP_Error(
                'yac_payment_not_verified',
                'Cannot fulfil a digital resource order before payment is verified.'
            );
        }

        return true;

    }

    /**
     * Get order record.
     */
    private function get_order_record($order_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d",
                $order_id
            ),
            ARRAY_A
        );

    }

}
