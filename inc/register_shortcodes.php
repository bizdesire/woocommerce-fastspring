<?php

if (!defined('ABSPATH')) {
    exit;
}

class RegisterShortcodes {

    public function __construct() {
        add_shortcode('custom-login-form', array($this, 'fn_custom_login_form_callback'));
       
    }


    function fn_custom_login_form_callback($atts) {
        ob_clean();
         if ( !is_user_logged_in() ) { 
        wc_get_template('global/form-login.php');
         } else {
             
         }
         
        return ob_get_clean();
    }

}

new RegisterShortcodes;
