<?php
declare(strict_types=1);

namespace App\Controller;

class ApiExplorerController extends APIController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setTemplatePath('API');
    }

    public function apiExplorer()
    {
        return parent::apiExplorer();
    }
}
