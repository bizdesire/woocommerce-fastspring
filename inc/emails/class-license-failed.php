<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LicenseFailedEmail extends WC_Email {

    function __construct() {

        // Add email ID, title, description, heading, subject
        $this->id = 'customer_license_failed';
        $this->title = __('Customer License Failed', 'wc-fastspring');
        $this->description = __('This email is received when a license faled.', 'wc-fastspring');

        $this->heading = __('Customer LIcense Failed', 'wc-fastspring');
        $this->subject = __('[{blogname}]  Licensed Failed', 'wc-fastspring');

        // email template path
        $this->template_html = 'emails/customer-license-failed.php';
        $this->template_plain = 'emails/plain/customer-license-failed.php';
        $this->customer_email = true;
        add_action('customer_license_failed_email_notification', array($this, 'trigger'));
        //  add_action( 'cancelled_subscription_notification', array( $this, 'trigger' ) );
        $this->template_base = CUSTOM_TEMPLATE_PATH;
        //$this->template_base = plugin_dir_path(__FILE__) . '/templates/';
        parent::__construct();
    }

    function trigger($user) {
        $this->object = $user;
        $user_name = $user->display_name;
        $user_email = $user->user_email;
        $this->send($user_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    function get_content_html() {
        return wc_get_template_html(
                $this->template_html, array(
            'subscription' => $this->object,
            'email_heading' => $this->get_heading(),
            'additional_content' => is_callable(array($this, 'get_additional_content')) ? $this->get_additional_content() : '', // WC 3.7 introduced an additional content field for all emails.
            'sent_to_admin' => true,
            'plain_text' => false,
            'email' => $this,
                ), '', $this->template_base
        );
    }

    /**
     * get_content_plain function.
     *
     * @access public
     * @return string
     */
    function get_content_plain() {
        return wc_get_template_html(
                $this->template_plain, array(
            'subscription' => $this->object,
            'email_heading' => $this->get_heading(),
            'additional_content' => is_callable(array($this, 'get_additional_content')) ? $this->get_additional_content() : '', // WC 3.7 introduced an additional content field for all emails.
            'sent_to_admin' => true,
            'plain_text' => true,
            'email' => $this,
                ), '', $this->template_base
        );
    }

    // return the subject
    public function get_subject() {

        $order = new WC_order($this->object->order_id);
        return apply_filters('woocommerce_email_subject_' . $this->id, $this->format_string($this->subject), $this->object);
    }

    public function get_additional_content() {
        $content = $this->get_option('additional_content', '');
        return apply_filters('woocommerce_email_additional_content_' . $this->id, $this->format_string($content), $this->object, $this);
    }

    // return the email heading
    public function get_heading() {

        $order = new WC_order($this->object->order_id);
        return apply_filters('woocommerce_email_heading_' . $this->id, $this->format_string($this->heading), $this->object);
    }

    // form fields that are displayed in WooCommerce->Settings->Emails
    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-fastspring'),
                'type' => 'checkbox',
                'label' => __('Enable this email notification', 'wc-fastspring'),
                'default' => 'yes'
            ),
            'subject' => array(
                'title' => __('Subject', 'wc-fastspring'),
                'type' => 'text',
                'description' => sprintf(__('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'wc-fastspring'), $this->subject),
                'placeholder' => '',
                'default' => ''
            ),
            'heading' => array(
                'title' => __('Email Heading', 'wc-fastspring'),
                'type' => 'text',
                'description' => sprintf(__('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'wc-fastspring'), $this->heading),
                'placeholder' => '',
                'default' => ''
            ),
            'additional_content' => array(
                'title' => __('Additional content', 'woocommerce'),
                'description' => __('Text to appear below the main email content.', 'woocommerce') . ' ' . $placeholder_text,
                'css' => 'width:400px; height: 75px;',
                'placeholder' => __('N/A', 'woocommerce'),
                'type' => 'textarea',
                'default' => $this->get_default_additional_content(),
                'desc_tip' => true,
            ),
            'email_type' => array(
                'title' => __('Email type', 'wc-fastspring'),
                'type' => 'select',
                'description' => __('Choose which format of email to send.', 'wc-fastspring'),
                'default' => 'html',
                'class' => 'email_type',
                'options' => array(
                    'plain' => __('Plain text', 'wc-fastspring'),
                    'html' => __('HTML', 'wc-fastspring'),
                    'multipart' => __('Multipart', 'wc-fastspring'),
                )
            )
        );
    }

}
