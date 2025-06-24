<?php

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Http\Client;

class APIComponent extends Component
{

    const BASE_URL = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?year=2015&area=13';

    public function rEstateAPI(): string|bool
    {
        phpinfo();


        $c_i = curl_init();
        curl_setopt($c_i, CURLOPT_URL, self::BASE_URL);
        curl_setopt($c_i, CURLOPT_POST, true);
        // curl_setopt($c_i,CURLOPT_FIELDS, $param);
        curl_setopt($c_i, CURLOPT_HTTPHEADER, $head);
        $res = curl_exec($c_i);
        curl_close($c_i);
        return $res;
        $data = http_build_query(self::BASE_URL, "", "&");
        $header = array(
            "Content-Type: application/x-www-form-urlencoded",
            "Context-Length: " . strlen($data),
            "Ocp-Apim-Subscription-Key: " . env('API_KEY')
        );

        $context = array(
            "http" => array(
                "method" => "POST",
                "header" => implode("\r\n", $header),
                "content" => $data,
                'ignore_errors' => true
            )
        );
        file_get_contents(self::BASE_URL, false, stream_context_create($context));

    }
}
