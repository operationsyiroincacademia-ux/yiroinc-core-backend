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

        register_rest_route(
            $this->namespace,
            '/admin/resources',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'admin_get_resources'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/admin/resources/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'admin_get_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_resource'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
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

        $detail = YAC_Resource_Service::admin_detail($resource_id);

        return $this->success([
            'resource_id' => $resource_id,
            'resource'    => is_wp_error($detail) ? null : $detail['resource'],
        ], 201);

    }

    /**
     * Update resource.
     */
    public function update_resource(WP_REST_Request $request) {

        $resource_id = absint($request['id']);

        if (!$resource_id) {
            return $this->error('Invalid resource ID.', 422);
        }

        $existing = YAC_Resource_Service::find_raw($resource_id);

        if (!$existing) {
            return $this->error('Resource not found.', 404);
        }

        $data = $request->get_json_params();

        if (!array_key_exists('audiences', $data)) {
            $current_audiences = YAC_Resource_Service::audiences_for_resource($resource_id);

            if (!empty($current_audiences)) {
                $data['audiences'] = $current_audiences;
            }
        }

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

        $updated = YAC_Resource_Service::update($resource_id, $resource);

        if (!$updated) {
            return $this->error('Unable to update resource.');
        }

        if (
            $resource['source_type'] === 'file' &&
            (int) $resource['file_id'] !== (int) ($existing['file_id'] ?? 0) &&
            !YAC_Resource_Service::link_file($resource['file_id'], $resource_id)
        ) {
            return $this->error('Resource updated, but file could not be linked.', 500);
        }

        $detail = YAC_Resource_Service::admin_detail($resource_id);

        return $this->success([
            'message'  => 'Resource updated successfully.',
            'resource' => is_wp_error($detail) ? null : $detail['resource'],
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

        $resource = user_can($user_id, 'manage_options')
            ? YAC_Resource_Service::find_unrestricted($request['id'], $user_id)
            : YAC_Resource_Service::find($request['id'], $user_id);

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

    public function admin_get_resources(WP_REST_Request $request) {

        $result = YAC_Resource_Service::admin_all([
            'search'         => $request->get_param('search') ?: $request->get_param('q'),
            'exam'           => $request->get_param('exam'),
            'exam_expertise' => $request->get_param('exam_expertise'),
            'level'          => $request->get_param('level'),
            'pricing'        => $this->admin_pricing_filter($request),
            'visibility'     => $request->get_param('visibility') ?: $request->get_param('status'),
            'source_type'    => $request->get_param('source_type') ?: $request->get_param('resource_type'),
            'audience'       => $request->get_param('audience'),
            'page'           => $request->get_param('page'),
            'per_page'       => $request->get_param('per_page'),
        ]);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    public function admin_get_resource(WP_REST_Request $request) {

        $result = YAC_Resource_Service::admin_detail($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 404);
        }

        return $this->success($result);

    }

    public function delete_resource(WP_REST_Request $request) {

        $result = YAC_Resource_Service::deactivate($request['id']);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 422);
        }

        return $this->success($result);

    }

    private function admin_pricing_filter(WP_REST_Request $request) {

        $pricing = $request->get_param('pricing');

        if ($pricing !== null && $pricing !== '') {
            return $pricing;
        }

        $paid = $request->get_param('paid');

        if ($paid !== null && $paid !== '') {
            return filter_var($paid, FILTER_VALIDATE_BOOLEAN) ? 'paid' : '';
        }

        $free = $request->get_param('free');

        if ($free !== null && $free !== '') {
            return filter_var($free, FILTER_VALIDATE_BOOLEAN) ? 'free' : '';
        }

        return '';

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
            'exam_level'   => isset($data['exam_level'])
                ? sanitize_text_field($data['exam_level'])
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
            'exam_level'   => 100,
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

        $has_audiences = array_key_exists('audiences', $data);
        $audiences = $this->prepare_resource_audiences($data, $resource['profile_type']);

        if (is_wp_error($audiences)) {
            return $audiences;
        }

        if (
            !$has_audiences &&
            !empty($resource['profile_type']) &&
            !in_array($resource['profile_type'], $this->allowed_profile_types(), true)
        ) {
            return $this->validation_error(
                'Unsupported profile_type for resource targeting.',
                422
            );
        }

        $resource['_audiences'] = $audiences;

        $targeting_validation = $this->validate_resource_targeting($resource, $audiences);

        if (is_wp_error($targeting_validation)) {
            return $targeting_validation;
        }

        $resource['profile_type'] = $this->profile_type_for_audiences(
            $audiences,
            $resource['exam_type']
        );

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

    private function prepare_resource_audiences(array $data, $legacy_profile_type = null) {

        if (array_key_exists('audiences', $data)) {
            $raw_audiences = is_array($data['audiences'])
                ? $data['audiences']
                : [$data['audiences']];

            $audiences = YAC_Resource_Service::normalize_audiences($raw_audiences);

            if (count($audiences) !== count(array_unique(array_map('sanitize_key', $raw_audiences)))) {
                return $this->validation_error('Unsupported resource audience.', 422);
            }

            if (empty($audiences)) {
                return $this->validation_error('At least one resource audience is required.', 422);
            }

            return $audiences;
        }

        if (!empty($legacy_profile_type)) {
            $audiences = YAC_Resource_Service::normalize_audiences([$legacy_profile_type]);

            if (!empty($audiences)) {
                return $audiences;
            }
        }

        return $this->validation_error('At least one resource audience is required.', 422);

    }

    private function validate_resource_targeting(array &$resource, array $audiences) {

        if ($resource['exam_type'] !== null && trim((string) $resource['exam_type']) === '') {
            $resource['exam_type'] = null;
        }

        if ($resource['exam_level'] !== null && trim((string) $resource['exam_level']) === '') {
            $resource['exam_level'] = null;
        }

        if (!in_array('exam_candidate', $audiences, true)) {
            $resource['exam_type'] = null;
            $resource['exam_level'] = null;

            return true;
        }

        if (!empty($resource['exam_type'])) {
            $exam_type = class_exists('YAC_Tutor_Service')
                ? YAC_Tutor_Service::normalize_exam($resource['exam_type'])
                : strtoupper($resource['exam_type']);

            if ($exam_type === '') {
                return $this->validation_error('exam_type must be CFA or FRM when provided.', 422);
            }

            $resource['exam_type'] = $exam_type;
        }

        $exam_context = $resource['exam_type'];

        if (!empty($resource['exam_level'])) {
            if (!$exam_context) {
                return $this->validation_error(
                    'exam_type is required when exam_level is provided.',
                    422
                );
            }

            $level = class_exists('YAC_Tutor_Service')
                ? YAC_Tutor_Service::normalize_level($resource['exam_level'], $exam_context)
                : sanitize_key($resource['exam_level']);

            if ($level === '') {
                return $this->validation_error('Invalid exam_level.', 422);
            }

            $cfa_levels = ['level_1', 'level_2', 'level_3'];
            $frm_levels = ['part_1', 'part_2'];

            if ($exam_context === 'CFA' && !in_array($level, $cfa_levels, true)) {
                return $this->validation_error('CFA resources must use level_1, level_2, or level_3.', 422);
            }

            if ($exam_context === 'FRM' && !in_array($level, $frm_levels, true)) {
                return $this->validation_error('FRM resources must use part_1 or part_2.', 422);
            }

            $resource['exam_level'] = $level;
        }

        return true;

    }

    private function profile_type_for_audiences(array $audiences, $exam_type = null) {

        if (count($audiences) !== 1) {
            return null;
        }

        if ($audiences[0] === 'academic') {
            return 'academic_user';
        }

        if ($audiences[0] === 'corporate') {
            return 'corporate_client';
        }

        if ($audiences[0] === 'exam_candidate') {
            if ($exam_type === 'CFA') {
                return 'cfa_candidate';
            }

            if ($exam_type === 'FRM') {
                return 'frm_candidate';
            }

            return 'exam_candidate';
        }

        return null;

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
