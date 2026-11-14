<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Exception\BadRequestException;

/**
 * 民間賃貸API向け検索サービス。
 *
 * 優先順位:
 * 1. RapidAPI SUUMO Japan Real Estate（RAPIDAPI_KEY）
 * 2. LIFULL HOME'S API（HOMES_API_BEARER）
 * 3. デモデータ（鍵未設定時）
 */
class RentalSearchService
{
    private const HOMES_BASE = 'https://openai-api.homes.co.jp';
    private const RAPIDAPI_HOST = 'suumo-japan-real-estate.p.rapidapi.com';

    /**
     * @param array<string, mixed> $params
     * @return array{provider: string, provider_label: string, total: int, items: array<int, array<string, mixed>>, demo: bool, message: string|null}
     */
    public function search(array $params): array
    {
        $query = $this->normalizeQuery($params);

        $rapidKey = (string)env('RAPIDAPI_KEY', '');
        if ($rapidKey !== '') {
            return $this->searchRapidApiSuumo($query, $rapidKey);
        }

        $homesToken = (string)env('HOMES_API_BEARER', '');
        if ($homesToken !== '') {
            return $this->searchHomes($query, $homesToken);
        }

        return $this->searchDemo($query);
    }

    /**
     * @return array<string, string>
     */
    public function providerStatus(): array
    {
        if ((string)env('RAPIDAPI_KEY', '') !== '') {
            return [
                'id' => 'rapidapi_suumo',
                'label' => 'RapidAPI SUUMO Japan Real Estate',
                'mode' => 'live',
            ];
        }
        if ((string)env('HOMES_API_BEARER', '') !== '') {
            return [
                'id' => 'homes',
                'label' => "LIFULL HOME'S API",
                'mode' => 'live',
            ];
        }

        return [
            'id' => 'demo',
            'label' => 'デモデータ（RAPIDAPI_KEY 未設定）',
            'mode' => 'demo',
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{free_word: string, fulladdr: string, month_money_room: ?float, month_money_room_h: ?float, page: int, geo_pt: ?string, geo_rd: ?float, prefecture: string, city: string, ward: string}
     */
    private function normalizeQuery(array $params): array
    {
        $page = (int)($params['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $minRentMan = $this->toOptionalFloat($params['month_money_room'] ?? null);
        $maxRentMan = $this->toOptionalFloat($params['month_money_room_h'] ?? null);
        if ($minRentMan !== null && $maxRentMan !== null && $minRentMan > $maxRentMan) {
            throw new BadRequestException('最低賃料が最高賃料を超えています。');
        }

        return [
            'free_word' => trim((string)($params['free_word'] ?? '')),
            'fulladdr' => trim((string)($params['fulladdr'] ?? '')),
            'month_money_room' => $minRentMan,
            'month_money_room_h' => $maxRentMan,
            'page' => $page,
            'geo_pt' => $this->toOptionalGeoPoint($params['geo_pt'] ?? null),
            'geo_rd' => $this->toOptionalFloat($params['geo_rd'] ?? null),
            'prefecture' => trim((string)($params['prefecture'] ?? '')),
            'city' => trim((string)($params['city'] ?? '')),
            'ward' => trim((string)($params['ward'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{provider: string, provider_label: string, total: int, items: array<int, array<string, mixed>>, demo: bool, message: string|null}
     */
    private function searchHomes(array $query, string $bearer): array
    {
        $params = [
            'page' => (string)$query['page'],
        ];
        if ($query['free_word'] !== '') {
            $params['free_word'] = $query['free_word'];
            $params['fw_type'] = '1';
        }
        if ($query['fulladdr'] !== '') {
            $params['fulladdr'] = $query['fulladdr'];
        }
        if ($query['month_money_room'] !== null) {
            $params['month_money_room'] = (string)$query['month_money_room'];
        }
        if ($query['month_money_room_h'] !== null) {
            $params['month_money_room_h'] = (string)$query['month_money_room_h'];
        }
        if ($query['geo_pt'] !== null) {
            $params['geo_pt'] = $query['geo_pt'];
            $params['geo_rd'] = (string)($query['geo_rd'] ?? 2);
        }

        $response = $this->httpGet(
            self::HOMES_BASE . '/v1/realestate_article/search/room?' . http_build_query($params),
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $bearer,
            ]
        );

        if ($response['status'] >= 400) {
            return [
                'provider' => 'homes',
                'provider_label' => "LIFULL HOME'S API",
                'total' => 0,
                'items' => [],
                'demo' => false,
                'message' => 'HOME\'S API の取得に失敗しました（HTTP ' . $response['status'] . '）。トークンと利用契約を確認してください。',
            ];
        }

        $decoded = json_decode($response['body'], true);
        $rows = [];
        $total = 0;
        if (is_array($decoded)) {
            $resultSet = $decoded['result_set'] ?? $decoded;
            if (is_array($resultSet)) {
                $total = (int)($resultSet['total_hits'] ?? 0);
                $rowSet = $resultSet['row_set'] ?? [];
                if (is_array($rowSet)) {
                    foreach ($rowSet as $row) {
                        if (is_array($row)) {
                            $rows[] = $this->normalizeHomesItem($row);
                        }
                    }
                }
            }
        }

        return [
            'provider' => 'homes',
            'provider_label' => "LIFULL HOME'S API",
            'total' => $total > 0 ? $total : count($rows),
            'items' => $rows,
            'demo' => false,
            'message' => null,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{provider: string, provider_label: string, total: int, items: array<int, array<string, mixed>>, demo: bool, message: string|null}
     */
    private function searchRapidApiSuumo(array $query, string $apiKey): array
    {
        // RapidAPI docs: prefecture=JIS2桁, city=市区町村コード, rent_* = 万円単位
        $params = [
            'page' => (string)$query['page'],
            'limit' => '20',
            'prefecture' => $this->toPrefectureCode($query['prefecture'], $query['fulladdr']),
        ];

        $city = $query['city'] !== '' ? $query['city'] : $query['ward'];
        if ($city !== '') {
            $params['city'] = $city;
        }
        if ($query['free_word'] !== '') {
            $params['keyword'] = $query['free_word'];
        }
        if ($query['month_money_room'] !== null) {
            $params['rent_min'] = (string)$query['month_money_room'];
        }
        if ($query['month_money_room_h'] !== null) {
            $params['rent_max'] = (string)$query['month_money_room_h'];
        }

        $response = $this->httpGet(
            'https://' . self::RAPIDAPI_HOST . '/search-rentals?' . http_build_query($params),
            [
                'Accept: application/json',
                'X-RapidAPI-Key: ' . $apiKey,
                'X-RapidAPI-Host: ' . self::RAPIDAPI_HOST,
            ]
        );

        $status = $response['status'];
        $body = $response['body'];
        if ($status >= 400) {
            $hint = 'キーと「Suumo Japan Real Estate」の購読状態を確認してください。';
            if ($status === 403) {
                $hint = 'API未購読の可能性が高いです。RapidAPIで Subscribe してください。';
            } elseif ($status === 429) {
                $hint = '無料枠の上限に達した可能性があります。';
            }

            return [
                'provider' => 'rapidapi_suumo',
                'provider_label' => 'RapidAPI SUUMO Japan Real Estate',
                'total' => 0,
                'items' => [],
                'demo' => false,
                'message' => 'RapidAPI 取得失敗（HTTP ' . $status . '）。' . $hint,
                'debug_body' => mb_substr($body, 0, 300),
            ];
        }

        $decoded = json_decode($body, true);
        $extracted = $this->extractRapidApiList($decoded);
        $rows = [];
        foreach ($extracted['list'] as $row) {
            if (is_array($row)) {
                $normalized = $this->normalizeRapidApiItem($row);
                if ($normalized['name'] !== '' || $normalized['address'] !== '') {
                    $rows[] = $normalized;
                }
            }
        }

        return [
            'provider' => 'rapidapi_suumo',
            'provider_label' => 'RapidAPI SUUMO Japan Real Estate',
            'total' => $extracted['total'] > 0 ? $extracted['total'] : count($rows),
            'items' => $rows,
            'demo' => false,
            'message' => count($rows) === 0 ? 'APIは成功しましたが物件が0件でした。条件を緩めて再検索してください。' : null,
        ];
    }

    /**
     * @param mixed $decoded
     * @return array{total: int, list: array<int, mixed>}
     */
    private function extractRapidApiList($decoded): array
    {
        if (!is_array($decoded)) {
            return ['total' => 0, 'list' => []];
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $total = (int)(
            $decoded['total']
            ?? $decoded['count']
            ?? $decoded['total_count']
            ?? $decoded['hit']
            ?? $data['total']
            ?? 0
        );
        $candidates = [
            $data['items'] ?? null,
            $data['results'] ?? null,
            $data['listings'] ?? null,
            $decoded['results'] ?? null,
            $decoded['listings'] ?? null,
            $decoded['items'] ?? null,
            $decoded['properties'] ?? null,
            $decoded['buildings'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            // 連想配列の単一物件ではなく、リストであることを確認
            if ($candidate !== [] && array_keys($candidate) !== range(0, count($candidate) - 1)) {
                continue;
            }

            return ['total' => $total, 'list' => $candidate];
        }

        if ($decoded !== [] && array_keys($decoded) === range(0, count($decoded) - 1)) {
            return ['total' => $total > 0 ? $total : count($decoded), 'list' => $decoded];
        }

        return ['total' => $total, 'list' => []];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{provider: string, provider_label: string, total: int, items: array<int, array<string, mixed>>, demo: bool, message: string|null}
     */
    private function searchDemo(array $query): array
    {
        $samples = $this->demoListings();
        $filtered = array_values(array_filter($samples, function (array $item) use ($query): bool {
            if ($query['free_word'] !== '') {
                $haystack = mb_strtolower(
                    ($item['name'] ?? '') . ' ' . ($item['address'] ?? '') . ' ' . ($item['station'] ?? '') . ' ' . ($item['layout'] ?? '')
                );
                $needle = mb_strtolower($query['free_word']);
                if (mb_strpos($haystack, $needle) === false) {
                    return false;
                }
            }
            if ($query['fulladdr'] !== '') {
                if (mb_strpos((string)$item['address'], $query['fulladdr']) === false) {
                    return false;
                }
            }
            $rentMan = isset($item['rent_yen']) ? ((float)$item['rent_yen'] / 10000) : null;
            if ($query['month_money_room'] !== null && $rentMan !== null && $rentMan < $query['month_money_room']) {
                return false;
            }
            if ($query['month_money_room_h'] !== null && $rentMan !== null && $rentMan > $query['month_money_room_h']) {
                return false;
            }

            return true;
        }));

        return [
            'provider' => 'demo',
            'provider_label' => 'デモデータ（APIキー未設定）',
            'total' => count($filtered),
            'items' => $filtered,
            'demo' => true,
            'message' => 'HOMES_API_BEARER または RAPIDAPI_KEY を設定すると、実際の民間賃貸APIから取得します。',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeHomesItem(array $row): array
    {
        $traffics = is_array($row['traffics'] ?? null) ? $row['traffics'] : [];
        $firstTraffic = isset($traffics[0]) && is_array($traffics[0]) ? $traffics[0] : [];
        $photos = is_array($row['photos'] ?? null) ? $row['photos'] : [];
        $photoUrl = null;
        if (isset($photos[0]) && is_array($photos[0]) && isset($photos[0]['url'])) {
            $photoUrl = (string)$photos[0]['url'];
        }

        $rentText = (string)($row['month_money_room_text'] ?? $row['money_room_text'] ?? '');
        $rentYen = $this->parseYenAmount($rentText);
        if ($rentYen === null && isset($row['month_money_room'])) {
            $rentYen = (int)round(((float)$row['month_money_room']) * 10000);
        }

        return [
            'id' => (string)($row['property_id'] ?? ''),
            'name' => (string)($row['realestate_article_name'] ?? $row['full_address'] ?? '物件'),
            'type' => (string)($row['realestate_article_type_name'] ?? '賃貸'),
            'address' => (string)($row['full_address'] ?? ''),
            'lat' => $this->toOptionalFloat($row['lat'] ?? null),
            'lng' => $this->toOptionalFloat($row['lng'] ?? null),
            'layout' => (string)($row['floor_plan_text'] ?? ''),
            'rent_text' => $rentText !== '' ? $rentText : ($rentYen !== null ? number_format($rentYen) . '円' : ''),
            'rent_yen' => $rentYen,
            'maintenance_text' => (string)($row['money_maintenance_text'] ?? ''),
            'deposit_text' => (string)($row['deposit_text'] ?? ''),
            'key_money_text' => (string)($row['key_money_text'] ?? ''),
            'building_age' => isset($row['house_age']) ? (int)$row['house_age'] : null,
            'building_completed_text' => (string)($row['building_completed_text'] ?? ''),
            'station' => (string)($firstTraffic['station_name'] ?? ''),
            'walk_minutes' => isset($firstTraffic['walk_time']) ? (int)$firstTraffic['walk_time'] : null,
            'photo_url' => $photoUrl,
            'detail_url' => (string)($row['url'] ?? ''),
            'source' => 'homes',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRapidApiItem(array $row): array
    {
        // HappyEndpoint SUUMO 形式（日本語キー）を優先し、英語キーにもフォールバック
        $rentYen = null;
        if (isset($row['kakaku_value']) && is_numeric($row['kakaku_value'])) {
            $rentYen = (int)round(((float)$row['kakaku_value']) * 10000);
        }
        if ($rentYen === null) {
            foreach (['getsugaku', 'kakaku', 'rent', 'rent_yen', 'monthly_rent', 'price'] as $key) {
                if (!isset($row[$key])) {
                    continue;
                }
                $rentYen = $this->parseYenAmount($row[$key]);
                if ($rentYen !== null) {
                    break;
                }
            }
        }

        $lat = $this->toOptionalFloat($row['lt'] ?? $row['lat'] ?? $row['latitude'] ?? null);
        $lng = $this->toOptionalFloat($row['lg'] ?? $row['lng'] ?? $row['longitude'] ?? null);

        $addressParts = [
            (string)($row['todofuken'] ?? ''),
            (string)($row['shigunku'] ?? ''),
            (string)($row['choson'] ?? ''),
        ];
        $address = trim(implode('', array_filter($addressParts, static fn(string $part): bool => $part !== '')));
        if ($address === '') {
            $address = (string)($row['address'] ?? $row['full_address'] ?? $row['kaisha_jusho'] ?? '');
        }

        $name = (string)($row['chintai_bukken_nm'] ?? $row['bukken_nm'] ?? $row['title'] ?? $row['name'] ?? '');
        if ($name === '') {
            $name = $address !== '' ? $address : '物件';
        }

        $stationText = (string)($row['kotsu'] ?? $row['bukken_nm'] ?? $row['station'] ?? '');
        $stationName = $stationText;
        if (preg_match('/\/([^\\s]+駅)/u', $stationText, $matches)) {
            $stationName = $matches[1];
        } elseif (preg_match('/([^\\s\\/]+駅)/u', $stationText, $matches)) {
            $stationName = $matches[1];
        }

        $walk = $row['eki_toho_funsu'] ?? $row['walk_minutes'] ?? $row['walk'] ?? null;
        $photo = $row['kanigazo'] ?? $row['gaikangazo_b'] ?? $row['image'] ?? $row['photo_url'] ?? null;
        $rentText = (string)($row['getsugaku'] ?? $row['kakaku'] ?? '');
        if ($rentText === '' && $rentYen !== null) {
            $rentText = number_format($rentYen) . '円';
        }

        return [
            'id' => (string)($row['bukken_cd'] ?? $row['id'] ?? $row['listing_id'] ?? md5($name . $address)),
            'name' => $name,
            'type' => (string)($row['tatemono_shu_nm'] ?? $row['category'] ?? $row['property_type'] ?? '賃貸'),
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'layout' => (string)($row['madori'] ?? $row['layout'] ?? $row['floor_plan'] ?? ''),
            'rent_text' => $rentText,
            'rent_yen' => $rentYen,
            'maintenance_text' => (string)($row['kanrihi'] ?? $row['management_fee'] ?? ''),
            'deposit_text' => (string)($row['shikikin'] ?? $row['deposit'] ?? ''),
            'key_money_text' => (string)($row['reikin'] ?? $row['key_money'] ?? ''),
            'building_age' => isset($row['age']) ? (int)$row['age'] : null,
            'building_completed_text' => (string)($row['chikunen'] ?? $row['built'] ?? $row['built_year'] ?? ''),
            'station' => $stationName,
            'walk_minutes' => $walk !== null && $walk !== '' ? (int)$walk : null,
            'photo_url' => $photo ? (string)$photo : null,
            'detail_url' => (string)($row['pc_link'] ?? $row['link'] ?? $row['smp_link'] ?? $row['url'] ?? ''),
            'source' => 'rapidapi_suumo',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoListings(): array
    {
        return [
            [
                'id' => 'demo-shibuya-1',
                'name' => 'デモ物件 渋谷駅徒歩8分 1K',
                'type' => '賃貸マンション',
                'address' => '東京都渋谷区道玄坂1丁目',
                'lat' => 35.6595,
                'lng' => 139.7005,
                'layout' => '1K',
                'rent_text' => '98,000円',
                'rent_yen' => 98000,
                'maintenance_text' => '5,000円',
                'deposit_text' => '1ヶ月',
                'key_money_text' => '1ヶ月',
                'building_age' => 12,
                'building_completed_text' => '2014年3月',
                'station' => '渋谷',
                'walk_minutes' => 8,
                'photo_url' => null,
                'detail_url' => '',
                'source' => 'demo',
            ],
            [
                'id' => 'demo-shinjuku-1',
                'name' => 'デモ物件 新宿三丁目 1LDK',
                'type' => '賃貸マンション',
                'address' => '東京都新宿区新宿3丁目',
                'lat' => 35.6909,
                'lng' => 139.7005,
                'layout' => '1LDK',
                'rent_text' => '145,000円',
                'rent_yen' => 145000,
                'maintenance_text' => '8,000円',
                'deposit_text' => '1ヶ月',
                'key_money_text' => 'なし',
                'building_age' => 7,
                'building_completed_text' => '2019年8月',
                'station' => '新宿三丁目',
                'walk_minutes' => 5,
                'photo_url' => null,
                'detail_url' => '',
                'source' => 'demo',
            ],
            [
                'id' => 'demo-yokohama-1',
                'name' => 'デモ物件 横浜駅 2LDK',
                'type' => '賃貸アパート',
                'address' => '神奈川県横浜市西区南幸2丁目',
                'lat' => 35.4657,
                'lng' => 139.6220,
                'layout' => '2LDK',
                'rent_text' => '118,000円',
                'rent_yen' => 118000,
                'maintenance_text' => '6,000円',
                'deposit_text' => '1ヶ月',
                'key_money_text' => '1ヶ月',
                'building_age' => 15,
                'building_completed_text' => '2011年4月',
                'station' => '横浜',
                'walk_minutes' => 12,
                'photo_url' => null,
                'detail_url' => '',
                'source' => 'demo',
            ],
            [
                'id' => 'demo-osaka-1',
                'name' => 'デモ物件 梅田 1DK',
                'type' => '賃貸マンション',
                'address' => '大阪府大阪市北区曽根崎2丁目',
                'lat' => 34.7024,
                'lng' => 135.4959,
                'layout' => '1DK',
                'rent_text' => '85,000円',
                'rent_yen' => 85000,
                'maintenance_text' => '4,000円',
                'deposit_text' => 'なし',
                'key_money_text' => 'なし',
                'building_age' => 9,
                'building_completed_text' => '2017年1月',
                'station' => '梅田',
                'walk_minutes' => 7,
                'photo_url' => null,
                'detail_url' => '',
                'source' => 'demo',
            ],
        ];
    }

    private function toOptionalFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float)$value;

        return is_finite($number) ? $number : null;
    }

    private function toOptionalGeoPoint($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = trim((string)$value);
        if (!preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $text)) {
            throw new BadRequestException('geo_pt は lat,lng 形式で指定してください。');
        }

        return $text;
    }

    /**
     * @param mixed $value
     */
    private function parseYenAmount($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (int)round((float)$value);
        }
        $text = (string)$value;
        if (preg_match('/(\d+(?:\.\d+)?)\s*万/', $text, $matches)) {
            return (int)round(((float)$matches[1]) * 10000);
        }
        $digits = preg_replace('/[^\d]/', '', $text);
        if ($digits === null || $digits === '') {
            return null;
        }

        return (int)$digits;
    }

    /**
     * @param array<int, string> $headers
     * @return array{status: int, body: string}
     */
    private function httpGet(string $url, array $headers): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 25,
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            if ($body === false) {
                return ['status' => 502, 'body' => $error !== '' ? $error : 'curl request failed'];
            }

            return ['status' => $status > 0 ? $status : 502, 'body' => (string)$body];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 25,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 502;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int)$matches[1];
        }

        return ['status' => $status, 'body' => $body === false ? '' : (string)$body];
    }

    private function toPrefectureCode(string $prefecture, string $fulladdr = ''): string
    {
        if (preg_match('/^\d{2}$/', $prefecture)) {
            return $prefecture;
        }

        $map = [
            'hokkaido' => '01', '北海道' => '01',
            'tokyo' => '13', '東京都' => '13', '東京' => '13',
            'kanagawa' => '14', '神奈川県' => '14', '神奈川' => '14',
            'osaka' => '27', '大阪府' => '27', '大阪' => '27',
            'aichi' => '23', '愛知県' => '23', '愛知' => '23',
            'fukuoka' => '40', '福岡県' => '40', '福岡' => '40',
            'saitama' => '11', '埼玉県' => '11', '埼玉' => '11',
            'chiba' => '12', '千葉県' => '12', '千葉' => '12',
            'hyogo' => '28', '兵庫県' => '28', '兵庫' => '28',
            'kyoto' => '26', '京都府' => '26', '京都' => '26',
        ];
        $key = strtolower($prefecture);
        if (isset($map[$key])) {
            return $map[$key];
        }
        if (isset($map[$prefecture])) {
            return $map[$prefecture];
        }

        foreach ($map as $name => $code) {
            if ($fulladdr !== '' && mb_strpos($fulladdr, (string)$name) !== false) {
                return $code;
            }
        }

        return '13';
    }
}
