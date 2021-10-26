<?php

if (!defined('ABSPATH')) {
    exit;
}

use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Order;
use Emileperron\FastSpring\Entity\Product;
use Emileperron\FastSpring\Entity\Subscription;
use Automattic\WooCommerce\Client;
use Emileperron\FastSpring\Entity\Account;

class RegisterEndpoints {

    public function __construct() {
        $this->init();
        $apiUsername = esc_attr(get_option('api_username'));
        $apiPassword = esc_attr(get_option('api_password'));
        FastSpring::initialize($apiUsername, $apiPassword);
    }

    public function init() {
        // FastSpring::initialize('P3YHLPY6TXUL1WF7CJBXWQ', 'kffP5OK2RV-gcFye-Cf1AQ');
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/create_order/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'create_woocommerce_order_callback')
            ));
        });
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/canceled_order/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'canceled_woocommerce_order_callback')
            ));
        });
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/subscription_canceled/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'canceled_woocommerce_subscription_callback')
            ));
        });
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/refund_order/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'refund_woocommerce_order_callback')
            ));
        });
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/subscription_charge_completed/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'fastspring_subscription_charge_completed')
            ));
        });
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/subscription_updated/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'fastspring_subscription_updated')
            ));
        });
        add_action('rest_api_init', function () {
            register_rest_route('woocommerce-fastspring/v2', '/create_subscribe_user/', array(
                'methods' => array('POST', 'GET'),
                'callback' => array($this, 'wordpress_create_subscriber')
            ));
        });
//        add_action('rest_api_init', function () {
//            register_rest_route('woocommerce-fastspring/v2', '/subscription_activated/', array(
//                'methods' => array('POST', 'GET'),
//                'callback' => array($this, 'activate_woocommerce_subscription_callback')
//            ));
//        });
    }

    public function wordpress_create_subscriber(WP_REST_Request $request_data) {
        write_custom_logs('Request wordpress_create_subscriber');
        $response = array();
        $parameters = $request_data->get_body();
        $display_address = array();
        $account_id = '';
        $customer_details = json_decode($parameters);
        $first_name = $customer_details->first_name;
        $last_name = $customer_details->last_name;
        $email_id = $customer_details->email;
        $default_password = wp_generate_password();
        if ($email_id):
            if (!$user = get_user_by('email', $email_id)):
                wc_create_new_customer($email_id, '', $default_password);
                $user = get_user_by('email', $email_id);
                $user_id = $user->id;
                $user_args = array(
                    'ID' => $user_id
                );
                if ($first_name) :
                    $user_args['first_name'] = $first_name;
                endif;
                if ($last_name) :
                    $user_args['last_name'] = $last_name;
                endif;
                wp_update_user($user_args);
                RegisterEndpoints::create_default_subscription($user_id);
                $response['success'] = TRUE;
                $response['msg'] = "<div class='response-message-inner'>Please check your email id for the further process.</div>";
            else:
                $response['msg'] = "<div class='response-message-inner'>This email id alreay exist, please try with different email id or <a href='" . home_url('my-account') . "' target='_blank'>Click here</a> to login.</div>";
                $response['success'] = FALSE;

            endif;

        endif;
        return $response;
    }

    public function create_woocommerce_order_callback(WP_REST_Request $request_data) {
        write_custom_logs('Request create_woocommerce_order_callback');

        $parameters = $request_data->get_body();
        $display_address = array();
        $account_id = '';
        $meta = json_decode($parameters);
        if (!empty($meta->events)):
            foreach ($meta->events as $key => $event):
                if ($event->type == 'order.completed'):
                    $order_data = $event->data;
                    $first_name = $last_name = $email_id = $phone_no = '';
                    if (!empty($order_data->account)):
                        $account = $order_data->account;
                        $account_id = @$account->id;
                    endif;
                    if (!empty($order_data->customer)):
                        $customer = $order_data->customer;
                        $first_name = @$customer->first;
                        $last_name = @$customer->last;
                        $email_id = @$customer->email;
                        $phone_no = @$customer->phone;
                    endif;
                    if (!empty($order_data->address)):
                        $address = $order_data->address;
                        $woo_address = array(
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'email' => $email_id,
                            'phone' => $phone_no,
                            'address_1' => $address->addressLine1,
                            'city' => $address->city,
                            'state' => $address->region,
                            'postcode' => $address->postalCode,
                            'country' => $address->country
                        );
                        $display_address = array(
                            'addr1' => $address->addressLine1,
                            'city' => $address->city,
                            'state' => $address->region,
                            'zip' => $address->postalCode,
                            'country' => $address->country,
                        );
                    endif;

                    $default_password = wp_generate_password();
                    if ($email_id)
                        if (!$user = get_user_by('email', $email_id)):
                            wc_create_new_customer($email_id, '', $default_password);
                            $user = get_user_by('email', $email_id);

                    endif;

                    $order_items = $order_data->items;
                    $order_currency = $order_data->currency;
                    $user_id = $user->id;
                    $user_args = array(
                        'ID' => $user_id
                    );
                    if ($first_name) :
                        $user_args['first_name'] = $first_name;
                    endif;
                    if ($last_name) :
                        $user_args['last_name'] = $last_name;
                    endif;
                    wp_update_user($user_args);
                    if ($woo_address) :
                        foreach ($woo_address as $key => $address):
                            update_user_meta($user_id, 'billing_' . $key, $address);
                            update_user_meta($user_id, 'shipping_' . $key, $address);
                        endforeach;
                    endif;
                    if ($account_id):
                        update_user_meta($user_id, 'fastspring_customerID', $account_id);
                    endif;
                    $status = 'active';
                    if (!empty($order_items)):
                        foreach ($order_items as $key => $order_item):
                            $prod_id = $order_item->product;
                            $quantity = $order_item->quantity;
                            $woo_order = wc_create_order(array('customer_id' => $user->id));
                            $woo_order->add_meta_data('_fastspring_response', $parameters);
                            $woo_prod = get_product_by_fastspring($prod_id);

                            if (!empty($order_item->subscription)):
                                $instruction_trial = '';
                                $instruction_regular = '';

                                $trial_end = $start_date = $payment_start = 0;
                                $start_date = date('Y-m-d H:i:s', $order_item->subscription->beginInSeconds);
                                $product_trial = false;
                                foreach ($order_item->subscription->instructions as $instruction):
                                    if ($instruction->type == 'trial'):
                                        $instruction_trial = $instruction->type;
                                        $trial_end = date('Y-m-d H:i:s', $instruction->periodEndDateInSeconds);
                                        $product_trial = true;
                                    // $trial_end = date('Y-m-d H:i:s', strtotime("+5 minutes"));
                                    endif;
                                    if ($instruction->type == 'regular'):
                                        $instruction_regular = $instruction->type;
                                        $payment_start = date('Y-m-d H:i:s', $instruction->periodStartDateInSeconds);
                                        if ($product_trial)
                                            $trial_end = $payment_start;
                                    //$payment_start = date('Y-m-d H:i:s', strtotime("+5 minutes"));
                                    endif;
                                endforeach;
                                $subscription = $order_item->subscription;
                                $subscription_id = $subscription->id;
                                $subscription_subtotal = $subscription->subtotal;
                                $period = $subscription->intervalUnit;
                                $interval = $subscription->intervalLength;
                                if ($woo_prod) :
                                    $woo_order->add_product(get_product($woo_prod), $quantity, array(
                                        'subtotal' => $subscription_subtotal,
                                        'total' => $subscription_subtotal
                                    ));
                                endif;
                                $active_subscriptions = wcs_get_users_subscriptions($user_id);
                                /*                                 * *****Check starter package******* */
                                if (strpos($prod_id, 'starter') == TRUE) :
                                    $starter_added = get_user_meta($user_id, 'starter_added', TRUE);
                                    if ($starter_added == 'Yes'):
                                        MandrillAPP::trigger_already_account_email($user_id);
                                    endif;
                                    update_user_meta($user_id, 'starter_package_id', $subscription_id);
                                endif;
                                $starter_package_id = get_user_meta($user_id, 'starter_package_id', TRUE);
                                $sub = wcs_create_subscription(
                                        array(
                                            'order_id' => $woo_order->id,
                                            'billing_period' => $period,
                                            'billing_interval' => $interval,
                                            'start_date' => $start_date,
                                        )
                                );

                                $prod_id = $subscription->product;
                                $subscription_price = $subscription->price;
                                $subscription_currency = $subscription->currency;
                                update_user_meta($user_id, 'subscription_currency', $subscription_currency);
                                $woo_prod = get_product_by_fastspring($prod_id);
                                if ($woo_prod) :
                                    $sub->add_product(get_product($woo_prod), $quantity, array(
                                        'subtotal' => $subscription_subtotal,
                                        'total' => $subscription_subtotal
                                    ));
                                    $sub->set_address($woo_address, 'billing');
                                    $sub->set_address($woo_address, 'shipping');
                                    $dates = array(
                                        'trial_end' => $trial_end,
                                        'next_payment' => $payment_start,
                                    );
                                    $recent_sub_id = $sub->get_id();
                                    $sub->update_dates($dates);
                                    $sub->add_order_note('Subscription ID: ' . $subscription_id);
                                    $sub->add_meta_data('_fastspring_subscriptionID', $subscription_id);
                                    $sub->add_meta_data('_subscription_price', $subscription_price);
                                    $sub->add_meta_data('_subscription_currency', $subscription_currency);
                                    $sub->set_currency($subscription_currency);
                                    $sub->calculate_totals();
                                    // $active_subscriptions = wcs_get_users_subscriptions($user_id);
                                    remove_user_old_roles($user);
                                    if ($active_subscriptions):
                                        if (strpos($prod_id, 'starter') == TRUE):
                                            update_user_meta($user_id, 'starter_added', 'Yes');
                                            update_user_meta($user_id, 'fastspring_subscriptionID', $subscription_id);
                                            foreach ($active_subscriptions as $id => $active_subscription):
                                                if ($active_subscription->has_status(array('active', 'on-hold', 'register_expired'))):
                                                    $curr_subscription_id = get_post_meta($id, '_fastspring_subscriptionID', TRUE);
                                                    RegisterEndpoints::request_cancel_fastspring_subscription($active_subscription, $curr_subscription_id);
                                                endif;
                                            endforeach;

                                        elseif (strpos($prod_id, 'trial') == TRUE):
                                            $trial_added = get_user_meta($user_id, 'trial_added', TRUE);
                                            if ($trial_added == "Yes"):
                                                RegisterEndpoints::request_cancel_fastspring_subscription($sub, $subscription_id);
                                                MandrillAPP::trigger_exist_trial_email($user_id);
                                            else:
                                                update_user_meta($user_id, 'fastspring_subscriptionID', $subscription_id);
                                                foreach ($active_subscriptions as $id => $active_subscription):
                                                    if ($active_subscription->has_status(array('active', 'on-hold', 'register_expired'))):
                                                        $curr_subscription_id = get_post_meta($id, '_fastspring_subscriptionID', TRUE);
                                                        RegisterEndpoints::request_cancel_fastspring_subscription($active_subscription, $curr_subscription_id);
                                                        add_user_new_roles($user, $sub);
                                                    endif;
                                                endforeach;
                                                update_user_meta($user_id, 'trial_added', 'Yes');
                                            endif;
                                        else :
                                            update_user_meta($user_id, 'fastspring_subscriptionID', $subscription_id);
                                            foreach ($active_subscriptions as $id => $active_subscription):
                                                if ($active_subscription->has_status(array('active', 'on-hold', 'register_expired'))):
                                                    $curr_subscription_id = get_post_meta($id, '_fastspring_subscriptionID', TRUE);
                                                    RegisterEndpoints::request_cancel_fastspring_subscription($active_subscription, $curr_subscription_id);
                                                endif;
                                            endforeach;
                                        endif;
                                    else:
                                        if (strpos($prod_id, 'trial') == TRUE) :
                                            update_user_meta($user_id, 'trial_added', 'Yes');
                                        endif;
                                        update_user_meta($user_id, 'fastspring_subscriptionID', $subscription_id);
                                    endif;
                                    if (strpos($prod_id, 'trial') != TRUE):
                                        add_user_new_roles($user, $sub);
                                    endif;
                                    $sub->save_meta_data();

                                endif;
                            endif;
                            $woo_order->add_meta_data('_subscription_price', $subscription_price);
                            $woo_order->add_meta_data('_subscription_currency', $subscription_currency);
                            $woo_order->add_meta_data('_order_currency', $subscription_currency);
                            $woo_order->set_currency($subscription_currency);
                            $woo_order->set_address($woo_address, 'billing');
                            $woo_order->set_address($woo_address, 'shipping');
                            $woo_order->add_order_note('Order ID: ' . $order_data->order);
                            $woo_order->add_order_note('Subscription ID: ' . $subscription_id);
                            $woo_order->calculate_totals();
                            $completed = $order_data->completed;
                            $woo_order->add_meta_data('_fastspring_orderID', $order_data->order);
                            $woo_order->add_meta_data('_fastspring_subscriptionID', $subscription_id);
                            $woo_order->add_meta_data('_fastspring_response', $parameters);
                            $woo_order->save_meta_data();
                            if ($completed)
                                $woo_order->update_status('completed');

                            if (get_option('enable_mailchimp') == 'yes') :
                                $data = [
                                    'email' => $email_id,
                                    'status' => 'subscribed',
                                    'firstname' => $first_name,
                                    'lastname' => $last_name,
                                    'phone' => $phone_no,
                                    'display' => $display_address
                                ];
                                Senddatamailchimp($data);
                            endif;
                        endforeach;
                    endif;

                endif;

            endforeach;
        endif;
        return TRUE;
    }

    public function canceled_woocommerce_order_callback(WP_REST_Request $request_data) {
        write_custom_logs('Request canceled_woocommerce_order_callback');

        $parameters = $request_data->get_body();
        //update_post_meta(89, 'fastspring_cancel', $parameters);
        //$meta = json_decode($parameters);
    }

    public function canceled_woocommerce_subscription_callback(WP_REST_Request $request_data) {
        write_custom_logs('Request canceled_woocommerce_subscription_callback');

        $woo_id = 0;
        $parameters = $request_data->get_body();
        $meta = json_decode($parameters);
        if (!empty($meta->events)):
            foreach ($meta->events as $key => $event):
                if (($event->type == 'subscription.deactivated') || ($event->type == 'subscription.canceled')):
                    $order_data = $event->data;
                    $subscription_id = $order_data->subscription;
                    $woo_id = get_subscription_by_fastspring($subscription_id);
                    if ($subscription) {
                        if (!is_object($woo_id)) {
                            $subscription = wcs_get_subscription($woo_id);
                        }
                        update_post_meta($woo_id, 'fastspring_cancel_request', $request_data);
                        if ($subscription->has_status(array('pending-cancel', 'cancelled'))) {
                            WC_Subscriptions_Manager::cancel_subscriptions_for_order($woo_id);
                        }
                    }
                endif;
            endforeach;
        endif;
        return $subscription;
    }

    public function refund_woocommerce_order_callback(WP_REST_Request $request_data) {
        write_custom_logs('Request refund_woocommerce_order_callback');

        $woo_id = 0;
        $parameters = $request_data->get_body();
        $meta = json_decode($parameters);
        if (!empty($meta->events)):
            foreach ($meta->events as $key => $event):
                if ($event->type == 'return.created'):
                    $order_data = $event->data;
                    if (!empty($order_data)):
                        $order_details = $order_data->original;
                        if (!empty($order_details)):
                            $order_key = $order_details->order;
                            $subscriptions = $order_details->subscriptions;
                            if (count($subscriptions)):
                                foreach ($subscriptions as $key => $subscription):
                                    $woo_id = get_subscription_by_fastspring($subscription);
                                    if ($woo_id) :
                                        WC_Subscriptions_Manager::cancel_subscriptions_for_order($woo_id);
                                        $subscription = wcs_get_subscription($woo_id);
                                        $subscription->update_status('cancelled');
                                    endif;
                                endforeach;
                            endif;
                            $refund_reason = $order_data->reason;
                            $woo_orders = get_order_by_fastspring_key($order_key);
                            if (count($woo_orders)):
                                foreach ($woo_orders as $key => $order_id):
                                    update_post_meta($order_id, '_fastspring_refund_response', $parameters);
                                    RegisterEndpoints::proces_wc_refund_order($order_id, $refund_reason);
                                endforeach;
                            endif;
                        endif;
                    endif;
                endif;
            endforeach;
        endif;
    }

    public function fastspring_subscription_updated(WP_REST_Request $request_data) {
        write_custom_logs('Request fastspring_subscription_updated');

        $woo_id = 0;
        $parameters = $request_data->get_body();
        $metas = json_decode($parameters);

        update_post_meta(2324, 'subscription_update', $metas);
        if (!empty($metas->events)):
            foreach ($metas as $key => $events):
                foreach ($events as $key => $event):
                    $order_data = $event->data;
                    $subscription_id = $order_data->subscription;
                    if ($subscription_id):
                        $woo_id = get_subscription_by_fastspring($subscription_id);
                        if (!is_object($woo_id)) :
                            $subscription = wcs_get_subscription($woo_id);
                            if ($subscription) :
                                $subscription_product = $order_data->product;
                                $subscription_subtotal = $order_data->subtotal;
                                $subscription_intervalUnit = $order_data->intervalUnit;
                                $next_day = date('Y-m-d', $order_data->nextInSeconds);
                                $woo_id = get_subscription_by_fastspring($subscription_id);
                                if (!is_object($woo_id)) {
                                    $subscription = wcs_get_subscription($woo_id);
                                    $end_date = $subscription->get_date('trial_end');
                                    $trial_time = date('H:i:s', strtotime($end_date . " +10 minutes"));
                                    $plan_start = $next_day . " " . $trial_time;
                                    $dates = array(
                                        'next_payment' => date('Y-m-d H:i:s', strtotime($plan_start)),
                                        'end' => '',
                                    );
                                    if ($subscription) {
                                        $order = method_exists($subscription, 'get_parent') ? $subscription->get_parent() : $subscription->order;
                                        foreach ($order->get_items() as $item_id => $line_item) {
                                            wc_delete_order_item($item_id);
                                        }
                                        //$order->calculate_totals();
                                        $woo_prod = get_product_by_fastspring($subscription_product->product);
                                        if ($woo_prod):
                                            $product = wc_get_product($woo_prod);
                                            $order->add_product($product, 1, array(
                                                'subtotal' => $subscription_subtotal,
                                                'total' => $subscription_subtotal,
                                                    // 'quantity' => 1,
                                                    )
                                            );
                                            $order->set_total($subscription_subtotal);

                                            $order->add_order_note(sprintf(__('Added line items: %s', 'woocommerce'), $product->get_formatted_name()), false, true);
                                            foreach ($subscription->get_items() as $item_id => $line_item) {
                                                wc_delete_order_item($item_id);
                                            }
                                            $subscription->calculate_totals(false);
                                            $subscription->update_dates($dates);
                                            $subscription->add_product($product, 1, array(
                                                'subtotal' => $subscription_subtotal,
                                                'total' => $subscription_subtotal,
                                                    // 'quantity' => 1,
                                            ));
                                            $sub_id = $subscription->get_id();
                                            update_post_meta($sub_id, '_billing_period', $subscription_intervalUnit);
                                            $subscription->add_order_note(sprintf(__('Added line items: %s', 'woocommerce'), $product->get_formatted_name()), false, true);
                                            $subscription->set_total($subscription_subtotal);
                                            $subscription->save();
                                            $subscription->update_status('active', true);
                                            //echo $calculated_next_payment = $subscription->calculate_date('next_payment');
//                                            $order->calculate_totals();
                                            $order->save();
//echo "<pre>";
//var_dump($subscription);
//echo "</pre>";
                                            $user_email = $subscription->billing_email;
                                            $oldProduct = LicenseServerAPI::check_license_user($user_email);
                                            if ($oldProduct != 'false') {
                                                LicenseServerAPI::update_license_user($subscription, $oldProduct);
                                            } else {
                                                LicenseServerAPI::add_license_user($subscription);
                                            }

                                        endif;
                                    }
                                }
                            endif;
                        endif;
                    endif;

//                    echo "<pre>";
//                    var_dump($subscription_id);
//                    $payment_start = date('Y-m-d', $order_data->nextInSeconds);
//                    var_dump($payment_start);
//                    echo "</pre>";
                endforeach;
            endforeach;
        endif;
    }

    public function fastspring_subscription_charge_completed(WP_REST_Request $request_data) {
        write_custom_logs('Request fastspring_subscription_charge_completed');
        $woo_id = 0;
        $parameters = $request_data->get_body();
        $meta = json_decode($parameters);
//        update_post_meta(3394, 'charge_completed', $meta);
// update_post_meta(3394, 'charge_completed_32', 32);
       if (!empty($meta->events)):
    foreach ($meta->events as $key => $event):
        if ($event->type == 'subscription.charge.completed'):
            $order_data = $event->data;
            if (!empty($order_data)):
                $fastspring_order = $order_data->order;

                $completed = $fastspring_order->completed;
                if ($completed):
                    $subscription = $order_data->subscription;
                    $changed = $fastspring_order->changedInSeconds;
                    echo $fastspring_date = date('Y-m-d', $changed);
                    $fastspring_date = strtotime($fastspring_date);
                    if ($subscription):
                        $subscription_id = $subscription->subscription;
                        $woo_id = get_subscription_by_fastspring($subscription);
                        if ($woo_id) :
                            $subscription = wcs_get_subscription($woo_id);
                            $renewal_orders = $subscription->get_related_orders('ids', 'renewal');
                            if ($renewal_orders):
                                foreach ($renewal_orders as $order_id => $renewal_order):
                                    $order = wc_get_order($order_id);
                                    $renewal_order_time = $order->get_date_created()->date('Y-m-d');
                                    $renewal_order_time = strtotime($renewal_order_time);
                                    if ($fastspring_date == $renewal_order_time):
                                          $order->add_meta_data('_subscription_charge_completed', $meta);
                                        $order->update_status('completed');
                                    endif;
                                endforeach;
                            endif;
                            $subscription->update_status('active');
                            $subscription->add_meta_data('_subscription_charge_completed', $meta);
                        endif;
                    endif;
                endif;
            endif;
        endif;
    endforeach;
endif;
    }

    public function proces_wc_refund_order($order_id, $refund_reason = '') {
        write_custom_logs('Request proces_wc_refund_order');

        $order = wc_get_order($order_id);
        if (!is_a($order, 'WC_Order')) {
            return;
        }
        $order_items = $order->get_items();
        $refund_amount = 0;
        $line_items = array();
        if ($order_items = $order->get_items()) {
            foreach ($order_items as $item_id => $item) {
                $item_meta = $order->get_item_meta($item_id);
                $product_data = wc_get_product($item_meta["_product_id"][0]);
                $item_ids[] = $item_id;
                $tax_data = $item_meta['_line_tax_data'];
                $refund_tax = 0;
                if (is_array($tax_data[0])) {
                    $refund_tax = array_map('wc_format_decimal', $tax_data[0]);
                }
                $refund_amount = wc_format_decimal($refund_amount) + wc_format_decimal($item_meta['_line_total'][0]);
                $line_items[$item_id] = array('qty' => $item_meta['_qty'][0], 'refund_total' => wc_format_decimal($item_meta['_line_total'][0]), 'refund_tax' => $refund_tax);
            }
        }
        $refund = wc_create_refund(array(
            'amount' => $refund_amount,
            'reason' => $refund_reason,
            'order_id' => $order_id,
            'line_items' => $line_items,
            'refund_payment' => FALSE
        ));
    }

    public function request_cancel_fastspring_subscription($sub, $subscription_id) {
        write_custom_logs('Request request_cancel_fastspring_subscription');

        $response = $sub->update_status('cancelled', true);
        if ($sub->has_status(array('active'))):
            $user_id = $sub->get_user_id();
//            MandrillAPP::trigger_exist_trial_email($user_id);
//            $email_oc = new SuspendedSubscriptionEmail();
//            $email_oc->trigger($sub);
        endif;
        $sub->update_status('cancelled');
        $customer_id = $sub->customer_id;
        $user = get_user_by('id', $customer_id);
        remove_user_old_roles($user);
        if (!$subscription_id)
            return;
        try {
            FastSpring::delete('subscriptions', [$subscription_id]);
        } catch (Exception $exc) {
            $sub->update_status('cancelled');
        }
    }

    public function send_request_license_server($user_id) {
        write_custom_logs('Request send_request_license_server');

        if (!$user_id)
            return;

        $active_subscriptions = wcs_get_users_subscriptions($user_id);
        if ($active_subscriptions):
            foreach ($active_subscriptions as $id => $subscription):
                if ($subscription->has_status(array('active'))):

                endif;
            endforeach;
        endif;
    }

    public function create_default_subscription($user_id) {
        write_custom_logs('Request create_default_subscription');

        if (!$user_id)
            return;
        $active_subscriptions = wcs_get_users_subscriptions($user_id);
        if ($active_subscriptions):
            foreach ($active_subscriptions as $id => $active_subscription):
                if ($active_subscription->has_status(array('active'))):
                    $active_subscription->update_status('cancelled');
                endif;
            endforeach;
        endif;

        $woo_order = wc_create_order(array('customer_id' => $user_id));
        $woo_order->add_meta_data('_fastspring_response', $parameters);
        $woo_prod = get_product_by_fastspring('excel-add-in-starter');
        $subscription_currency = get_user_meta($user_id, 'subscription_currency', TRUE);
        if ($woo_prod) :
            $woo_order->add_product(get_product($woo_prod), 1);
            $user_info = get_userdata($user_id);
            $first_name = $user_info->first_name;
            $last_name = $user_info->last_name;
            $email_id = $user_info->user_email;
            $billing_state = get_user_meta($user_id, 'billing_state', true);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $billing_phone = get_user_meta($user_id, 'billing_phone', true);
            $address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_country = get_user_meta($user_id, 'billing_country', true);
            $woo_address = array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email_id,
                'phone' => $billing_phone,
                'address_1' => $address_1,
                'city' => $billing_city,
                'state' => $billing_state,
                'postcode' => $billing_postcode,
                'country' => $billing_country
            );
            $woo_order->set_address($woo_address, 'billing');
            $date = date('Y-m-d H:i:s', strtotime("+365 days"));
            $trial = get_product($woo_prod);

            $sub = wcs_create_subscription(
                    array(
                        'order_id' => $woo_order->id,
                        'billing_period' => 'day',
                        'billing_interval' => 1,
                        'start_date' => date('Y-m-d H:i:s'),
                    )
            );
            $sub->add_product(get_product($woo_prod), 1);
            $dates = array(
                'next_payment' => $date,
            );
            $recent_sub_id = $sub->get_id();
            $sub->update_dates($dates);
            if ($subscription_currency):
                $sub->set_currency($subscription_currency);
            endif;
            $sub->calculate_totals();
            remove_user_old_roles($user);
            add_user_new_roles($user, $sub);
            $sub->save_meta_data();
            $woo_order->calculate_totals();
            $woo_order->update_status('completed');
            $woo_order->add_order_note('Added default subscription');
            if ($subscription_currency):
                $woo_order->set_currency($subscription_currency);
            endif;
            $woo_order->save_meta_data();
            $user_email = $sub->billing_email;
            write_custom_logs('Request create strater pack');
//            $oldProduct = LicenseServerAPI::check_license_user($user_email);
//            write_custom_logs($oldProduct);
//            if ($oldProduct != 'false'):
//                LicenseServerAPI::update_license_user($sub);
//                write_custom_logs('Request update license');
//            else:
//                LicenseServerAPI::add_license_user($sub);
//                write_custom_logs('Request ' . $url);
//                write_custom_logs('Request add license');
//            endif;
//            $user_info = get_userdata($user_id);
//            $first_name = $user_info->first_name;
//            $last_name = $user_info->last_name;
//            $email = $user_info->user_email;
//            $country = get_user_meta( $current_user->ID, 'billing_country', true );            
//            $payload = array(
//                'email' => $email,
//                'firstName' => $first_name,
//                'lastName' => $last_name,
//                'country' => $country,
//                'product' => 'XL Agent Starter',
//                'endDate' => gmdate(DATE_ATOM, $date),
//            );
        endif;
    }

}

new RegisterEndpoints();
