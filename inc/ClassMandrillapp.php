<?php

if (!defined('ABSPATH')) {
    exit;
}

class MandrillAPP {

    public $app_key;

    function __construct() {
        //$app_key = esc_attr(get_option('mandrill_api_key'));
    }

    function trigger_already_account_email($user_id) {
        $app_key = esc_attr(get_option('mandrill_api_key'));
        if (!$user_id)
            return;
        $template_name = esc_attr(get_option('mandrill_already_account'));
        $user_info = get_userdata($user_id);
        $first_name = $user_info->first_name;
        $last_name = $user_info->last_name;
        $mandrill = new Mandrill($app_key);
        $message = array(
            'subject' => $template_name,
            'from_email' => 'support@workscope.com',
            'to' => array(array('email' => $user_info->user_email, 'name' => $first_name . ' ' . $last_name)),
            'merge_vars' => array(array(
                    'rcpt' => $user_info->user_email,
                    'vars' =>
                    array(
                        array(
                            'name' => 'FIRSTNAME',
                            'content' => $first_name),
                        array(
                            'name' => 'LASTNAME',
                            'content' => $last_name),
                        array(
                            'name' => 'LIST:DESCRIPTION',
                            'content' => get_bloginfo('description')),
                        array(
                            'name' => 'LIST:COMPANY',
                            'content' => get_bloginfo('name')),
                    )
        )));

        $template_content = array();

        try {
            $mandrill->messages->sendTemplate($template_name, $template_content, $message);
        } catch (Exception $ex) {
            
        }
    }

    function trigger_new_account_email($user_id) {
        $app_key = esc_attr(get_option('mandrill_api_key'));
        if (!$user_id)
            return;
        $template_name = esc_attr(get_option('mandrill_welcome_email'));
        $user_info = get_userdata($user_id);
        $first_name = $user_info->first_name;
        $last_name = $user_info->last_name;
        $mandrill = new Mandrill($app_key);
        $message = array(
            'subject' => $template_name,
            'from_email' => 'support@workscope.com',
            'to' => array(array('email' => $user_info->user_email, 'name' => $first_name . ' ' . $last_name)),
            'merge_vars' => array(array(
                    'rcpt' => $user_info->user_email,
                    'vars' =>
                    array(
                        array(
                            'name' => 'FIRSTNAME',
                            'content' => $first_name),
                        array(
                            'name' => 'LASTNAME',
                            'content' => $last_name),
                        array(
                            'name' => 'DESCRIPTION',
                            'content' => get_bloginfo('description')),
                        array(
                            'name' => 'COMPANY',
                            'content' => get_bloginfo('name')),
        ))));

        $template_content = array();

        try {
            $mandrill->messages->sendTemplate($template_name, $template_content, $message);
        } catch (Exception $ex) {
            
        }
    }

    function trigger_exist_trial_email($user_id) {
        $app_key = esc_attr(get_option('mandrill_api_key'));
        if (!$user_id)
            return;
        $template_name = esc_attr(get_option('mandrill_already_trial'));
        $user_info = get_userdata($user_id);
        $first_name = $user_info->first_name;
        $last_name = $user_info->last_name;
        $mandrill = new Mandrill($app_key);
        $message = array(
            'subject' => $template_name,
            'from_email' => 'support@workscope.com',
            'to' => array(array('email' => $user_info->user_email, 'name' => $first_name . ' ' . $last_name)),
            'merge_vars' => array(array(
                    'rcpt' => $user_info->user_email,
                    'vars' =>
                    array(
                        array(
                            'name' => 'FIRSTNAME',
                            'content' => $first_name),
                        array(
                            'name' => 'LASTNAME',
                            'content' => $last_name),
                        array(
                            'name' => 'DESCRIPTION',
                            'content' => get_bloginfo('description')),
                        array(
                            'name' => 'COMPANY',
                            'content' => get_bloginfo('name')),
        ))));

        $template_content = array();

        try {
            $mandrill->messages->sendTemplate($template_name, $template_content, $message);
        } catch (Exception $ex) {
            
        }
    }

    function trigger_user_activity_email($user_id, $template_name) {
        $app_key = esc_attr(get_option('mandrill_api_key'));
        if (!$user_id)
            return;
        $user_info = get_userdata($user_id);
        $first_name = $user_info->first_name;
        $last_name = $user_info->last_name;
        $mandrill = new Mandrill($app_key);
        $message = array(
            'subject' => $template_name,
            'from_email' => 'support@workscope.com',
            'to' => array(array('email' => $user_info->user_email, 'name' => $first_name . ' ' . $last_name)),
            'merge_vars' => array(array(
                    'rcpt' => $user_info->user_email,
                    'vars' =>
                    array(
                        array(
                            'name' => 'FIRSTNAME',
                            'content' => $first_name),
                        array(
                            'name' => 'LASTNAME',
                            'content' => $last_name),
                        array(
                            'name' => 'LIST:DESCRIPTION',
                            'content' => get_bloginfo('description')),
                        array(
                            'name' => 'LIST:COMPANY',
                            'content' => get_bloginfo('name')),
        ))));

        $template_content = array();

        try {
            $mandrill->messages->sendTemplate($template_name, $template_content, $message);
        } catch (Exception $ex) {
            
        }
    }

    function generate_email_template($user_id, $payload) {

        $app_key = esc_attr(get_option('mandrill_api_key'));
        if (!$user_id)
            return;
        $old_product = $payload['oldProduct'];
        $new_product = $payload['product'];
        if (strpos($old_product, 'Starter') !== false) {
            if ((strpos($new_product, 'Pro') !== false) || (strpos($new_product, 'Advanced') !== false)) {
                $template_name = esc_attr(get_option('mandrill_subscription_upgraded'));
                MandrillAPP::trigger_user_activity_email($user_id, $template_name);
            }
        }
        if ((strpos($old_product, 'Pro') !== false) || (strpos($old_product, 'Advanced') !== false)) {
            if (strpos($new_product, 'Starter') !== false) {
                $template_name = esc_attr(get_option('mandrill_paid_subscription_ended'));
                MandrillAPP::trigger_user_activity_email($user_id, $template_name);
            }
        }
        if (strpos($old_product, 'Pro') !== false) {
            if (strpos($new_product, 'Advanced') !== false) {
                $template_name = esc_attr(get_option('mandrill_subscription_upgraded'));
                MandrillAPP::trigger_user_activity_email($user_id, $template_name);
            }
            if (strpos($new_product, 'Starter') !== false) {
                $template_name = esc_attr(get_option('mandrill_subscription_downgraded'));
                MandrillAPP::trigger_user_activity_email($user_id, $template_name);
            }
        }
        if (strpos($old_product, 'Advanced') !== false) {
            $template_name = esc_attr(get_option('mandrill_subscription_downgraded'));
            MandrillAPP::trigger_user_activity_email($user_id, $template_name);
        }
    }

}
