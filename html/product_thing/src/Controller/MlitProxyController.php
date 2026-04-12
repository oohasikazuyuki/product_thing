<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Client;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;

class MlitProxyController extends AppController
{
    private const MLIT_PATH = '/ex-api/external/XIT001';

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

        $apiKey = (string)env('MLIT_API_KEY', (string)env('API_KEY', ''));
        if ($apiKey === '') {
            throw new InternalErrorException('MLIT API key is not configured.');
        }

        $query = $this->buildQueryParams();
        if ($query === []) {
            throw new BadRequestException('At least one query parameter is required.');
        }

        $client = new Client([
            'scheme' => 'https',
            'host' => 'www.reinfolib.mlit.go.jp',
            'timeout' => 15,
        ]);

        $apiResponse = $client->get(self::MLIT_PATH, $query, [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'Ocp-Apim-Subscription-Key' => $apiKey,
            ],
        ]);

        return $this->response
            ->withType('application/json')
            ->withStatus($apiResponse->getStatusCode())
            ->withStringBody($apiResponse->getStringBody());
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
}
