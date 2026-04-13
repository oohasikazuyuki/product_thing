<?php
declare(strict_types=1);

namespace App\Controller;

class AreaAnalysisController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
    }

    public function safetySurvey()
    {
        $this->set('area', (string)$this->request->getQuery('area', '13'));
        $this->set('city', (string)$this->request->getQuery('city', '13101'));
        $this->set('year', (string)$this->request->getQuery('year', '2024'));
    }

    public function schoolSurvey()
    {
        $this->set('area', (string)$this->request->getQuery('area', '13'));
        $this->set('city', (string)$this->request->getQuery('city', '13101'));
        $this->set('year', (string)$this->request->getQuery('year', '2024'));
    }
}
