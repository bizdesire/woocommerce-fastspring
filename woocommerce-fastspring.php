<?php

/*
  Plugin Name: Woocommerce Fastspring
  description: This plugin will interact between woocommerce and fastspring
  Version: 1.0.1
  Author: Bizdesire
  License: GPL2
  Text Domain: wc-fastspring
  Domain Path: /languages
  WC requires at least: 3.0.0
  WC tested up to: 3.2.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
register_activation_hook(__FILE__, 'check_plugin_activate');

function check_plugin_activate() {
    if ((!is_plugin_active('woocommerce/woocommerce.php') || !is_plugin_active('fastspring/fastspring.php')) && current_user_can('activate_plugins')) {
        wp_die('Sorry, but this plugin requires the Woocommerce Plugin to be installed and active. <br><a href="' . admin_url('plugins.php') . '">&laquo; Return to Plugins</a>');
    }
}

define('CUSTOM_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/');


require __DIR__ . '/inc/lib/fastspring/vendor/autoload.php';
require __DIR__ . '/inc/lib/mandrill-api-php/src/Mandrill.php';
require 'inc/register_options.php';
require 'inc/register_endpoints.php';
require 'inc/woocommerce_functions.php';
require 'inc/register_metabox.php';
require 'inc/helper_functions.php';
require 'inc/register_shortcodes.php';
require 'inc/ClassPageRestrictions.php';
require 'inc/class-wc-subscribed-order-email.php';
require 'inc/theme_functions.php';
require 'inc/lib/licenseserver/LicenseServer.php';
require 'inc/ClassLicenseServer.php';
require 'inc/ClassMandrillapp.php';
require 'inc/Base2n.php';

define('WC_FASTSPRING_PLUGIN_FILE', __FILE__);
register_activation_hook(WC_FASTSPRING_PLUGIN_FILE, 'wc_fastspring_plugin_activation');

function wc_fastspring_plugin_activation() {

    if (!current_user_can('activate_plugins'))
        return;

    global $wpdb;

    if (null === $wpdb->get_row("SELECT post_name FROM {$wpdb->prefix}posts WHERE post_name = 'restricted-roles'", 'ARRAY_A')) {
        $current_user = wp_get_current_user();
        $page = array(
            'post_title' => __('Restricted Roles'),
            'post_status' => 'publish',
            'post_author' => $current_user->ID,
            'post_type' => 'page',
        );
        $post_id = wp_insert_post($page);
        add_option('restricted_page_redirect', $post_id, '', 'yes');
    }
}

function unhook_those_pesky_emails($email_class) {

    remove_action('woocommerce_low_stock_notification', array($email_class, 'low_stock'));
    remove_action('woocommerce_no_stock_notification', array($email_class, 'no_stock'));
    remove_action('woocommerce_product_on_backorder_notification', array($email_class, 'backorder'));
    remove_action('woocommerce_order_status_pending_to_processing_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
    remove_action('woocommerce_order_status_pending_to_completed_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
    remove_action('woocommerce_order_status_pending_to_on-hold_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
    remove_action('woocommerce_order_status_failed_to_processing_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
    remove_action('woocommerce_order_status_failed_to_completed_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
    remove_action('woocommerce_order_status_failed_to_on-hold_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
    remove_action('woocommerce_order_status_pending_to_processing_notification', array($email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger'));
    remove_action('woocommerce_order_status_pending_to_on-hold_notification', array($email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger'));
    remove_action('woocommerce_order_status_completed_notification', array($email_class->emails['WC_Email_Customer_Completed_Order'], 'trigger'));
    remove_action('woocommerce_new_customer_note_notification', array($email_class->emails['WC_Email_Customer_Note'], 'trigger'));
    remove_action('woocommerce_subscriptions_email_order_details', array($email_class->emails['WC_Subscriptions_Email'], 'trigger'));
}

add_filter('woocommerce_report_customers_export_columns', 'fn_woocommerce_report_customers_export_columns');

function fn_woocommerce_report_customers_export_columns($export_columns) {
    $export_columns['subscription_items'] = __('Subscription Items', 'woocommerce');
    return $export_columns;
}

add_filter('woocommerce_rest_report_customers_schema', 'fn_woocommerce_admin_report_columns', 10);

function fn_woocommerce_admin_report_columns($properties) {
    $properties['subscription_items'] = array(
        'description' => __('Subscription items', 'woocommerce'),
        'type' => 'string',
        'context' => array('view', 'edit'),
        'readonly' => TRUE,
    );
    return $properties;
}

//add_filter('woocommerce_admin_report_columns', 'fn_woocommerce_admin_report_columns_callback', 10);

function fn_woocommerce_admin_report_columns_callback($report_columns) {
    $report_columns['subscription_items'] = "CASE WHEN {$orders_count} = 0 THEN NULL ELSE {$total_spend} / {$orders_count} END AS avg_order_value";

    return $report_columns;
}

add_filter('woocommerce_rest_prepare_report_customers', 'fn_woocommerce_rest_prepare_report_customers_callback', 10, 3);

use Automattic\WooCommerce\Admin\API\Reports\Customers\Controller;

function fn_woocommerce_rest_prepare_report_customers_callback($response, $report, $request) {
    foreach ($response as $key => $details):
        $all_items = array();
        if (isset($details['user_id']) && $details['user_id'] != ''):
            $user_id = $details['user_id'];
            $subscriptions = wcs_get_users_subscriptions($user_id);
            if (!empty($subscriptions)):
                foreach ($subscriptions as $sub_id => $subscription):
                    if ($subscription->get_status() == 'active'):
                        $subscription_items = $subscription->get_items();
                        foreach ($subscription->get_items() as $line_item) :
                            $product = $line_item->get_product();
                            $all_items[] = $product->get_name();
                        endforeach;
                    endif;
                endforeach;
            endif;
            $response->data['name'] = implode(', ', $all_items);
        endif;
    endforeach;
    return $response;
}

add_filter('woocommerce_report_customers_prepare_export_item', 'fn_woocommerce_report_customers_prepare_export_item', 10, 2);

function fn_woocommerce_report_customers_prepare_export_item($export_item, $item) {
    return $export_item['username'] = $item["subscription_items"];
    return $export_item;
}

function write_custom_logs($message) {
    if (is_array($message)) {
        $message = json_encode($message);
    }
    $newfile = fopen(__DIR__ . "/custom_logs.txt", "a");
    $str = "\n" . gmdate("Y-m-d\TH:i:s\Z") . " :: " . $message;
    fwrite($newfile, $str);
    fclose($newfile);
//    $file = fopen(__DIR__ . "/custom_logs.log", "w");
//    echo fwrite($file, );
//    fclose($file);
    return;
}
