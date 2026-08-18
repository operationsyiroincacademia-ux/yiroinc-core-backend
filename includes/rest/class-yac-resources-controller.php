<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resources_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/resources',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_resources'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/resources/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/resources/purchased',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_purchased_resources'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

    }

    /**
     * Create resource.
     */
    public function create_resource(WP_REST_Request $request) {

        $data = $request->get_json_params();

        $resource = $this->prepare_resource_data($data);

        if (is_wp_error($resource)) {
            return $resource;
        }

        $resource_id = YAC_Resource_Service::create($resource);

        if (!$resource_id) {
            return $this->error('Unable to create resource.');
        }

        if (
            $resource['source_type'] === 'file' &&
            !YAC_Resource_Service::link_file($resource['file_id'], $resource_id)
        ) {
            return $this->error('Resource created, but file could not be linked.', 500);
        }

        return $this->success([
            'resource_id' => $resource_id,
        ]);

    }

    /**
     * Update resource.
     */
    public function update_resource(WP_REST_Request $request) {

        global $wpdb;

        $resource_id = absint($request['id']);

        if (!$resource_id) {
            return $this->error('Invalid resource ID.', 422);
        }

        $existing = YAC_Resource_Service::find_raw($resource_id);

        if (!$existing) {
            return $this->error('Resource not found.', 404);
        }

        $data = $request->get_json_params();

        $merged = array_merge($existing, $data);

        if (($merged['source_type'] ?? null) === 'external' && !array_key_exists('file_id', $data)) {
            $merged['file_id'] = null;
        }

        if (($merged['source_type'] ?? null) === 'file' && !array_key_exists('external_url', $data)) {
            $merged['external_url'] = null;
        }

        $resource = $this->prepare_resource_data(
            $merged,
            true,
            $existing
        );

        if (is_wp_error($resource)) {
            return $resource;
        }

        $updated = $wpdb->update(
            YAC_Resources_Table::table_name(),
            $resource,
            [
                'id' => $resource_id,
            ]
        );

        if ($updated === false) {
            return $this->error('Unable to update resource.');
        }

        if (
            $resource['source_type'] === 'file' &&
            (int) $resource['file_id'] !== (int) ($existing['file_id'] ?? 0) &&
            !YAC_Resource_Service::link_file($resource['file_id'], $resource_id)
        ) {
            return $this->error('Resource updated, but file could not be linked.', 500);
        }

        return $this->success([
            'message' => 'Resource updated successfully.',
        ]);

    }

    /**
     * Get all resources.
     */
    public function get_resources(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'resources' => YAC_Resource_Service::all($user_id),
        ]);

    }

    /**
     * Get single resource.
     */
    public function get_resource(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $resource = YAC_Resource_Service::find($request['id'], $user_id);

        if (!$resource) {
            return $this->error('Resource not found.', 404);
        }

        return $this->success([
            'resource' => $resource,
        ]);

    }

    /**
     * Get purchased resources.
     */
    public function get_purchased_resources(WP_REST_Request $request) {

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        return $this->success([
            'resources' => YAC_Resource_Service::purchased($user_id),
        ]);

    }

    private function prepare_resource_data(array $data, $is_update = false, $existing = null) {

        $required = [
            'title',
            'source_type',
        ];

        foreach ($required as $field) {

            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        $source_type = sanitize_key($data['source_type']);

        if (!in_array($source_type, ['file', 'external'], true)) {
            return $this->validation_error('Invalid resource source type.', 422);
        }

        $resource = [
            'title'        => sanitize_text_field($data['title']),
            'description'  => isset($data['description'])
                ? sanitize_textarea_field($data['description'])
                : null,
            'category'     => isset($data['category'])
                ? sanitize_text_field($data['category'])
                : null,
            'source_type'  => $source_type,
            'file_id'      => null,
            'external_url' => null,
            'price'        => 0.0,
            'currency'     => isset($data['currency'])
                ? strtoupper(sanitize_text_field($data['currency']))
                : get_option('yac_bank_currency', 'NGN'),
            'profile_type' => isset($data['profile_type'])
                ? sanitize_key($data['profile_type'])
                : null,
            'exam_type'    => isset($data['exam_type'])
                ? sanitize_text_field($data['exam_type'])
                : null,
            'is_public'    => !empty($data['is_public']) ? 1 : 0,
        ];

        if (isset($data['price'])) {
            if (!is_numeric($data['price'])) {
                return $this->validation_error('Invalid price.', 422);
            }

            $resource['price'] = (float) $data['price'];
        }

        if ($resource['price'] < 0) {
            return $this->validation_error('price must be zero or greater.', 422);
        }

        if (!preg_match('/^[A-Z]{3,10}$/', $resource['currency'])) {
            return $this->validation_error('Invalid currency.', 422);
        }

        $lengths = [
            'title'        => 255,
            'category'     => 100,
            'profile_type' => 50,
            'exam_type'    => 100,
        ];

        foreach ($lengths as $field => $length) {

            if ($resource[$field] === null) {
                continue;
            }

            $validation = YAC_Validation_Service::max_length($resource[$field], $length);

            if (is_wp_error($validation)) {
                return $validation;
            }

        }

        if (!$resource['is_public'] && empty($resource['profile_type'])) {
            return $this->validation_error(
                'profile_type is required for non-public resources.',
                422
            );
        }

        if (
            !empty($resource['profile_type']) &&
            !in_array($resource['profile_type'], $this->allowed_profile_types(), true)
        ) {
            return $this->validation_error(
                'Unsupported profile_type for resource targeting.',
                422
            );
        }

        if (!empty($resource['exam_type']) && empty($resource['profile_type'])) {
            return $this->validation_error(
                'profile_type is required when exam_type is provided.',
                422
            );
        }

        if ($source_type === 'file') {
            return $this->prepare_file_resource($data, $resource, $is_update, $existing);
        }

        return $this->prepare_external_resource($data, $resource);

    }

    private function prepare_file_resource(array $data, array $resource, $is_update = false, $existing = null) {

        if (empty($data['file_id'])) {
            return $this->validation_error('file_id is required for file resources.', 422);
        }

        if (!empty($data['external_url'])) {
            return $this->validation_error('File resources must not include external_url.', 422);
        }

        $file_id = absint($data['file_id']);

        if (!$file_id) {
            return $this->validation_error('Invalid file_id.', 422);
        }

        $file = YAC_Resource_Service::get_resource_file($file_id);

        if (!$file) {
            return $this->validation_error('Resource file not found.', 404);
        }

        if (
            !empty($file['related_id']) &&
            (!$is_update || (int) $file['related_id'] !== (int) ($existing['id'] ?? 0))
        ) {
            return $this->validation_error(
                'Resource file is already associated with a resource.',
                409
            );
        }

        $resource['file_id'] = $file_id;

        return $resource;

    }

    private function prepare_external_resource(array $data, array $resource) {

        if (!empty($data['file_id'])) {
            return $this->validation_error('External resources must not include file_id.', 422);
        }

        if (empty($data['external_url'])) {
            return $this->validation_error('external_url is required for external resources.', 422);
        }

        $url = esc_url_raw($data['external_url']);

        if (!$url || !wp_http_validate_url($url)) {
            return $this->validation_error('Invalid external_url.', 422);
        }

        $resource['external_url'] = $url;

        return $resource;

    }

    private function validation_error($message, $status = 400) {

        return new WP_Error(
            'yac_validation_error',
            $message,
            [
                'status' => $status,
            ]
        );

    }

    private function allowed_profile_types() {

        return [
            'academic_user',
            'exam_candidate',
            'corporate_client',
            'cfa_candidate',
            'frm_candidate',
            'consulting_lead',
        ];

    }

}
