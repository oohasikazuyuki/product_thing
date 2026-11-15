<?php $this->assign('title', '学校・生活調査'); ?>
<?php $this->start('css'); ?>
<link href="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css" rel="stylesheet">
<style>
        #surveyList { max-height: 260px; overflow: auto; }
</style>
<?php $this->end(); ?>
    <div class="page-header">
        <h1>学校・生活調査</h1>
        <p>取引価格に加えて、学校区・学校・保育園・医療/福祉施設を重ねて確認できます。 <span class="small">出典：不動産情報ライブラリ（国土交通省）</span></p>
    </div>
    <div class="mb-3">
        <?= $this->Html->link('取引価格の結果へ', ['controller' => 'PriceSearch', 'action' => 'displayPrice', '?' => ['area' => $area, 'city' => $city, 'year' => $year]], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        <?= $this->Html->link('防災調査へ', ['controller' => 'AreaAnalysis', 'action' => 'safetySurvey', '?' => ['area' => $area, 'city' => $city, 'year' => $year, 'district' => $district]], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>
    <?= $this->element('analysis_filter', ['targetAction' => 'schoolSurvey', 'area' => $area, 'city' => $city, 'year' => $year, 'district' => $district, 'areaOptions' => $areaOptions, 'cityOptions' => $cityOptions, 'yearOptions' => $yearOptions, 'districtOptions' => $districtOptions]) ?>
    <div class="row">
        <div class="col-lg-8">
            <div id="map" class="map-canvas"></div>
            <div id="status" class="alert alert-secondary mt-3 mb-0" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>データを読み込み中です（十数秒かかることがあります）…</div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">表示レイヤー</h2>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" id="layer-xkt004" data-layer="xkt004" checked><label class="form-check-label" for="layer-xkt004">小学校区</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" id="layer-xkt005" data-layer="xkt005" checked><label class="form-check-label" for="layer-xkt005">中学校区</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" id="layer-xkt006" data-layer="xkt006" checked><label class="form-check-label" for="layer-xkt006">学校</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" id="layer-xkt007" data-layer="xkt007" checked><label class="form-check-label" for="layer-xkt007">保育園・幼稚園</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" id="layer-xkt010" data-layer="xkt010" checked><label class="form-check-label" for="layer-xkt010">医療機関</label></div>
                    <div class="form-check"><input class="form-check-input layer-toggle" type="checkbox" id="layer-xkt011" data-layer="xkt011" checked><label class="form-check-label" for="layer-xkt011">福祉施設</label></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">調査サマリー</h2>
                    <table class="table table-sm mb-0">
                        <tbody id="surveySummary"><tr><td class="text-muted">読み込み中…</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">調査対象（抜粋）</h2>
                    <ul id="surveyList" class="small mb-0 pl-3"><li class="text-muted">読み込み中…</li></ul>
                </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="svModal" tabindex="-1" role="dialog" aria-labelledby="svTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="svTitle">ストリートビュー</h2>
                <a id="svExternal" class="ml-auto mr-3 small" href="#" target="_blank" rel="noopener">新しいタブで開く</a>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="svFrame" title="ストリートビュー" style="width:100%;height:70vh;border:0" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</div>
<?php $this->start('script'); ?>
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

    // 内部ID（英数字）ではなく、日本語の名称・属性だけを表示に使う
    const jpValues = (p) => Object.keys(p).map((k) => p[k]).filter((v) => typeof v === 'string' && /[\u3040-\u30ff\u3400-\u9fff]/.test(v));
    const escapeHtml = (v) => String(v).replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    // レイヤーごとの項目（名称・種別・住所・診療科）。URLはデータに無いため、名称＋住所から検索リンクを作る
    const fieldMap = {
        xkt004: {name: 'A27_004_ja', kind: 'A27_002', addr: 'A27_005'},
        xkt005: {name: 'A32_004_ja', kind: 'A32_002', addr: 'A32_005'},
        xkt006: {name: 'P29_004_ja', kind: 'P29_003_name_ja', addr: 'P29_005_ja'},
        xkt007: {name: 'preSchoolName_ja', addr: 'location_ja'},
        xkt010: {name: 'P04_002_ja', kind: 'P04_001_name_ja', addr: 'P04_003_ja', subject: 'medical_subject_ja'},
        xkt011: {name: 'P14_008_ja', kind: 'P14_005_name_ja', addr: 'P14_004_ja'}
    };
    const selectedText = (name) => {
        const el = document.querySelector('select[name="' + name + '"]');
        return el && el.selectedOptions[0] ? el.selectedOptions[0].textContent.trim() : '';
    };
    const areaPrefix = () => selectedText('area') + selectedText('city'); // 住所に市区町村が無いデータの検索精度を上げる
    const describe = (p, layerKey) => {
        const m = fieldMap[layerKey];
        if (m) {
            const clean = (v) => (typeof v === 'string' ? v.replace(/[　\s]+/g, ' ').trim() : '');
            return {name: clean(p[m.name]), kind: m.kind ? clean(p[m.kind]) : '', addr: clean(p[m.addr]), subject: m.subject ? clean(p[m.subject]) : ''};
        }
        const jp = jpValues(p); // 取引価格など：日本語の値だけを並べる
        return {name: jp[0] || '', kind: '', addr: jp.slice(1, 4).join(' / '), subject: ''};
    };
    const popupHtml = (p, label, layerKey, c) => {
        const d = describe(p, layerKey);
        const q = encodeURIComponent((areaPrefix() + ' ' + (d.name || '') + ' ' + (layerKey === 'xit001' ? '' : d.addr)).trim());
        const rows = ['<strong>' + escapeHtml(d.name || label) + '</strong> <span class="text-muted">(' + escapeHtml(label) + (d.kind ? ' / ' + escapeHtml(d.kind) : '') + ')</span>'];
        if (d.addr) rows.push(escapeHtml(d.addr));
        if (d.subject) rows.push('<span class="text-muted">診療科: ' + escapeHtml(d.subject.slice(0, 60)) + (d.subject.length > 60 ? '…' : '') + '</span>');
        const links = [
            '<a href="https://www.google.com/maps/search/?api=1&query=' + q + '" target="_blank" rel="noopener">Googleマップ</a>',
            d.name ? '<a href="https://www.google.com/search?q=' + encodeURIComponent((areaPrefix() + ' ' + d.name + ' 公式サイト').trim()) + '" target="_blank" rel="noopener">公式サイト・口コミを検索</a>' : '',
            '<a href="#" data-sv="' + c[1] + ',' + c[0] + '">ストリートビュー</a>'
        ].filter(Boolean);
        rows.push('<div class="mt-1 small">' + links.join(' ・ ') + '</div>');
        return '<div style="max-width:240px">' + rows.join('<br>') + '</div>';
    };

    const addFeatures = (features, color, label, layerKey) => {
        let count = 0;
        const samples = [];
        features.forEach((f) => {
            const c = featureCoord(f);
            if (!c) return;
            const p = f.properties || {};
            const marker = new maplibregl.Marker({color}).setLngLat(c).setPopup(
                new maplibregl.Popup({offset: 24, maxWidth: '260px'}).setHTML(popupHtml(p, label, layerKey, c))
            ).addTo(map);
            markerRegistry[layerKey].push(marker);
            count += 1;
            const name = describe(p, layerKey).name;
            if (samples.length < 5 && name) {
                samples.push({text: escapeHtml(name) + ' <span class="text-muted">(' + escapeHtml(label) + ')</span>', marker});
            }
        });
        return {count, samples};
    };

    const fitMapToFeatures = (features) => {
        const bounds = new maplibregl.LngLatBounds();
        let hasPoint = false;
        features.forEach((f) => {
            const c = featureCoord(f);
            if (!c) return;
            bounds.extend(c);
            hasPoint = true;
        });
        if (hasPoint) {
            map.fitBounds(bounds, {padding: 40, maxZoom: 14});
        }
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
        if (!res.ok) {
            return {count: 0, samples: [], error: 'レイヤーAPIの取得に失敗しました: ' + def.apiId};
        }
        const payload = await res.json();
        if (payload && payload.success === false) {
            return {count: 0, samples: [], error: (payload.message || ('レイヤーAPIエラー: ' + def.apiId))};
        }
        const features = payload && payload.body && Array.isArray(payload.body.features) ? payload.body.features : [];
        const result = addFeatures(features, def.color, def.label, def.key);
        return {count: result.count, samples: result.samples, error: null};
    };

    const toggleLayer = (layerKey, visible) => {
        markerRegistry[layerKey].forEach((marker) => {
            marker.getElement().style.display = visible ? '' : 'none';
        });
    };

    const renderSummary = (summaryRows) => {
        summaryEl.innerHTML = summaryRows.map((r) => '<tr><th>' + r.label + '</th><td class="text-right">' + r.count + '件</td></tr>').join('');
    };

    // 一覧の項目を押すと、地図がその地点へ移動してポップアップが開く
    const renderList = (items) => {
        surveyListEl.innerHTML = items.length ? items.map((item, i) => '<li><a href="#" data-sample="' + i + '">' + item.text + '</a></li>').join('') : '<li>対象データなし</li>';
        surveyListEl.querySelectorAll('[data-sample]').forEach((a) => {
            a.addEventListener('click', (event) => {
                event.preventDefault();
                const marker = items[Number(a.dataset.sample)].marker;
                map.flyTo({center: marker.getLngLat(), zoom: Math.max(map.getZoom(), 16), essential: true});
                if (!marker.getPopup().isOpen()) marker.togglePopup();
            });
        });
    };

    // ポップアップ内の「ストリートビュー」：モーダルで表示（キー不要の埋め込み）
    document.addEventListener('click', (event) => {
        const sv = event.target.closest('[data-sv]');
        if (!sv) return;
        event.preventDefault();
        const loc = sv.dataset.sv;
        document.getElementById('svFrame').src = 'https://maps.google.com/maps?layer=c&cbll=' + loc + '&cbp=11,0,0,0,0&output=svembed';
        document.getElementById('svExternal').href = 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' + loc;
        $('#svModal').modal('show');
    });
    $('#svModal').on('hidden.bs.modal', () => { document.getElementById('svFrame').src = 'about:blank'; });

    map.on('load', async function () {
        try {
            const txRes = await fetch(mlitGeoJsonUrl + '?area=' + encodeURIComponent(area) + '&city=' + encodeURIComponent(city) + '&year=' + encodeURIComponent(year));
            if (!txRes.ok) {
                throw new Error('取引データAPIの取得に失敗しました。');
            }
            const txGeo = await txRes.json();
            if (txGeo && txGeo.error) {
                throw new Error(txGeo.error);
            }
            const txWarning = txGeo && txGeo.warning ? txGeo.warning : null;
            let txFeatures = Array.isArray(txGeo.features) ? txGeo.features : [];
            const txTotalCount = Number(txGeo && txGeo.transaction_count ? txGeo.transaction_count : txFeatures.length);
            if (district) {
                txFeatures = txFeatures.filter((f) => {
                    const p = f && f.properties ? f.properties : {};
                    return (p.DistrictName || '') === district;
                });
            }
            const tx = addFeatures(txFeatures, '#16a34a', '取引価格', 'xit001');
            fitMapToFeatures(txFeatures);

            const layerResults = [];
            const layerErrors = [];
            const fetched = await Promise.all(layerDefs.map((def) => fetchLayer(def).catch(() => ({count: 0, samples: [], error: 'レイヤーAPIの取得に失敗しました: ' + def.apiId}))));
            fetched.forEach((result, i) => {
                if (result.error) {
                    layerErrors.push(result.error);
                }
                layerResults.push({label: layerDefs[i].label, key: layerDefs[i].key, count: result.count, samples: result.samples});
            });

            const displayTxCount = tx.count > 0 ? tx.count : txTotalCount;
            renderSummary([{label: '取引価格', count: displayTxCount}].concat(layerResults.map((r) => ({label: r.label, count: r.count}))));
            renderList(layerResults.flatMap((r) => r.samples).slice(0, 12));

            document.querySelectorAll('.layer-toggle').forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    toggleLayer(checkbox.dataset.layer, checkbox.checked);
                });
            });

            const warnings = [];
            if (txWarning) {
                warnings.push(txWarning);
            }
            if (tx.count === 0 && txTotalCount > 0) {
                warnings.push('取引データは' + txTotalCount + '件ありますが、座標点が取得できないため地図には表示できません。');
            }
            warnings.push(...layerErrors);

            if (warnings.length > 0) {
                const isFallbackOnly = warnings.length === 1 && txWarning && layerErrors.length === 0 && txWarning.indexOf('住所ジオコーディングで補完表示しています') !== -1;
                status.className = (isFallbackOnly ? 'alert alert-info' : 'alert alert-warning') + ' mt-3 mb-0';
                status.textContent = isFallbackOnly
                    ? '取引ポイントのデータを取得できなかったため、住所から位置を補完して表示しています。'
                    : '一部データの取得に失敗しました: ' + warnings.join(' / ');
                status.title = warnings.join(' / ');
            } else {
                status.className = 'alert alert-success mt-3 mb-0';
                status.textContent = '調査データの読み込みが完了しました。右側パネルで件数確認・レイヤー切替ができます。';
            }
        } catch (e) {
            status.className = 'alert alert-danger mt-3 mb-0';
            status.textContent = e && e.message ? ('データ取得に失敗しました: ' + e.message) : 'データ取得に失敗しました。';
        }
    });
});
</script>
<?php $this->end(); ?>
