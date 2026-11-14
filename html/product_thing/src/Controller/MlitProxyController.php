<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Geocoder;
use App\Service\MlitApi;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;

class MlitProxyController extends AppController
{
    private const ALLOWED_QUERY_PARAMS = [
        'year',
        'quarter',
        'city',
        'area',
        'priceClassification',
        'language',
    ];

    public function transactions()
    {
        $this->request->allowMethod(['get']);
        $query = $this->requireQueryParams();
        $this->requireApiKey();
        $apiResponse = MlitApi::get('XIT001', $query);

        return $this->jsonResponse($apiResponse['body'], $apiResponse['status'] ?: 502);
    }

    public function geojson()
    {
        $this->request->allowMethod(['get']);
        $query = $this->requireQueryParams();
        $this->requireApiKey();

        $transactions = MlitApi::get('XIT001', $query);
        $transactionStatus = $transactions['status'];
        if ($transactionStatus === 0 || $transactionStatus >= 400) {
            return $this->jsonResponse(
                json_encode([
                    'type' => 'FeatureCollection',
                    'features' => [],
                    'error' => 'Failed to fetch transaction source data. status=' . $transactionStatus,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                502
            );
        }

        // 取引APIには座標が無い(XPT001は地図タイル指定専用)ため、地区名から住所検索で座標を付ける
        $transactionData = Geocoder::attach($this->extractDataRows($transactions['body']));
        $features = [];
        foreach ($transactionData as $row) {
            if (!isset($row['Longitude'], $row['Latitude'])) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['Longitude'], (float)$row['Latitude']]],
                'properties' => $row,
            ];
        }
        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'transaction_count' => count($transactionData),
        ];

        return $this->jsonResponse(json_encode($featureCollection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, string>
     */
    private function buildQueryParams(): array
    {
        $query = [];
        foreach (self::ALLOWED_QUERY_PARAMS as $param) {
            $value = $this->request->getQuery($param);
            if ($value === null || $value === '') {
                continue;
            }
            $query[$param] = (string)$value;
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function requireQueryParams(): array
    {
        $query = $this->buildQueryParams();
        if ($query === []) {
            throw new BadRequestException('At least one query parameter is required.');
        }

        return $query;
    }

    private function requireApiKey(): string
    {
        $apiKey = (string)env('MLIT_API_KEY', (string)env('API_KEY', ''));
        if ($apiKey === '') {
            throw new InternalErrorException('MLIT API key is not configured.');
        }

        return $apiKey;
    }

    private function jsonResponse(string $body, int $status = 200)
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody($body);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractDataRows(string $responseBody): array
    {
        $decoded = $this->decodeJsonBody($responseBody);
        if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
            return [];
        }

        return array_values(array_filter($decoded['data'], 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonBody(string $body): ?array
    {
        $decodedBody = $body;
        if (substr($body, 0, 2) === "\x1f\x8b") {
            $inflated = gzdecode($body);
            if ($inflated !== false) {
                $decodedBody = $inflated;
            }
        }

        $json = json_decode($decodedBody, true);
        return is_array($json) ? $json : null;
    }
}
