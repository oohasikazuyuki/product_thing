<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class MlitProxyControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testTransactionsRequiresQueryParams(): void
    {
        $this->get('/api/mlit/transactions');
        $this->assertResponseCode(400);
    }

    public function testTransactionsOnlyAllowsGet(): void
    {
        $this->post('/api/mlit/transactions');
        $this->assertResponseCode(405);
    }
}
