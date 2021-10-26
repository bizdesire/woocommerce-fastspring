<?php

use \Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore as CustomersDataStore;
use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Order;
use Emileperron\FastSpring\Entity\Product;
use Emileperron\FastSpring\Entity\Subscription;
use Automattic\WooCommerce\Client;
use Emileperron\FastSpring\Entity\Account;

class WoocommerceOrder {

    public function __construct() {
        $apiUsername = esc_attr(get_option('api_username'));
        $apiPassword = esc_attr(get_option('api_password'));
        FastSpring::initialize($apiUsername, $apiPassword);
        /*
          Override Actions
         */
        add_action('woocommerce_before_customer_changed_subscription_to_register_expired', array($this, 'fn_trigger_fastspring_subscription_register_expired'));
        add_action('woocommerce_customer_changed_subscription_to_cancelled', array($this, 'fn_trigger_fastspring_subscription_cancel'));
        add_action('woocommerce_subscription_status_cancelled', array($this, 'fn_trigger_fastspring_subscription_cancel'));
        add_action('woocommerce_subscription_status_expired', array($this, 'fn_trigger_fastspring_subscription_expired'));
        add_action('init', array($this, 'register_manage_plan_endpoint'));
        add_action('manage_product_posts_custom_column', array($this, 'get_fastspring_product_id'), 10, 2);
        add_action('delete_user', array($this, 'delete_woocommerce_customer'));
        add_action('template_redirect', array($this, 'redirect_woo_pages_to_myaccount'));
        add_action('woocommerce_account_manage-plan_endpoint', array($this, 'add_manage_plan_content'));
        add_action('woocommerce_account_manage-billing_endpoint', array($this, 'add_manage_billing_content'));
        // add_action('woocommerce_account_customer-delete_endpoint', array($this, 'add_manage_customer_delete_content'));

        /*
          Override Filters
         */
        add_filter('query_vars', array($this, 'manage_plan_query_vars'));
        add_filter('woocommerce_account_menu_items', array($this, 'register_manage_plan_page_item'));
        add_filter('manage_product_posts_columns', array($this, 'add_fastspring_postid_filter_posts_columns'));
        add_filter('wcs_view_subscription_actions', array($this, 'override_subscription_actions'), 99, 2);
        add_filter('woocommerce_account_menu_items', array($this, 'change_woocommerce_account_menu_items'), 99, 2);
        add_filter('woocommerce_subscriptions_registered_statuses', array($this, 'register_new_post_status'), 100, 1);
        add_filter('wcs_subscription_statuses', array($this, 'add_new_subscription_statuses'), 100, 1);
        add_filter('woocommerce_can_subscription_be_updated_to', array($this, 'subscription_can_be_updated_to'), 100, 3);
        add_filter('woocommerce_can_subscription_be_updated_to_active', array($this, 'woocommerce_can_subscription_be_updated_to_active'), 100, 3);

        add_action('woocommerce_subscription_status_updated', array($this, 'extends_update_status'), 100, 3);

        //  add_filter('woocommerce_get_endpoint_url', array($this, 'add_custom_billing_endpoint'), 10, 4);

        /*
         * Remove Actions
         *  */
        add_action('woocommerce_init', array($this, 'remove_woommerce_actions'), 15);
    }

    function subscription_can_be_updated_to($can_be_updated, $new_status, $subscription) {
        if ($new_status == 'register_expired') {
            if ($subscription->has_status(array('active', 'register_expired', 'on-hold'))) {
                $can_be_updated = true;
            } else {
                $can_be_updated = false;
            }
        }
        return $can_be_updated;
    }

    function woocommerce_can_subscription_be_updated_to_active($can_be_updated, $subscription) {
        if ($subscription->has_status(array('register_expired'))) {
            $can_be_updated = true;
        }
        return $can_be_updated;
    }

    function extends_update_status($subscription, $new_status, $old_status) {
        if ($new_status == 'register_expired') {
            $subscription->update_suspension_count($subscription->suspension_count + 1);
            wcs_maybe_make_user_inactive($subscription->customer_user);
        }
    }

    function add_new_subscription_statuses($subscription_statuses) {
        $subscription_statuses['wc-register_expired'] = _x('Cancel Subscription', 'Subscription status', 'custom-wcs-status-texts');
        return $subscription_statuses;
    }

    function register_new_post_status($registered_statuses) {
        $registered_statuses['wc-register_expired'] = _nx_noop('Cancel Subscription <span class="count">(%s)</span>', 'Cancel Subscription <span class="count">(%s)</span>', 'post status label including post count', 'custom-wcs-status-texts');
        return $registered_statuses;
    }

    function add_custom_billing_endpoint($url, $endpoint, $value, $permalink) {
        if ($endpoint === 'billing') {
            $url = 'https://app.fastspring.com/';
        }
        return $url;
    }

    function remove_woommerce_actions() {
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart');
        remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
    }

    function change_woocommerce_account_menu_items($items, $endpoints) {
        unset($items['dashboard']);
        unset($items['downloads']);
        unset($items['edit-address']);
        //  $items['customer-delete'] = __('Delete account', 'woocommerce');
        return $items;
    }

    function override_subscription_actions($actions, $subscription) {
        $status = $subscription->get_status();
        foreach ($actions as $key => $action) :
            if ($key != 'cancel')
                unset($actions[$key]);
        endforeach;
        $current_status = $subscription->get_status();
        //        echo "<pre>";
//        var_dump($actions);
//        echo "</pre>";


        foreach ($subscription->get_items() as $line_item) {
            $product = $line_item->get_product();
            $product_id = $product->get_id();
            $terms = get_the_terms($product_id, 'product_cat');
            $term_name = '';
            if ($terms && !is_wp_error($terms)) :
                $draught_links = array();
                foreach ($terms as $term) {
                    $term_name = $term->name;
                }
            endif;
        }
//        echo $term_name;
        unset($actions['cancel']);
        if ($term_name == 'Starter') {
            unset($actions['cancel']);
        } else {
            if ($status != 'pending' && $status != 'register_expired') {
                $actions['register_expired'] = array(
                    'url' => wcs_get_users_change_status_link($subscription->get_id(), 'register_expired', $current_status),
                    'name' => _x('Cancel Subscription', 'an action on a subscription', 'woocommerce-subscriptions'),
                );
            }
        }
        return $actions;
    }

    function delete_woocommerce_customer($customer_id) {
        $customers_data_store = new CustomersDataStore();
        $customers_data_store->delete_customer_by_user_id($customer_id);
    }

    function add_fastspring_postid_filter_posts_columns($columns) {
        $columns['product_priority'] = __('Product Priority');
        $columns['fastspring_id'] = __('Fastspring ID');
        return $columns;
    }

    function get_fastspring_product_id($column, $post_id) {

        if ('fastspring_id' == $column) {
            echo get_post_meta($post_id, '_fastspring_productid', TRUE);
        }
        if ('product_priority' == $column) {
            echo get_post_meta($post_id, '_assign_product_priority', TRUE);
        }
    }

    public function fn_trigger_fastspring_subscription_register_expired($subscription) {
//        echo "<pre>";
//        var_dump($subscription);
//        echo "</pre>";
//        die();
        if (!$subscription)
            return;
        $subscription->update_status('wc-register_expired');
        if ($subscription->get_date('next_payment')) {
            $dates = array(
                'end' => $subscription->get_date('next_payment'),
                'next_payment' => ''
            );
            $subscription->add_order_note(_x('Subscription cancelled by the subscriber from their account page.', 'order note left on subscription after user action', 'woocommerce-subscriptions'));

            $subscription->update_dates($dates);
            $subscription->save_meta_data();
            $template_name = esc_attr(get_option('mandrill_subscription_canceled'));
            $user_id = $subscription->get_user_id();
            MandrillAPP::trigger_user_activity_email($user_id, $template_name);
        }
    }

    public function fn_trigger_fastspring_subscription_expired($subscription) {

        $subscription_id = $subscription->get_meta('_fastspring_subscriptionID');
        $user_id = $subscription->get_user_id();
        if ($user_id):
            foreach ($subscription->get_items() as $line_item) {
                $product = $line_item->get_product();
                $product_id = $product->get_id();
                $terms = get_the_terms($product_id, 'product_cat');
                $term_name = '';
                if ($terms && !is_wp_error($terms)) :
                    $draught_links = array();
                    foreach ($terms as $term) {
                        $term_name = $term->name;
                    }
                endif;
            }
            if ($term_name == 'Pro' || $term_name == 'Advanced') {
                RegisterEndpoints::create_default_subscription($user_id);
            }
        endif;

        $customer_id = $subscription->customer_id;
        $user = get_user_by('id', $customer_id);
        remove_user_old_roles($user);
        if (!$subscription_id)
            return;
        try {
            FastSpring::delete('subscriptions', [$subscription_id]);
        } catch (Exception $exc) {
//            echo '<pre>';
//            var_dump($exc);
//            echo '</pre>';
            $subscription->update_status('expired');
        }
        $user_email = $subscription->billing_email;
//                echo "<pre>";
//        var_dump($subscription);
//        echo "</pre>";
//die();
        // LicenseServerAPI::delete_license_user($user_email);
    }

    public function fn_trigger_fastspring_subscription_cancel($subscription) {

        $subscription_id = $subscription->get_meta('_fastspring_subscriptionID');
        $user_id = $subscription->get_user_id();
        if ($user_id):
            foreach ($subscription->get_items() as $line_item) {
                $product = $line_item->get_product();
                $product_id = $product->get_id();
                $terms = get_the_terms($product_id, 'product_cat');
                $term_name = '';
                if ($terms && !is_wp_error($terms)) :
                    $draught_links = array();
                    foreach ($terms as $term) {
                        $term_name = $term->name;
                    }
                endif;
            }
            if ($term_name == 'Pro' || $term_name == 'Advanced') {
                RegisterEndpoints::create_default_subscription($user_id);
            }
        endif;

        try {
            RegisterEndpoints::request_cancel_fastspring_subscription($subscription, $subscription_id);
        } catch (Exception $exc) {
            
        }
        $user_email = $subscription->billing_email;
//                echo "<pre>";
//        var_dump($subscription);
//        echo "</pre>";
//die();
        // LicenseServerAPI::delete_license_user($user_email);
    }

    public function register_manage_plan_page_item($menu_links) {
        $new = array(
            'manage-plan' => 'Manage Plan',
            'manage-billing' => 'Billing',
                //  'customer-delete' => 'Delete account',
        );
        $menu_links = array_slice($menu_links, 0, 1, true) + $new + array_slice($menu_links, 1, NULL, true);
        return $menu_links;
    }

    public function register_manage_plan_endpoint() {
        add_rewrite_endpoint('manage-plan', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('manage-billing', EP_ROOT | EP_PAGES);
        // add_rewrite_endpoint('customer-delete', EP_ROOT | EP_PAGES);
    }

    function manage_plan_query_vars($vars) {
        $vars[] = 'manage-plan';
        $vars[] = 'manage-billing';
        // $vars[] = 'customer-delete';
        return $vars;
    }

    function redirect_woo_pages_to_myaccount() {
        if (is_woocommerce() && !is_account_page()):
            $account_link = get_permalink(get_option('woocommerce_myaccount_page_id'));
            wp_safe_redirect($account_link);
            exit;
        endif;
    }

    function add_manage_billing_content() {
        wc_get_template(
                'woocommerce/manage-billing.php', array(), '', CUSTOM_TEMPLATE_PATH
        );
    }

    function add_manage_plan_content_new() {
        $subscribed_products = array();
        ?>
        <div class="product-plan-outer products columns-4">
            <div class="plans-blocks">
                <span class="section-title">Current Plan</span>
                <?php
                if (null == $user_id && is_user_logged_in())
                    $user_id = get_current_user_id();
                $active_subscriptions = wcs_get_users_subscriptions($user_id);
                foreach ($active_subscriptions as $id => $active_subscription):
                    if ($active_subscription->has_status(array('active', 'on-hold', 'register_expired'))):
                        $next_payment = $active_subscription->get_date('next_payment');
                        $now = date_create(date("Y-m-d"));
                        $next_pay = date_create($next_payment);
                        $diff = date_diff($now, $next_pay);
                        $days = $diff->format("%a");
                        $days = intval($days);

                        foreach ($active_subscription->get_items() as $line_item):
                            $product = $line_item->get_product();
                            $product_id = $product->get_id();
                            $subscribed_products[] = $product_id;
                            if (!$subscription_period = get_post_meta($product_id, '_subscription_period', true)) {
                                $subscription_period = 'month';
                            }
                            // $subscription_period = 'month';
                            $terms = get_the_terms($product_id, 'product_cat');
                            $term_name = '';
                            if ($terms && !is_wp_error($terms)) :
                                $draught_links = array();
                                foreach ($terms as $term):
                                    $term_name = $term->name;
                                endforeach;
                            endif;
                            ?>

                            <?php
//                            $subscribed_products[] = get_the_id();
//                            wc_get_template(
//                                    'woocommerce/product-loop.php', array(
//                                'product_ids' => $subscribed_products
//                                    ), '', CUSTOM_TEMPLATE_PATH
//                            );
                            ?>

                            <?php
                        endforeach;
                    endif;
                endforeach;
                ?>
            </div>
            <?php ?>
            <div class="plans-blocks">
                <span class="section-title">Change Plan</span>
                <?php
                $args = array(
                    'posts_per_page' => -1,
                    'post_type' => 'product',
                    'meta_query' => array(
                        array(
                            'key' => '_fastspring_productid',
                            'value' => 'trial',
                            'compare' => 'NOT LIKE'
                        )
                    )
                );
                $the_query = new WP_Query($args);
                if ($the_query->have_posts()) :
                    while ($the_query->have_posts()) : $the_query->the_post();
                        $current_period = get_post_meta(get_the_id(), '_subscription_period', true);
                        $title = get_the_title();

                        if ($subscription_period == "month" && $term_name == "Pro"):
                            if ($product_id != get_the_id()):

                                if ((strpos($title, $term_name) !== TRUE) && (strpos($title, 'Starter') == FALSE)):
                                    wc_get_template(
                                            'woocommerce/product-loop.php', array(
                                        'product_ids' => $subscribed_products
                                            ), '', CUSTOM_TEMPLATE_PATH
                                    );
                                endif;
                            endif;
                        elseif ($subscription_period == "year" && $term_name == "Pro"):
                            if ($days >= 30):
                                if ($product_id != get_the_id()):
                                    $terms = get_the_terms(get_the_id(), 'product_cat');
                                    $all_terms = [];
                                    if ($terms && !is_wp_error($terms)) :
                                        $draught_links = array();
                                        foreach ($terms as $term):
                                            $all_terms[] = $term->name;
                                        endforeach;
                                    endif;
                                    if (!in_array($term_name, $all_terms)):
                                        wc_get_template(
                                                'woocommerce/product-loop.php', array(
                                            'product_ids' => $subscribed_products
                                                ), '', CUSTOM_TEMPLATE_PATH
                                        );
                                    endif;
                                endif;
                            endif;
                        elseif ($subscription_period == "month" && $term_name == "Advanced"):
                            if ($product_id != get_the_id()):
                                $terms = get_the_terms(get_the_id(), 'product_cat');
                                $all_terms = [];
                                if ($terms && !is_wp_error($terms)) :
                                    $draught_links = array();
                                    foreach ($terms as $term):
                                        $all_terms[] = $term->name;
                                    endforeach;
                                endif;
                                if (strpos($title, $term_name) == TRUE):
                                    wc_get_template(
                                            'woocommerce/product-loop.php', array(
                                        'product_ids' => $subscribed_products
                                            ), '', CUSTOM_TEMPLATE_PATH
                                    );
                                endif;
                            endif;

                        elseif ($subscription_period == "year" && $term_name == "Advanced"):
                            if ($days >= 30):
                                if ($product_id != get_the_id()):
                                    wc_get_template(
                                            'woocommerce/product-loop.php', array(
                                        'product_ids' => $subscribed_products
                                            ), '', CUSTOM_TEMPLATE_PATH
                                    );
                                endif;
                            endif;
                        endif;
                    endwhile;

//                    if ($subscription_period == "month" && $term_name == "Pro"):
//                        $find_meta = "pro";
//
//                        $args = array(
//                            'posts_per_page' => -1,
//                            'post_type' => 'product',
//                            'meta_query' => array(
//                                array(
//                                    'key' => '_fastspring_productid',
//                                    'value' => $find_meta,
//                                    'compare' => 'LIKE'
//                                ),
//                                array(
//                                    'key' => '_fastspring_productid',
//                                    'value' => 'trial',
//                                    'compare' => 'NOT LIKE'
//                                ),
//                                array(
//                                    'key' => '_subscription_period',
//                                    'value' => $subscription_period,
//                                    'compare' => 'NOT LIKE'
//                                )
//                            )
//                        );
//                        $the_query = new WP_Query($args);
//                        if ($the_query->have_posts()) :
//                            while ($the_query->have_posts()) : $the_query->the_post();
//                                $current_period = get_post_meta(get_the_id(), '_subscription_period', true);
//                                wc_get_template(
//                                        'woocommerce/product-loop.php', array(
//                                    'product_ids' => $subscribed_products
//                                        ), '', CUSTOM_TEMPLATE_PATH
//                                );
//                            endwhile;
//                        endif;
//                    endif;
//                    if ($subscription_period == "year" && $term_name == "Advance"):
//                        $args = array(
//                            'posts_per_page' => -1,
//                            'post_type' => 'product',
//                            'meta_query' => array(
//                                array(
//                                    'key' => '_fastspring_productid',
//                                    'value' => $find_meta,
//                                    'compare' => 'NOT LIKE'
//                                ),
//                                array(
//                                    'key' => '_fastspring_productid',
//                                    'value' => 'trial',
//                                    'compare' => 'NOT LIKE'
//                                ),
//                            )
//                        );
//                        $the_query = new WP_Query($args);
//                        if ($the_query->have_posts()) :
//                            while ($the_query->have_posts()) : $the_query->the_post();
//                                $current_period = get_post_meta(get_the_id(), '_subscription_period', true);
//                                wc_get_template(
//                                        'woocommerce/product-loop.php', array(
//                                    'product_ids' => $subscribed_products
//                                        ), '', CUSTOM_TEMPLATE_PATH
//                                );
//                            endwhile;
//                        endif;
//                    endif;
//                    $args = array(
//                        'posts_per_page' => -1,
//                        'post_type' => 'product',
//                        'meta_query' => array(
//                            array(
//                                'key' => '_fastspring_productid',
//                                'value' => $find_meta,
//                                'compare' => 'NOT LIKE'
//                            ),
//                            array(
//                                'key' => '_fastspring_productid',
//                                'value' => 'trial',
//                                'compare' => 'NOT LIKE'
//                            )
//                        )
//                    );
//                    $the_query = new WP_Query($args);
//                    if ($the_query->have_posts()) :
//                        while ($the_query->have_posts()) : $the_query->the_post();
//                            wc_get_template(
//                                    'woocommerce/product-loop.php', array(
//                                'product_ids' => $subscribed_products
//                                    ), '', CUSTOM_TEMPLATE_PATH
//                            );
//                        endwhile;
//                    endif;
                endif;
                ?>
            </div>
        </div>
        <?php
    }

    function add_manage_plan_content() {
        wc_get_template(
                'woocommerce/manage-plans.php', array(), '', CUSTOM_TEMPLATE_PATH
        );
        /*
          $current_priority = 0;
          $subscribed_products = array();
          $user_id = 0;
          if (is_user_logged_in()):
          $subscriptions = wcs_get_users_subscriptions();

          $subscription_count = count($subscriptions);
          $thank_you_message = '';
          $my_account_subscriptions_url = get_permalink(wc_get_page_id('myaccount'));
          if ($subscription_count) {
          foreach ($subscriptions as $subscription) {
          //                    echo $subscription->get_status() . '-' . $subscription->get_id();
          //                    echo '</br>';
          if ($subscription->has_status((array('active', 'on-hold', 'register_expired')))) {
          foreach ($subscription->get_items() as $line_item) {
          $product = $line_item->get_product();
          $product_id = $product->get_id();
          $active_fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
          $current_priority = get_post_meta($product_id, '_assign_product_priority', true);
          $subscribed_products[] = $product_id;
          if (!$subscription_period = get_post_meta($product_id, '_subscription_period', true)) {
          $subscription_period = 'month';
          }
          $terms = get_the_terms($product_id, 'product_cat');
          $term_name = '';
          if ($terms && !is_wp_error($terms)) :
          $draught_links = array();
          foreach ($terms as $term):
          $term_name = $term->name;
          endforeach;
          endif;
          }
          }
          }
          }
          $user_id = get_current_user_id();
          endif;

          $args = array(
          'posts_per_page' => -1,
          'post_type' => 'product',
          'meta_key' => '_assign_product_priority',
          'orderby' => 'meta_value_num',
          'order' => 'ASC',
          'meta_query' => array(
          array(
          'key' => '_fastspring_productid',
          'compare' => 'EXISTS'
          )
          )
          );
          $upgrade_plan = array();
          $downgrade_plan = array();

          $the_query = new WP_Query($args);
          if ($the_query->have_posts()) :
          while ($the_query->have_posts()) : $the_query->the_post();
          $product_id = get_the_id();
          $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
          if (strpos($active_fastspring_productid, 'starter') == TRUE && !empty(check_trial_package($user_id))):
          if (strpos($fastspring_productid, 'trial') == false) :
          $priority = get_post_meta($product_id, '_assign_product_priority', true);
          //                            if ($priority < $current_priority) :
          //                                $downgrade_plan[] = get_the_ID();
          //                            else:
          $upgrade_plan[] = get_the_ID();
          //   endif;
          endif;
          elseif (strpos($active_fastspring_productid, 'starter') == TRUE):
          $upgrade_plan[] = get_the_ID();
          else:
          $priority = get_post_meta($product_id, '_assign_product_priority', true);
          //                            if ($priority < $current_priority) :
          //                                $downgrade_plan[] = get_the_ID();
          //                            else:
          $upgrade_plan[] = get_the_ID();
          // endif;

          endif;
          endwhile;
          endif;

          if ($the_query->have_posts()) :
          ?>
          <div class="product-plan-outer products columns-4">

          <?php if (count($subscribed_products)): ?>
          <div class="plans-blocks">
          <span class="section-title">Current Plan</span>
          <?php
          while ($the_query->have_posts()) : $the_query->the_post();
          $product_id = get_the_id();

          if (in_array($product_id, $subscribed_products)) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;
          endwhile;
          ?>
          </div>
          <?php endif; ?>
          <?php if (count($upgrade_plan) && $current_priority): ?>
          <div class="plans-blocks">
          <span class="section-title">Change Plan</span>
          <?php
          //                        echo "<pre>";
          //                        var_dump($upgrade_plan);
          //                        echo "</pre>";

          while ($the_query->have_posts()) : $the_query->the_post();
          $product_id = get_the_id();
          $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
          if ((strpos($active_fastspring_productid, 'starter') == TRUE) && empty(check_trial_package($user_id))) :
          if (strpos($fastspring_productid, 'trial') == TRUE) :

          if (in_array($product_id, $upgrade_plan) && !in_array($product_id, $subscribed_products)) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;
          endif;
          else:
          if ((strpos($active_fastspring_productid, 'trial') !== false)):
          //                               echo "<pre>";
          //                               var_dump($upgrade_plan);
          //                               echo "</pre>";

          if ((strpos($fastspring_productid, 'trial') !== false)):
          if (in_array($product_id, $upgrade_plan) && !in_array($product_id, $subscribed_products)) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;

          endif;
          if ((strpos($fastspring_productid, 'starter') !== false)):
          if (in_array($product_id, $upgrade_plan) && !in_array($product_id, $subscribed_products)) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;
          endif;
          else:

          if ((strpos($fastspring_productid, 'trial') == false)):
          if (in_array($product_id, $upgrade_plan) && !in_array($product_id, $subscribed_products)) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;
          endif;

          endif;
          // endif;
          endif;
          endwhile;
          ?>
          </div>
          <?php endif; ?>
          <?php if (count($downgrade_plan)): ?>
          <div class="plans-blocks">
          <span class="section-title">Downgrade Plan</span>
          <?php
          while ($the_query->have_posts()) : $the_query->the_post();
          $product_id = get_the_id();
          $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
          if (strpos($fastspring_productid, 'trial') == false) :
          if (in_array($product_id, $downgrade_plan) && !in_array($product_id, $subscribed_products)) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;
          endif;
          endwhile;
          ?>
          </div>
          <?php endif; ?>
          <?php if (!$current_priority): ?>
          <div class="plans-blocks">
          <span class="section-title">Choose Plan</span>
          <?php
          while ($the_query->have_posts()) : $the_query->the_post();
          $product_id = get_the_id();
          $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
          if (strpos($fastspring_productid, 'trial') == false && strpos($fastspring_productid, 'starter') == false) :
          wc_get_template(
          'woocommerce/product-loop.php', array(
          'product_ids' => $subscribed_products,
          'subscription_period' => $subscription_period,
          'term_name' => $term_name
          ), '', CUSTOM_TEMPLATE_PATH
          );
          endif;
          endwhile;
          ?>
          </div>
          <?php endif; ?>
          <div class="response-message"></div>
          </div>
          <style>
          .response-message-inner {
          border: 1px solid red;
          padding: 10px;
          width: 100%;
          font-size: 18px;
          }
          </style>
          <?php
          endif; */
        ?>
        <!--        <div class="response-message"></div>
           
                <style>
                    .response-message-inner {
                        border: 1px solid red;
                        padding: 10px;
                        width: 100%;
                        font-size: 18px;
                    }
                </style>-->
        <?php
    }

}

new WoocommerceOrder();

add_action('validate_password_reset', 'rsm_redirect_after_rest', 10, 2);

function rsm_redirect_after_rest($errors, $user) {
    if ((!$errors->get_error_code() ) && isset($_POST['password_1']) && !empty($_POST['password_1'])) {
        reset_password($user, $_POST['password_1']);

        list( $rp_path ) = explode('?', wp_unslash($_SERVER['REQUEST_URI']));
        $rp_cookie = 'wp-resetpass-' . COOKIEHASH;
        setcookie($rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true);
        MandrillAPP::trigger_new_account_email($user->ID);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user); //`[Codex Ref.][1]
        wp_redirect(home_url('/my-account'));
        exit;
    }
}

add_action('woocommerce_subscription_status_active', 'fn_trigger_license_server');

function fn_trigger_license_server($subscription) {
    $user_email = $subscription->billing_email;
    $oldProduct = LicenseServerAPI::check_license_user($user_email);
    if ($oldProduct != 'false'):
        LicenseServerAPI::update_license_user($subscription, $oldProduct);
    else:
        LicenseServerAPI::add_license_user($subscription);
    endif;
}

add_action('deleted_user', 'delete_user_from_ls', 10, 3);

function delete_user_from_ls($id, $reassign, $user) {
//    echo "<pre>";
//    var_dump($user);
//    echo "</pre>";
//    die();
    $email_id = $user->data->user_email;
    LicenseServerAPI::delete_license_user($email_id);
}

add_action('template_redirect', 'delete_wc_template_redirect');

function delete_wc_template_redirect() {
    global $wp_query, $wp;
    if (isset($wp->query_vars['customer-logout'])) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            RegisterEndpoints:: create_default_subscription($user_id);
        }
        wp_safe_redirect(str_replace('&amp;', '&', wp_logout_url(wc_get_page_permalink('myaccount'))));
        exit;
    }
}

add_filter('wcs_renewal_order_created', 'update_wcs_renewal_parent_order_status', 10, 2);

function update_wcs_renewal_parent_order_status($renewal_order, $subscription) {
    $subscription->update_status('active');
}

function display_product_btn($post_id, $subscription_period, $term_name, $days) {
    $show = false;
    if (!$post_id)
        return TRUE;
    $title = get_the_title($post_id);
    //echo "prod" . $post_id . " " . $subscription_period . " " . $term_name . " " . $days;
    if ($subscription_period == "month" && $term_name == "Pro"):
        if ($product_id != get_the_id()):
            if ((strpos($title, $term_name) !== TRUE) && (strpos($title, 'Starter') == FALSE)):
                $terms = get_the_terms($post_id, 'product_cat');
                $all_terms = [];
                if ($terms && !is_wp_error($terms)) :
                    $draught_links = array();
                    foreach ($terms as $term):
                        $all_terms[] = $term->name;
                    endforeach;
                endif;
                //if (!in_array('Starter', $all_terms)):
                $show = TRUE;
            // endif;
            endif;
        endif;
    elseif ($subscription_period == "year" && $term_name == "Pro"):
        //if ($days >= 30):
        $terms = get_the_terms($post_id, 'product_cat');
        $all_terms = [];
        if ($terms && !is_wp_error($terms)) :
            $draught_links = array();
            foreach ($terms as $term):
                $all_terms[] = $term->name;
            endforeach;
        endif;
        if (!in_array($term_name, $all_terms) && !in_array('Starter', $all_terms)):
            $show = TRUE;
        endif;
    //endif;
    elseif ($subscription_period == "month" && $term_name == "Advanced"):
        if ($product_id != get_the_id()):
            $terms = get_the_terms(get_the_id(), 'product_cat');
            $all_terms = [];
            if ($terms && !is_wp_error($terms)) :
                $draught_links = array();
                foreach ($terms as $term):
                    $all_terms[] = $term->name;
                endforeach;
            endif;
            //if (strpos($title, $term_name) == TRUE):
            $show = TRUE;
        // endif;
        endif;

    elseif ($subscription_period == "year" && $term_name == "Advanced"):
        if ($days >= 30):
            if ($product_id != get_the_id()):
                $show = TRUE;
            endif;
        endif;
    endif;

    return $show;
}

add_filter('woocommerce_currency_symbol', 'change_existing_currency_symbol', 10, 2);

function change_existing_currency_symbol($currency_symbol, $currency) {
    switch ($currency) {
        case 'INR': $currency_symbol = 'INR';
            break;
    }
    return $currency_symbol;
}

add_filter('woocommerce_subscriptions_product_price_string_inclusions', 'change_woocommerce_subscriptions_product_price_string_inclusions', 10, 2);

function change_woocommerce_subscriptions_product_price_string_inclusions($include, $product) {
    $product_id = $product->get_id();
    $product_price = get_post_meta($product_id, '_fastspring_product_price', true);
    if ($product_price):
        $include['price'] = $product_price;
    endif;
//   echo "<pre>";
//   var_dump($include);
//   echo "</pre>";
    return $include;
}

add_action('woocommerce_subscription_after_actions', 'change_woocommerce_subscription_after_actions', 10, 1);

function change_woocommerce_subscription_after_actions($subscription) {
    $status = $subscription->get_status();
    if ($status == 'register_expired') {
        ?>
        <tr>
            <td colspan="2">
                <strong>
                    Your subscription request has been received. You will be migrated to the starter pack after your end date.
                </strong>
            </td>
        </tr>
        <?php
    }
}

function get_product_button($category_id, $product_ids, $subscription_period, $term_name) {
    $active_fastspring_productid = get_current_subscribed_product();
    $active_subscriptions = wcs_get_users_subscriptions($user_id);
    $days = 0;
    $subscription_status = '';
    foreach ($active_subscriptions as $id => $active_subscription):
        if ($active_subscription->has_status(array('active', 'on-hold', 'register_expired'))):
            $next_payment = $active_subscription->get_date('next_payment');
            $subscription_status = $active_subscription->get_status();
            if ($next_payment):
                $now = date_create(date("Y-m-d"));
                $next_pay = date_create($next_payment);
                $diff = date_diff($now, $next_pay);
                $days = $diff->format("%a");
//            var_dump($days);
                $days = intval($days);
            endif;
        endif;
    endforeach;
    $args = array(
        'post_type' => 'product',
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_id
            )
        )
    );

    $the_query = new WP_Query($args);
    if ($the_query->have_posts()) :
        while ($the_query->have_posts()) : $the_query->the_post();
            $has_starter = FALSE;
            $user_id = get_current_user_id();
            $current_id = get_the_id();
            $sub_period = get_post_meta($current_id, '_subscription_period', TRUE);
            $product_id = get_the_id();

            $fastspring_productid = get_post_meta($product_id, '_fastspring_productid', true);
            ?>
            <div class="<?= $sub_period ?> <?= $fastspring_productid ?> <?= $active_fastspring_productid ?> days-<?= $days ?>" style="<?= $sub_period == 'year' ? "display: none" : '' ?>">
                <?php
                if (strpos($active_fastspring_productid, 'starter') !== false):
                    if (strpos($fastspring_productid, 'starter') !== false):
                        ?>
                        <a href="#" class="fastspring_btn fastspring_btn-success disabled">Current Plan</a>                                           
                        <?php
                    else:
                        $nonce = wp_create_nonce("change_plan_nonce");
                        $trial_added = get_user_meta($user_id, 'trial_added', TRUE);
                        if ($trial_added == "Yes"):
                            if (strpos($fastspring_productid, 'trial') == false) :
                                the_content();
                            endif;
                        else:
                            if (strpos($fastspring_productid, 'trial') !== false) :
                                the_content();
                            endif;

                        endif;
                    endif;
                elseif (strpos($active_fastspring_productid, 'trial') == false):
                    if (strpos($fastspring_productid, 'trial') == false) :
                        ?>
                        <?php
                        if ($fastspring_productid == $active_fastspring_productid):
                            ?>
                            <a href="#" class="fastspring_btn fastspring_btn-success disabled">Current Plan</a>                                           
                            <?php
                        else:
                            $has_starter = check_starter_package($user_id);
                            if (!$has_starter):
                                if (in_array(get_the_id(), $product_ids)):
                                    ?>
                                    <?php
                                else:
                                    if ($subscription_period && $term_name):
                                        $show = display_product_btn($current_id, $subscription_period, $term_name, $days);
                                        //var_dump($show);
                                        $fastspring_productid = get_post_meta($current_id, '_fastspring_productid', TRUE);
                                        if (strpos($fastspring_productid, 'trial') == TRUE) :
                                            $show = TRUE;
                                        endif;
                                        if ($show):
                                            $nonce = wp_create_nonce("change_plan_nonce");
                                            ?>
                                            <span data-fsc-item-selection-smartdisplay-inverse="" style="display: block;">
                                                <a href="#" class="fastspring_btn fastspring_btn-success change_plan_btn" data-nonce="<?= $nonce ?>" data-upgrade="plan" data-product_id="<?= get_the_id() ?>">
                                                    <i class="fa fa fa-plus" aria-hidden="true"></i>&nbsp;Change Plan</a>
                                            </span>
                                            <?php
                                        else:
                                            if (strpos($fastspring_productid, 'starter') !== false):
                                                if ($subscription_status == 'register_expired'):
                                                    ?>
                                                    <a href="#" class="fastspring_btn fastspring_btn-info disabled">
                                                        Upcoming Plan
                                                    </a>
                                                    <?php
                                                else:
                                                    ?>
                                                    <span>
                                                        <a href="#" class="fastspring_btn fastspring_btn-success activate-starter-request">
                                                            <i class="fa fa fa-plus" aria-hidden="true"></i>&nbsp;Change Plan</a>
                                                    </span>
                                                <?php
                                                endif;
                                            elseif (strpos($active_fastspring_productid, 'starter') == false):
                                                ?>
                                                <a href="#" class="fastspring_btn fastspring_btn-danger disabled">
                                                    Not available
                                                </a>

                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php the_content(); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php
                            else:
                                if (!in_array($current_id, $product_ids)):
                                    ?>
                                    <?php the_content(); ?>

                                    <?php
                                endif;
                            endif;

                        endif;
                    endif;
                else:
                    // echo "I am here";
                    if (strpos($fastspring_productid, 'starter') !== false):
                        if ($subscription_status == 'register_expired'):
                            ?>
                            <a href="#" class="fastspring_btn fastspring_btn-info disabled">
                                Upcoming Plan
                            </a>
                            <?php
                        else:
                            ?>
                            <span>
                                <a href="#" class="fastspring_btn fastspring_btn-success activate-starter-request">
                                    <i class="fa fa fa-plus" aria-hidden="true"></i>&nbsp;Change Plan</a>
                            </span>
                        <?php
                        endif;
                    elseif (strpos($active_fastspring_productid, 'trial') !== false):
                        if (strpos($fastspring_productid, 'trial') !== false) :
                            if ($fastspring_productid == $active_fastspring_productid):
                                ?>
                                <a href="#" class="fastspring_btn fastspring_btn-success disabled">Current Plan</a>                                    
                                <?php
                            else:
                                $nonce = wp_create_nonce("change_plan_nonce");
                                ?>
                                <span data-fsc-item-selection-smartdisplay-inverse="" style="display: block;">
                                    <a href="#" class="fastspring_btn fastspring_btn-success change_plan_btn" data-nonce="<?= $nonce ?>" data-upgrade="plan" data-product_id="<?= get_the_id() ?>">
                                        <i class="fa fa fa-plus" aria-hidden="true"></i>&nbsp;Change Plan</a>
                                </span>
                            <?php
                            endif;
                        endif;
                    endif;

                endif;
                ?>
            </div>
            <?php
        endwhile;
        wp_reset_postdata();
    endif;
}

add_filter('woocommerce_order_subtotal_to_display', 'woocommerce_trial_order_formatted_line_subtotal', 10, 3);

add_filter('woocommerce_order_formatted_line_subtotal', 'woocommerce_trial_order_formatted_line_subtotal', 10, 3);

function woocommerce_trial_order_formatted_line_subtotal($subtotal, $item, $order) {
    if (wcs_is_subscription($order)) {
        $trial_end = $order->get_date('trial_end');
        $today = strtotime(date("Y-m-d H:i:s"));
        if ($trial_end):
            if ($today < strtotime($trial_end)):
                $subtotal = wc_price(0, array('currency' => $order->get_currency()));
            endif;
        endif;
    }
    return $subtotal;
}

add_filter('woocommerce_get_formatted_subscription_total', 'woocommerce_trial_order_formatted_line_total', 10, 2);

function woocommerce_trial_order_formatted_line_total($subtotal, $order) {
    if (wcs_is_subscription($order)) {
        $trial_end = $order->get_date('trial_end');
        $today = strtotime(date("Y-m-d H:i:s"));
        if ($trial_end):
            if ($today < strtotime($trial_end)):
                $subtotal = wc_price(0, array('currency' => $order->get_currency()));
            endif;
        endif;
    }
    return $subtotal;
}
