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

        $result = \App\Service\MlitApi::get($apiId, $query);
        $response = $result['status'] === 0 ? false : $result['body'];
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
        return \App\Service\Prefectures::all();
    }
}
