<div class="product-plan-inner">
    <div class="product-plan-image">
        <?php echo woocommerce_get_product_thumbnail() ?>
    </div>
    <div class="product-plan-title">
        <?php the_title() ?>
    </div>
    <div class="product-plan-price">
        <?php wc_get_template('loop/price.php'); ?>
    </div>
    <div class="product-plan-btn <?= get_the_id() ?>">
        <?php
//        $post_id = get_post_meta(get_the_id(), '_fastspring_productid', true);
        $has_starter = FALSE;
        $days = 0;
        $user_id = get_current_user_id();
        $current_id = get_the_id();
        $has_starter = check_starter_package($user_id);
        $active_subscriptions = wcs_get_users_subscriptions($user_id);
        foreach ($active_subscriptions as $id => $active_subscription):
            if ($active_subscription->has_status(array('active', 'on-hold', 'register_expired'))):
                $next_payment = $active_subscription->get_date('next_payment');
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

        if (!$has_starter):
            if (in_array(get_the_id(), $product_ids)):
                ?>
                <?php
            else:
                if ($subscription_period && $term_name):
                    $show = display_product_btn($current_id, $subscription_period, $term_name, $days);
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
                        <?php //the_content();  ?>
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
        ?>
    </div>
</div>