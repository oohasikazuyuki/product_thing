<?php
namespace App\Controller;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use function PHPUnit\Framework\returnArgument;


class APIController extends AppController
{
    public $paginate = [
        'limit' => 10000,
        'order' => [
            'id' => 'asc'
        ]
    ];
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        $this->loadComponent('Paginator');
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


    public function selectAPI($prefectureCode = null,$year = null)
    {

        if ($this->request->is('post')) {
            $prefectureCode = $this->request->getData('prefecture');
            $year = $this->request->getData('year');
            $quarter = $this->request->getData('quarter');
            $cityID = $this->request->getData('city'); // ユーザーが選択した都市ID
            if(is_array($cityID)){
                foreach ($cityID as $city){
                    $this->displayPrice($prefectureCode,$city,$year,$quarter);
                }
            }

            // displayPriceメソッドにリダイレクト
      //      return $this->redirect(['action' => 'displayPrice', $prefectureCode, $cityID, $year, $quarter]);
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
            $base_url.http_build_query($query),false,$context);

        if(substr($response,0,2) === "\x1f\x8b"){
            $decode_response = json_decode(gzdecode($response),true);
        }else{
            $decode_response = json_decode($response,true);
        }

        $this->set('prefectures', $this->getPrefectures());
        $cities = null;
        if(isset($decode_response['data']) && is_array($decode_response['data'])) {
         $cities = array_combine(
                array_column($decode_response['data'], 'id'),
                array_column($decode_response['data'], 'name')
            );
         $this->set('cities', $cities);
        }else{
            $this->set('cities', null);
            error_log('APIからのデータ取得に失敗しました。');
        }

        $year = $this->getYear('years');
        $this->set('years', $year);

        $quauters = $this->getQuarters('quaters');
        $this->set('quarters', $quauters);





        $this->redirect(['action'=>'displayPrice','prefectureCode'=>$prefectureCode,'cityID'=>$cities,'year'=>$year,'quarter'=>$quauters]);
      // echo $baseurl2.$prefectureCode.'&year='.$year.'&quarter='.$quauters.'&city='.$cities;

     //   $this->redirect(['action'=>'selectAPI','prefectureCode'=>$prefectureCode,'year'=>$year]);



       // echo var_export($decode_response['data'][1]['name'],true);


        // file_put_contents('/var/www/html/product_thing/logs/aaa.zip', $response, FILE_APPEND);
     //   pr(json_decode(gzdecode($response),true));


   //     echo $decode_response;
    }

    public function displayPrice($prefectureCode,$cityID,$year,$quarter)
    {

        //APIを呼び出して不動産取引価格情報を取得する
        $base_url = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001?';
        $query =[
            'area'=>$prefectureCode,
            'city'=>$cityID,
            'year'=>$year,
            'quarter'=>$quarter
        ];

        $header = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: 2f8763d2bb7e41feb2485d92d1e426c4'
        );
        echo $base_url.http_build_query($query);

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


        echo $response;

    }

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

    public function getYear()
    {
        return [
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

    public function getQuarters()
    {
        return [
            '1' => '1月〜３月',
            '2' => '4月〜6月',
            '3' => '7月〜9月',
            '4' => '10月〜12月',
        ];
    }
}
