<?php
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
                $upgrade_plan[] = get_the_ID();
            endif;
        elseif (strpos($active_fastspring_productid, 'starter') == TRUE):
            $upgrade_plan[] = get_the_ID();
        else:
            $priority = get_post_meta($product_id, '_assign_product_priority', true);
            $upgrade_plan[] = get_the_ID();
        endif;
    endwhile;
    wp_reset_postdata();
endif;
$args = array(
    'post_type' => 'page',
    'post__in' => array(687)
);
$the_query = new WP_Query($args);
//echo "<pre>";
//var_dump($the_query);
//echo "</pre>";
$subscribed_product = get_current_subscribed_product();
//if ($the_query->have_posts()) :
//    while ($the_query->have_posts()) : $the_query->the_post();
?>
<div id="pricing">
    <div class="container">
        <!-- end .pricing-table-caption-mobile -->
        <div class="pricing-table">         
            <!-- end .pricing-table-col -->
            <?php
            $count = 0;
            if (have_rows('pricing_details', 687)):
                while (have_rows('pricing_details', 687)) : the_row();
                    ?>
                    <div class="pricing-table-col content-col <?= get_sub_field('plan_class') ?>">
                        <div class="pricing-table-head">
                            <div class="plan-name">
                                <?= get_sub_field('plan_title') ?>                                                    
                            </div>
                            <div class="inner">
                                <div class="plan-desc">
                                    <p> <?= get_sub_field('description') ?>  </p>
                                </div>
                                <div class="plan-payment">
                                    <?php if (!get_sub_field('its_free')): ?>
                                        <label class="toggle">
                                            <input type="hidden" name="payment-2" value="monthly" checked="">
                                            <input type="checkbox" name="payment-2" value="annualy">
                                            <span class="active">Pay<br>Monthly</span>
                                            <span>
                                                <em>Pay<br>Annualy</em>
                                                <em class="plan-save">3 MONTHS FREE</em>
                                            </span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <!-- end .plan-payment -->
                                <div class="plan-price">
                                    <?php if (get_sub_field('its_free')): ?>
                                        <div>
                                            <strong>Free</strong>
                                            <span></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (get_sub_field('monthly_price')): ?>
                                        <div class="monthly">
                                            <strong><?= get_sub_field('monthly_price') ?>/mo</strong>
                                            <span>billed monthly</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (get_sub_field('yearly_price')): ?>
                                        <div class="annualy">
                                            <strong><?= get_sub_field('yearly_price') ?>/yr</strong>
                                            <span>billed annually</span>
                                        </div>
                                    <?php endif; ?>

                                </div>
                      
                                <?php
                                $category_id = get_sub_field('product_category', get_the_ID());
                                get_product_button($category_id, $subscribed_products, $subscription_period, $term_name);
                                ?>
                            </div>

                        </div>
                        <?php
                        $benefits_args = array(
                            'health_check' => 'Health Check',
                            'worksheet_mapping' => 'Worksheet Mapping',
                            'cell_dependency_analysis' => 'Cell Dependency Analysis',
                            'compare_ranges' => 'Compare Ranges',
                            'calculation_clarity' => 'Calculation Clarity',
                            'compare_vba' => 'Compare VBA',
                            'export_results' => 'Export Results',
                            'knowledge_base' => 'Knowledge Base',
                            'support' => 'Support',
                        );
                        ?>
                        <div class="pricing-table-body">
                            <div class="inner">

                                <?php if (have_rows('plan_benefits', 687)): ?>
                                    <?php
                                    while (have_rows('plan_benefits', 687)): the_row();
                                        $link = get_sub_field('link');
                                        foreach ($benefits_args as $key => $benefit):
                                            $benefit_value = get_sub_field($key);
//                                                var_dump($benefit_value);
//                                                if ($benefit_value === true):
//                                                    echo "here";
//                                                endif;
                                            ?>
                                            <div class="md-visible pricing-table-cell <?= !$benefit_value ? 'disabled' : '' ?> ">
                                                <span class="text cell-title"><?= $benefit ?></span>
                                                <span>
                                                    <?php
                                                    if ($benefit_value === true || $benefit_value === 'yes'):
                                                        echo "";
                                                    elseif ($benefit_value && $benefit_value !== true):
                                                        echo $benefit_value;
                                                    else:
                                                        echo "---";
                                                    endif;
                                                    ?>
                                                </span>
                                            </div>
                                            <?php
                                            if (have_rows('plan_benefits_details')):
                                                while (have_rows('plan_benefits_details')): the_row();
                                                    ?>
                                                    <?php
                                                    if (have_rows($key)):
                                                        while (have_rows($key)): the_row();
                                                            ?>
                                                            <div class="mobile-plan md-hidden i0">
                                                                <div class="text">
                                                                    <img src="<?= get_sub_field('icon') ?>" height="18">
                                                                    <span><?= $benefit ?></span>
                                                                </div>
                                                                <div class="desc"><?= get_sub_field('description') ?></div>
                                                            </div>
                                                        <?php endwhile; ?>
                                                    <?php endif; ?>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                            </div>
                            <!-- end .inner -->
                        </div>
                        <!-- end .pricing-table-body -->
                    </div>
                    <?php
                endwhile;
            endif;
            ?>
            <!-- end .pricing-table-col -->
        </div>
        <!-- end .pricing-table -->
    </div>
    <!-- end .container -->
    
</div>
<?php // endwhile; ?>
<?php ///wp_reset_postdata(); ?>
<?php // endif; ?>


 <div class="response-message"></div>

<script
    id="fsc-api"
    src="https://d1f8f9xcsvx3ha.cloudfront.net/sbl/0.8.3/fastspring-builder.min.js"
    type="text/javascript"
    data-data-callback="fastspringCallback"
    data-storefront="workscope.test.onfastspring.com/popup-excel-plugin">
</script>

<style>
      .response-message-inner {
                border: 1px solid red;
                padding: 10px;
                width: 100%;
                font-size: 18px;
            }
    .response-message-inner {border: 1px solid red;padding: 10px;width: 100%;font-size: 18px;}
    .popup {position: fixed;top: 0px;left: 0px;background: rgb(0 0 0 / 80%);min-width: 100%;min-height: 100%;display: none;z-index: 999;}
    .popup-content {width: 100%;max-width: 375px;margin: 0 auto;box-sizing: border-box;padding: 15px;margin-top: 100px;background: #fff;position: relative;}
    .close-button {width: 25px;height: 25px;position: absolute;top: -10px;right: -10px;border-radius: 20px;background: rgba(0,0,0,0.8);font-size: 20px;text-align: center;color: #fff;text-decoration:none;}
    .close-button:hover {background: rgba(0,0,0,1);}
    .formRow {margin: 0px; text-align: left;position: relative;margin-top: 10px}
    .click{background-color: transparent;border: 1px solid #ccc;font-size: 12px;outline: none;color: #000;width: 100%;transition: all .3s;border-radius: 2px;padding: 7px 10px}
    .formRow .lab {transition: all .3s;position: absolute;top: 8px;color: #ccc;font-size: 12px;letter-spacing: 1px;font-weight: 400;left: 10px;background: #fff;padding: 0 5px;cursor: text;}
    .formRow label {width: 100%;display: inline-block;}
    .formRow input[type="submit"]{height: 40px;margin-bottom: 9px;background-color: #8edfe0;border-radius: 1px;color: #FFFFFF;border: 0;background-image: none;width: 100%;margin-top: 10px}
    .formRow input[type="submit"]:hover{background-color: #45c9b1;}
    .popUp_top_wrap {text-align: center;}
    .popUp_top_wrap h2 {color: #000 !important;text-align: center !important;font-size: 16px !important;margin-top: 0 !important;}
    .popUp_top_wrap img{max-width: 130px}
    .disabled{pointer-events: none}
    .fastspring_btn{border-radius: unset}
</style>

<script type="text/javascript">

    function fastspringCallback(data) {
        console.log(data);
        if (data && data.hasOwnProperty('groups')) {
            const {groups} = data;
            var currency = data.currency;
            var locale = data.language + "-" + data.country;
            groups.forEach(group => {
                if (group.items && Array.isArray(group.items)) {
                    group.items.forEach(item => {
                        var priceValue = item.priceValue;
                        var productPrice = priceValue.toLocaleString(locale, {
                            style: "currency",
                            currency: currency,
                            minimumFractionDigits: 0
                        });
                        let element;
                        console.log(item.path);
                        if ((element = document.getElementById(item.path))) {
                            element.innerText = productPrice;
                        }
                    });
                }
            });
        }
    }
    jQuery(function ($) {
        $(document.body).on('click', '.plan-payment .toggle', function (e) {
            if ($(e.target).is('span, em')) {
                $(this).toggleClass('checked').find('span').toggleClass('active');
                $(this).closest('.pricing-table-head').find('.month, .year').toggle();
                $(this).closest('.pricing-table-head').find('.monthly, .annualy').toggle();

            }
        });
    });
</script>