<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Http\Client;

/**
 * 最寄り駅から物件までの徒歩ルート。
 * 駅の位置は OpenStreetMap Nominatim、経路は OSM 徒歩ルーティング(routing.openstreetmap.de)で取得する。
 * どちらも公開サービス(利用制限あり)のため、結果は 'geocode' キャッシュ(30日)に保存して呼び出しを最小にする。
 */
class WalkingRoute
{
    private const WALK_METERS_PER_MINUTE = 80; // 不動産表示の慣習（徒歩1分=80m）
    private const MAX_STATION_DISTANCE_M = 3000;

    /**
     * @return array{ok: bool, message?: string, station?: array{name: string, lng: float, lat: float}, distance_m?: int, minutes?: int, line?: array<int, array{0: float, 1: float}>}
     */
    public static function between(string $station, float $lat, float $lng): array
    {
        $name = preg_replace('/駅$/u', '', trim($station)) . '駅';
        $stationPoint = self::findStation($name, $lat, $lng);
        if ($stationPoint === null) {
            return ['ok' => false, 'message' => '駅の位置を特定できませんでした。'];
        }
        if (self::haversine($stationPoint[1], $stationPoint[0], $lat, $lng) > self::MAX_STATION_DISTANCE_M) {
            return ['ok' => false, 'message' => '物件の近くに該当する駅が見つかりませんでした。'];
        }

        $route = self::route($stationPoint, [$lng, $lat]);
        if ($route === null) {
            return ['ok' => false, 'message' => '徒歩ルートを取得できませんでした。'];
        }

        return [
            'ok' => true,
            'station' => ['name' => $name, 'lng' => $stationPoint[0], 'lat' => $stationPoint[1]],
            'distance_m' => $route['distance'],
            'minutes' => (int)ceil($route['distance'] / self::WALK_METERS_PER_MINUTE),
            'line' => $route['line'],
        ];
    }

    /**
     * 物件の周辺(約±6km)で駅名に一致する駅のうち、物件に最も近いものを返す。
     *
     * @return array{0: float, 1: float}|null [経度, 緯度]
     */
    private static function findStation(string $name, float $lat, float $lng): ?array
    {
        $key = 'st_' . md5($name . '|' . round($lat, 2) . '|' . round($lng, 2));
        $cached = Cache::read($key, 'geocode');
        if ($cached !== null) {
            return $cached === 'none' ? null : $cached;
        }

        $d = 0.06;
        try {
            $response = (new Client(['timeout' => 8]))->get('https://nominatim.openstreetmap.org/search', [
                'q' => $name,
                'format' => 'jsonv2',
                'limit' => 20,
                'countrycodes' => 'jp',
                'accept-language' => 'ja',
                'viewbox' => ($lng - $d) . ',' . ($lat + $d) . ',' . ($lng + $d) . ',' . ($lat - $d),
                'bounded' => 1,
            ], ['headers' => ['User-Agent' => 'product-thing/1.0 (rental search; station lookup)', 'Accept' => 'application/json']]);
        } catch (\Throwable $e) {
            return null; // 通信失敗はキャッシュしない
        }
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $best = null;
        $bestDistance = INF;
        foreach ((array)json_decode($response->getStringBody(), true) as $row) {
            if (!is_array($row) || !isset($row['lat'], $row['lon'])) {
                continue;
            }
            $isRail = ($row['category'] ?? '') === 'railway' || ($row['category'] ?? '') === 'public_transport';
            if (!$isRail) {
                continue;
            }
            $distance = self::haversine((float)$row['lat'], (float)$row['lon'], $lat, $lng);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = [(float)$row['lon'], (float)$row['lat']];
            }
        }

        Cache::write($key, $best ?? 'none', 'geocode');

        return $best;
    }

    /**
     * @param array{0: float, 1: float} $from
     * @param array{0: float, 1: float} $to
     * @return array{distance: int, line: array<int, array{0: float, 1: float}>}|null
     */
    private static function route(array $from, array $to): ?array
    {
        $coords = sprintf('%.5F,%.5F;%.5F,%.5F', $from[0], $from[1], $to[0], $to[1]);
        $key = 'rt_' . md5($coords);
        $cached = Cache::read($key, 'geocode');
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = (new Client(['scheme' => 'https', 'host' => 'routing.openstreetmap.de', 'timeout' => 10]))
                ->get('/routed-foot/route/v1/foot/' . $coords, ['overview' => 'full', 'geometries' => 'geojson'], [
                    'headers' => ['User-Agent' => 'product-thing/1.0 (rental search; walking route)', 'Accept' => 'application/json'],
                ]);
        } catch (\Throwable $e) {
            return null;
        }
        $decoded = $response->getStatusCode() === 200 ? json_decode($response->getStringBody(), true) : null;
        $route = $decoded['routes'][0] ?? null;
        if (!is_array($route) || !isset($route['distance'], $route['geometry']['coordinates'])) {
            return null;
        }

        $result = ['distance' => (int)round((float)$route['distance']), 'line' => $route['geometry']['coordinates']];
        Cache::write($key, $result, 'geocode');

        return $result;
    }

    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($a));
    }
}
