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

        /**
         * Only accept product and quantity from the user.
         */
        $required = [
            'woo_product_id',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

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

        $data['product_name_snapshot'] = $product->get_name();
        $data['sku_snapshot']          = $product->get_sku() ?: null;
        $data['quantity']              = $quantity;
        $data['unit_price']            = $unit_price;
        $data['total_price']           = $unit_price * $quantity;

        $order_id = YAC_Order_Service::create($data);

        if (!$order_id) {
            return $this->error('Unable to create order.');
        }

        return $this->success([
            'order_id'        => $order_id,
            'order_reference' => $data['order_number'],
            'total_amount'    => $data['total_price'],
            'currency'        => get_woocommerce_currency(),
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

        $updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'fulfillment_status' => 'fulfilled',
            ],
            [
                'id' => $request['id'],
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to fulfil order.');
        }

        YAC_Notification_Service::create([
            'user_id'      => $order['user_id'],
            'related_type' => 'order',
            'related_id'   => $request['id'],
            'title'        => 'Order Fulfilled',
            'message'      => 'Your order has been fulfilled.',
            'type'         => 'success',
            'action_url'   => '/orders/' . $request['id'],
        ]);

        return $this->success([
            'message' => 'Order fulfilled successfully.',
        ]);

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
