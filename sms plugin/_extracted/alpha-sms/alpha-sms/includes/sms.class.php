<?php

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Alpha_SMS_Class
{

    public $numbers;
    public $body;
    public $sender_id = '';
    private $api_key;
    private $api_url = 'https://api.sms.net.bd';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * @return mixed
     */
    public function Send()
    {
        $sender_id = trim((string) $this->sender_id);

        $postFields = [
            'api_key'   => $this->api_key,
            'to'        => $this->numbers,
            'msg'       => $this->body,
        ];

        if ('' !== $sender_id) {
            $postFields['sender_id'] = $sender_id;
        }

        $response = $this->sendRequest($this->api_url . '/sendsms', 'POST', $postFields);

        return json_decode($response);

    }

    /**
     * @param $url
     * @param string $method
     * @param array $postfields
     * @return bool|string
     */
    private function sendRequest($url, $method = 'GET', $postfields = [])
    {

        $args = [
            'method'    => $method,
            'timeout'   => 45,
            'sslverify' => false,
            'body'      => $postfields
        ];

        $request = wp_remote_post($url, $args);

        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }

        return wp_remote_retrieve_body($request);
    }

    /**
     * @return mixed
     */
    public function getBalance()
    {
        $response = $this->sendRequest($this->api_url . '/user/balance/?api_key=' . $this->api_key);

        return json_decode($response);
    }
}