<?php

if (!defined('ABSPATH')) {
    exit;
}

class MailchimpHooks {

    private $apiKey;
    private $listId;

    public function __construct() {
        $this->listId = esc_attr(get_option('mailchimp_list_id'));
        $this->apiKey = esc_attr(get_option('mailchimp_api_key'));
    }

    public function Senddatamailchimp($data) {
        $memberId = md5(strtolower($data['email']));
        $dataCenter = substr($apiKey, strpos($apiKey, '-') + 1);
        $url = 'https://' . $dataCenter . '.api.mailchimp.com/3.0/lists/' . $this->listId . '/members/' . $memberId;

        $json = json_encode([
            'email_address' => $data['email'],
            'status' => $data['status'],
            'merge_fields' => [
                'FNAME' => @$data['firstname'],
                'LNAME' => @$data['lastname'],
//                'PHONE' => @$data['phone'],
//                'ADDRESS' => @$data['display'],
            ]
        ]);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
        return $httpCode;
    }

}
