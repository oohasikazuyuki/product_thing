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
            $warning = '取引ポイントAPIを利用できないため、住所ジオコーディングで補完表示しています。status=' . $pointStatus . ($pointMessage !== '' ? (' message=' . $pointMessage) : '');
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
        $geocodeCache = [];
        $geocodeCount = 0;
        $maxGeocodeRequests = 120;
        foreach ($transactions as $index => $row) {
            $coordinates = null;
            if (isset($points[$index]) && is_array($points[$index])) {
                $coordinates = $this->extractCoordinates($points[$index]);
            }

            if ($coordinates === null) {
                $address = $this->buildAddressForGeocoding($row);
                if ($address !== null) {
                    if (!array_key_exists($address, $geocodeCache) && $geocodeCount < $maxGeocodeRequests) {
                        $geocodeCache[$address] = $this->geocodeAddress($address);
                        $geocodeCount += 1;
                    }
                    if (array_key_exists($address, $geocodeCache) && is_array($geocodeCache[$address])) {
                        $coordinates = $geocodeCache[$address];
                    }
                }
            }

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
     * @param array<string, mixed> $row
     */
    private function buildAddressForGeocoding(array $row): ?string
    {
        $prefecture = isset($row['Prefecture']) ? trim((string)$row['Prefecture']) : '';
        $municipality = isset($row['Municipality']) ? trim((string)$row['Municipality']) : '';
        $district = isset($row['DistrictName']) ? trim((string)$row['DistrictName']) : '';
        $address = trim($prefecture . $municipality . $district);
        if ($address === '') {
            return null;
        }

        return $address;
    }

    /**
     * @return array<int, float>|null
     */
    private function geocodeAddress(string $address): ?array
    {
        $client = new Client(['timeout' => 8]);
        $response = $client->get('https://msearch.gsi.go.jp/address-search/AddressSearch', [
            'q' => $address,
        ], [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $decoded = json_decode($response->getStringBody(), true);
        if (!is_array($decoded) || !isset($decoded[0]['geometry']['coordinates']) || !is_array($decoded[0]['geometry']['coordinates'])) {
            return null;
        }

        $coordinates = $decoded[0]['geometry']['coordinates'];
        if (count($coordinates) < 2) {
            return null;
        }

        $longitude = (float)$coordinates[0];
        $latitude = (float)$coordinates[1];
        if (!is_finite($longitude) || !is_finite($latitude)) {
            return null;
        }

        return [$longitude, $latitude];
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
