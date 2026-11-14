<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\RentalSearchService;

class RentalSearchController extends AppController
{
    public function index()
    {
        $provider = (new RentalSearchService())->providerStatus();
        $prefectures = \App\Service\Prefectures::all();
        $this->set(compact('provider', 'prefectures'));
    }

    public function search()
    {
        $this->request->allowMethod(['get']);
        $result = (new RentalSearchService())->search($this->request->getQueryParams());

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
