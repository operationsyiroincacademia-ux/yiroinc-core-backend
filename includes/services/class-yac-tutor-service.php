<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Tutor_Service extends YAC_Base_Service {

    const AVAILABILITY_AVAILABLE = 'available';
    const AVAILABILITY_UNAVAILABLE = 'unavailable';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * List admin-managed tutors.
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function all($args = []) {

        global $wpdb;

        $page = !empty($args['page']) ? max(1, absint($args['page'])) : 1;
        $per_page = !empty($args['per_page']) ? max(1, absint($args['per_page'])) : 20;

        if ($per_page > 100) {
            $per_page = 100;
        }

        $where = [];
        $params = [];

        $status = isset($args['status']) ? sanitize_key($args['status']) : '';

        if ($status !== '' && $status !== 'all') {
            if (!in_array($status, self::statuses(), true)) {
                return new WP_Error('yac_invalid_tutor_status', 'Invalid tutor status filter.');
            }

            $where[] = 'status = %s';
            $params[] = $status;
        }

        $availability = isset($args['availability']) ? sanitize_key($args['availability']) : '';

        if ($availability !== '' && $availability !== 'all') {
            if (!in_array($availability, self::availabilities(), true)) {
                return new WP_Error('yac_invalid_tutor_availability', 'Invalid tutor availability filter.');
            }

            $where[] = 'availability = %s';
            $params[] = $availability;
        }

        $expertise_param = isset($args['exam_expertise']) ? sanitize_text_field($args['exam_expertise']) : '';
        $expertise = $expertise_param !== '' ? self::normalize_exam($expertise_param) : '';

        if ($expertise_param !== '') {
            if ($expertise === '' || !in_array($expertise, self::exam_expertise_values(), true)) {
                return new WP_Error('yac_invalid_exam_expertise', 'Invalid exam expertise filter.');
            }

            $where[] = 'exam_expertise LIKE %s';
            $params[] = '%' . $wpdb->esc_like($expertise) . '%';
        }

        $level_param = isset($args['level']) ? sanitize_text_field($args['level']) : '';
        $level = $level_param !== '' ? self::normalize_level($level_param, $expertise ?: null) : '';

        if ($level_param !== '') {
            if ($level === '') {
                return new WP_Error('yac_invalid_tutor_level', 'Invalid tutor level filter.');
            }

            $where[] = 'levels LIKE %s';
            $params[] = '%' . $wpdb->esc_like($level) . '%';
        }

        $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(name LIKE %s OR email LIKE %s OR whatsapp_number LIKE %s OR CAST(id AS CHAR) LIKE %s)';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $per_page;

        $query = "
            SELECT *
            FROM " . YAC_Tutors_Table::table_name() . "
            {$where_sql}
            ORDER BY created_at DESC, id DESC
            LIMIT %d OFFSET %d
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($query, ...array_merge($params, [$per_page, $offset])),
            ARRAY_A
        );

        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Tutors_Table::table_name() . "
            {$where_sql}
        ";

        $total = !empty($params)
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params))
            : (int) $wpdb->get_var($count_query);

        return [
            'tutors' => array_map([self::class, 'format_admin'], $rows),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ];

    }

    /**
     * Create a tutor.
     *
     * @param array $data
     * @return array|WP_Error
     */
    public static function create($data) {

        $data = is_array($data) ? $data : [];
        $prepared = self::prepare($data, true);

        if (is_wp_error($prepared)) {
            return $prepared;
        }

        $id = self::insert(YAC_Tutors_Table::table_name(), $prepared);

        if (!$id) {
            return new WP_Error('yac_tutor_create_failed', 'Unable to create tutor.');
        }

        return self::find($id);

    }

    /**
     * Update a tutor.
     *
     * @param int $id
     * @param array $data
     * @return array|WP_Error
     */
    public static function update($id, $data) {

        global $wpdb;

        $data = is_array($data) ? $data : [];
        $id = absint($id);

        if (!$id) {
            return new WP_Error('yac_invalid_tutor_id', 'Invalid tutor ID.');
        }

        $existing = self::find($id);

        if (!$existing) {
            return new WP_Error('yac_tutor_not_found', 'Tutor not found.');
        }

        $prepared = self::prepare($data, false, $existing);

        if (is_wp_error($prepared)) {
            return $prepared;
        }

        if (empty($prepared)) {
            return self::find($id);
        }

        $updated = $wpdb->update(
            YAC_Tutors_Table::table_name(),
            $prepared,
            ['id' => $id]
        );

        if ($updated === false) {
            return new WP_Error('yac_tutor_update_failed', 'Unable to update tutor.');
        }

        return self::find($id);

    }

    /**
     * Find one tutor.
     *
     * @param int $id
     * @return array|null
     */
    public static function find($id) {

        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Tutors_Table::table_name() . "
                 WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

    }

    /**
     * Format tutor for admin responses.
     *
     * @param array|null $tutor
     * @return array|null
     */
    public static function format_admin($tutor) {

        if (!$tutor) {
            return null;
        }

        return [
            'id'              => (int) $tutor['id'],
            'name'            => $tutor['name'],
            'email'           => $tutor['email'],
            'whatsapp_number' => $tutor['whatsapp_number'],
            'exam_expertise'  => self::csv_to_array($tutor['exam_expertise']),
            'levels'          => self::csv_to_array($tutor['levels']),
            'timezone'        => $tutor['timezone'],
            'availability'    => $tutor['availability'],
            'bio'             => $tutor['bio'],
            'status'          => $tutor['status'],
            'created_at'      => $tutor['created_at'],
            'updated_at'      => $tutor['updated_at'],
        ];

    }

    /**
     * Format tutor for the matched candidate.
     *
     * @param array|null $tutor
     * @return array|null
     */
    public static function format_candidate($tutor) {

        if (!$tutor) {
            return null;
        }

        return [
            'name'            => $tutor['name'],
            'email'           => $tutor['email'],
            'whatsapp_number' => $tutor['whatsapp_number'],
            'exam_expertise'  => self::csv_to_array($tutor['exam_expertise']),
            'levels'          => self::csv_to_array($tutor['levels']),
            'timezone'        => $tutor['timezone'],
            'availability'    => $tutor['availability'],
            'bio'             => $tutor['bio'],
        ];

    }

    /**
     * Validate tutor eligibility for a request.
     *
     * @param array $tutor
     * @param array $request
     * @return true|WP_Error
     */
    public static function validate_match($tutor, $request) {

        if (!$tutor) {
            return new WP_Error('yac_tutor_not_found', 'Tutor not found.');
        }

        if ($tutor['status'] !== self::STATUS_ACTIVE) {
            return new WP_Error('yac_tutor_inactive', 'Inactive tutors cannot be matched.');
        }

        if ($tutor['availability'] !== self::AVAILABILITY_AVAILABLE) {
            return new WP_Error('yac_tutor_unavailable', 'Unavailable tutors cannot be matched.');
        }

        $request_exam = self::normalize_exam($request['exam_type'] ?? '');

        if ($request_exam === '') {
            return new WP_Error('yac_unsupported_request_exam', 'Tutor request exam type is not supported for matching.');
        }

        $expertise = self::csv_to_array($tutor['exam_expertise']);

        if (!in_array($request_exam, $expertise, true)) {
            return new WP_Error('yac_tutor_exam_mismatch', 'Tutor expertise does not support this request exam.');
        }

        $request_level = self::normalize_level($request['exam_level'] ?? '', $request_exam);

        if ($request_level !== '' && !in_array($request_level, self::csv_to_array($tutor['levels']), true)) {
            return new WP_Error('yac_tutor_level_mismatch', 'Tutor levels do not support this request level.');
        }

        return true;

    }

    /**
     * Normalize an exam value.
     *
     * @param mixed $value
     * @return string
     */
    public static function normalize_exam($value) {

        $value = strtoupper(sanitize_text_field(is_array($value) ? reset($value) : (string) $value));

        if (strpos($value, 'CFA') !== false) {
            return 'CFA';
        }

        if (strpos($value, 'FRM') !== false) {
            return 'FRM';
        }

        return '';

    }

    /**
     * Normalize a level value.
     *
     * @param mixed $value
     * @param string|null $exam
     * @return string
     */
    public static function normalize_level($value, $exam = null) {

        if ($value === null || $value === '') {
            return '';
        }

        $normalized = strtolower(sanitize_text_field((string) $value));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);

        if (preg_match('/(level_|^l)?(3|iii)$/', $normalized) || strpos($normalized, 'level_iii') !== false) {
            return 'level_3';
        }

        if (preg_match('/(level_|^l)?(2|ii)$/', $normalized) || strpos($normalized, 'level_ii') !== false) {
            return $exam === 'FRM' ? 'part_2' : 'level_2';
        }

        if (preg_match('/(level_|^l)?(1|i)$/', $normalized) || strpos($normalized, 'level_i') !== false) {
            return $exam === 'FRM' ? 'part_1' : 'level_1';
        }

        if (strpos($normalized, 'part_1') !== false || strpos($normalized, 'part_i') !== false || $normalized === 'p1') {
            return 'part_1';
        }

        if (strpos($normalized, 'part_2') !== false || strpos($normalized, 'part_ii') !== false || $normalized === 'p2') {
            return 'part_2';
        }

        if (in_array($normalized, self::allowed_levels(), true)) {
            return $normalized;
        }

        return '';

    }

    private static function prepare($data, $creating, $existing = null) {

        $prepared = [];

        if ($creating || array_key_exists('name', $data)) {
            $name = sanitize_text_field($data['name'] ?? '');

            if ($name === '') {
                return new WP_Error('yac_tutor_name_required', 'Tutor name is required.');
            }

            $prepared['name'] = $name;
        }

        if (array_key_exists('email', $data)) {
            $email = sanitize_email((string) ($data['email'] ?? ''));

            if ($email !== '' && !is_email($email)) {
                return new WP_Error('yac_invalid_tutor_email', 'Tutor email must be valid.');
            }

            $prepared['email'] = $email !== '' ? $email : null;
        }

        if ($creating || array_key_exists('whatsapp_number', $data)) {
            $whatsapp = self::normalize_whatsapp($data['whatsapp_number'] ?? '');

            if (is_wp_error($whatsapp)) {
                return $whatsapp;
            }

            $prepared['whatsapp_number'] = $whatsapp;
        }

        if ($creating || array_key_exists('exam_expertise', $data)) {
            $expertise = self::prepare_exam_expertise($data['exam_expertise'] ?? []);

            if (is_wp_error($expertise)) {
                return $expertise;
            }

            $prepared['exam_expertise'] = implode(',', $expertise);
        }

        if ($creating || array_key_exists('levels', $data)) {
            $expertise = isset($prepared['exam_expertise'])
                ? self::csv_to_array($prepared['exam_expertise'])
                : ($existing ? self::csv_to_array($existing['exam_expertise']) : null);

            if ($expertise === null) {
                $expertise = self::exam_expertise_values();
            }

            $levels = self::prepare_levels($data['levels'] ?? [], $expertise);

            if (is_wp_error($levels)) {
                return $levels;
            }

            $prepared['levels'] = implode(',', $levels);
        } elseif (!$creating && array_key_exists('exam_expertise', $prepared) && $existing) {
            $expertise = self::csv_to_array($prepared['exam_expertise']);
            $levels = self::prepare_levels(self::csv_to_array($existing['levels']), $expertise);

            if (is_wp_error($levels)) {
                return $levels;
            }
        }

        if (array_key_exists('timezone', $data)) {
            $timezone = sanitize_text_field((string) ($data['timezone'] ?? ''));

            if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
                return new WP_Error('yac_invalid_tutor_timezone', 'Tutor timezone must be a valid timezone identifier.');
            }

            $prepared['timezone'] = $timezone !== '' ? $timezone : null;
        }

        if (array_key_exists('availability', $data)) {
            $availability = sanitize_key($data['availability']);

            if (!in_array($availability, self::availabilities(), true)) {
                return new WP_Error('yac_invalid_tutor_availability', 'Invalid tutor availability.');
            }

            $prepared['availability'] = $availability;
        } elseif ($creating) {
            $prepared['availability'] = self::AVAILABILITY_AVAILABLE;
        }

        if (array_key_exists('status', $data)) {
            $status = sanitize_key($data['status']);

            if (!in_array($status, self::statuses(), true)) {
                return new WP_Error('yac_invalid_tutor_status', 'Invalid tutor status.');
            }

            $prepared['status'] = $status;
        } elseif ($creating) {
            $prepared['status'] = self::STATUS_ACTIVE;
        }

        if (array_key_exists('bio', $data)) {
            $prepared['bio'] = sanitize_textarea_field((string) ($data['bio'] ?? ''));
        }

        return $prepared;

    }

    private static function normalize_whatsapp($value) {

        $number = trim((string) $value);
        $number = preg_replace('/[\s\-\.\(\)]/', '', $number);

        if (strpos($number, '00') === 0) {
            $number = '+' . substr($number, 2);
        }

        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
            return new WP_Error('yac_invalid_tutor_whatsapp', 'Tutor WhatsApp number must use international format, for example +2348012345678.');
        }

        return $number;

    }

    private static function prepare_exam_expertise($value) {

        $values = is_array($value) ? $value : explode(',', (string) $value);
        $expertise = [];

        foreach ($values as $item) {
            $exam = self::normalize_exam($item);

            if ($exam !== '') {
                $expertise[] = $exam;
            }
        }

        $expertise = array_values(array_unique($expertise));

        if (empty($expertise)) {
            return new WP_Error('yac_tutor_expertise_required', 'Tutor exam expertise is required.');
        }

        return $expertise;

    }

    private static function prepare_levels($value, $expertise = null) {

        $values = is_array($value) ? $value : explode(',', (string) $value);
        $levels = [];

        $exam_context = is_array($expertise) && count($expertise) === 1 ? $expertise[0] : null;

        foreach ($values as $item) {
            $level = self::normalize_level($item, $exam_context);

            if ($level !== '') {
                $levels[] = $level;
            }
        }

        $levels = array_values(array_unique($levels));

        if (empty($levels)) {
            return new WP_Error('yac_tutor_levels_required', 'Tutor levels are required.');
        }

        $allowed = self::allowed_levels_for_expertise($expertise);

        foreach ($levels as $level) {
            if (!in_array($level, $allowed, true)) {
                return new WP_Error('yac_tutor_level_incompatible', 'Tutor levels must be compatible with selected exam expertise.');
            }
        }

        return $levels;

    }

    private static function allowed_levels_for_expertise($expertise) {

        $expertise = is_array($expertise) ? $expertise : self::exam_expertise_values();
        $allowed = [];

        if (in_array('CFA', $expertise, true)) {
            $allowed = array_merge($allowed, ['level_1', 'level_2', 'level_3']);
        }

        if (in_array('FRM', $expertise, true)) {
            $allowed = array_merge($allowed, ['part_1', 'part_2']);
        }

        return array_values(array_unique($allowed));

    }

    private static function allowed_levels() {

        return ['level_1', 'level_2', 'level_3', 'part_1', 'part_2'];

    }

    private static function exam_expertise_values() {

        return ['CFA', 'FRM'];

    }

    private static function statuses() {

        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    }

    private static function availabilities() {

        return [self::AVAILABILITY_AVAILABLE, self::AVAILABILITY_UNAVAILABLE];

    }

    private static function csv_to_array($value) {

        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));

    }
}
