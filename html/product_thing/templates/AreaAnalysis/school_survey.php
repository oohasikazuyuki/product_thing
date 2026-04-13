<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学校・生活調査</title>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
    <link href="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css" rel="stylesheet">
    <style>
        #map { height: 520px; border-radius: 8px; }
        #surveyList { max-height: 260px; overflow: auto; }
    </style>
</head>
<body>
<div class="container mt-4 mb-4">
    <h1 class="h4">学校・生活調査画面</h1>
    <p class="text-muted mb-2">取引価格に加えて、学校区・学校・保育園・医療/福祉施設を重ねて確認できます。</p>
    <p class="small text-muted">出典：不動産情報ライブラリ（国土交通省）</p>
    <div class="mb-3">
        <?= $this->Html->link('価格検索へ戻る', ['controller' => 'PriceSearch', 'action' => 'selectAPI'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        <?= $this->Html->link('防災調査へ', ['controller' => 'AreaAnalysis', 'action' => 'safetySurvey', '?' => ['area' => $area, 'city' => $city, 'year' => $year]], ['class' => 'btn btn-outline-primary btn-sm']) ?>
    </div>
    <?= $this->element('analysis_filter', ['targetAction' => 'schoolSurvey', 'area' => $area, 'city' => $city, 'year' => $year]) ?>
    <div class="row">
        <div class="col-lg-8">
            <div id="map"></div>
            <div id="status" class="alert alert-secondary mt-3 mb-0">読み込み中...</div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">表示レイヤー</h2>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" data-layer="xkt004" checked><label class="form-check-label">小学校区</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" data-layer="xkt005" checked><label class="form-check-label">中学校区</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" data-layer="xkt006" checked><label class="form-check-label">学校</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" data-layer="xkt007" checked><label class="form-check-label">保育園・幼稚園</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" data-layer="xkt010" checked><label class="form-check-label">医療機関</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" data-layer="xkt011" checked><label class="form-check-label">福祉施設</label></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">調査サマリー</h2>
                    <table class="table table-sm mb-0">
                        <tbody id="surveySummary"></tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">調査対象（抜粋）</h2>
                    <ul id="surveyList" class="small mb-0 pl-3"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const area = <?= json_encode($area, JSON_UNESCAPED_UNICODE) ?>;
    const city = <?= json_encode($city, JSON_UNESCAPED_UNICODE) ?>;
    const year = <?= json_encode($year, JSON_UNESCAPED_UNICODE) ?>;
    const layerDataUrl = <?= json_encode($this->Url->build(['controller' => 'LayerData', 'action' => 'layerData'])) ?>;
    const mlitGeoJsonUrl = <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'geojson'])) ?>;
    const status = document.getElementById('status');
    const summaryEl = document.getElementById('surveySummary');
    const surveyListEl = document.getElementById('surveyList');
    const markerRegistry = {
        xit001: [],
        xkt004: [],
        xkt005: [],
        xkt006: [],
        xkt007: [],
        xkt010: [],
        xkt011: []
    };
    const layerDefs = [
        {key: 'xkt004', apiId: 'XKT004', color: '#2563eb', label: '小学校区'},
        {key: 'xkt005', apiId: 'XKT005', color: '#0ea5e9', label: '中学校区'},
        {key: 'xkt006', apiId: 'XKT006', color: '#14b8a6', label: '学校'},
        {key: 'xkt007', apiId: 'XKT007', color: '#10b981', label: '保育園・幼稚園'},
        {key: 'xkt010', apiId: 'XKT010', color: '#ef4444', label: '医療機関'},
        {key: 'xkt011', apiId: 'XKT011', color: '#ec4899', label: '福祉施設'}
    ];

    const map = new maplibregl.Map({
        container: 'map',
        style: {
            version: 8,
            sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256 } },
            layers: [{ id: 'osm', type: 'raster', source: 'osm' }]
        },
        center: [139.7671, 35.6812],
        zoom: 11
    });
    map.addControl(new maplibregl.NavigationControl(), 'top-right');

    const lonToTile = (lon, z) => Math.floor((lon + 180) / 360 * Math.pow(2, z));
    const latToTile = (lat, z) => Math.floor((1 - Math.log(Math.tan(lat * Math.PI / 180) + 1 / Math.cos(lat * Math.PI / 180)) / Math.PI) / 2 * Math.pow(2, z));

    const featureCoord = function (f) {
        if (!f || !f.geometry || !Array.isArray(f.geometry.coordinates)) return null;
        const g = f.geometry;
        if (g.type === 'Point') return [g.coordinates[0], g.coordinates[1]];
        if (g.type === 'Polygon' && g.coordinates[0] && g.coordinates[0][0]) return g.coordinates[0][0];
        if (g.type === 'MultiPolygon' && g.coordinates[0] && g.coordinates[0][0] && g.coordinates[0][0][0]) return g.coordinates[0][0][0];
        return null;
    };

    const addFeatures = (features, color, label, layerKey) => {
        let count = 0;
        const samples = [];
        features.forEach((f) => {
            const c = featureCoord(f);
            if (!c) return;
            const p = f.properties || {};
            const marker = new maplibregl.Marker({color}).setLngLat(c).setPopup(
                new maplibregl.Popup({offset: 24}).setHTML('<strong>' + label + '</strong><br>' + Object.keys(p).slice(0, 4).map((k) => k + ': ' + p[k]).join('<br>'))
            ).addTo(map);
            markerRegistry[layerKey].push(marker);
            count += 1;
            if (samples.length < 5) {
                const value = Object.keys(p).slice(0, 2).map((k) => p[k]).join(' / ');
                samples.push((value || '名称不明') + ' (' + label + ')');
            }
        });
        return {count, samples};
    };

    const fetchLayer = async (def) => {
        const center = map.getCenter();
        const z = 14;
        const params = new URLSearchParams({
            api_id: def.apiId,
            response_format: 'geojson',
            z: String(z),
            x: String(lonToTile(center.lng, z)),
            y: String(latToTile(center.lat, z))
        });
        const res = await fetch(layerDataUrl + '?' + params.toString());
        if (!res.ok) return {count: 0, samples: []};
        const payload = await res.json();
        const features = payload && payload.body && Array.isArray(payload.body.features) ? payload.body.features : [];
        return addFeatures(features, def.color, def.label, def.key);
    };

    const toggleLayer = (layerKey, visible) => {
        markerRegistry[layerKey].forEach((marker) => {
            marker.getElement().style.display = visible ? '' : 'none';
        });
    };

    const renderSummary = (summaryRows) => {
        summaryEl.innerHTML = summaryRows.map((r) => '<tr><th>' + r.label + '</th><td class="text-right">' + r.count + '件</td></tr>').join('');
    };

    const renderList = (items) => {
        surveyListEl.innerHTML = items.length ? items.map((item) => '<li>' + item + '</li>').join('') : '<li>対象データなし</li>';
    };

    map.on('load', async function () {
        try {
            const txRes = await fetch(mlitGeoJsonUrl + '?area=' + encodeURIComponent(area) + '&city=' + encodeURIComponent(city) + '&year=' + encodeURIComponent(year));
            const txGeo = await txRes.json();
            const txFeatures = Array.isArray(txGeo.features) ? txGeo.features : [];
            const tx = addFeatures(txFeatures, '#16a34a', '取引価格', 'xit001');

            const layerResults = [];
            for (const def of layerDefs) {
                const result = await fetchLayer(def);
                layerResults.push({label: def.label, key: def.key, count: result.count, samples: result.samples});
            }

            renderSummary([{label: '取引価格', count: tx.count}].concat(layerResults.map((r) => ({label: r.label, count: r.count}))));
            renderList(layerResults.flatMap((r) => r.samples).slice(0, 12));

            document.querySelectorAll('.layer-toggle').forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    toggleLayer(checkbox.dataset.layer, checkbox.checked);
                });
            });

            status.className = 'alert alert-success mt-3 mb-0';
            status.textContent = '調査データの読み込みが完了しました。右側パネルで件数確認・レイヤー切替ができます。';
        } catch (e) {
            status.className = 'alert alert-danger mt-3 mb-0';
            status.textContent = 'データ取得に失敗しました。';
        }
    });
});
</script>
</body>
</html>
