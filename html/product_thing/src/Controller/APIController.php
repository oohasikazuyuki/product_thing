<?php
namespace App\Controller;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use Composer\Util\Zip;
use function PHPUnit\Framework\containsOnly;
use function PHPUnit\Framework\returnArgument;

class APIController extends AppController
{


    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
    }

    public function index()
    {
        $this->redirect(['action' => 'selectAPI']);
    }


    public function rEstateAPI()
    {
        $base_url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?year=2016&quarter=2&city=14100&priceClassification=01';

        $query = ['year'=>'2015','quarter'=>'2','city'=>'13102','priceClassification'=>'01'];
        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: ' . env('API_KEY')
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
// https://qiita.com/api/v2/tags/PHP/items?page=1&per_page=5

// 結果はjson形式で返されるので
        pr($base_url.http_build_query($query));
    }

    public function selectAPI($prefectureCode = null,$cityID = null,$year = null)
    {
        //データベースから選択できるようにしたい
        if ($this->request->is('post')) {
            $prefectureCode = $this->request->getData('prefecture');
            $year = $this->request->getData('year');
            $quarter = $this->request->getData('quarter');
            $cityID = $this->request->getData('city'); // ユーザーが選択した都市ID
            if(is_array($cityID) && !empty($cityID)){
                $cityID = reset($cityID);
            }
            if(is_array($year) && !empty($year)){
                $year = reset($year);
            }
            // yearが配列の場合、最初の要素を取得

            // displayPriceメソッドにリダイレクト

            if (!empty($prefectureCode) && !empty($cityID) && !empty($year) && $this->request->is('post')) {
                return $this->redirect([
                    'controller' => 'API',
                    'action' => 'display_price',
                    '?'=>[
                    'area' => $prefectureCode,
                    'city' => $cityID,
                    'year' => $year,
                    ]
                ]);
            }
            $this->set('prefectures',$this->getPrefectures());
            $this->set('years',$this->getYear());
            $this->set('cityID',null);
        }

        $base_url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT002?';
        $baseurl2 = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?';
        $query  = [
            'area' => $prefectureCode,
            'year' => $year,
        ];

        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: ' . env('API_KEY')
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
            $base_url.http_build_query($query),false,$context);

        if(substr($response,0,2) === "\x1f\x8b"){
            $decode_response = json_decode(gzdecode($response),true);
        }else{
            $decode_response = json_decode($response,true);
        }

        $this->set('prefectures', $this->getPrefectures());
        $cityID = null;
        if(isset($decode_response['data']) && is_array($decode_response['data'])) {
            $cityID = array_combine(
                array_column($decode_response['data'], 'id'),
                array_column($decode_response['data'], 'name')
            );
            $this->set('cityID', $cityID);
        }else{
            $this->set('cityID', null);
            error_log('APIからのデータ取得に失敗しました。');
        }

        //年度をgetYearメソッドから取得
        $year = $this->getYear();
        $this->set('years', $year);

    }

    public function displayPrice($prefectureCode = null,$cityID = null,$year = null)
    {
        //前のメソッドからGETで値を取得
     $area  = $this->request->getQuery('area');

     $city = $this->request->getQuery('city');

     $year = $this->request->getQuery('year');
     //値がない場合はエラーを表示
     if($area == 00 || $city == 00 || $year == 00){
         $this->Flash->error('都道府県、市区町村、年度を選択してください。');
         return $this->redirect(['action'=>'selectAPI']);
     }
    $this->set(compact('prefectureCode', 'cityID', 'year'));

        $base_url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?';
        $query = [
            'area'=> $area,
            'city' => $city,
            'year' => $year
        ];

        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: ' . env('API_KEY')
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



        $response = file_get_contents($base_url . http_build_query($query), false, $context);
        $return = true;

        if ($response === false) {
            $return = false;
            $data= array();
            error_log('APIからのデータ取得に失敗しました。');
            // ここでエラーメッセージを設定または例外を投げる
            throw new \Exception("APIからのデータ取得に失敗しました。");
        }
        if($response === '{"message":"検索結果がありません。"}'){
            $return = false;
            $data  = array();
        }

        if($return) {
            $data = json_decode(gzdecode($response), true);
        }


        if (!is_array($data)) {
            $data = [];
        }

        // ページネーションの設定
        $page = $this->request->getQuery('page', 1);
        $limit = 30;
        $total = 0;
        $start = ($page - 1) * $limit;
        $paginatedData = [];
        if(isset($data['data'])){
            $paginatedData = array_slice($data['data'], $start, $limit);
            $total = count($data['data']);
        }


        $this->set('data', $paginatedData);

        $this->set('page', $page);
        $this->set('limit', $limit);
        $this->set('total', $total);
        $this->set('pages', ceil($total / $limit));

    }
    /** */
    public function getPrefectures()
    {

        return [
            '01' => '北海道',
            '02' => '青森県',
            '03' => '岩手県',
            '04' => '宮城県',
            '05' => '秋田県',
            '06' => '山形県',
            '07' => '福島県',
            '08' => '茨城県',
            '09' => '栃木県',
            '10' => '群馬県',
            '11' => '埼玉県',
            '12' => '千葉県',
            '13' => '東京都',
            '14' => '神奈川県',
            '15' => '新潟県',
            '16' => '富山県',
            '17' => '石川県',
            '18' => '福井県',
            '19' => '山梨県',
            '20' => '長野県',
            '21' => '岐阜県',
            '22' => '静岡県',
            '23' => '愛知県',
            '24' => '三重県',
            '25' => '滋賀県',
            '26' => '京都府',
            '27' => '大阪府',
            '28' => '兵庫県',
            '29' => '奈良県',
            '30' => '和歌山県',
            '31' => '鳥取県',
            '32' => '島根県',
            '33' => '岡山県',
            '34' => '広島県',
            '35' => '山口県',
            '36' => '徳島県',
            '37' => '香川県',
            '38' => '愛媛県',
            '39' => '高知県',
            '40' => '福岡県',
            '41' => '佐賀県',
            '42' => '長崎県',
            '43' => '熊本県',
            '44' => '大分県',
            '45' => '宮崎県',
            '46' => '鹿児島県',
            '47' => '沖縄県',
        ];
    }
        /**年度を取得するメソッド。現状2004年データはあまりない状態 */
    public function getYear()
    {
        return [
            '2005' => '2005年',
            '2006' => '2006年',
            '2007' => '2007年',
            '2008' => '2008年',
            '2009' => '2009年',
            '2010' => '2010年',
            '2011' => '2011年',
            '2012' => '2012年',
            '2013' => '2013年',
            '2014' => '2014年',
            '2015' => '2015年',
            '2016' => '2016年',
            '2017' => '2017年',
            '2018' => '2018年',
            '2019' => '2019年',
            '2020' => '2020年',
            '2021' => '2021年',
            '2022' => '2022年',
            '2023' => '2023年',
            '2024' => '2024年',
            '2025' => '2025年',
            '2026' => '2026年',
            '2027' => '2027年',
            '2028' => '2028年',
            '2029' => '2029年',
            '2030' => '2030年',

        ];
    }

}
