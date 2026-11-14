<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Http\Client;

/**
 * 不動産情報ライブラリ(MLIT)APIの取得。成功(HTTP 200)した応答は1時間キャッシュする。
 */
class MlitApi
{
    private const BASE_PATH = '/ex-api/external/';

    /**
     * @param string $apiId 例: XIT001
     * @param array<string, mixed> $query
     * @return array{status: int, body: string} 通信失敗時は status=0
     */
    public static function get(string $apiId, array $query): array
    {
        $key = 'mlit_' . md5($apiId . '?' . http_build_query($query));
        $cached = Cache::read($key);
        if (is_array($cached)) {
            return $cached;
        }

        $apiKey = (string)env('MLIT_API_KEY', (string)env('API_KEY', ''));
        try {
            $response = (new Client(['scheme' => 'https', 'host' => 'www.reinfolib.mlit.go.jp', 'timeout' => 15]))
                ->get(self::BASE_PATH . $apiId, $query, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Accept-Encoding' => 'identity',
                        'Ocp-Apim-Subscription-Key' => $apiKey,
                    ],
                ]);
        } catch (\Throwable $e) {
            return ['status' => 0, 'body' => ''];
        }

        $result = ['status' => $response->getStatusCode(), 'body' => $response->getStringBody()];
        if ($result['status'] === 200 && $result['body'] !== '') {
            Cache::write($key, $result);
        }

        return $result;
    }
}
