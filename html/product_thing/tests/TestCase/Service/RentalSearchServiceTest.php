<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\RentalSearchService;
use Cake\TestSuite\TestCase;

class RentalSearchServiceTest extends TestCase
{
    public function testDemoSearchFiltersByKeywordAndRent(): void
    {
        putenv('HOMES_API_BEARER=');
        putenv('RAPIDAPI_KEY=');

        $service = new RentalSearchService();
        $result = $service->search([
            'free_word' => '新宿',
            'month_money_room_h' => 20,
        ]);

        $this->assertSame('demo', $result['provider']);
        $this->assertTrue($result['demo']);
        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertStringContainsString('新宿', $item['name'] . $item['address'] . $item['station']);
            $this->assertLessThanOrEqual(200000, (int)$item['rent_yen']);
        }
    }

    public function testProviderStatusFallsBackToDemo(): void
    {
        putenv('HOMES_API_BEARER=');
        putenv('RAPIDAPI_KEY=');
        $service = new RentalSearchService();
        $status = $service->providerStatus();
        $this->assertSame('demo', $status['id']);
    }
}
