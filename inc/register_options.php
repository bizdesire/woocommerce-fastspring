<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'register_wc_fastspring_menu_page');

function register_wc_fastspring_menu_page() {
    add_menu_page('Woocommerce Fastspring', 'Woocommerce Fastspring', 'manage_options', 'wc-fastspring', 'wc_fastspring_settings_page', 'dashicons-welcome-widgets-menus', 90);
    add_action('admin_init', 'register_wc_fastspring_settings_page_settings');
}

function register_wc_fastspring_settings_page_settings() {
    register_setting('wc_fastspring_settings', 'api_username');
    register_setting('wc_fastspring_settings', 'api_password');
    register_setting('wc_fastspring_settings', 'mailchimp_api_key');
    register_setting('wc_fastspring_settings', 'mailchimp_list_id');
    register_setting('wc_fastspring_settings', 'mandrill_already_trial');
    register_setting('wc_fastspring_settings', 'mandrill_welcome_email');
    register_setting('wc_fastspring_settings', 'mandrill_api_key');
    register_setting('wc_fastspring_settings', 'enable_mailchimp');
    register_setting('wc_fastspring_settings', 'restricted_descriptions');
    register_setting('wc_fastspring_settings', 'mandrill_subscription_downgraded');
    register_setting('wc_fastspring_settings', 'mandrill_paid_subscription_ended');
    register_setting('wc_fastspring_settings', 'mandrill_subscription_upgraded');
    register_setting('wc_fastspring_settings', 'mandrill_already_account');
    register_setting('wc_fastspring_settings', 'mandrill_subscription_canceled');
}

function wc_fastspring_settings_page() {
    ?>
    <div class="wrap">
        <h1>Woocommerce Fastspring</h1>
        <form method="post" action="options.php">
            <h3>Fastspring API Details</h3>


            <?php settings_fields('wc_fastspring_settings'); ?>
            <?php do_settings_sections('wc_fastspring_settings'); ?>
            <div class="tabs">
                <ul class="tabs-list">
                    <li class="active"><a href="#fastspring">Fastspring API Details</a></li>
                    <li><a href="#mailchimp">Mailchimp API Details</a></li>
                    <li><a href="#urlendpoints">Url endpoints</a></li>
                    <li><a href="#shortcodes">Shortcodes</a></li>
                    <li><a href="#restricted">Restricted</a></li>
                </ul>

                <div id="fastspring" class="tab active">
                    <h3>Fastspring API Details</h3>
                    <div class="col-wrap">                   
                        <div class="form-field form-required api-username-wrap">
                            <label for="api_username">API Username</label>
                            <input type="text" name="api_username" value="<?php echo esc_attr(get_option('api_username')); ?>"  size="40" aria-required="true" />
                        </div>
                        <div class="form-field form-required api-password-wrap">
                            <label for="api_password">API Password</label>
                            <input type="text" name="api_password" value="<?php echo esc_attr(get_option('api_password')); ?>" />
                        </div>
                    </div>
                    <?php submit_button(); ?>

                </div>
                <div id="mailchimp" class="tab">
                    <h3>Mailchimp API Details</h3>
                    <div class="col-wrap">
                        <div class="form-field form-required mailchimp-list-wrap">
                            <label for="enable_mailchimp">Enable Mailchimp</label>
                            <input type="checkbox" name="enable_mailchimp" <?= get_option('enable_mailchimp') == 'yes' ? 'checked' : '' ?> value="yes" size="40" aria-required="true" />
                        </div>
                        <div class="col-wrap">
                            <div class="form-field form-required mailchimp-list-wrap">
                                <label for="mailchimp_list_id">List ID</label>
                                <input type="text" name="mailchimp_list_id" value="<?php echo esc_attr(get_option('mailchimp_list_id')); ?>"  size="40" aria-required="true" />
                            </div>
                            <div class="form-field form-required mailchimp-api-wrap">
                                <label for="mailchimp_api_username">API Key</label>
                                <input type="text" name="mailchimp_api_key" value="<?php echo esc_attr(get_option('mailchimp_api_key')); ?>"  size="40" aria-required="true" />
                            </div>
                        </div>
                        <h3>Mandril API Details</h3>
                        <div class="col-wrap">
                            <div class="col-wrap">
                                <div class="form-field form-required mailchimp-list-wrap">
                                    <label for="mailchimp_list_id">Mandrill API key</label>
                                    <input type="text" name="mandrill_api_key" value="<?php echo esc_attr(get_option('mandrill_api_key')); ?>"  size="40" aria-required="true" />
                                </div>
                                <?php
                                if (get_option('mandrill_api_key') && get_option('mandrill_api_key') != ''):
                                    try {
                                        $mandrill = new Mandrill(get_option('mandrill_api_key'));
                                        $results = $mandrill->templates->getList();
                                        $welcome_email = esc_attr(get_option('mandrill_welcome_email'));
                                        $already_trial = esc_attr(get_option('mandrill_already_trial'));
                                        $paid_end = esc_attr(get_option('mandrill_paid_subscription_ended'));
                                        $subscription_downgraded = esc_attr(get_option('mandrill_subscription_downgraded'));
                                        $subscription_upgraded = esc_attr(get_option('mandrill_subscription_upgraded'));
                                        $already_account = esc_attr(get_option('mandrill_already_account'));
                                        $subscription_canceled = esc_attr(get_option('mandrill_subscription_canceled'));
                                        if (count($results)):
                                            ?>
                                            <div class="form-field form-required mailchimp-list-wrap">

                                                <label for="mandrill_already_trial">Already have an account</label>
                                                <select name="mandrill_already_account">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $already_account ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="form-field form-required mailchimp-list-wrap">

                                                <label for="mandrill_already_trial">Already had a trial template</label>
                                                <select name="mandrill_already_trial">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $already_trial ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="form-field form-required mailchimp-list-wrap">

                                                <label for="mandrill_already_trial">Paid Workscope subscription has ended</label>
                                                <select name="mandrill_paid_subscription_ended">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $paid_end ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="form-field form-required mailchimp-list-wrap">

                                                <label for="mandrill_already_trial">Workscope subscription downgraded</label>
                                                <select name="mandrill_subscription_downgraded">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $subscription_downgraded ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="form-field form-required mailchimp-list-wrap">
                                                <label for="mandrill_subscription_canceled">Workscope subscription Canceled</label>
                                                <select name="mandrill_subscription_canceled">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $subscription_canceled ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="form-field form-required mailchimp-list-wrap">

                                                <label for="mandrill_already_trial">Workscope subscription upgraded!</label>
                                                <select name="mandrill_subscription_upgraded">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $subscription_upgraded ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>

                                            <div class="form-field form-required mailchimp-list-wrap">
                                                <label for="mandrill_welcome_email">Welcome email template</label>
                                                <select name="mandrill_welcome_email">
                                                    <?php
                                                    foreach ($results as $key => $result):
                                                        ?>
                                                        <option value="<?= $result['name'] ?>" <?= @$result['name'] == $welcome_email ? 'selected' : '' ?>><?= $result['name'] ?></option>
                                                        <?php
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>

                                            <?php
                                        endif;
                                    } catch (Exception $ex) {
                                        
                                    }
                                endif;
                                ?>

                            </div>
                        </div>
                        <?php submit_button(); ?>
                    </div>
                </div>
                <div id="urlendpoints" class="tab">
                    <div class="col-wrap">
                        <h2>All endpoints</h2>
                        <div class="col-wrap">
                            <table class="wp-list-table widefat fixed striped table-view-list tags ui-sortable">
                                <thead>
                                    <tr>
                                        <th scope="col" class="manage-column">Type</th>
                                        <th scope="col" class="manage-column">Url</th>
                                    </tr>
                                </thead>
                                <tbody id="the-list" data-wp-lists="list:tag">
                                    <tr>
                                        <td class="input-label">order.completed</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/create_order</td>
                                    </tr>
                                    <tr>
                                        <td class="input-label">order.canceled</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/canceled_order</td>
                                    </tr>
                                    <tr>
                                        <td class="input-label">subscription.activated</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/subscription_activated</td>
                                    </tr>
                                    <tr>
                                        <td class="input-label">subscription.deactivated</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/subscription_deactivated</td>
                                    </tr>
                                    <tr>
                                        <td class="input-label"> subscription.charge.completed</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/subscription_charged</td>
                                    </tr>
                                    <tr>
                                        <td class="input-label">subscription.charge.failed</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/subscription_failed</td>
                                    </tr>
                                    <tr>
                                        <td class="input-label">return.created</td>
                                        <td><?= home_url() ?>/wp-json/woocommerce-fastspring/v2/order_refund</td>
                                    </tr>

                                </tbody>
                            </table>
                            <span class="input-label">

                            </span>
                        </div>

                    </div>
                </div>
                <div id="shortcodes" class="tab">
                    <h3>Available shortcodes</h3>
                    <div class="col-wrap">
                        <div class="col-wrap">
                            <table class="wp-list-table widefat fixed striped table-view-list tags ui-sortable">
                                <thead>
                                    <tr>
                                        <th scope="col" class="manage-column">Type</th>
                                        <th scope="col" class="manage-column">Url</th>
                                    </tr>
                                </thead>
                                <tbody id="the-list" data-wp-lists="list:tag">
                                    <tr>
                                        <td class="input-label">Login</td>
                                        <td>[custom_login_form]</td>
                                    </tr>


                                </tbody>
                            </table>
                            <span class="input-label">

                            </span>
                        </div>
                    </div>
                </div>
                <div id="restricted" class="tab">
                    <div class="col-wrap">
                        <h2>Restricted page description</h2>
                        <div class="col-wrap">
                            <div class="col-wrap">                   
                                <div class="form-field form-required restricted_page_redirect-wrap">
                                    <label for="restricted_descriptions">Restricted Descriptions</label>
                                    <textarea  name="restricted_descriptions" rows="20"><?php echo esc_attr(get_option('restricted_descriptions')); ?></textarea>
                                </div>

                            </div>
                            <?php submit_button(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <style>
        .form-wrap .form-field {
            margin: 1em 0;
            padding: 0;
        }
        .tabs {
            width: 75%;
            height: auto;
            margin: 45px 0;
        }

        /* tab list item */
        .tabs .tabs-list{
            list-style: none;
            margin: 0px;
            padding: 0px;
            display: flex;
        }
        .tabs .tabs-list li{
            float:left;
            margin:0px;
            margin-right:2px;
            padding:10px 5px;
            text-align: center;
            background-color:cornflowerblue;
        }
        .tabs .tabs-list li:hover{
            cursor:pointer;
        }
        .tabs .tabs-list li a{
            text-decoration: none;
            color: white;
            font-size: 16px;
            font-weight: 500;
            margin: 20px;
            clear: both;
        }

        /* Tab content section */
        .tabs .tab{
            display:none;
            width:96%;
            min-height:250px;
            height:auto;
            padding:20px 15px;
            background-color:lavender;
            color:darkslategray;
            clear:both;
        }
        .tabs .tab h3{
            border-bottom:3px solid cornflowerblue;
            letter-spacing:1px;
            font-weight:normal;
            padding:5px;
        }
        .tabs .tab p{
            line-height:20px;
            letter-spacing: 1px;
        }

        /* When active state */
        .active{
            display:block !important;
        }
        .tabs .tabs-list li.active{
            background-color:lavender !important;
            color:black !important;
        }
        .active a{
            color:black !important;
        }

        /* media query */
        @media screen and (max-width:360px){
            .tabs{
                margin:0;
                width:96%;
            }
            .tabs .tabs-list li{
                width:80px;
            }
        }
    </style>
    <script>
        jQuery(function ($) {
            $(".tabs-list li a").click(function (e) {
                e.preventDefault();
            });
            $(".tabs-list li").click(function () {
                var tabid = $(this).find("a").attr("href");
                $(".tabs-list li,.tabs div.tab").removeClass("active");
                $(".tab").hide();
                $(tabid).show();
                $(this).addClass("active");
            });
        });
    </script>
    <?php
}
