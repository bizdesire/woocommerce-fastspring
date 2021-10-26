<?php

if (!defined('ABSPATH'))
    exit;

class Suspended_Subscription_Email_Manager {

    /**
     * Constructor sets up actions
     */
    public function __construct() {
        add_filter('woocommerce_email_classes', array($this, 'custom_init_emails'));
        // add_action('woocommerce_init', array($this, 'hook_customer_transactional_emails'));
    }

    public static function hook_customer_transactional_emails() {
        WC()->mailer();
        add_action('woocommerce_subscription_status_changed', array($this, 'send_customer_cancelled_email', 10));
    }

    public function custom_init_emails($email_classes) {
        if (!isset($email_classes['SuspendedSubscriptionEmail'])) {
            require __DIR__ . '/emails/class-suspended-subscription-email.php';

            $email_classes['SuspendedSubscriptionEmail'] = new SuspendedSubscriptionEmail();
        }
        if (!isset($email_classes['LicenseFailedEmail'])) {
            require __DIR__ . '/emails/class-license-failed.php';

            $email_classes['LicenseFailedEmail'] = new LicenseFailedEmail();
        }
        if (!isset($email_classes['AdminLicenseFailedEmail'])) {
            require __DIR__ . '/emails/class-admin-license-failed.php';

            $email_classes['AdminLicenseFailedEmail'] = new AdminLicenseFailedEmail();
        }

        return $email_classes;
    }

    public function send_customer_cancelled_email($subscription) {

        if (!is_object($subscription)) {
            $subscription = wcs_get_subscription($subscription);
        }
        WC()->mailer();
        if ($subscription->has_status(array('pending-cancel', 'cancelled')) && 'true' !== get_post_meta($subscription->get_id(), '_cancelled_custom_email_sent', true)) {
            $user_id = $subscription->get_user_id();
            //  MandrillAPP::trigger_exist_trial_email($user_id);
            //do_action('customer_suspended_subscription_email_notification', $subscription);
        }
    }

}

new Suspended_Subscription_Email_Manager();

add_action('woocommerce_subscription_status_changed', 'send_customer_cancelled_email', 10, 2);

function send_customer_cancelled_email($subscription) {
    if (!is_object($subscription)) {
        $subscription = wcs_get_subscription($subscription);
    }
    WC()->mailer();
    if ($subscription->has_status(array('pending-cancel', 'cancelled')) && 'true' !== get_post_meta($subscription->get_id(), '_cancelled_custom_email_sent', true)) {
        $user_id = $subscription->get_user_id();
        // MandrillAPP::trigger_exist_trial_email($user_id);
        //        do_action('customer_suspended_subscription_email_notification', $subscription);
    }
}
