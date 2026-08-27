<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Email_Service {

    /**
     * Send a customer payment rejection email.
     *
     * @param array  $payment
     * @param string $rejection_reason
     * @return bool
     */
    public static function send_payment_rejected(array $payment, $rejection_reason) {

        $payment_id = absint($payment['id'] ?? 0);
        $user_id = absint($payment['user_id'] ?? 0);

        if (!$payment_id || !$user_id) {
            return false;
        }

        $user = get_userdata($user_id);

        if (!$user || empty($user->user_email) || !is_email($user->user_email)) {
            return false;
        }

        $order = self::payment_order((int) ($payment['order_id'] ?? 0));
        $subject = 'Payment requires your attention';
        $body = self::payment_rejected_body($user, $payment, $order, $rejection_reason);

        $sent = wp_mail($user->user_email, $subject, $body);

        if (!$sent) {
            error_log('[YAC Email] Payment rejected email could not be sent for payment ID ' . $payment_id . '.');
        }

        return (bool) $sent;

    }

    /**
     * Send a customer order-ready email after fulfilment completes.
     *
     * @param array $order
     * @return bool
     */
    public static function send_order_ready(array $order) {

        $order_id = absint($order['id'] ?? 0);
        $user_id = absint($order['user_id'] ?? 0);

        if (!$order_id || !$user_id) {
            return false;
        }

        $user = get_userdata($user_id);

        if (!$user || empty($user->user_email) || !is_email($user->user_email)) {
            return false;
        }

        $is_resource = ($order['order_source'] ?? 'woocommerce_product') === 'resource';
        $subject = $is_resource
            ? 'Your resource is ready'
            : 'Your order has been fulfilled';
        $body = $is_resource
            ? self::resource_ready_body($user, $order)
            : self::store_order_ready_body($user, $order);

        $sent = wp_mail($user->user_email, $subject, $body);

        if (!$sent) {
            error_log('[YAC Email] Order ready email could not be sent for order ID ' . $order_id . '.');
        }

        return (bool) $sent;

    }

    /**
     * Send a customer tutor assignment or reassignment email.
     *
     * @param array $request
     * @param array $tutor
     * @param bool  $is_reassignment
     * @return bool
     */
    public static function send_tutor_assignment(array $request, array $tutor, $is_reassignment = false) {

        $request_id = absint($request['id'] ?? 0);
        $user_id = absint($request['user_id'] ?? 0);

        if (!$request_id || !$user_id) {
            return false;
        }

        $user = get_userdata($user_id);

        if (!$user || empty($user->user_email) || !is_email($user->user_email)) {
            return false;
        }

        $subject = $is_reassignment
            ? 'Your tutor has been updated'
            : 'Your tutor has been assigned';
        $body = self::tutor_assignment_body($user, $request, $tutor, (bool) $is_reassignment);

        $sent = wp_mail($user->user_email, $subject, $body);

        if (!$sent) {
            error_log('[YAC Email] Tutor assignment email could not be sent for tutor request ID ' . $request_id . '.');
        }

        return (bool) $sent;

    }

    private static function payment_rejected_body(WP_User $user, array $payment, $order, $rejection_reason) {

        $name = self::greeting_name($user);

        $currency = sanitize_text_field((string) ($payment['currency'] ?? ''));
        $amount = isset($payment['amount_paid'])
            ? number_format((float) $payment['amount_paid'], 2)
            : '';

        $lines = [
            'Hi ' . $name . ',',
            'We could not approve the payment proof submitted for your order.',
            'Reason:',
            sanitize_textarea_field((string) $rejection_reason),
            'Payment reference:',
            sanitize_text_field((string) ($payment['payment_reference'] ?? '')),
        ];

        if ($currency !== '' || $amount !== '') {
            $lines[] = 'Amount:';
            $lines[] = trim($currency . ' ' . $amount);
        }

        if ($order && !empty($order['order_number'])) {
            $lines[] = 'Order number:';
            $lines[] = sanitize_text_field((string) $order['order_number']);
        }

        $lines[] = 'Please review the reason above and submit a new proof of payment.';

        $payment_link = self::frontend_payment_url((int) ($payment['id'] ?? 0));

        if ($payment_link !== '') {
            $lines[] = 'Review payment:';
            $lines[] = $payment_link;
        }

        $lines[] = 'YiroInc Academia';

        return implode("\n\n", array_filter($lines, static function ($line) {
            return $line !== '';
        }));

    }

    private static function resource_ready_body(WP_User $user, array $order) {

        $lines = [
            'Hi ' . self::greeting_name($user) . ',',
            'Your purchase has been completed and your resource is now available in your YiroInc Academia account.',
        ];

        self::append_order_item_lines($lines, $order, 'Resource:');

        $order_link = self::frontend_order_url((int) ($order['id'] ?? 0));

        if ($order_link !== '') {
            $lines[] = 'View your order:';
            $lines[] = $order_link;
        }

        $lines[] = 'You can sign in to your account to access your purchased resource.';
        $lines[] = 'YiroInc Academia';

        return self::body($lines);

    }

    private static function store_order_ready_body(WP_User $user, array $order) {

        $lines = [
            'Hi ' . self::greeting_name($user) . ',',
            'Your YiroInc Academia order has been fulfilled.',
        ];

        self::append_order_lines($lines, $order);
        self::append_item_lines($lines, $order, 'Item:');

        $order_link = self::frontend_order_url((int) ($order['id'] ?? 0));

        if ($order_link !== '') {
            $lines[] = 'View your order:';
            $lines[] = $order_link;
        }

        $lines[] = 'YiroInc Academia';

        return self::body($lines);

    }

    private static function tutor_assignment_body(WP_User $user, array $request, array $tutor, $is_reassignment) {

        $request_id = absint($request['id'] ?? 0);
        $exam = self::display_exam($request['exam_type'] ?? '');
        $level = self::display_exam_level($request['exam_level'] ?? '', $exam);
        $whatsapp = sanitize_text_field((string) ($tutor['whatsapp_number'] ?? ''));
        $whatsapp_link = self::whatsapp_url($whatsapp);

        $lines = [
            'Hi ' . self::greeting_name($user) . ',',
            $is_reassignment
                ? 'Your tutor for this YiroInc Academia tutoring request has been updated.'
                : 'A tutor has been assigned to your YiroInc Academia tutoring request.',
            $is_reassignment ? 'New tutor:' : 'Tutor:',
            sanitize_text_field((string) ($tutor['name'] ?? '')),
        ];

        if ($exam !== '') {
            $lines[] = 'Exam:';
            $lines[] = $exam;
        }

        if ($level !== '') {
            $lines[] = 'Level/Part:';
            $lines[] = $level;
        }

        if ($whatsapp !== '') {
            $lines[] = 'WhatsApp:';
            $lines[] = $whatsapp;

            if ($whatsapp_link !== '') {
                $lines[] = 'WhatsApp link:';
                $lines[] = $whatsapp_link;
            }
        }

        $lines[] = 'Request:';
        $lines[] = '#' . $request_id;

        $lines[] = $is_reassignment
            ? 'Please use the updated tutor details above for future communication.'
            : 'You can now contact your tutor on WhatsApp and continue from your tutoring request page.';

        $request_link = self::frontend_tutor_request_url($request_id);

        if ($request_link !== '') {
            $lines[] = 'View tutoring request:';
            $lines[] = $request_link;
        }

        $lines[] = 'YiroInc Academia';

        return self::body($lines);

    }

    private static function append_order_item_lines(array &$lines, array $order, $item_label) {

        self::append_item_lines($lines, $order, $item_label);
        self::append_order_lines($lines, $order);

    }

    private static function append_item_lines(array &$lines, array $order, $item_label) {

        if (!empty($order['product_name_snapshot'])) {
            $lines[] = $item_label;
            $lines[] = sanitize_text_field((string) $order['product_name_snapshot']);
        }

    }

    private static function append_order_lines(array &$lines, array $order) {

        if (!empty($order['order_number'])) {
            $lines[] = 'Order:';
            $lines[] = sanitize_text_field((string) $order['order_number']);
        }

    }

    private static function greeting_name(WP_User $user) {

        $name = trim((string) $user->first_name);

        if ($name === '') {
            $name = trim((string) $user->display_name);
        }

        return $name !== '' ? $name : 'there';

    }

    private static function body(array $lines) {

        return implode("\n\n", array_filter($lines, static function ($line) {
            return $line !== '';
        }));

    }

    private static function payment_order($order_id) {

        global $wpdb;

        $order_id = absint($order_id);

        if (!$order_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, order_number, product_name_snapshot
                 FROM " . YAC_Orders_Table::table_name() . "
                 WHERE id = %d",
                $order_id
            ),
            ARRAY_A
        );

    }

    private static function frontend_order_url($order_id) {

        $base_url = self::frontend_url();

        if ($base_url === '' || !$order_id) {
            return '';
        }

        return esc_url_raw($base_url . '/orders/' . absint($order_id));

    }

    private static function frontend_payment_url($payment_id) {

        $base_url = self::frontend_url();

        if ($base_url === '' || !$payment_id) {
            return '';
        }

        return esc_url_raw($base_url . '/payments/' . absint($payment_id));

    }

    private static function frontend_tutor_request_url($request_id) {

        $base_url = self::frontend_url();

        if ($base_url === '' || !$request_id) {
            return '';
        }

        return esc_url_raw($base_url . '/tutor-requests/' . absint($request_id));

    }

    private static function display_exam($value) {

        $exam = YAC_Tutor_Service::normalize_exam($value);

        if ($exam !== '') {
            return $exam;
        }

        return sanitize_text_field((string) $value);

    }

    private static function display_exam_level($value, $exam) {

        $level = YAC_Tutor_Service::normalize_level($value, $exam);

        $labels = [
            'level_1' => 'Level I',
            'level_2' => 'Level II',
            'level_3' => 'Level III',
            'part_1'  => 'Part I',
            'part_2'  => 'Part II',
        ];

        if (isset($labels[$level])) {
            return $labels[$level];
        }

        return sanitize_text_field((string) $value);

    }

    private static function whatsapp_url($number) {

        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return '';
        }

        return esc_url_raw('https://wa.me/' . $digits);

    }

    private static function frontend_url() {

        if (!defined('YAC_FRONTEND_URL')) {
            return '';
        }

        $url = trim((string) YAC_FRONTEND_URL);

        if ($url === '') {
            return '';
        }

        return untrailingslashit($url);

    }

}
