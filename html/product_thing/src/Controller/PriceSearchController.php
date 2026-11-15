<?php
declare(strict_types=1);

namespace App\Controller;

class PriceSearchController extends APIController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setTemplatePath('API');
    }

    public function index()
    {
        // トップは取引価格検索の入口。index用のテンプレートは無いので select_a_p_i を描画する
        return $this->selectAPI() ?? $this->render('select_a_p_i');
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
