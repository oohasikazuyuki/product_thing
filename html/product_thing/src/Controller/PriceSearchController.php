<?php
declare(strict_types=1);

namespace App\Controller;

class PriceSearchController extends APIController
{
    public function index()
    {
        return $this->selectAPI();
    }

    public function selectAPI($prefectureCode = null, $cityID = null, $year = null)
    {
        return parent::selectAPI($prefectureCode, $cityID, $year);
    }

    public function displayPrice($prefectureCode = null, $cityID = null, $year = null)
    {
        return parent::displayPrice($prefectureCode, $cityID, $year);
    }
}
