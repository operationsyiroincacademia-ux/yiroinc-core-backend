<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Order_Service extends YAC_Base_Service {

    /**
     * Create an order.
     *
     * @param array $data
     * @return int|false
     */
    public static function create($data) {

        return self::insert(
            YAC_Orders_Table::table_name(),
            [
                'order_number'          => $data['order_number'],
                'user_id'               => $data['user_id'],
                'order_source'          => $data['order_source'] ?? 'woocommerce_product',
                'woo_product_id'        => $data['woo_product_id'] ?? null,
                'woo_variation_id'      => $data['woo_variation_id'] ?? null,
                'resource_id'           => $data['resource_id'] ?? null,
                'product_name_snapshot' => $data['product_name_snapshot'],
                'sku_snapshot'          => $data['sku_snapshot'] ?? null,
                'quantity'              => $data['quantity'] ?? 1,
                'unit_price'            => $data['unit_price'],
                'total_price'           => $data['total_price'],
                'currency'              => $data['currency'] ?? 'NGN',
                'customer_note'         => $data['customer_note'] ?? null,
                'admin_note'            => null,
            ]
        );

    }

    /**
     * Get all orders for a user with the latest related payment context.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        $orders_table   = YAC_Orders_Table::table_name();
        $payments_table = YAC_Payments_Table::table_name();

        $orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    o.*,
                    p.id AS payment_id,
                    p.has_pop,
                    p.payment_status AS related_payment_status
                 FROM {$orders_table} o
                 LEFT JOIN {$payments_table} p
                    ON p.id = (
                        SELECT p2.id
                        FROM {$payments_table} p2
                        WHERE p2.order_id = o.id
                        AND p2.user_id = o.user_id
                        ORDER BY p2.created_at DESC
                        LIMIT 1
                    )
                 WHERE o.user_id = %d
                 ORDER BY o.created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        return array_map([self::class, 'format_order'], $orders);

    }

    /**
     * Normalize order payment context fields.
     *
     * @param array $order
     * @return array
     */
    public static function format_order($order) {

        if (empty($order['currency'])) {
            if (($order['order_source'] ?? 'woocommerce_product') === 'resource') {
                $order['currency'] = get_option('yac_bank_currency', 'NGN');
            } else {
                $order['currency'] = function_exists('get_woocommerce_currency')
                    ? get_woocommerce_currency()
                    : get_option('yac_bank_currency', 'NGN');
            }
        }

        $order['payment_id'] = !empty($order['payment_id'])
            ? (int) $order['payment_id']
            : null;

        $order['has_pop'] = !empty($order['has_pop'])
            ? (int) $order['has_pop']
            : 0;

        $order['related_payment_status'] =
            $order['related_payment_status'] ?: null;

        return $order;

    }

}
