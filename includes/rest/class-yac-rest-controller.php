<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class YAC_REST_Controller {

    /**
     * Namespace.
     */
    protected $namespace = 'yac/v1';

    /**
     * Register routes.
     */
    abstract public function register_routes();

    /**
     * Success response.
     */
    protected function success($data = [], $status = 200) {

        return new WP_REST_Response(
            [
                'success' => true,
                'data'    => $data,
            ],
            $status
        );

    }

    /**
     * Error response.
     */
    protected function error($message, $status = 400) {

        return new WP_REST_Response(
            [
                'success' => false,
                'message' => $message,
            ],
            $status
        );

    }

}