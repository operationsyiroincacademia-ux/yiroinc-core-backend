<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_CRM_Service {

        /**
     * Sync a WordPress user to FluentCRM.
     *
     * @param int $user_id
     * @return bool
     */
    public static function sync_user($user_id) {

        if (!function_exists('FluentCrmApi')) {
            return false;
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        $profile = YAC_Profile_Service::get_by_user_id($user_id);

        $contactApi = FluentCrmApi('contacts');

        $contact = $contactApi->createOrUpdate([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->user_email,
            'user_id'    => $user_id,
            'status'     => 'subscribed'
        ]);

        return !empty($contact);

    }

            /**
     * Apply a FluentCRM tag.
     *
     * @param int $user_id
     * @param string $tag_slug
     * @return bool
     */
    public static function apply_tag($user_id, $tag_slug) {
    
        if (!function_exists('FluentCrmApi')) {
            return false;
        }
    
        $user = get_userdata($user_id);
    
        if (!$user) {
            return false;
        }
    
        $contactApi = FluentCrmApi('contacts');
    
        $contact = $contactApi->getContact($user->user_email);
    
        if (!$contact) {
            return false;
        }
    
        $tag = \FluentCrm\App\Models\Tag::where('slug', $tag_slug)->first();
    
        if (!$tag) {
            return false;
        }
    
        $contact->attachTags([
            $tag->id
        ]);
    
        return true;
    }

    /**
     * Mark a closed customer account as unsubscribed in FluentCRM.
     *
     * @param string $email
     * @param int    $user_id
     * @return bool
     */
    public static function close_deleted_account_contact($email, $user_id) {

        if (!function_exists('FluentCrmApi')) {
            return false;
        }

        $email = sanitize_email((string) $email);

        if ($email === '' || !is_email($email)) {
            return false;
        }

        $contactApi = FluentCrmApi('contacts');
        $contact = $contactApi->getContact($email);

        if (!$contact) {
            return false;
        }

        if (method_exists($contact, 'update')) {
            $contact->update([
                'status' => 'unsubscribed',
            ]);

            return true;
        }

        $contact->status = 'unsubscribed';

        if (method_exists($contact, 'save')) {
            return (bool) $contact->save();
        }

        return false;

    }
}
