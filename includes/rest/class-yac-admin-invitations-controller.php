<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Admin_Invitations_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/admin-invitations',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_invitation'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize_admin',
                    ],
                ],
            ]
        );
        
        register_rest_route(
            $this->namespace,
            '/admin-invitations/accept',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'accept_invitation'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

    }

    /**
     * Create invitation.
     */
    public function create_invitation(WP_REST_Request $request) {

        global $wpdb;

        $data = $request->get_json_params();

        $required = [
            'email',
            'full_name',
        ];

        foreach ($required as $field) {

            if (empty($data[$field])) {
                return $this->error("Missing field: {$field}", 422);
            }
        }
        
        $email = sanitize_email($data['email']);
        $full_name = sanitize_text_field($data['full_name']);
        
        if (!is_email($email)) {
            return $this->error('Invalid email address.', 422);
        }
        
        $invited_by = YAC_Auth_Helper::user_id();
        
        if (!$invited_by) {
            return $this->error('Unauthorized.', 401);
        }
        
        $invitation_token = bin2hex(random_bytes(32));
        
        $expires_at = wp_date(
            'Y-m-d H:i:s',
            current_time('timestamp') + (48 * HOUR_IN_SECONDS)
        );

        $inserted = $wpdb->insert(
            YAC_Admin_Invitations_Table::table_name(),
            [
                'email'             => $email,
                'full_name'         => $full_name,
                'invitation_token'  => $invitation_token,
                'invited_by'        => $invited_by,
                'expires_at'        => $expires_at,
            ]
        );

        if (!$inserted) {
            return $this->error('Unable to create invitation.');
        }

        return $this->success([
            'invitation_id'    => $wpdb->insert_id,
            'invitation_token' => $invitation_token,
            'expires_at'       => $expires_at,
        ]);

    }
    
        /**
     * Accept invitation.
     */
    public function accept_invitation(WP_REST_Request $request) {
    
        global $wpdb;
    
        $data = $request->get_json_params();
    
        if (empty($data['invitation_token'])) {
            return $this->error('Invitation token is required.', 422);
        }
    
        if (empty($data['password'])) {
            return $this->error('Password is required.', 422);
        }
    
        $invitation_token = sanitize_text_field($data['invitation_token']);
        $password = (string) $data['password'];
    
        if (strlen($password) < 8) {
            return $this->error(
                'Password must be at least 8 characters.',
                422
            );
        }
    
        $invitation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Admin_Invitations_Table::table_name() . "
                 WHERE invitation_token = %s
                 AND status = %s
                 LIMIT 1",
                $invitation_token,
                'pending'
            ),
            ARRAY_A
        );
    
        if (!$invitation) {
            return $this->error('Invalid invitation.', 404);
        }
    
        if (strtotime($invitation['expires_at']) < current_time('timestamp')) {
            return $this->error('Invitation has expired.', 410);
        }
    
        if (email_exists($invitation['email'])) {
            return $this->error(
                'A user with this email already exists.',
                409
            );
        }
    
        $user_id = wp_create_user(
            $invitation['email'],
            $password,
            $invitation['email']
        );
    
        if (is_wp_error($user_id)) {
            return $this->error(
                $user_id->get_error_message(),
                500
            );
        }
    
        $updated_user = wp_update_user([
            'ID'           => $user_id,
            'display_name' => $invitation['full_name'],
            'first_name'   => $invitation['full_name'],
            'role'         => 'administrator',
        ]);
        
        if (is_wp_error($updated_user)) {
            wp_delete_user($user_id);
        
            return $this->error(
                $updated_user->get_error_message(),
                500
            );
        }
    
        $updated = $wpdb->update(
            YAC_Admin_Invitations_Table::table_name(),
            [
                'status'      => 'accepted',
                'accepted_at' => current_time('mysql'),
            ],
            [
                'id' => $invitation['id'],
            ]
        );
    
        if ($updated === false) {
            return $this->error(
                'Admin account created, but invitation status could not be updated.',
                500
            );
        }
    
        return $this->success([
            'user_id' => $user_id,
            'email'   => $invitation['email'],
        ]);
    
    }

}