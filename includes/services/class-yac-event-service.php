<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Event_Service {

    /**
     * Dispatch an event across the platform.
     *
     * @param array $data
     * @return void
     */
    public static function dispatch($data) {

        // Notification
        if (!empty($data['notification'])) {
            YAC_Notification_Service::create($data['notification']);
        }

        // Timeline
        if (!empty($data['timeline'])) {
            YAC_Timeline_Service::record($data['timeline']);
        }

        // Audit
        if (!empty($data['audit'])) {
            YAC_Audit_Service::record($data['audit']);
        }

    }

}