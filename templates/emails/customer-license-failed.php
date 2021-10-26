<?php
/**
 * Cancelled Subscription email
 *
 * @author  Prospress
 * @package WooCommerce_Subscriptions/Templates/Emails
 * @version 2.6.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

do_action('woocommerce_email_header', $email_heading, $email);
?>

<?php /* translators: $1: customer's billing first name and last name */ ?>
<p><?php printf(esc_html__('Error obtaining license, please contact support', 'wc-fastspring'), esc_html($user->display_name)); ?></p>

<br/>
<?php

if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email);
