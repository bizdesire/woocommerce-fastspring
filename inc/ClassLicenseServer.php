<?php

if (!defined('ABSPATH')) {
    exit;
}

class LicenseServerAPI {

    public $challenge_code;
    static $base_url = 'https://ls-admin-test.workscope.com/LicenseServer';

    public function __construct() {
        //self::generate_challenge_code();
    }

    public function delete_license_user($email_id) {
        $challenge_code = LicenseServerAPI::generate_challenge_code();
        $url = self::$base_url . '/EC/UserLicense/' . urlencode($email_id);
        write_custom_logs('Request ' . $url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", 'Authorization:' . $challenge_code]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (curl_errno($ch)) {
            write_custom_logs('Curl error: ' . curl_error($ch));
        }
        write_custom_logs('Response ' . @$info['http_code']);
        curl_close($ch);

//        die();
    }

    public function check_license_user($email_id) {
        $challenge_code = LicenseServerAPI::generate_challenge_code();
        $url = self::$base_url . '/EC/HasLicense?email=' . urlencode($email_id);
        write_custom_logs('Request ' . $url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", 'Authorization:' . $challenge_code]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (curl_errno($ch)) {
            write_custom_logs('Curl error: ' . curl_error($ch));
        }
        write_custom_logs('Response ' . @$info['http_code']);
        curl_close($ch);
        return $response;
    }

    public function add_license_user($subscription) {
        $challenge_code = LicenseServerAPI::generate_challenge_code();

        $user_email = $subscription->billing_email;
        $payload = LicenseServerAPI::generate_payload($subscription);
        $subscription_id = $subscription->get_id();
        $user_id = $subscription->get_user_id();
        // LicenseServerAPI::delete_license_user($user_email);
//        echo "<pre>";
//        var_dump($payload);
//        echo "</pre>";

        $url = self::$base_url . '/EC/UserLicense';
        write_custom_logs('TransactionId:ecomm_' . $subscription_id . ' Request Url ' . $url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", 'Authorization:' . $challenge_code, 'TransactionId:ecomm_' . $subscription_id]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (curl_errno($ch)) {
            write_custom_logs('Curl error: ' . curl_error($ch));
        }
        write_custom_logs('Response ' . @$info['http_code']);

        if ($user_id)
            add_user_subscribed_product($user_id, $payload['product']);
        if ($info['http_code'] == 200) {
            $license_key = is_string($response) ? json_decode($response, true) : $response;
            $subscription->add_meta_data('_licenseKey', $license_key);
            $subscription->add_order_note('Server License : ' . $license_key);
        }
        curl_close($ch);
    }

    public function update_license_user($subscription,$oldProduct) {
        //  echo "<br>";
        $user_email = $subscription->billing_email;
        $subscription_id = $subscription->get_id();
        $user_id = $subscription->get_user_id();

        //LicenseServerAPI::delete_license_user($user_email);1  
        $payload = LicenseServerAPI::generate_payload($subscription);
        if ($user_id) {
               if ($oldProduct != 'false') {
                $payload['oldProduct'] = trim($oldProduct, '"');
            }
        }
        MandrillAPP::generate_email_template($user_id, $payload);
        $url = self::$base_url . '/EC/UserLicense/' . $user_email;
        $challenge_code = LicenseServerAPI::generate_challenge_code();
        write_custom_logs('TransactionId:ecomm_' . $subscription_id . ' Request Url ' . $url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", 'Authorization:' . $challenge_code, 'TransactionId:ecomm_' . $subscription_id]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        write_custom_logs('Response ' . @$info['http_code']);
        if (curl_errno($ch)) {
            write_custom_logs('Curl error: ' . curl_error($ch));
        }

        if ($user_id)
            add_user_subscribed_product($user_id, $payload['product']);
//        if ($info['http_code'] == 200) {
//            $license_key = is_string($response) ? json_decode($response, true) : $response;
//            $subscription->add_meta_data('_licenseKey', $license_key);
//            $subscription->add_order_note('Server License : ' . $license_key);
//        }

        curl_close($ch);
    }

    public function generate_challenge_code() {
        $ec_string = 0;
        $url = self::$base_url . '/Authorization/RequestChallenge';
        write_custom_logs('Request ' . $url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["application/json-patch+json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        write_custom_logs('Response ' . @$info['http_code']);

        if (curl_errno($ch)) {
            write_custom_logs('Curl error: ' . curl_error($ch));
        }
        if ($info['http_code'] == 200) {
            $challenge = is_string($response) ? json_decode($response, true) : $response;
            $IV = "gaHYYuY4YyFBJxKDilrLHg==";
            $Key = "d5PuLesnpbPpggCe2p/uHF6b95vIHEAr8+lVvL89wCg=";
            $plaintext = base64_decode($challenge);
            $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
            $iv = base64_decode($IV);
            $key = base64_decode($Key);
            $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
            $ec_string = base64_encode($ciphertext_raw);
            return $ec_string;
            curl_close($ch);
        }
        curl_close($ch);
    }

    public function generate_payload($subscription) {
        if (!$subscription)
            return;
        $payload = array();
        if ($subscription->has_status(array('active'))):
            $first_name = $subscription->billing_first_name;
            $last_name = $subscription->billing_last_name;
            $country = $subscription->billing_country;
            $next_payment = $subscription->schedule_next_payment;
            $email = $subscription->billing_email;
            $date = strtotime(date('Y-m-d H:i:s', strtotime($next_payment)));
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
            $payload = array(
                'email' => $email,
                'firstName' => $first_name,
                'lastName' => $last_name,
                'country' => $country,
                'product' => 'XL Agent ' . $term_name,
                'endDate' => gmdate(DATE_ATOM, $date),
            );

        endif;

        return $payload;
    }

}

new LicenseServerAPI();
