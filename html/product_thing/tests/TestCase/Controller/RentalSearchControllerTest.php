<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Cache\Cache;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RentalSearchControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testIndexShowsFormWithAllPrefecturesAndLayoutChips(): void
    {
        $this->get('/rentals');
        $this->assertResponseOk();
        $this->assertResponseContains('id="layoutChips"');
        $this->assertResponseContains('name="area_min"');
        $this->assertResponseContains('北海道');
        $this->assertResponseContains('沖縄県');
    }

    public function testSearchRejectsAreaMinGreaterThanMax(): void
    {
        $this->get('/api/rentals/search?prefecture=13&area_min=50&area_max=20');
        $this->assertResponseCode(400);
    }

    public function testRouteRequiresStationAndPosition(): void
    {
        $this->get('/api/rentals/route');
        $this->assertResponseCode(400);
        $this->get('/api/rentals/route?station=渋谷&lat=0&lng=0');
        $this->assertResponseCode(400);
    }

    public function testUnknownUrlRendersJapanese404NotRedirect(): void
    {
        $this->get('/no-such-page');
        $this->assertResponseCode(404);
    }

    /** 無料枠が尽きたら、赤いバッジで残り0回と分かるようにする（ライブ鍵がある環境のみ意味を持つ） */
    public function testQuotaBadgeShowsRemainingWhenRecorded(): void
    {
        $old = Cache::read('rapidapi_quota', 'geocode');
        Cache::write('rapidapi_quota', ['remaining' => 0, 'limit' => 300, 'reset_at' => null], 'geocode');
        try {
            $this->get('/rentals');
            $this->assertResponseOk();
            if (env('RAPIDAPI_KEY')) {
                $this->assertResponseContains('今月の無料枠 残り 0 / 300 回');
                $this->assertResponseContains('badge-danger');
            }
        } finally {
            $old ? Cache::write('rapidapi_quota', $old, 'geocode') : Cache::delete('rapidapi_quota', 'geocode');
        }
    }
}
