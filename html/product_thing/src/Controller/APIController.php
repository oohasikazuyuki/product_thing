<?php
namespace App\Controller;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;


class APIController extends AppController
{


    /*
        public function initialize(): void
        {
            parent::initialize();
            $this->loadComponent('RequestHandler');
        }

        public function index(): void
        {
            $this->viewBuilder()->setLayout('API');
        }


  */

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
    }

    public function index(): void
    {
        $this->viewBuilder()->setLayout('API');
    }


    public function rEstateAPI()
    {
        $base_url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?year=2015&quarter=2&city=13102&priceClassification=01';

        $query = ['year'=>'2015','quarter'=>'2','city'=>'13102','priceClassification'=>'01'];
        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: 2f8763d2bb7e41feb2485d92d1e426c4'
        );
        $content = array(
            'http' => array(
                'method' => 'GET',
                'header' => implode("\r\n", $header),
                'content' => "",
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($content);

        $response = file_get_contents(
            $base_url,false,$context);
       // file_put_contents('/var/www/html/product_thing/logs/aaa.zip', $response, FILE_APPEND);
        pr(json_decode(gzdecode($response),true));


// https://qiita.com/api/v2/tags/PHP/items?page=1&per_page=5

// 結果はjson形式で返されるので
      //  $result = json_decode($response,true);
        pr($base_url.http_build_query($query));
    }
    public function phpinfo()
    {
        echo  phpinfo();

    }


    public function selectAPI()
    {




        $base_url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT002?area=13';




        $query = ['year'=>'2015','quarter'=>'2','city'=>'13102','priceClassification'=>'01'];
        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: 2f8763d2bb7e41feb2485d92d1e426c4'
        );
        $content = array(
            'http' => array(
                'method' => 'GET',
                'header' => implode("\r\n", $header),
                'content' => "",
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($content);

        $response = file_get_contents(
            $base_url,false,$context);

        $decode_response = json_decode(gzdecode($response),true);

        for($i = 0; $i < count($decode_response['data']); $i++){
            echo var_export($decode_response['data'][$i]['name'],true);
        }


       // echo var_export($decode_response['data'][1]['name'],true);


        // file_put_contents('/var/www/html/product_thing/logs/aaa.zip', $response, FILE_APPEND);
     //   pr(json_decode(gzdecode($response),true));


   //     echo $decode_response;

    }

    public function GetPrefecturesAndCities($area = null)
    {
        $url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT002?';
        $params = [
            'area' => $area
        ];
        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: 2f8763d2bb7e41feb2485d92d1e426c4'
        );
        $content = array(
            'http' => array(
                'method' => 'GET',
                'header' => implode("\r\n", $header),
                'content' => "",
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($content);
        $response  = file_get_contents($url,false,$context);
        echo $response[1];

    }
}
