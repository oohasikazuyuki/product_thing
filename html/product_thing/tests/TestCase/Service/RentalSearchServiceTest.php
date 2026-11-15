<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\RentalSearchService;
use Cake\TestSuite\TestCase;

class RentalSearchServiceTest extends TestCase
{
    /** @var array<string, array{server: ?string, env: ?string}> */
    private array $saved = [];

    /**
     * Cake の env() は $_SERVER/$_ENV を先に見るため、putenv だけでは .env の鍵を打ち消せない。
     * テストでは鍵を空にして必ずデモ経路を通す（ライブAPIを呼ばない）。
     */
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['RAPIDAPI_KEY', 'HOMES_API_BEARER'] as $key) {
            $this->saved[$key] = ['server' => $_SERVER[$key] ?? null, 'env' => $_ENV[$key] ?? null];
            $_SERVER[$key] = '';
            $_ENV[$key] = '';
            putenv($key . '=');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $old) {
            $old['server'] === null ? $this->unsetKey($_SERVER, $key) : $_SERVER[$key] = $old['server'];
            $old['env'] === null ? $this->unsetKey($_ENV, $key) : $_ENV[$key] = $old['env'];
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $array
     */
    private function unsetKey(array &$array, string $key): void
    {
        unset($array[$key]);
    }

    public function testDemoSearchFiltersByKeywordAndRent(): void
    {
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
        $service = new RentalSearchService();
        $status = $service->providerStatus();
        $this->assertSame('demo', $status['id']);
    }

    public function testLayoutFilterKeepsOnlySelectedLayouts(): void
    {
        $all = (new RentalSearchService())->search([]);
        $layouts = array_values(array_unique(array_column($all['items'], 'layout')));
        $this->assertGreaterThan(1, count($layouts), 'デモデータに複数の間取りが必要');

        $result = (new RentalSearchService())->search(['layouts' => $layouts[0] . ',' . 'bad layout!']);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertSame($layouts[0], $item['layout']);
        }
        $this->assertSame(count($result['items']), $result['total']);
    }

    public function testAreaMinGreaterThanMaxIsRejected(): void
    {
        $this->expectException(\Cake\Http\Exception\BadRequestException::class);
        (new RentalSearchService())->search(['area_min' => 50, 'area_max' => 20]);
    }
}
