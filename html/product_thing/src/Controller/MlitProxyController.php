<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Client;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;

class MlitProxyController extends AppController
{
    private const TRANSACTIONS_PATH = '/ex-api/external/XIT001';
    private const TRANSACTION_POINTS_PATH = '/ex-api/external/XPT001';

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
        $apiResponse = $this->createApiClient()->get(self::TRANSACTIONS_PATH, $query, [
            'headers' => $this->buildHeaders($this->requireApiKey()),
        ]);

        return $this->jsonResponse($apiResponse->getStringBody(), $apiResponse->getStatusCode());
    }

    public function geojson()
    {
        $this->request->allowMethod(['get']);
        $query = $this->requireQueryParams();
        $apiKey = $this->requireApiKey();
        $client = $this->createApiClient();
        $headers = $this->buildHeaders($apiKey);

        $transactions = $client->get(self::TRANSACTIONS_PATH, $query, ['headers' => $headers]);
        $points = $client->get(self::TRANSACTION_POINTS_PATH, $query, ['headers' => $headers]);
        $transactionStatus = $transactions->getStatusCode();
        $pointStatus = $points->getStatusCode();
        if ($transactionStatus >= 400) {
            return $this->jsonResponse(
                json_encode([
                    'type' => 'FeatureCollection',
                    'features' => [],
                    'error' => 'Failed to fetch transaction source data. status=' . $transactionStatus,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                502
            );
        }

        $transactionData = $this->extractDataRows($transactions->getStringBody());
        $pointData = [];
        $warning = null;
        if ($pointStatus < 400) {
            $pointData = $this->extractDataRows($points->getStringBody());
        } else {
            $pointBody = $this->decodeJsonBody($points->getStringBody());
            $pointMessage = is_array($pointBody) && isset($pointBody['message']) ? (string)$pointBody['message'] : '';
            $warning = 'Point source is unavailable. status=' . $pointStatus . ($pointMessage !== '' ? (' message=' . $pointMessage) : '');
        }

        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => $this->buildFeatures($transactionData, $pointData),
            'transaction_count' => count($transactionData),
        ];
        if ($warning !== null) {
            $featureCollection['warning'] = $warning;
        }

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

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $apiKey): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'identity',
            'Ocp-Apim-Subscription-Key' => $apiKey,
        ];
    }

    private function createApiClient(): Client
    {
        return new Client([
            'scheme' => 'https',
            'host' => 'www.reinfolib.mlit.go.jp',
            'timeout' => 15,
        ]);
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

    /**
     * @param array<int, array<string, mixed>> $transactions
     * @param array<int, array<string, mixed>> $points
     * @return array<int, array<string, mixed>>
     */
    private function buildFeatures(array $transactions, array $points): array
    {
        $features = [];
        foreach ($transactions as $index => $row) {
            if (!isset($points[$index]) || !is_array($points[$index])) {
                continue;
            }

            $coordinates = $this->extractCoordinates($points[$index]);
            if ($coordinates === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $coordinates,
                ],
                'properties' => $row,
            ];
        }

        return $features;
    }

    /**
     * @return array<int, float>|null
     */
    private function extractCoordinates(array $row): ?array
    {
        $longitude = $this->extractCoordinateValue($row, ['Longitude', 'longitude', 'lng', 'Lon', 'LON', 'x']);
        $latitude = $this->extractCoordinateValue($row, ['Latitude', 'latitude', 'lat', 'Lat', 'LAT', 'y']);
        if ($longitude === null || $latitude === null) {
            return null;
        }

        return [$longitude, $latitude];
    }

    /**
     * @param array<int, string> $keys
     */
    private function extractCoordinateValue(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (!isset($row[$key]) || $row[$key] === '') {
                continue;
            }
            $value = (float)$row[$key];
            if (is_finite($value)) {
                return $value;
            }
        }

        return null;
    }
}
