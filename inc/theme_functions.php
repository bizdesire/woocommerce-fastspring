<?php

use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Subscription;

add_action('wp_enqueue_scripts', 'register_script_enqueuer');

function register_script_enqueuer() {
    wp_register_script("plugin_script", WP_PLUGIN_URL . '/woocommerce-fastspring/assets/js/plugin_scripts.js', array(), time());
    wp_localize_script('plugin_script', 'myAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
    wp_enqueue_script('plugin_script');
}

add_filter('wp_nav_menu_items', 'wti_loginout_menu_link', 10, 2);

function wti_loginout_menu_link($items, $args) {
    if ($args->theme_location == 'primary') {
        if (is_user_logged_in()) {
            $items .= '<li class="right"><a href="' . wp_logout_url(home_url()) . '">' . __("Log Out") . '</a></li>';
        }
    }
    return $items;
}

add_filter('cron_schedules', 'cron_add_fiveminutes_interval');

function cron_add_fiveminutes_interval($schedules) {
    $schedules['5min'] = array(
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display' => __('Every 5 minutes')
    );
    return $schedules;
}

function schedule_customer_license_checker($user_id) {
    $cron_count = 0;
    $user_info = get_userdata($user_id);
    $user_license = get_user_meta($user_id, 'send_license', TRUE);
    $cron_count = get_user_meta($user_id, 'cron_count', TRUE);
    if ($cron_count == 6):
        WC()->mailer();
        do_action('customer_license_failed_email_notification', $user_info);
        delete_user_meta($user_id, 'cron_count');
    elseif ($cron_count < 6 || !$cron_count):
        if (!$user_license || $user_license == 'not_send') :
            wp_schedule_single_event(time() + 300, 'customer_license_checker', array($user_id));
            update_user_meta($user_id, 'cron_count', $cron_count + 1);
        endif;
    endif;
}

add_action('customer_license_checker', 'schedule_customer_license_checker', 10, 1);
//wp_schedule_single_event(time() + 300, 'customer_license_checker', array($user_id));

add_action("wp_ajax_change_fastspring_plan", "change_fastspring_plan_callback");
add_action("wp_ajax_nopriv_change_fastspring_plan", "change_fastspring_plan_callback");

function change_fastspring_plan_callback() {
    if (!wp_verify_nonce($_POST['nonce'], "change_plan_nonce")) {
        exit("Un-verified request.");
    }
    $product_id = $_POST['product_id'];
    $upgrade = $_POST['upgrade'];
    $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
    if (!$fastspring_productid)
        exit("Fastspring product id missing.");
    if (!is_user_logged_in())
        exit("Please login first to change a plan.");
    $user_id = get_current_user_id();
    $subscription_id = get_user_meta($user_id, 'fastspring_subscriptionID', TRUE);

    if (!$subscription_id)
        exit("Please buy a product from Price & Plan page.");
    $subscription_period = 'month';
    if (!$subscription_period = get_post_meta($post->ID, '_subscription_period', true)) {
        $subscription_period = 'month';
    }
    $subscription_period_interval = 1;
    if (!$subscription_period_interval = get_post_meta($post->ID, '_subscription_period_interval', true)) {
        $subscription_period_interval = 1;
    }
    $next = strtotime(' + ' . $subscription_period_interval . ' ' . $subscription_period);
    $apiUsername = esc_attr(get_option('api_username'));
    $apiPassword = esc_attr(get_option('api_password'));
    FastSpring::initialize($apiUsername, $apiPassword);
    $prorate = true;
    if ($upgrade == 'trial'):
        $prorate = FALSE;
    endif;
    $args = array(
        "subscriptions" => array(
            array(
                "subscription" => $subscription_id,
                // "next" => date('Y-m-d',$next),
                "product" => $fastspring_productid,
                "coupons" => [],
                "prorate" => $prorate
            )
        )
    );
    try {
        $response = FastSpring::post('subscriptions', $args);
        $response = "Your subscription has been successfylly update.";
    } catch (Exception $exc) {
        $response = $exc->getMessage();
    }
    echo "<div class='response-message-inner'>" . $response . "</div>";
    die();
}

add_action("wp_ajax_activate_starter_request", "activate_starter_request_callback");
add_action("wp_ajax_nopriv_activate_starter_request", "activate_starter_request_callback");

function activate_starter_request_callback() {

    if (!is_user_logged_in())
        exit("Please login first to change a plan.");
    $user_id = get_current_user_id();
    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    foreach ($active_subscriptions as $id => $subscription):
        if ($subscription->has_status(array('active', 'on-hold', 'register_expired'))):
            $subscription->update_status('wc-register_expired');
            if ($subscription->get_date('next_payment')) :
                $dates = array(
                    'end' => $subscription->get_date('next_payment'),
                    'next_payment' => ''
                );
                $subscription->add_order_note(_x('Subscription cancelled by the subscriber from their account page.', 'order note left on subscription after user action', 'woocommerce-subscriptions'));
                $subscription->update_dates($dates);
                $subscription->save_meta_data();
            endif;
        endif;
    endforeach;
      echo "<div class='response-message-inner'>Your subscription request has been received. You will be migrated to the starter pack after your end date</div>";
    die();
}
