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

        $decodeResponse = function ($response) {
            if (!is_string($response) || $response === '') {
                return null;
            }

            $decodedBody = $response;
            if (substr($response, 0, 2) === "\x1f\x8b") {
                $inflated = gzdecode($response);
                if ($inflated !== false) {
                    $decodedBody = $inflated;
                }
            }

            $json = json_decode($decodedBody, true);
            return is_array($json) ? $json : null;
        };

        $response = file_get_contents($base_url . http_build_query($query), false, $context);
        if ($response === false) {
            error_log('APIからのデータ取得に失敗しました。');
            throw new \Exception("APIからのデータ取得に失敗しました。");
        }

        $data = $decodeResponse($response) ?? [];
        if (isset($data['message']) && $data['message'] === '検索結果がありません。') {
            $data = [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $pointBaseUrl = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XPT001?';
            $pointResponse = file_get_contents($pointBaseUrl . http_build_query($query), false, $context);
            $pointData = $decodeResponse($pointResponse);

            if (is_array($pointData) && isset($pointData['data']) && is_array($pointData['data'])) {
                $pointRows = array_values($pointData['data']);
                $extractCoordinateValue = function (array $row, array $keys) {
                    foreach ($keys as $key) {
                        if (isset($row[$key]) && $row[$key] !== '') {
                            return $row[$key];
                        }
                    }

                    return null;
                };

                $longitudeKeys = ['Longitude', 'longitude', 'lng', 'Lon', 'LON', 'x'];
                $latitudeKeys = ['Latitude', 'latitude', 'lat', 'Lat', 'LAT', 'y'];

                foreach ($data['data'] as $index => &$record) {
                    if (!is_array($record) || !isset($pointRows[$index]) || !is_array($pointRows[$index])) {
                        continue;
                    }

                    $pointRecord = $pointRows[$index];
                    $longitude = $extractCoordinateValue($pointRecord, $longitudeKeys);
                    $latitude = $extractCoordinateValue($pointRecord, $latitudeKeys);

                    if ($longitude !== null && $latitude !== null) {
                        $record['Longitude'] = $longitude;
                        $record['Latitude'] = $latitude;
                    }
                }
                unset($record);
            }
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
        $this->set('googleMapsApiKey', env('GOOGLE_MAPS_API_KEY') ?: null);

    }

    public function apiExplorer()
    {
        $apiCatalog = $this->getLibraryApiCatalog();
        $apiOptions = [];
        foreach ($apiCatalog as $item) {
            $apiOptions[$item['id']] = $item['id'] . ' - ' . $item['name'];
        }

        $selectedApi = 'XIT001';
        $defaultQueries = [
            'XIT001' => 'area=13&city=13101&year=2024',
            'XIT002' => 'area=13&year=2024',
            'XCT001' => 'area=13&city=13101&year=2024',
            'XPT001' => 'area=13&city=13101&year=2024',
            'XPT002' => 'year=2024',
            'XGT001' => 'lat=35.6812&lon=139.7671&radius=1000',
            'XST001' => 'lat=35.6812&lon=139.7671&radius=1000',
        ];
        $queryString = $defaultQueries[$selectedApi] ?? 'year=2024';
        $resultData = null;
        $rawResult = null;
        $requestUrl = null;
        $curlExample = null;
        $errorMessage = null;
        $apiKey = (string)env('API_KEY');

        if ($this->request->is('post')) {
            $selectedApi = (string)$this->request->getData('api_id');
            $queryString = trim((string)$this->request->getData('query_string'));
            if ($queryString === '') {
                $queryString = $defaultQueries[$selectedApi] ?? 'year=2024';
            }

            if (!isset($apiOptions[$selectedApi])) {
                $errorMessage = 'API IDが不正です。';
            } elseif ($apiKey === '') {
                $errorMessage = 'API_KEY が未設定です。config/.env に設定してください。';
            } else {
                parse_str($queryString, $queryParams);
                if (!is_array($queryParams)) {
                    $queryParams = [];
                }
                $queryParams = array_filter($queryParams, function ($value) {
                    return is_scalar($value) && trim((string)$value) !== '';
                });

                $header = [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Context-Length: ' . 20,
                    'Ocp-Apim-Subscription-Key: ' . $apiKey,
                ];
                $content = [
                    'http' => [
                        'method' => 'GET',
                        'header' => implode("\r\n", $header),
                        'content' => '',
                        'ignore_errors' => true,
                    ],
                ];
                $context = stream_context_create($content);

                $baseUrl = 'https://www.reinfolib.mlit.go.jp/ex-api/external/' . $selectedApi . '?';
                $requestUrl = $baseUrl . http_build_query($queryParams);
                $curlExample = 'curl -s -H "Ocp-Apim-Subscription-Key: ' . $apiKey . '" "' . $requestUrl . '"';
                $response = file_get_contents($requestUrl, false, $context);

                if ($response === false) {
                    $errorMessage = 'APIレスポンスの取得に失敗しました。';
                } else {
                    $decodedBody = $response;
                    if (substr($response, 0, 2) === "\x1f\x8b") {
                        $inflated = gzdecode($response);
                        if ($inflated !== false) {
                            $decodedBody = $inflated;
                        }
                    }
                    $resultData = json_decode($decodedBody, true);

                    if (!is_array($resultData)) {
                        $errorMessage = 'JSONの解析に失敗しました。';
                        $resultData = null;
                    } else {
                        $rawResult = json_encode($resultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
            }
        }

        $this->set(compact(
            'apiCatalog',
            'apiOptions',
            'selectedApi',
            'queryString',
            'resultData',
            'rawResult',
            'requestUrl',
            'curlExample',
            'errorMessage'
        ));
    }

    public function layerData()
    {
        $this->request->allowMethod(['get']);

        $apiId = (string)$this->request->getQuery('api_id');
        $allowedApiIds = [
            'XKT019',
        ];
        if (!in_array($apiId, $allowedApiIds, true)) {
            $payload = ['success' => false, 'message' => 'api_id is not allowed', 'data' => []];
            return $this->response->withStatus(400)->withType('application/json')->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $apiKey = (string)env('API_KEY');
        if ($apiKey === '') {
            $payload = ['success' => false, 'message' => 'API_KEY is missing', 'data' => []];
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $queryParams = $this->request->getQueryParams();
        unset($queryParams['api_id']);
        $queryParams = array_filter($queryParams, function ($value) {
            return is_scalar($value) && trim((string)$value) !== '';
        });

        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'Context-Length: ' . 20,
            'Ocp-Apim-Subscription-Key: ' . $apiKey,
        ];
        $content = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $header),
                'content' => '',
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($content);
        $baseUrl = 'https://www.reinfolib.mlit.go.jp/ex-api/external/' . $apiId . '?';
        $requestUrl = $baseUrl . http_build_query($queryParams);
        $response = file_get_contents($requestUrl, false, $context);

        if ($response === false) {
            $payload = ['success' => false, 'message' => 'failed to fetch layer data', 'request_url' => $requestUrl, 'data' => []];
            return $this->response->withStatus(502)->withType('application/json')->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $decoded = $this->decodeApiResponse($response);
        if (!is_array($decoded)) {
            $payload = ['success' => false, 'message' => 'failed to decode layer data', 'request_url' => $requestUrl, 'data' => []];
            return $this->response->withStatus(502)->withType('application/json')->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $payload = ['success' => true, 'api_id' => $apiId, 'request_url' => $requestUrl, 'data' => $data];
        return $this->response->withType('application/json')->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function decodeApiResponse(string $response): ?array
    {
        if ($response === '') {
            return null;
        }

        $decodedBody = $response;
        if (substr($response, 0, 2) === "\x1f\x8b") {
            $inflated = gzdecode($response);
            if ($inflated !== false) {
                $decodedBody = $inflated;
            }
        }

        $json = json_decode($decodedBody, true);
        return is_array($json) ? $json : null;
    }

    private function getLibraryApiCatalog(): array
    {
        return [
            ['id' => 'XIT001', 'category' => '価格情報', 'name' => '不動産価格（取引価格・成約価格）情報取得API', 'source' => '不動産取引価格情報 / 成約価格情報'],
            ['id' => 'XIT002', 'category' => '市区町村情報', 'name' => '都道府県内市区町村一覧取得API', 'source' => '全国地方公共団体コード準拠'],
            ['id' => 'XCT001', 'category' => '価格情報', 'name' => '鑑定評価書情報API', 'source' => '地価公示（鑑定評価書）'],
            ['id' => 'XPT001', 'category' => '価格情報', 'name' => '不動産価格情報のポイント (点) API', 'source' => '不動産取引価格情報 / 成約価格情報'],
            ['id' => 'XPT002', 'category' => '価格情報', 'name' => '地価公示・地価調査のポイント (点) API', 'source' => '地価公示 / 地価調査'],
            ['id' => 'XKT001', 'category' => '都市計画情報', 'name' => '都市計画区域/区域区分 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT002', 'category' => '都市計画情報', 'name' => '用途地域 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT003', 'category' => '都市計画情報', 'name' => '立地適正化計画 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT004', 'category' => '周辺施設情報', 'name' => '小学校区 API', 'source' => '国土数値情報（小学校区）'],
            ['id' => 'XKT005', 'category' => '周辺施設情報', 'name' => '中学校区 API', 'source' => '国土数値情報（中学校区）'],
            ['id' => 'XKT006', 'category' => '周辺施設情報', 'name' => '学校 API', 'source' => '国土数値情報（学校）'],
            ['id' => 'XKT007', 'category' => '周辺施設情報', 'name' => '保育園・幼稚園等 API', 'source' => '国土数値情報（学校・福祉施設加工）'],
            ['id' => 'XKT010', 'category' => '周辺施設情報', 'name' => '医療機関 API', 'source' => '国土数値情報（医療機関）'],
            ['id' => 'XKT011', 'category' => '周辺施設情報', 'name' => '福祉施設 API', 'source' => '国土数値情報（福祉施設）'],
            ['id' => 'XKT013', 'category' => '人口情報等', 'name' => '将来推計人口250mメッシュ API', 'source' => '国土数値情報（250mメッシュ別将来推計人口）'],
            ['id' => 'XKT014', 'category' => '都市計画情報', 'name' => '防火・準防火地域 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT015', 'category' => '人口情報等', 'name' => '駅別乗降客数 API', 'source' => '国土数値情報（駅別乗降客数）'],
            ['id' => 'XKT016', 'category' => '防災情報', 'name' => '災害危険区域 API', 'source' => '国土数値情報（災害危険区域）'],
            ['id' => 'XKT017', 'category' => '周辺施設情報', 'name' => '図書館 API', 'source' => '国土数値情報（文化施設加工）'],
            ['id' => 'XKT018', 'category' => '周辺施設情報', 'name' => '市区町村役場及び集会施設等 API', 'source' => '国土数値情報（市町村役場等及び公的集会施設）'],
            ['id' => 'XKT019', 'category' => '周辺施設情報', 'name' => '自然公園地域 API', 'source' => '国土数値情報（自然公園地域）'],
            ['id' => 'XKT020', 'category' => '地形情報', 'name' => '大規模盛土造成地マップ API', 'source' => '国土数値情報（大規模盛土造成地）'],
            ['id' => 'XKT021', 'category' => '防災情報', 'name' => '地すべり防止地区 API', 'source' => '国土数値情報（地すべり防止区域）'],
            ['id' => 'XKT022', 'category' => '防災情報', 'name' => '急傾斜地崩壊危険区域 API', 'source' => '国土数値情報（急傾斜地崩壊危険区域）'],
            ['id' => 'XKT023', 'category' => '都市計画情報', 'name' => '地区計画 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT024', 'category' => '都市計画情報', 'name' => '高度利用地区 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT025', 'category' => '防災情報', 'name' => '液状化の発生傾向図 API', 'source' => '国土交通省都市局'],
            ['id' => 'XKT026', 'category' => '防災情報', 'name' => '洪水浸水想定区域 API', 'source' => '国土数値情報（洪水浸水想定区域）'],
            ['id' => 'XKT027', 'category' => '防災情報', 'name' => '高潮浸水想定区域 API', 'source' => '国土数値情報（高潮浸水想定区域）'],
            ['id' => 'XKT028', 'category' => '防災情報', 'name' => '津波浸水想定 API', 'source' => '国土数値情報（津波浸水想定）'],
            ['id' => 'XKT029', 'category' => '防災情報', 'name' => '土砂災害警戒区域 API', 'source' => '国土数値情報（土砂災害警戒区域）'],
            ['id' => 'XKT030', 'category' => '都市計画情報', 'name' => '都市計画道路 API', 'source' => '都市計画決定GISデータ（令和6年度）'],
            ['id' => 'XKT031', 'category' => '人口情報等', 'name' => '人口集中地区 API', 'source' => '国土数値情報（人口集中地区データ）'],
            ['id' => 'XGT001', 'category' => '防災情報', 'name' => '指定緊急避難場所 API', 'source' => '国土地理院GISデータ'],
            ['id' => 'XST001', 'category' => '防災情報', 'name' => '災害履歴 API', 'source' => '国土調査（土地履歴調査）'],
        ];
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
