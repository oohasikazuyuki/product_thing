<?php
declare(strict_types=1);

namespace App\Controller;

class AreaAnalysisController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
    }

    public function safetySurvey()
    {
        $this->prepareAnalysisFilters();
    }

    public function schoolSurvey()
    {
        $this->prepareAnalysisFilters();
    }

    private function prepareAnalysisFilters(): void
    {
        $areaOptions = $this->getPrefectureOptions();
        $selectedArea = (string)$this->request->getQuery('area', '13');
        if (!isset($areaOptions[$selectedArea])) {
            $selectedArea = '13';
        }

        $yearOptions = $this->getYearOptions();
        $selectedYear = (string)$this->request->getQuery('year', '2024');
        if (!isset($yearOptions[$selectedYear])) {
            $selectedYear = '2024';
        }

        $cityOptions = $this->fetchCityOptions($selectedArea, $selectedYear);
        $selectedCity = (string)$this->request->getQuery('city', '');
        if ($selectedCity === '' && $cityOptions !== []) {
            $selectedCity = (string)array_key_first($cityOptions);
        }
        if ($selectedCity !== '' && !isset($cityOptions[$selectedCity])) {
            $selectedCity = '';
        }

        $districtOptions = $this->fetchDistrictOptions($selectedArea, $selectedCity, $selectedYear);
        $selectedDistrict = (string)$this->request->getQuery('district', '');
        if ($selectedDistrict !== '' && !isset($districtOptions[$selectedDistrict])) {
            $selectedDistrict = '';
        }

        $this->set('area', $selectedArea);
        $this->set('city', $selectedCity);
        $this->set('year', $selectedYear);
        $this->set('district', $selectedDistrict);
        $this->set('areaOptions', $areaOptions);
        $this->set('cityOptions', $cityOptions);
        $this->set('yearOptions', $yearOptions);
        $this->set('districtOptions', $districtOptions);
    }

    private function fetchCityOptions(string $area, string $year): array
    {
        $decoded = $this->callMlitApi('XIT002', ['area' => $area, 'year' => $year]);
        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            return [];
        }

        $options = [];
        foreach ($decoded['data'] as $row) {
            if (!is_array($row) || empty($row['id']) || empty($row['name'])) {
                continue;
            }
            $options[(string)$row['id']] = (string)$row['name'];
        }

        return $options;
    }

    private function fetchDistrictOptions(string $area, string $city, string $year): array
    {
        if ($city === '') {
            return [];
        }

        $decoded = $this->callMlitApi('XIT001', ['area' => $area, 'city' => $city, 'year' => $year]);
        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            return [];
        }

        $options = [];
        foreach ($decoded['data'] as $row) {
            if (!is_array($row) || empty($row['DistrictName'])) {
                continue;
            }
            $name = (string)$row['DistrictName'];
            $options[$name] = $name;
        }
        ksort($options);

        return $options;
    }

    private function callMlitApi(string $apiId, array $query): array
    {
        $apiKey = (string)env('API_KEY');
        if ($apiKey === '') {
            return [];
        }

        $query = array_filter($query, function ($value) {
            return trim((string)$value) !== '';
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
        $response = file_get_contents($baseUrl . http_build_query($query), false, $context);
        if ($response === false) {
            return [];
        }

        if (substr($response, 0, 2) === "\x1f\x8b") {
            $inflated = gzdecode($response);
            if ($inflated !== false) {
                $response = $inflated;
            }
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getYearOptions(): array
    {
        $years = [];
        for ($year = 2005; $year <= 2030; $year++) {
            $years[(string)$year] = $year . '年';
        }

        return $years;
    }

    private function getPrefectureOptions(): array
    {
        return [
            '01' => '北海道', '02' => '青森県', '03' => '岩手県', '04' => '宮城県', '05' => '秋田県', '06' => '山形県', '07' => '福島県',
            '08' => '茨城県', '09' => '栃木県', '10' => '群馬県', '11' => '埼玉県', '12' => '千葉県', '13' => '東京都', '14' => '神奈川県',
            '15' => '新潟県', '16' => '富山県', '17' => '石川県', '18' => '福井県', '19' => '山梨県', '20' => '長野県', '21' => '岐阜県',
            '22' => '静岡県', '23' => '愛知県', '24' => '三重県', '25' => '滋賀県', '26' => '京都府', '27' => '大阪府', '28' => '兵庫県',
            '29' => '奈良県', '30' => '和歌山県', '31' => '鳥取県', '32' => '島根県', '33' => '岡山県', '34' => '広島県', '35' => '山口県',
            '36' => '徳島県', '37' => '香川県', '38' => '愛媛県', '39' => '高知県', '40' => '福岡県', '41' => '佐賀県', '42' => '長崎県',
            '43' => '熊本県', '44' => '大分県', '45' => '宮崎県', '46' => '鹿児島県', '47' => '沖縄県',
        ];
    }
}
