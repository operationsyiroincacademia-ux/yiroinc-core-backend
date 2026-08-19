<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Timeline_Service extends YAC_Base_Service {

    /**
     * Record a timeline event.
     *
     * @param array $data
     * @return int|false
     */
    public static function record($data) {

        return self::insert(
            YAC_Timeline_Table::table_name(),
            [
                'user_id'      => $data['user_id'],
                'actor_id'     => $data['actor_id'] ?? 0,
                'event'        => $data['event'],
                'title'        => $data['title'],
                'description'  => $data['description'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id'   => $data['related_id'] ?? null,
                'metadata'     => isset($data['metadata']) ? wp_json_encode($data['metadata']) : null,
                'visibility'   => $data['visibility'] ?? 'user',
            ]
        );

    }

    /**
     * Get all timeline events for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function all($user_id) {

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

    }

    /**
     * Get a single timeline event.
     *
     * @param int $id
     * @param int $user_id
     * @return array|null
     */
    public static function find($id, $user_id) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $id,
                $user_id
            ),
            ARRAY_A
        );

    }

    /**
     * Get historical payment activity events newest-first.
     *
     * @param int $payment_id
     * @return array
     */
    public static function payment_activity($payment_id) {

        global $wpdb;

        $events = $wpdb->get_results(
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
                    metadata,
                    visibility,
                    created_at
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE related_type = %s
                 AND related_id = %d
                 AND event IN (
                    'payment_created',
                    'proof_submitted',
                    'payment_rejected',
                    'replacement_proof_submitted',
                    'payment_approved',
                    'payment_verified'
                 )
                 ORDER BY created_at DESC, id DESC",
                'payment',
                absint($payment_id)
            ),
            ARRAY_A
        );

        return array_map([self::class, 'format_payment_activity_event'], $events);

    }

    /**
     * Check whether a payment event has already occurred.
     *
     * @param int $payment_id
     * @param string $event
     * @return bool
     */
    public static function payment_has_event($payment_id, $event) {

        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM " . YAC_Timeline_Table::table_name() . "
                 WHERE related_type = %s
                 AND related_id = %d
                 AND event = %s",
                'payment',
                absint($payment_id),
                sanitize_key($event)
            )
        );

    }

    /**
     * Normalize payment activity event payloads.
     *
     * @param array $event
     * @return array
     */
    private static function format_payment_activity_event($event) {

        if ($event['event'] === 'payment_verified') {
            $event['event'] = 'payment_approved';
            $event['title'] = 'Payment Approved';
        }

        $event['id'] = (int) $event['id'];
        $event['user_id'] = (int) $event['user_id'];
        $event['actor_id'] = (int) $event['actor_id'];
        $event['related_id'] = !empty($event['related_id']) ? (int) $event['related_id'] : null;

        return $event;

    }

}
