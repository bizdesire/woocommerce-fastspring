<?php

function get_product_by_fastspring($prod_id) {
    if (empty($prod_id))
        return FALSE;
    $post_id = 0;
    $args = array(
        'posts_per_page' => 1,
        'post_type' => 'product',
        'meta_query' => array(
            array(
                'key' => '_fastspring_productid',
                'value' => $prod_id,
                'compare' => '=',
            )
        )
    );
    $the_query = new WP_Query($args);
    if ($the_query->have_posts()) :
        while ($the_query->have_posts()) : $the_query->the_post();
            $post_id = get_the_ID();
        endwhile;
    endif;
    return $post_id;
}

function get_subscription_by_fastspring($subscription_id) {
    if (empty($subscription_id))
        return FALSE;
    $post_id = 0;
    $args = array(
        'posts_per_page' => 1,
        'post_type' => 'shop_subscription',
        'post_status' => 'active',
        'meta_query' => array(
            array(
                'key' => '_fastspring_subscriptionID',
                'value' => $subscription_id,
                'compare' => 'LIKE',
            )
        )
    );

    $the_query = new WP_Query($args);
    if ($the_query->have_posts()) :
        while ($the_query->have_posts()) : $the_query->the_post();
            $post_id = get_the_ID();
        endwhile;
    endif;
    return $post_id;
}

function get_order_by_fastspring_key($order_key) {
    if (empty($order_key))
        return FALSE;
    $post_id = 0;
    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'shop_order',
        'post_status' => array_keys(wc_get_order_statuses()),
        'meta_query' => array(
            array(
                'key' => '_fastspring_orderID',
                'value' => $order_key,
                'compare' => 'LIKE',
            )
        )
    );
    $order_ids = array();
    $the_query = new WP_Query($args);
    if ($the_query->have_posts()) :
        while ($the_query->have_posts()) : $the_query->the_post();
            $order_ids[] = get_the_ID();
        endwhile;
    endif;
    return $order_ids;
}

function check_starter_package($user_id) {
    $has_starter = false;
    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    if ($active_subscriptions):
        foreach ($active_subscriptions as $id => $subscription):
            if ($subscription->has_status(array('active', 'on-hold','register_expired'))):
                foreach ($subscription->get_items() as $line_item) {
                    $product = $line_item->get_product();
                    $product_id = $product->get_id();
                    $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
                    if (strpos($fastspring_productid, 'starter') !== false) {
                        $has_starter = $subscription;
                    }
                }
            endif;
        endforeach;
    endif;

    return $has_starter;
}

function remove_user_old_roles($user) {
    if (empty($user))
        return;

    $user_id = $user->id;
    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    if ($active_subscriptions):
        foreach ($active_subscriptions as $id => $subscription):
            if ($subscription->has_status(array('pending-cancel', 'cancelled'))):
                foreach ($subscription->get_items() as $line_item) {
                    $product = $line_item->get_product();
                    $product_id = $product->get_id();
                    $product_role = get_post_meta($product_id, '_fastspring_product_role', true);
                    if ($product_role):
                        $user->remove_role($product_role);
                    endif;
                }
            endif;
        endforeach;
    endif;
}

/* * *******Assign user role ***************** */

function add_user_new_roles($user, $subscription) {

    if (empty($user))
        return;

    $user_id = $user->id;
    if ($subscription->has_status(array('active', 'pending'))):
        foreach ($subscription->get_items() as $line_item) {
            $product = $line_item->get_product();
            $product_id = $product->get_id();
            $product_role = get_post_meta($product_id, '_fastspring_product_role', true);

            if ($product_role):
                $user->add_role($product_role);
            endif;
        }
    endif;
}

/* * *******Save productto usermeta ***************** */

function add_user_subscribed_product($user_id, $product_name) {

    if (empty($user_id))
        return;
    update_user_meta($user_id, 'subscribed_product', $product_name);
}

function get_user_subscribed_product($user_id) {

    if (empty($user_id))
        return;
    return get_user_meta($user_id, 'subscribed_product', true);
}

function check_trial_package($user_id) {
    $has_trial = false;
    if (!$user_id)
        return;
    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    if ($active_subscriptions):
        foreach ($active_subscriptions as $id => $subscription):
            foreach ($subscription->get_items() as $line_item) :
                $product = $line_item->get_product();
                $product_id = $product->get_id();
                $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
                if (strpos($fastspring_productid, 'trial') !== false) :
                    $has_trial = $subscription;
                endif;
            endforeach;
        endforeach;
    endif;
    return $has_trial;
}

function Senddatamailchimp($data) {
    $listId = esc_attr(get_option('mailchimp_list_id'));
    $apiKey = esc_attr(get_option('mailchimp_api_key'));
    $memberId = md5(strtolower($data['email']));
    $dataCenter = substr($apiKey, strpos($apiKey, '-') + 1);
    $url = 'https://' . $dataCenter . '.api.mailchimp.com/3.0/lists/' . $listId . '/members/' . $memberId;

    $json = json_encode([
        'email_address' => $data['email'],
        'status' => $data['status'],
        'merge_fields' => [
            'FNAME' => @$data['firstname'],
            'LNAME' => @$data['lastname'],
            'PHONE' => @$data['phone'],
            'ADDRESS' => @$data['display'],
        ]
    ]);

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $apiKey);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}

function get_subscriptions_using_fastspring_key($all_subscriptions) {
    if (empty($all_subscriptions))
        return FALSE;
    $already_bought = false;
    $post_id = 0;
    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'shop_order',
        'post_status' => array_keys(wc_get_order_statuses()),
        'meta_query' => array(
            array(
                'key' => '_fastspring_subscriptionID',
                'value' => $all_subscriptions,
                'compare' => 'IN',
            )
        )
    );
    $order_ids = array();
    $the_query = new WP_Query($args);

    if ($the_query->have_posts()) :
        while ($the_query->have_posts()) : $the_query->the_post();
            $order_id = get_the_ID();
            $order = wc_get_order($order_id);
            foreach ($order->get_items() as $line_item) {
                $product = $line_item->get_product();
                $product_id = $product->get_id();
                $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
                if (strpos($fastspring_productid, 'trial') !== false) {
                    $already_bought = TRUE;
                }
            }
        endwhile;
    endif;
    return $already_bought;
}

function user_has_active_trial_subscription($user_id = null) {
    $staus = TRUE;
    if (null == $user_id && is_user_logged_in())
        $user_id = get_current_user_id();
    // User not logged in we return false
    if ($user_id == 0)
        return false;

    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    foreach ($active_subscriptions as $id => $active_subscription):
        if ($active_subscription->has_status(array('active', 'on-hold','register_expired'))):
            $trial_end = $active_subscription->get_date('trial_end');
            $trial_added = get_user_meta($user_id, 'trial_added', TRUE);
            if ($trial_end && $trial_added == "Yes"):
                $current_timestamp = strtotime(date('Y-m-d H:i:s'));
                $trial_end = strtotime($trial_end);
                if ($current_timestamp < $trial_end):
                    $staus = "user_on_trial";
                elseif ($trial_added == 'Yes'):
                    $staus = "user_take_trial";
                else:
                endif;
            endif;
        endif;
    endforeach;
    return $staus;
}

function get_current_subscribed_product(){
    $fastspring_productid = '';
    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    if ($active_subscriptions):
        foreach ($active_subscriptions as $id => $subscription):
            if ($subscription->has_status(array('active', 'on-hold','register_expired'))):
                foreach ($subscription->get_items() as $line_item) {
                    $product = $line_item->get_product();
                    $product_id = $product->get_id();
                    $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);                 
                }
            endif;
        endforeach;
    endif;
    return $fastspring_productid;
}