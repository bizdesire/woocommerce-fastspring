<?php

if (!defined('ABSPATH')) {
    exit;
}
class EndpointCallbacks{
        public function create_woocommerce_order_callback() {
//    echo "<pre>";
//    var_dump($_GET);
//    echo "</pre>";
        return $_GET['orderID'];
    }

}
new EndpointCallbacks();