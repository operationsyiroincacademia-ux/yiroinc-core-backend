<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Status_Service {

    /**
     * Order statuses.
     */
    public static function order_statuses() {

        return [
            'awaiting_payment',
            'under_review',
            'processing',
            'completed',
            'cancelled',
        ];

    }

    /**
     * Payment statuses.
     */
    public static function payment_statuses() {

        return [
            'pending',
            'submitted',
            'verified',
            'rejected',
        ];

    }

    /**
     * Fulfillment statuses.
     */
    public static function fulfillment_statuses() {

        return [
            'not_started',
            'processing',
            'ready',
            'dispatched',
            'fulfilled',
        ];

    }

    /**
     * Tutor request statuses.
     */
    public static function tutor_request_statuses() {

        return [
            'pending',
            'matched',
            'in_progress',
            'completed',
            'cancelled',
        ];

    }

    /**
     * Consulting request statuses.
     */
    public static function consulting_request_statuses() {

        return [
            'pending',
            'under_review',
            'assigned',
            'in_progress',
            'completed',
            'cancelled',
        ];

    }

    /**
     * Procurement statuses.
     */
    public static function procurement_statuses() {

        return [
            'pending',
            'sourcing',
            'ordered',
            'shipped',
            'delivered',
            'cancelled',
        ];

    }

}
