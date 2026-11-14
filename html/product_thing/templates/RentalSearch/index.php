<?php
/**
 * @var \App\View\AppView $this
 * @var array{id: string, label: string, mode: string} $provider
 * @var array<string, string> $prefectures
 */
$isDemo = ($provider['mode'] ?? '') === 'demo';
$this->assign('title', '賃貸検索');
$this->assign('containerClass', 'container-fluid px-md-4');
?>
<div class="page-header">
    <h1>賃貸物件を地図で探す</h1>
    <p>エリアと賃料を指定すると、地図と一覧で賃貸物件を確認できます。</p>
    <div class="mt-2">
        <span id="providerChip" class="badge badge-<?= $isDemo ? 'warning' : 'success' ?> p-2">
            <?= $isDemo ? 'デモモード' : 'ライブ接続' ?> · <?= h($provider['label']) ?>
        </span>
        <?php if ($isDemo): ?>
            <small class="text-muted ml-2">実際の物件を取得するには、環境変数 <code>RAPIDAPI_KEY</code>（RapidAPI の SUUMO API）または <code>HOMES_API_BEARER</code> を設定してください。</small>
        <?php endif; ?>
    </div>
</div>

<form id="rentalSearchForm" class="card card-body mb-3" autocomplete="off">
    <div class="form-row align-items-end">
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="small mb-1" for="prefecture">都道府県</label>
            <select id="prefecture" name="prefecture" class="form-control form-control-sm">
                <?php foreach ($prefectures as $code => $name): ?>
                    <option value="<?= h($code) ?>"<?= (string)$code === '13' ? ' selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="small mb-1" for="city">市区町村</label>
            <select id="city" name="city" class="form-control form-control-sm">
                <option value="">読み込み中…</option>
            </select>
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="small mb-1" for="month_money_room">最低賃料（万円）</label>
            <input type="number" step="0.1" min="0" id="month_money_room" name="month_money_room" value="5" class="form-control form-control-sm">
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="small mb-1" for="month_money_room_h">最高賃料（万円）</label>
            <input type="number" step="0.1" min="0" id="month_money_room_h" name="month_money_room_h" value="15" class="form-control form-control-sm">
        </div>
        <div class="form-group col-8 col-md-3 mb-2">
            <label class="small mb-1" for="free_word">キーワード</label>
            <input type="search" id="free_word" name="free_word" class="form-control form-control-sm" placeholder="例：1K、駅名">
        </div>
        <div class="form-group col-4 col-md-1 mb-2">
            <button type="submit" class="btn btn-success btn-sm btn-block" id="searchBtn">検索</button>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="d-flex flex-wrap align-items-center mb-2 map-toolbar">
            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="地図の種類">
                <button type="button" class="btn btn-outline-secondary active" data-basemap="map">地図</button>
                <button type="button" class="btn btn-outline-secondary" data-basemap="aerial">航空写真</button>
            </div>
            <span id="searchStatus" class="small text-muted" role="status" aria-live="polite">条件を選んで「検索」を押してください。</span>
        </div>
        <div id="rentalMap" class="map-canvas" role="img" aria-label="賃貸物件の地図"></div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h2 class="h6 mb-0">物件一覧</h2>
            <span id="resultCount" class="small text-muted" role="status">—</span>
        </div>
        <div id="rentalResults" class="listing-scroll">
            <div class="listing-empty">条件を指定して検索してください。</div>
        </div>
        <nav id="rentalPager" class="mt-2" aria-label="ページ送り"></nav>
    </div>
</div>

<?php $this->start('css'); ?>
<link href="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css" rel="stylesheet">
<?php $this->end(); ?>
<?php $this->start('script'); ?>
<script src="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js"></script>
<script>
    window.RENTAL_MAP_CONFIG = {
        searchEndpoint: <?= json_encode($this->Url->build(['controller' => 'RentalSearch', 'action' => 'search'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        citiesEndpoint: <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'cities'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        defaultCity: '13113'
    };
</script>
<?= $this->Html->script('rental-map') ?>
<?php $this->end(); ?>
