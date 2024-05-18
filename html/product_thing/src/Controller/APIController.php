<?php
namespace App\Controller;
use Cake\Core\Configure;
use Cake\Http\Client;

class APIController extends AppController
{

    public function index(): void
    {
        $this->viewBuilder()->setLayout('API');
    }


  public function rEstateAPI(): void
{
    $Baseurl ="https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?";
    $API_KEY = getenv('API_KEY');
    error_log($API_KEY);
    $param = [
        'year' => 2021,
        'priceClassification' => 01,
         'area' => 13101,
    ];
    $url = $Baseurl . http_build_query($param);
    $http  = new Client();
   // echo $url;
    $response = $http->get($url, [], [
        'headers' => ['Ocp-Apim-Subscription-Key' => $API_KEY]
    ]);



   // echo $url;
    if($response->isOk()){
        $body = $response->getStringBody();
        $data = json_decode($body, true);
        $this->set('data', $data);
    } else {
        $this->Flash->error('APIの取得に失敗しました');
        $this->Flash->error($response->getStringBody());
        if ($response->getStatusCode() == 401) {
            $this->Flash->error('APIキーが無効または設定されていません。');
        } elseif ($response->getStatusCode() == 400) {
            $this->Flash->error('APIリクエストのパラメータが正しく設定されていません。');
        } elseif ($response->getStatusCode() == 404) {
            $this->Flash->error('指定したURLに該当するAPIが存在しません。');
        }
    }
}
}
