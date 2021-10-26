<?php

class FastspringMetabox {

    public function __construct() {
        add_action('add_meta_boxes_product', array($this, 'fastspring_metabox_meta_box'));
        add_action('save_post', array($this, 'save_fastspring_product_meta_box_data'));
    }

    public function fastspring_metabox_meta_box() {

        add_meta_box('fastspring_product', __('Fastspring Product ID', 'wc-fastspring'), array($this, 'fastspring_product_meta_box_callback'));
        add_meta_box('fastspring_product_price', __('Fastspring Product Price', 'wc-fastspring'), array($this, 'fastspring_product_price_meta_box_callback'));
        add_meta_box('assign_product_user', __('Add role to user', 'wc-fastspring'), array($this, 'fastspring_product_add_role_user'));
        add_meta_box('assign_product_priority', __('Product Priority', 'wc-fastspring'), array($this, 'fastspring_product_add_priority'));
    }

    public function fastspring_product_price_meta_box_callback($post) {
        $value = get_post_meta($post->ID, '_fastspring_product_price', true);
        ?>
        <textarea required class="mettabox-input" rows="5" autocomplete="off" cols="120" name="fastspring_product_price" placeholder="Enter Fastspring Product Price"><?= $value ? $value : '' ?></textarea>
        <?php
    }

    public function fastspring_product_meta_box_callback($post) {
        $value = get_post_meta($post->ID, '_fastspring_productid', true);
        ?>
        <input type="text" class="mettabox-input" name="fastspring_productid" placeholder="Enter Fastspring Product ID" value="<?= $value ? $value : '' ?>">
        <?php
    }

    public function fastspring_product_add_priority($post) {
        $product_priority = get_post_meta($post->ID, '_assign_product_priority', true);
        $args = array('post_type' => 'product', 'post_status' => 'publish',
            'posts_per_page' => -1);
        $products = new WP_Query($args);
        wp_reset_query();
        $total_count = $products->found_posts;
        ?>
        <div class="components-base-control__field css-11vcxb9-StyledField e1puf3u1">
            <label class="components-checkbox-control__label" for="inspector-checkbox-control-0">Product priority</label>
            <select name="assign_product_priority" >
                <option value="" >Select product priority</option>
                <?php
                for ($i = 1; $i <= $total_count; $i++):
                    ?>
                    <option value="<?= $i ?>"  <?= ($product_priority == $i) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php
                endfor;
                ?>
            </select>
        </div>
        <?php
    }

    public function fastspring_product_add_role_user($post) {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $restricted_roles = array();
        $editable_roles = apply_filters('editable_roles', $all_roles);
        wp_nonce_field('restricted_roles_nonce', 'restricted_roles_nonce');
        $restricted_roles = get_post_meta($post->ID, '_fastspring_product_role', true);
        ?>
        <div class="components-base-control__field css-11vcxb9-StyledField e1puf3u1">
            <label class="components-checkbox-control__label" for="inspector-checkbox-control-0">Product user role</label>
            <select name="fastspring_product_role" >
                <?php
                foreach ($editable_roles as $key => $role):
                    ?>
                    <option value="<?= $key ?>"  <?= $restricted_roles && $key== $restricted_roles ? 'selected' : '' ?>><?= $role['name'] ?></option>
                    <?php
                endforeach;
                ?>
            </select>
        </div>
        <?php
    }

    function save_fastspring_product_meta_box_data($post_id) {
        if (isset($_POST['fastspring_product_price'])) {
            $my_data = $_POST['fastspring_product_price'];
            update_post_meta($post_id, '_fastspring_product_price', $my_data);
        }
        if (isset($_POST['fastspring_productid'])) {
            $my_data = sanitize_text_field($_POST['fastspring_productid']);
            update_post_meta($post_id, '_fastspring_productid', $my_data);
        }
        if (isset($_POST['fastspring_product_role'])) {
            $fastspring_product_role = $_POST['fastspring_product_role'];
            update_post_meta($post_id, '_fastspring_product_role', $fastspring_product_role);
        }
        if (isset($_POST['assign_product_priority'])) {
            $priority = $_POST['assign_product_priority'];
            update_post_meta($post_id, '_assign_product_priority', $priority);
        }
    }

}

new FastspringMetabox();
