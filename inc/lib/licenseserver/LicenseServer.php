<?php

//namespace LicenseServer;

class LicenseServer {

    static $host = 'https://wkscp-lsa-uks-test.azurewebsites.net/LicenseServer';
    private $apiUseremail;
    private $apiPassword;
    private static $instance = null;

//     function __construct(string $apiUseremail, string $apiPassword) {
//        $this->apiUseremail = $apiUseremail;
//        $this->apiPassword = $apiPassword;
//    }

    public static function initialize(string $apiUseremail, string $apiPassword) {
        static::$instance = new static($apiUseremail, $apiPassword);
    }

    public function request(string $method, string $endpoint, $payload = null) {
         $dir = __dir__ . '/openssl/privatekey.pem';
        $method = self::standardizeMethod($method);
        $url = self::buildUrl($method, $endpoint, $payload);
        $payload = self::standardizePayload($payload);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSLKEY, $dir);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["application/json-patch+json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } else if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($info['http_code'] == 200) {
            return is_string($response) ? json_decode($response, true) : $response;
        }

        throw new \Exception(sprintf('Error %s reported by LicenseServer\'s API for your %s request to the "%s" endpoint. Response: %s', $info['http_code'], $method, $endpoint, json_encode($response)));
    }

    public function get(string $endpoint, $payload = null) {
        return self::request('GET', $endpoint, $payload);
    }

    public function post(string $endpoint, $payload = null) {
        self::request('POST', $endpoint, $payload);
    }

    public function put(string $endpoint, $payload = null) {
        self::request('PUT', $endpoint, $payload);
    }

    public function delete(string $endpoint, $payload = null) {
        self::request('DELETE', $endpoint, $payload);
    }

    public function standardizeMethod(string $method) {
        $uppercaseMethod = strtoupper($method);

        if (!in_array($uppercaseMethod, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new \Exception(sprintf("Invalid method \"%s\" provided for request.", $method));
        }

        return $uppercaseMethod;
    }

    public function standardizePayload($payload) {
        if (!$payload) {
            return null;
        }

        if (is_array($payload) || is_object($payload)) {
            $payload = json_encode($payload);
        }

        try {
            json_decode($payload);
        } catch (\Exception $e) {
            throw new \Exception("Invalid body provided for request: an array, object or JSON string is expected.");
        }

        return $payload;
    }

    public function buildUrl(string $method, string $endpoint, $payload) {
        $url = self::$host . '/' . trim($endpoint, '/');

        if (in_array($method, ['GET', 'DELETE']) && is_array($payload)) {
            // Associative array = filters
            if (array_keys($payload) !== range(0, count($payload) - 1)) {
                $url .= '?' . http_build_query($payload);
            } else {
                // Regular array = list of ids
                $url .= '/' . implode(',', $payload);
            }
        }

        return $url;
    }

}

return new LicenseServer;
