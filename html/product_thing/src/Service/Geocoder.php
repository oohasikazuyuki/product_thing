<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Http\Client;

/**
 * 住所（都道府県+市区町村+地区名）を国土地理院の住所検索で座標に変換する。
 * 成功も失敗も 'geocode' キャッシュ(30日)に残すので、同じ地区は2回目以降すぐ引ける。
 */
class Geocoder
{
    /**
     * @return array{0: float, 1: float}|null [経度, 緯度]
     */
    public static function lookup(string $address, bool $allowRequest = true, bool &$requested = false): ?array
    {
        $key = 'g_' . md5($address);
        $cached = Cache::read($key, 'geocode');
        if ($cached !== null && $cached !== false) {
            return $cached === 'none' ? null : $cached;
        }
        if (!$allowRequest) {
            return null;
        }

        $requested = true;
        $coordinates = self::request($address);
        Cache::write($key, $coordinates ?? 'none', 'geocode');

        return $coordinates;
    }

    /**
     * 取引行（Prefecture/Municipality/DistrictName を持つ配列）に Longitude/Latitude を付ける。
     * 未キャッシュの住所は1回の呼び出しで $budget 件まで検索し、残りは次回以降に持ち越す。
     *
     * @param array<int, mixed> $rows
     * @return array<int, mixed>
     */
    public static function attach(array $rows, int $budget = 120): array
    {
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $address = trim(($row['Prefecture'] ?? '') . ($row['Municipality'] ?? '') . ($row['DistrictName'] ?? ''));
            if ($address === '') {
                continue;
            }
            $requested = false;
            $hit = self::lookup($address, $budget > 0, $requested);
            if ($requested) {
                $budget--;
            }
            if ($hit !== null) {
                [$row['Longitude'], $row['Latitude']] = $hit;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private static function request(string $address): ?array
    {
        try {
            $response = (new Client(['timeout' => 8]))->get(
                'https://msearch.gsi.go.jp/address-search/AddressSearch',
                ['q' => $address],
                ['headers' => ['Accept' => 'application/json']]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if ($response->getStatusCode() >= 400) {
            return null;
        }
        $decoded = json_decode($response->getStringBody(), true);
        $coordinates = $decoded[0]['geometry']['coordinates'] ?? null;
        if (!is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }
        $longitude = (float)$coordinates[0];
        $latitude = (float)$coordinates[1];

        return is_finite($longitude) && is_finite($latitude) ? [$longitude, $latitude] : null;
    }
}
