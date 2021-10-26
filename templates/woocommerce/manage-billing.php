<?php
/**
 * Manage billing
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$allowed_html = array(
    'a' => array(
        'href' => array(),
    ),
);
?>

<p>
    <?php
    printf(__('Click the link bellow to manage your payment methods', 'woocommerce'));
    ?>
</p>
<p>
    <a href="https://workscope.onfastspring.com/account" target="_blank" >FastSpring account<img class="account-img" width="16" height="16" src="<?= plugins_url() ?>/woocommerce-fastspring/assets/imgs/open_new.png" alt="" title=""></a>   
</p>