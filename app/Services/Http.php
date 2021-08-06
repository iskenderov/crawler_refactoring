<?php

namespace App\Services;

class Http
{

    /**
     * @var false|resource
     */
    private $client;

    public function __construct()
    {
        $this->init();
    }

    private function init()
    {
        $this->client = curl_init();

        if (!isset ($opts ['timeout']) || !is_int($opts ['timeout'])) {
            $opts ['timeout'] = 10000;
        }

        curl_setopt($this->client, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->client, CURLOPT_USERAGENT, 'TS-PHP/1.0.0');
        curl_setopt($this->client, CURLOPT_HEADER, false);
        curl_setopt($this->client, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->client, CURLOPT_CONNECTTIMEOUT, 50000);
        curl_setopt($this->client, CURLOPT_TIMEOUT, $opts ['timeout']);
    }

    public function call($endpoint, $params = array(), $method)
    {

        curl_setopt($this->client, CURLOPT_URL, $endpoint);

        if ($method == 'post') {
            curl_setopt($this->client, CURLOPT_POST, true);
            curl_setopt($this->client, CURLOPT_HTTPHEADER, []);

            if ($params != null) {
                $params = json_encode($params);
                curl_setopt($this->client, CURLOPT_POSTFIELDS, $params);
            }
        }

        if ($method == 'delete') {
            $this->client = curl_init();
            curl_setopt($this->client, CURLOPT_POST, false);
            curl_setopt($this->client, CURLOPT_HTTPGET, false);
            curl_setopt($this->client, CURLOPT_CUSTOMREQUEST, "DELETE");
            if (isset($params)) {
                $json = json_encode($params);
            } else {
                $json = '';
            }
            curl_setopt($this->client, CURLOPT_POSTFIELDS, $json);
        }

        if ($method == 'get') {
            curl_setopt($this->client, CURLOPT_POST, false);
            curl_setopt($this->client, CURLOPT_HTTPGET, true);
            curl_setopt($this->client, CURLOPT_HEADER, 1);
//            curl_setopt($this->client, CURLOPT_USERPWD, "$this->username:$this->password");
//            $this->buildAuthHttpHeader ( $params ['SessionToken'], $params ['UserId'] );
        }

        $response_body = curl_exec($this->client);
        $info = curl_getinfo($this->client);
        if ($method == 'get') {
            $rr = [];
            $header_size = curl_getinfo($this->client, CURLINFO_HEADER_SIZE);
            $header = substr($response_body, 0, $header_size);
            $rr["body"] = substr($response_body, $header_size);
            $rr["header"] = $header;
            $response_body = $rr;
        }
        if (floor($info ['http_code'] / 100) >= 4) {
            return @file_get_contents($endpoint);
        }

        return $response_body;
    }
}
