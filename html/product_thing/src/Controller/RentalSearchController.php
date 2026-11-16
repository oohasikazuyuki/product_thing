<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\RentalSearchService;
use App\Service\WalkingRoute;

class RentalSearchController extends AppController
{
    /** 1分あたりの上限（クライアントIPごと）。RapidAPI無料枠（月300回）を守るため */
    public const RATE_LIMITS = ['search' => 20, 'route' => 60];

    public function index()
    {
        $provider = (new RentalSearchService())->providerStatus();
        $prefectures = \App\Service\Prefectures::all();
        $googleMapsApiKey = env('GOOGLE_MAPS_API_KEY') ?: null;
        $quota = \Cake\Cache\Cache::read('rapidapi_quota', 'geocode') ?: null; // 直近の検索で記録した無料枠の残り
        $this->set(compact('provider', 'prefectures', 'googleMapsApiKey', 'quota'));
    }

    public function search()
    {
        $this->request->allowMethod(['get']);
        if ($limited = $this->rateLimited('search')) {
            return $limited;
        }
        $result = (new RentalSearchService())->search($this->request->getQueryParams());

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 最寄り駅から物件までの徒歩ルート（地図に線を描くための座標列）。
     */
    public function route()
    {
        $this->request->allowMethod(['get']);
        if ($limited = $this->rateLimited('route')) {
            return $limited;
        }
        $station = trim((string)$this->request->getQuery('station', ''));
        $lat = (float)$this->request->getQuery('lat', 0);
        $lng = (float)$this->request->getQuery('lng', 0);
        $inJapan = $lat >= 20 && $lat <= 46 && $lng >= 122 && $lng <= 154;
        if ($station === '' || mb_strlen($station) > 40 || !$inJapan) {
            $result = ['ok' => false, 'message' => '駅名と物件の位置が必要です。'];
            $this->response = $this->response->withStatus(400);
        } else {
            $result = WalkingRoute::between($station, $lat, $lng);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 固定窓（1分）でIPごとに数える。超過なら429のレスポンスを返し、範囲内ならnull。
     * ponytail: read/writeなので同時アクセスで多少ずれる。厳密にするならAPCu/Redisのincrement。
     */
    private function rateLimited(string $name): ?\Cake\Http\Response
    {
        $key = 'ratelimit_' . $name . '_' . md5($this->request->clientIp());
        $now = time();
        $state = \Cake\Cache\Cache::read($key, 'geocode');
        if (!is_array($state) || $now - $state['start'] >= 60) {
            $state = ['start' => $now, 'count' => 0];
        }
        $state['count']++;
        \Cake\Cache\Cache::write($key, $state, 'geocode');
        if ($state['count'] <= self::RATE_LIMITS[$name]) {
            return null;
        }

        return $this->response
            ->withStatus(429)
            ->withType('application/json')
            ->withStringBody(json_encode(
                ['message' => 'アクセスが多すぎます。しばらくしてから再度お試しください。'],
                JSON_UNESCAPED_UNICODE
            ));
    }
}
