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
        <?php
        // 数字を打たずに選べるよう、よく使う区切りをプルダウンにする
        $rangeSelects = [
            ['label' => '賃料', 'min' => 'month_money_room', 'max' => 'month_money_room_h', 'defMin' => '5', 'defMax' => '15',
                'steps' => [3, 4, 5, 6, 7, 8, 9, 10, 12, 15, 20, 25, 30, 40, 50], 'unit' => '万円'],
            ['label' => '広さ（面積）', 'min' => 'area_min', 'max' => 'area_max', 'defMin' => '', 'defMax' => '',
                'steps' => [15, 20, 25, 30, 40, 50, 60, 70, 80, 100], 'unit' => '㎡'],
        ];
        ?>
        <?php foreach ($rangeSelects as $range): ?>
            <div class="form-group col-12 col-md-3 mb-2">
                <span class="small d-block mb-1"><?= h($range['label']) ?></span>
                <div class="input-group input-group-sm">
                    <?php foreach (['min' => ['下限なし', $range['defMin'], 'の下限'], 'max' => ['上限なし', $range['defMax'], 'の上限']] as $edge => [$none, $default, $suffix]): ?>
                        <select id="<?= h($range[$edge]) ?>" name="<?= h($range[$edge]) ?>" class="form-control" aria-label="<?= h($range['label'] . $suffix) ?>">
                            <option value=""><?= h($none) ?></option>
                            <?php foreach ($range['steps'] as $step): ?>
                                <option value="<?= (int)$step ?>"<?= (string)$step === $default ? ' selected' : '' ?>><?= (int)$step . h($range['unit']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="form-group col-12 col-md-2 mb-2">
            <button type="submit" class="btn btn-success btn-sm btn-block" id="searchBtn">検索</button>
        </div>
    </div>
    <?php $layoutOptions = ['ワンルーム', '1K', '1DK', '1LDK', '2K', '2DK', '2LDK', '3K', '3DK', '3LDK']; ?>
    <fieldset class="mb-0">
        <legend class="small mb-1 col-form-label p-0">間取り（複数選択可・未選択ならすべて）</legend>
        <div class="chip-group" id="layoutChips">
            <?php foreach ($layoutOptions as $i => $layout): ?>
                <input type="checkbox" id="layout-<?= (int)$i ?>" name="layouts" value="<?= h($layout) ?>">
                <label for="layout-<?= (int)$i ?>"><?= h($layout) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>
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
        routeEndpoint: <?= json_encode($this->Url->build('/api/rentals/route'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        citiesEndpoint: <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'cities'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        defaultCity: '13113'
    };
</script>
<?= $this->Html->script('rental-map') ?>
<?php $this->end(); ?>
