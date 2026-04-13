<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>防災・規制調査</title>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
    <link href="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css" rel="stylesheet">
    <style>
        #map { height: 520px; border-radius: 8px; }
    </style>
</head>
<body>
<div class="container mt-4 mb-4">
    <h1 class="h4">防災・規制調査画面</h1>
    <p class="text-muted mb-2">取引価格に加えて、都市計画・用途地域・立地適正化計画を重ねて確認できます。</p>
    <p class="small text-muted">出典：不動産情報ライブラリ（国土交通省）</p>
    <div class="mb-3">
        <?= $this->Html->link('価格検索へ戻る', ['controller' => 'PriceSearch', 'action' => 'selectAPI'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        <?= $this->Html->link('学校調査へ', ['controller' => 'AreaAnalysis', 'action' => 'schoolSurvey', '?' => ['area' => $area, 'city' => $city, 'year' => $year, 'district' => $district]], ['class' => 'btn btn-outline-primary btn-sm']) ?>
    </div>
    <?= $this->element('analysis_filter', ['targetAction' => 'safetySurvey', 'area' => $area, 'city' => $city, 'year' => $year, 'district' => $district, 'areaOptions' => $areaOptions, 'cityOptions' => $cityOptions, 'yearOptions' => $yearOptions, 'districtOptions' => $districtOptions]) ?>
    <div id="map"></div>
    <div id="status" class="alert alert-secondary mt-3 mb-0">読み込み中...</div>
</div>

<script src="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const area = <?= json_encode($area, JSON_UNESCAPED_UNICODE) ?>;
    const city = <?= json_encode($city, JSON_UNESCAPED_UNICODE) ?>;
    const year = <?= json_encode($year, JSON_UNESCAPED_UNICODE) ?>;
    const district = <?= json_encode($district, JSON_UNESCAPED_UNICODE) ?>;
    const layerDataUrl = <?= json_encode($this->Url->build(['controller' => 'LayerData', 'action' => 'layerData'])) ?>;
    const mlitGeoJsonUrl = <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'geojson'])) ?>;
    const status = document.getElementById('status');

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

    const addFeatures = (features, color, label) => {
        let count = 0;
        features.forEach((f) => {
            const c = featureCoord(f);
            if (!c) return;
            const p = f.properties || {};
            new maplibregl.Marker({color}).setLngLat(c).setPopup(
                new maplibregl.Popup({offset: 24}).setHTML('<strong>' + label + '</strong><br>' + Object.keys(p).slice(0, 4).map((k) => k + ': ' + p[k]).join('<br>'))
            ).addTo(map);
            count += 1;
        });
        return count;
    };

    const fetchLayer = async (apiId, color, label) => {
        const center = map.getCenter();
        const z = 14;
        const params = new URLSearchParams({
            api_id: apiId,
            response_format: 'geojson',
            z: String(z),
            x: String(lonToTile(center.lng, z)),
            y: String(latToTile(center.lat, z))
        });
        const res = await fetch(layerDataUrl + '?' + params.toString());
        if (!res.ok) return 0;
        const payload = await res.json();
        const features = payload && payload.body && Array.isArray(payload.body.features) ? payload.body.features : [];
        return addFeatures(features, color, label);
    };

    map.on('load', async function () {
        try {
            const txRes = await fetch(mlitGeoJsonUrl + '?area=' + encodeURIComponent(area) + '&city=' + encodeURIComponent(city) + '&year=' + encodeURIComponent(year));
            const txGeo = await txRes.json();
            let txFeatures = Array.isArray(txGeo.features) ? txGeo.features : [];
            if (district) {
                txFeatures = txFeatures.filter((f) => {
                    const p = f && f.properties ? f.properties : {};
                    return (p.DistrictName || '') === district;
                });
            }
            const txCount = addFeatures(txFeatures, '#16a34a', '取引価格');

            const c1 = await fetchLayer('XKT001', '#4b5563', '都市計画区域');
            const c2 = await fetchLayer('XKT002', '#2563eb', '用途地域');
            const c3 = await fetchLayer('XKT003', '#0ea5e9', '立地適正化計画');

            status.className = 'alert alert-success mt-3 mb-0';
            status.textContent = '取引価格: ' + txCount + '件 / 都市計画: ' + c1 + '件 / 用途地域: ' + c2 + '件 / 立地適正化: ' + c3 + '件';
        } catch (e) {
            status.className = 'alert alert-danger mt-3 mb-0';
            status.textContent = 'データ取得に失敗しました。';
        }
    });
});
</script>
</body>
</html>
