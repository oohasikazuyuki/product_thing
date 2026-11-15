<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PriceSearchControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /** トップ(/)はテンプレート欠落で500になっていた（エラー全リダイレクトの陰に隠れていた） */
    public function testTopPageRendersSearchForm(): void
    {
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('物件の取引価格');
        $this->assertResponseContains('id="searchForm"');
    }
}
