<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Support_Service extends YAC_Base_Service {

    const RELATED_TYPE = 'support_ticket';
    const MESSAGE_RELATED_TYPE = 'support_message';
    const ATTACHMENT_FILE_TYPE = 'support_attachment';

    public static function create_ticket($user_id, array $data, $attachment = null) {

        global $wpdb;

        $user_id = absint($user_id);
        $prepared = self::prepare_ticket_data($data);

        if (is_wp_error($prepared)) {
            return $prepared;
        }

        $message = self::prepare_message($data['message'] ?? '');

        if (is_wp_error($message)) {
            return $message;
        }

        $now = current_time('mysql');
        $ticket_id = self::insert(
            YAC_Support_Tickets_Table::table_name(),
            [
                'user_id'         => $user_id,
                'subject'         => $prepared['subject'],
                'category'        => $prepared['category'],
                'status'          => 'open',
                'priority'        => $prepared['priority'],
                'last_message_at' => $now,
                'last_message_by' => $user_id,
            ]
        );

        if (!$ticket_id) {
            return self::error('yac_support_ticket_create_failed', 'Unable to create support ticket.', 500);
        }

        $message_id = self::create_message_record($ticket_id, $user_id, 'user', $message);

        if (is_wp_error($message_id)) {
            return $message_id;
        }

        $attachment_result = self::save_attachment($attachment, $message_id, $user_id);

        if (is_wp_error($attachment_result)) {
            return $attachment_result;
        }

        YAC_Notification_Service::notify_admins([
            'sender_id'    => $user_id,
            'related_type' => self::RELATED_TYPE,
            'related_id'   => $ticket_id,
            'title'        => 'New Support Ticket',
            'message'      => 'A new support ticket is open.',
            'type'         => 'info',
            'action_url'   => '/admin/support/' . $ticket_id,
        ], $user_id);

        return [
            'ticket_id'  => (int) $ticket_id,
            'message_id' => (int) $message_id,
            'ticket'     => self::get_ticket_for_user($ticket_id, $user_id),
        ];

    }

    public static function user_tickets($user_id, array $args = []) {

        return self::tickets_query([
            'user_id'  => absint($user_id),
            'status'   => $args['status'] ?? null,
            'category' => $args['category'] ?? null,
            'page'     => $args['page'] ?? null,
            'per_page' => $args['per_page'] ?? null,
        ]);

    }

    public static function get_ticket_for_user($ticket_id, $user_id) {

        $ticket = self::ticket_record($ticket_id, absint($user_id));

        if (!$ticket) {
            return null;
        }

        return self::ticket_detail_payload($ticket, false);

    }

    public static function add_user_reply($ticket_id, $user_id, array $data, $attachment = null) {

        $ticket = self::ticket_record($ticket_id, absint($user_id));

        if (!$ticket) {
            return self::error('yac_support_ticket_not_found', 'Support ticket not found.', 404);
        }

        if ($ticket['status'] === 'resolved') {
            return self::error('yac_support_ticket_resolved', 'Resolved tickets must be reopened before replying.', 409);
        }

        $message = self::prepare_message($data['message'] ?? '');

        if (is_wp_error($message)) {
            return $message;
        }

        $message_id = self::create_message_record($ticket['id'], $user_id, 'user', $message);

        if (is_wp_error($message_id)) {
            return $message_id;
        }

        $attachment_result = self::save_attachment($attachment, $message_id, $user_id);

        if (is_wp_error($attachment_result)) {
            return $attachment_result;
        }

        $updated = self::update_ticket_after_message($ticket['id'], $user_id);

        if (is_wp_error($updated)) {
            return $updated;
        }

        YAC_Notification_Service::notify_admins([
            'sender_id'    => $user_id,
            'related_type' => self::RELATED_TYPE,
            'related_id'   => $ticket['id'],
            'title'        => 'Support Ticket Reply',
            'message'      => 'A support ticket has a new customer reply.',
            'type'         => 'info',
            'action_url'   => '/admin/support/' . $ticket['id'],
        ], $user_id);

        return [
            'message_id' => (int) $message_id,
            'ticket'     => self::get_ticket_for_user($ticket['id'], $user_id),
        ];

    }

    public static function resolve_ticket($ticket_id, $user_id) {

        $ticket = self::ticket_record($ticket_id, absint($user_id));

        if (!$ticket) {
            return self::error('yac_support_ticket_not_found', 'Support ticket not found.', 404);
        }

        return self::change_status($ticket['id'], 'resolved', absint($user_id), false);

    }

    public static function reopen_ticket($ticket_id, $user_id) {

        $ticket = self::ticket_record($ticket_id, absint($user_id));

        if (!$ticket) {
            return self::error('yac_support_ticket_not_found', 'Support ticket not found.', 404);
        }

        return self::change_status($ticket['id'], 'open', absint($user_id), false);

    }

    public static function admin_tickets(array $args = []) {

        return self::tickets_query([
            'status'   => $args['status'] ?? null,
            'category' => $args['category'] ?? null,
            'page'     => $args['page'] ?? null,
            'per_page' => $args['per_page'] ?? null,
            'admin'    => true,
        ]);

    }

    public static function admin_ticket($ticket_id) {

        $ticket = self::ticket_record($ticket_id);

        if (!$ticket) {
            return null;
        }

        return self::ticket_detail_payload($ticket, true);

    }

    public static function add_admin_reply($ticket_id, $admin_id, array $data, $attachment = null) {

        $ticket = self::ticket_record($ticket_id);

        if (!$ticket) {
            return self::error('yac_support_ticket_not_found', 'Support ticket not found.', 404);
        }

        if ($ticket['status'] === 'resolved') {
            return self::error('yac_support_ticket_resolved', 'Resolved tickets must be reopened before replying.', 409);
        }

        $message = self::prepare_message($data['message'] ?? '');

        if (is_wp_error($message)) {
            return $message;
        }

        $message_id = self::create_message_record($ticket['id'], $admin_id, 'admin', $message);

        if (is_wp_error($message_id)) {
            return $message_id;
        }

        $attachment_result = self::save_attachment($attachment, $message_id, $admin_id);

        if (is_wp_error($attachment_result)) {
            return $attachment_result;
        }

        $updated = self::update_ticket_after_message($ticket['id'], $admin_id);

        if (is_wp_error($updated)) {
            return $updated;
        }

        YAC_Notification_Service::create([
            'user_id'      => (int) $ticket['user_id'],
            'sender_id'    => $admin_id,
            'related_type' => self::RELATED_TYPE,
            'related_id'   => $ticket['id'],
            'title'        => 'Support replied',
            'message'      => 'Support has replied to your ticket.',
            'type'         => 'info',
            'action_url'   => '/support/' . $ticket['id'],
        ]);

        return [
            'message_id' => (int) $message_id,
            'ticket'     => self::admin_ticket($ticket['id']),
        ];

    }

    public static function admin_change_status($ticket_id, $status, $admin_id) {

        $ticket = self::ticket_record($ticket_id);

        if (!$ticket) {
            return self::error('yac_support_ticket_not_found', 'Support ticket not found.', 404);
        }

        $status = sanitize_key($status);

        if (!in_array($status, YAC_Status_Service::support_ticket_statuses(), true)) {
            return self::error('yac_invalid_support_status', 'Invalid support ticket status.', 422);
        }

        return self::change_status($ticket['id'], $status, absint($admin_id), true);

    }

    public static function can_download_attachment($file, $user_id) {

        if (
            ($file['related_type'] ?? '') !== self::MESSAGE_RELATED_TYPE ||
            ($file['file_type'] ?? '') !== self::ATTACHMENT_FILE_TYPE
        ) {
            return false;
        }

        global $wpdb;

        $ticket_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.id
                 FROM " . YAC_Support_Messages_Table::table_name() . " m
                 INNER JOIN " . YAC_Support_Tickets_Table::table_name() . " t
                    ON t.id = m.ticket_id
                 WHERE m.id = %d
                 AND t.user_id = %d
                 LIMIT 1",
                absint($file['related_id']),
                absint($user_id)
            )
        );

        return (bool) $ticket_id;

    }

    private static function prepare_ticket_data(array $data) {

        $subject = sanitize_text_field($data['subject'] ?? '');
        $category = sanitize_key($data['category'] ?? '');
        $priority = sanitize_key($data['priority'] ?? 'medium');

        if ($subject === '') {
            return self::error('yac_support_subject_required', 'subject is required.', 400);
        }

        if (mb_strlen($subject) > 255) {
            return self::error('yac_support_subject_too_long', 'Maximum length is 255 characters.', 400);
        }

        if ($category === '') {
            return self::error('yac_support_category_required', 'category is required.', 400);
        }

        if (!in_array($category, YAC_Status_Service::support_ticket_categories(), true)) {
            return self::error('yac_invalid_support_category', 'Invalid support ticket category.', 422);
        }

        if (!in_array($priority, YAC_Status_Service::support_ticket_priorities(), true)) {
            return self::error('yac_invalid_support_priority', 'Invalid support ticket priority.', 422);
        }

        return [
            'subject'  => $subject,
            'category' => $category,
            'priority' => $priority,
        ];

    }

    private static function prepare_message($message) {

        $message = trim(sanitize_textarea_field((string) $message));

        if ($message === '') {
            return self::error('yac_support_message_required', 'message is required.', 400);
        }

        if (mb_strlen($message) > 10000) {
            return self::error('yac_support_message_too_long', 'Maximum length is 10000 characters.', 400);
        }

        return $message;

    }

    private static function create_message_record($ticket_id, $sender_user_id, $sender_type, $message) {

        $message_id = self::insert(
            YAC_Support_Messages_Table::table_name(),
            [
                'ticket_id'      => absint($ticket_id),
                'sender_user_id' => absint($sender_user_id),
                'sender_type'    => sanitize_key($sender_type),
                'message'        => $message,
            ]
        );

        if (!$message_id) {
            return self::error('yac_support_message_create_failed', 'Unable to create support message.', 500);
        }

        return (int) $message_id;

    }

    private static function save_attachment($attachment, $message_id, $user_id) {

        if (empty($attachment) || empty($attachment['tmp_name'])) {
            return null;
        }

        global $wpdb;

        $upload = YAC_File_Service::upload($attachment, 'support');

        if (is_wp_error($upload)) {
            return $upload;
        }

        $inserted = $wpdb->insert(
            YAC_Files_Table::table_name(),
            [
                'user_id'       => absint($user_id),
                'related_type'  => self::MESSAGE_RELATED_TYPE,
                'related_id'    => absint($message_id),
                'file_type'     => self::ATTACHMENT_FILE_TYPE,
                'file_name'     => basename($upload['file']),
                'original_name' => sanitize_file_name($attachment['name']),
                'file_path'     => $upload['file'],
                'mime_type'     => $upload['type'],
                'file_size'     => absint($attachment['size']),
                'visibility'    => 'private',
                'uploaded_by'   => absint($user_id),
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
            ]
        );

        if ($inserted === false) {
            return self::error('yac_support_attachment_create_failed', 'Unable to save support attachment.', 500);
        }

        return (int) $wpdb->insert_id;

    }

    private static function update_ticket_after_message($ticket_id, $sender_user_id) {

        global $wpdb;

        $updated = $wpdb->update(
            YAC_Support_Tickets_Table::table_name(),
            [
                'last_message_at' => current_time('mysql'),
                'last_message_by' => absint($sender_user_id),
            ],
            [
                'id' => absint($ticket_id),
            ],
            [
                '%s',
                '%d',
            ],
            [
                '%d',
            ]
        );

        if ($updated === false) {
            return self::error('yac_support_ticket_update_failed', 'Unable to update support ticket.', 500);
        }

        return true;

    }

    private static function change_status($ticket_id, $status, $actor_id, $include_user_context = true) {

        global $wpdb;

        $data = [
            'status' => $status,
        ];

        $formats = [
            '%s',
        ];

        if ($status === 'resolved') {
            $data['resolved_by'] = absint($actor_id);
            $data['resolved_at'] = current_time('mysql');
            $formats[] = '%d';
            $formats[] = '%s';
        } else {
            $data['resolved_by'] = null;
            $data['resolved_at'] = null;
            $formats[] = '%d';
            $formats[] = '%s';
        }

        $updated = $wpdb->update(
            YAC_Support_Tickets_Table::table_name(),
            $data,
            [
                'id' => absint($ticket_id),
            ],
            $formats,
            [
                '%d',
            ]
        );

        if ($updated === false) {
            return self::error('yac_support_ticket_update_failed', 'Unable to update support ticket.', 500);
        }

        return [
            'ticket' => self::ticket_detail_payload(
                self::ticket_record($ticket_id),
                $include_user_context
            ),
        ];

    }

    private static function tickets_query(array $args) {

        global $wpdb;

        $pagination = self::pagination_args($args);
        $where = [];
        $params = [];
        $is_admin = !empty($args['admin']);

        if (empty($is_admin)) {
            $where[] = 't.user_id = %d';
            $params[] = absint($args['user_id']);
        }

        $status = isset($args['status']) ? self::normalize_status($args['status']) : '';

        if ($status !== '') {
            if (!in_array($status, YAC_Status_Service::support_ticket_statuses(), true)) {
                return self::error('yac_invalid_support_status', 'Invalid support ticket status.', 422);
            }

            if ($status === 'open') {
                $where[] = "t.status IN ('open', 'awaiting_admin', 'awaiting_user')";
            } else {
                $where[] = 't.status = %s';
                $params[] = $status;
            }
        }

        $category = isset($args['category']) ? sanitize_key($args['category']) : '';

        if ($category !== '') {
            if (!in_array($category, YAC_Status_Service::support_ticket_categories(), true)) {
                return self::error('yac_invalid_support_category', 'Invalid support ticket category.', 422);
            }

            $where[] = 't.category = %s';
            $params[] = $category;
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $join_sql = $is_admin
            ? "LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id
               LEFT JOIN " . YAC_Profiles_Table::table_name() . " p ON p.user_id = t.user_id"
            : '';

        $select_user_sql = $is_admin
            ? ', u.display_name AS user_name, u.user_email AS user_email, p.profile_type AS profile_type'
            : '';

        $query = "
            SELECT t.*{$select_user_sql}
            FROM " . YAC_Support_Tickets_Table::table_name() . " t
            {$join_sql}
            {$where_sql}
            ORDER BY t.last_message_at DESC, t.id DESC
            LIMIT %d OFFSET %d
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                ...array_merge($params, [$pagination['per_page'], $pagination['offset']])
            ),
            ARRAY_A
        );

        $count_query = "
            SELECT COUNT(*)
            FROM " . YAC_Support_Tickets_Table::table_name() . " t
            {$join_sql}
            {$where_sql}
        ";

        $total = !empty($params)
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params))
            : (int) $wpdb->get_var($count_query);

        return [
            'tickets'    => array_map(
                function ($ticket) use ($is_admin) {
                    return self::format_ticket($ticket, $is_admin);
                },
                $rows
            ),
            'pagination' => [
                'page'        => $pagination['page'],
                'per_page'    => $pagination['per_page'],
                'total'       => $total,
                'total_pages' => (int) ceil($total / $pagination['per_page']),
            ],
        ];

    }

    private static function ticket_record($ticket_id, $user_id = null) {

        global $wpdb;

        $query = "SELECT t.*, u.display_name AS user_name, u.user_email AS user_email, p.profile_type AS profile_type
            FROM " . YAC_Support_Tickets_Table::table_name() . " t
            LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id
            LEFT JOIN " . YAC_Profiles_Table::table_name() . " p ON p.user_id = t.user_id
            WHERE t.id = %d";

        $params = [absint($ticket_id)];

        if ($user_id !== null) {
            $query .= ' AND t.user_id = %d';
            $params[] = absint($user_id);
        }

        $query .= ' LIMIT 1';

        return $wpdb->get_row(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );

    }

    private static function ticket_detail_payload($ticket, $include_user_context) {

        return [
            'ticket'   => self::format_ticket($ticket, $include_user_context),
            'user'     => $include_user_context ? self::format_user_context($ticket) : null,
            'messages' => self::messages_for_ticket((int) $ticket['id']),
        ];

    }

    private static function messages_for_ticket($ticket_id) {

        global $wpdb;

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, u.display_name AS sender_name, u.user_email AS sender_email
                 FROM " . YAC_Support_Messages_Table::table_name() . " m
                 LEFT JOIN {$wpdb->users} u
                    ON u.ID = m.sender_user_id
                 WHERE m.ticket_id = %d
                 ORDER BY m.created_at ASC, m.id ASC",
                absint($ticket_id)
            ),
            ARRAY_A
        );

        if (empty($messages)) {
            return [];
        }

        $message_ids = array_map(
            function ($message) {
                return (int) $message['id'];
            },
            $messages
        );

        $attachments = self::attachments_for_messages($message_ids);

        return array_map(
            function ($message) use ($attachments) {
                $message['id'] = (int) $message['id'];
                $message['ticket_id'] = (int) $message['ticket_id'];
                $message['sender_user_id'] = (int) $message['sender_user_id'];
                $message['attachments'] = $attachments[$message['id']] ?? [];

                return $message;
            },
            $messages
        );

    }

    private static function attachments_for_messages(array $message_ids) {

        if (empty($message_ids)) {
            return [];
        }

        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count($message_ids), '%d'));
        $files = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, related_id, file_name, original_name, mime_type, file_size, created_at
                 FROM " . YAC_Files_Table::table_name() . "
                 WHERE related_type = %s
                 AND file_type = %s
                 AND related_id IN ({$placeholders})
                 ORDER BY created_at ASC, id ASC",
                ...array_merge([self::MESSAGE_RELATED_TYPE, self::ATTACHMENT_FILE_TYPE], $message_ids)
            ),
            ARRAY_A
        );

        $grouped = [];

        foreach ($files as $file) {
            $related_id = (int) $file['related_id'];

            if (!isset($grouped[$related_id])) {
                $grouped[$related_id] = [];
            }

            $grouped[$related_id][] = [
                'file_id'       => (int) $file['id'],
                'file_name'     => $file['file_name'],
                'original_name' => $file['original_name'],
                'mime_type'     => $file['mime_type'],
                'file_size'     => (int) $file['file_size'],
                'created_at'    => $file['created_at'],
                'download_url'  => rest_url('yac/v1/files/' . (int) $file['id'] . '/download'),
            ];
        }

        return $grouped;

    }

    private static function format_ticket($ticket, $include_user_context = false) {

        $formatted = [
            'id'              => (int) $ticket['id'],
            'user_id'         => (int) $ticket['user_id'],
            'subject'         => $ticket['subject'],
            'category'        => $ticket['category'],
            'status'          => self::normalize_status($ticket['status'] ?? 'open'),
            'priority'        => self::normalize_priority($ticket['priority'] ?? 'medium'),
            'last_message_at' => $ticket['last_message_at'],
            'last_message_by' => !empty($ticket['last_message_by']) ? (int) $ticket['last_message_by'] : null,
            'resolved_by'     => !empty($ticket['resolved_by']) ? (int) $ticket['resolved_by'] : null,
            'resolved_at'     => $ticket['resolved_at'],
            'created_at'      => $ticket['created_at'],
            'updated_at'      => $ticket['updated_at'],
        ];

        if ($include_user_context) {
            $formatted['user'] = self::format_user_context($ticket);
        }

        return $formatted;

    }

    private static function normalize_status($status) {

        $status = sanitize_key($status);

        if (in_array($status, ['awaiting_admin', 'awaiting_user'], true)) {
            return 'open';
        }

        return $status ?: 'open';

    }

    private static function normalize_priority($priority) {

        $priority = sanitize_key($priority);

        if (!in_array($priority, YAC_Status_Service::support_ticket_priorities(), true)) {
            return 'medium';
        }

        return $priority;

    }

    private static function format_user_context($ticket) {

        return [
            'id'           => (int) $ticket['user_id'],
            'name'         => $ticket['user_name'] ?? null,
            'email'        => $ticket['user_email'] ?? null,
            'profile_type' => $ticket['profile_type'] ?? null,
        ];

    }

    private static function pagination_args(array $args) {

        $page = !empty($args['page'])
            ? max(1, absint($args['page']))
            : 1;

        $per_page = !empty($args['per_page'])
            ? max(1, absint($args['per_page']))
            : 20;

        if ($per_page > 100) {
            $per_page = 100;
        }

        return [
            'page'     => $page,
            'per_page' => $per_page,
            'offset'   => ($page - 1) * $per_page,
        ];

    }

    private static function error($code, $message, $status) {

        return new WP_Error(
            $code,
            $message,
            [
                'status' => $status,
            ]
        );

    }
}
