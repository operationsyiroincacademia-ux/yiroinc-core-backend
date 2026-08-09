<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Audit_Service extends YAC_Base_Service {

    /**
     * Record an audit log.
     *
     * @param array $data
     * @return int|false
     */
    public static function record($data) {

        return self::insert(
            YAC_Audit_Logs_Table::table_name(),
            [
                'actor_id'    => $data['actor_id'] ?? 0,
                'action'      => $data['action'],
                'entity_type' => $data['entity_type'],
                'entity_id'   => $data['entity_id'],
                'old_values'  => isset($data['old_values']) ? wp_json_encode($data['old_values']) : null,
                'new_values'  => isset($data['new_values']) ? wp_json_encode($data['new_values']) : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );

    }

}