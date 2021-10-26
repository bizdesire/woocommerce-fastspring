<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ClassPageRestrictions {

    public function __construct() {
        $this->register_custom_roles();
        add_action('add_meta_boxes', array($this, 'register_role_meta_box'));
        add_action('save_post', array($this, 'save_restricted_roles_meta_box_data'));
        add_filter('template_include', array($this, 'register_restricted_template'));
    }

    public function register_custom_roles() {
        add_role(
                'basic_user', __('Basic User', 'wc-fastspring'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
                )
        );
        add_role(
                'pro_user', __('Pro User', 'wc-fastspring'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
                )
        );
        add_role(
                'advance_user', __('Advance User', 'wc-fastspring'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
                )
        );
    }

    public function register_role_meta_box() {
        $post_types = get_post_types();

        foreach ($post_types as $post_type) {
            add_meta_box('content_restrictions', __('Restrict page for user roles', 'wc-fastspring'), array($this, 'assign_metabox_to_post_callback'), $post_type);
        }
    }

    public function assign_metabox_to_post_callback($post) {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $restricted_roles = array();
        $editable_roles = apply_filters('editable_roles', $all_roles);
        wp_nonce_field('restricted_roles_nonce', 'restricted_roles_nonce');
        $restricted_roles = get_post_meta($post->ID, '_restricted_roles', true);
        foreach ($editable_roles as $key => $role):
            ?>
            <div class="components-base-control__field css-11vcxb9-StyledField e1puf3u1">
                <span class="components-checkbox-control__input-container">
                    <input  class="components-checkbox-control__input" name="restricted_roles[]" <?= is_array($restricted_roles) && in_array($key, $restricted_roles) ? 'checked' : '' ?> type="checkbox" value="<?= $key ?>">
                </span>
                <label class="components-checkbox-control__label" for="inspector-checkbox-control-0"><?= $role['name'] ?></label>
            </div>
            <?php
            ?>
            <?php
        endforeach;
    }

    function save_restricted_roles_meta_box_data($post_id) {

        if (!isset($_POST['restricted_roles_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['restricted_roles_nonce'], 'restricted_roles_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (isset($_POST['post_type']) && 'page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }
       
        $restricted_roles = $_POST['restricted_roles'];
        update_post_meta($post_id, '_restricted_roles', $restricted_roles);
    }

  function register_restricted_template($original_template) {
        global $post;
        $containsSearch = array();
        if (current_user_can('administrator'))
            return $original_template;
        $restricted_roles = get_post_meta($post->ID, '_restricted_roles', true);
//        var_dump($restricted_roles);
        if (is_array($restricted_roles)):
            if (is_user_logged_in()):
                $user = wp_get_current_user();
                $containsSearch = array_intersect($restricted_roles, $user->roles);
                if (!count($containsSearch)) :
                    $original_template = CUSTOM_TEMPLATE_PATH . 'pages/restrict_content.php';
                endif;
            else:
                $original_template = CUSTOM_TEMPLATE_PATH . 'pages/restrict_content.php';
            endif;
        endif;
        return $original_template;
    }

}

new ClassPageRestrictions();
