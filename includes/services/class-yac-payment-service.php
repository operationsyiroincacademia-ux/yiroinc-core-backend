<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Payment_Service extends YAC_Base_Service {

    /**
     * Create a payment record.
     *
     * @param array $data
     * @return int|false
     */
    public static function create($data) {

        return self::insert(
            YAC_Payments_Table::table_name(),
            [
                'payment_reference' => $data['payment_reference'],
                'order_id'          => $data['order_id'],
                'user_id'           => $data['user_id'],
                'payment_method'    => $data['payment_method'] ?? 'bank_transfer',
                'amount_paid'       => $data['amount_paid'],
                'currency'          => $data['currency'] ?? 'NGN',
                'has_pop'           => 0,
                'payment_status'    => 'pending',
                'verified_by'       => null,
                'user_note'         => $data['user_note'] ?? null,
                'admin_note'        => null,
                'submitted_at'      => null,
                'verified_at'       => null,
            ]
        );
    }
    
        /**
     * Get all payments for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

    }
}