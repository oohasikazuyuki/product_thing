<?php
$this->assign('title', '取引価格結果');
$this->assign('containerClass', 'container-fluid px-md-4');
$query = $this->request->getQuery();
$page = (int)$page;
$pages = (int)$pages;
$first = (!empty($data) && is_array($data) && is_array(reset($data))) ? reset($data) : [];
$summary = trim(($first['Prefecture'] ?? '') . ' ' . ($first['Municipality'] ?? '') . ' ' . ($query['year'] ?? '') . '年');
$cell = fn($v) => ($v === null || $v === '') ? '—' : h((string)$v);
$yen = function ($v) {
    if (!is_numeric($v)) return '—';
    $v = (float)$v;
    $label = $v >= 100000000 ? rtrim(rtrim(number_format($v / 100000000, 2), '0'), '.') . '億円' : number_format($v / 10000) . '万円';
    return $label;
};
$pageUrl = fn(int $n) => ['?' => array_merge($query, ['page' => $n])];
$renderPager = function () use ($page, $pages, $pageUrl) {
    if ($pages <= 1) return '';
    $items = [];
    $add = function (string $label, ?int $n, bool $active = false) use (&$items, $pageUrl) {
        $link = $n === null
            ? '<span class="page-link">' . h($label) . '</span>'
            : $this->Html->link($label, $pageUrl($n), ['class' => 'page-link']);
        $items[] = '<li class="page-item' . ($active ? ' active' : '') . ($n === null && !$active ? ' disabled' : '') . '">' . $link . '</li>';
    };
    $add('« 前へ', $page > 1 ? $page - 1 : null);
    if ($page > 3) { $add('1', 1); if ($page > 4) $add('…', null); }
    for ($n = max(1, $page - 2); $n <= min($pages, $page + 2); $n++) $add((string)$n, $n === $page ? null : $n, $n === $page);
    if ($page < $pages - 2) { if ($page < $pages - 3) $add('…', null); $add((string)$pages, (int)$pages); }
    $add('次へ »', $page < $pages ? $page + 1 : null);
    return '<nav aria-label="ページ送り"><ul class="pagination pagination-sm justify-content-center mb-0">' . implode('', $items) . '</ul></nav>';
};
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-end">
    <div>
        <h1>取引価格結果</h1>
        <p><?= h($summary) ?>　<?= $total ? number_format((int)$total) . '件' : '' ?></p>
    </div>
    <?= $this->Html->link('条件を変更', ['controller' => 'PriceSearch', 'action' => 'selectAPI', '?' => ['prefecture' => $query['area'] ?? '', 'year' => $query['year'] ?? '']], ['class' => 'btn btn-outline-success btn-sm']) ?>
</div>

<div class="row">
    <div class="col-lg-3 mb-3">
        <div class="card mb-3">
            <div class="card-body p-3">
                <h2 class="h6">分析モード</h2>
                <div class="btn-group-vertical btn-group-sm w-100" role="group" aria-label="分析モード">
                    <button type="button" class="btn btn-outline-secondary mode-btn is-active" data-mode="living">お買い物・生活</button>
                    <button type="button" class="btn btn-outline-secondary mode-btn" data-mode="safety">安心・防災</button>
                    <button type="button" class="btn btn-outline-secondary mode-btn" data-mode="invest">プロ・投資</button>
                    <button type="button" class="btn btn-outline-secondary mode-btn" data-mode="nature">自然環境</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body p-3">
                <h2 class="h6">レイヤー表示</h2>
                <div id="layerControls" class="mb-0"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-9 mb-3">
        <div class="d-flex flex-wrap align-items-center mb-2 map-toolbar">
            <div class="btn-group btn-group-sm mr-2 mb-1" role="group" aria-label="地図の種類">
                <button type="button" class="btn btn-outline-secondary active" data-basemap="map">地図</button>
                <button type="button" class="btn btn-outline-secondary" data-basemap="aerial">航空写真</button>
            </div>
            <button type="button" id="streetViewBtn" class="btn btn-sm btn-outline-secondary mb-1" aria-pressed="false">ストリートビュー（地図をクリック）</button>
            <small class="text-muted ml-2 mb-1">表の行をクリックすると、その場所を航空写真で拡大します</small>
        </div>
        <div id="map" class="map-canvas" aria-label="不動産データの地図"></div>
        <div class="d-flex flex-wrap align-items-center small text-muted mt-2" aria-label="取引価格の色の凡例">
            <span class="mr-3">取引価格:</span>
            <span class="mr-3"><span class="legend-dot" style="background:#16a34a"></span>3,000万円未満</span>
            <span class="mr-3"><span class="legend-dot" style="background:#f59e0b"></span>3,000万〜5,000万円</span>
            <span class="mr-3"><span class="legend-dot" style="background:#ea580c"></span>5,000万〜1億円</span>
            <span class="mr-3"><span class="legend-dot" style="background:#b91c1c"></span>1億円以上</span>
            <span><span class="legend-dot" style="background:#2563eb"></span>価格不明</span>
        </div>
        <div id="mapStatus" class="alert alert-secondary mt-2 mb-0" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>取引データを取得しています（数秒かかります）…</div>
    </div>
</div>

<?php if (!empty($data)): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <input type="search" id="searchInput" class="form-control form-control-sm w-auto mb-2" style="min-width:16rem" placeholder="このページ内を絞り込み（例：神奈川区）" aria-label="このページ内を絞り込み">
        <small class="text-muted mb-2"><?= __('ページ {0} / {1}（全{2}件）', $page, $pages, $total) ?></small>
    </div>
    <div class="table-card mb-3">
        <table class="table table-striped table-hover table-sm" id="dataTable">
            <thead>
            <tr>
                <th>種類</th>
                <th class="d-none d-xl-table-cell">都道府県</th>
                <th>市区町村・地区</th>
                <th class="text-right">取引価格</th>
                <th class="text-right">面積</th>
                <th>建築年</th>
                <th>間取り</th>
                <th class="d-none d-xl-table-cell">構造</th>
                <th class="d-none d-xl-table-cell">用途</th>
                <th>取引時期</th>
            </tr>
            </thead>
            <tbody>
            <?php $rowIndex = 0; ?>
            <?php foreach ($data as $record): ?>
                <?php if (is_array($record)): ?>
                    <tr class="row-clickable" data-index="<?= $rowIndex++ ?>" tabindex="0" title="クリックで航空写真に拡大">
                        <td><?= $cell($record['Type'] ?? null) ?><div class="small text-muted"><?= $cell($record['PriceCategory'] ?? null) ?></div></td>
                        <td class="d-none d-xl-table-cell"><?= $cell($record['Prefecture'] ?? null) ?></td>
                        <td><?= $cell($record['Municipality'] ?? null) ?> <?= h($record['DistrictName'] ?? '') ?>
                            <div class="small text-muted d-xl-none"><?= $cell($record['Structure'] ?? null) ?> / <?= $cell($record['Purpose'] ?? null) ?></div></td>
                        <td class="text-right text-nowrap" title="<?= isset($record['TradePrice']) ? h(number_format((float)$record['TradePrice']) . ' 円') : '' ?>"><?= h($yen($record['TradePrice'] ?? null)) ?></td>
                        <td class="text-right text-nowrap"><?= isset($record['Area']) && $record['Area'] !== '' ? h($record['Area']) . ' m²' : '—' ?></td>
                        <td><?= $cell($record['BuildingYear'] ?? null) ?></td>
                        <td><?= $cell($record['FloorPlan'] ?? null) ?></td>
                        <td class="d-none d-xl-table-cell"><?= $cell($record['Structure'] ?? null) ?></td>
                        <td class="d-none d-xl-table-cell"><?= $cell($record['Purpose'] ?? null) ?></td>
                        <td class="text-nowrap"><?= $cell($record['Period'] ?? null) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $renderPager() ?>
<?php else: ?>
    <div class="alert alert-warning text-center">条件に合うデータがありません。条件を変更してお試しください。</div>
<?php endif; ?>

<div class="modal fade" id="streetViewModal" tabindex="-1" role="dialog" aria-labelledby="streetViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="streetViewTitle">ストリートビュー</h2>
                <a id="streetViewExternal" class="ml-auto mr-3 small" href="#" target="_blank" rel="noopener">新しいタブで開く</a>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="streetViewFrame" title="ストリートビュー" style="width:100%;height:70vh;border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</div>

<?php $this->start('css'); ?>
<link href="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css" rel="stylesheet">
<?php $this->end(); ?>
<?php $this->start('script'); ?>
<?php if (!empty($googleMapsApiKey)): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($googleMapsApiKey) ?>&libraries=streetView"></script>
<?php else: ?>
<script src="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js"></script>
<?php endif; ?>
<?php
$mapRecords = [];
if (!empty($data) && is_array($data)) {
    foreach ($data as $record) {
        if (is_array($record)) {
            $mapRecords[] = $record;
        }
    }
}
?>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        const defaultCenter = [139.7671, 35.6812];
        const defaultZoom = 10;
        const googleMapsApiKey = <?= json_encode($googleMapsApiKey ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const useGoogleMaps = Boolean(googleMapsApiKey && window.google && window.google.maps);
        const layerDataEndpoint = <?= json_encode('/layer-data', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const geoJsonEndpoint = <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'geojson'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const mapStatus = document.getElementById('mapStatus');
        let records = <?= json_encode($mapRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const tableRecords = records.slice();
        const pageQuery = new URLSearchParams(window.location.search);
        const layerControls = document.getElementById('layerControls');
        const modeButtons = document.querySelectorAll('.mode-btn');
        const modeName = {
            living: 'お買い物・生活',
            safety: '安心・防災',
            invest: 'プロ・投資',
            nature: '自然環境'
        };
        const layerCatalog = [
            {id: 'price-points', label: '取引価格ポイント', available: true},
            {id: 'medical-facilities', label: '医療機関（XKT010）', available: true},
            {id: 'facility-poi', label: '生活施設レイヤー（準備中）', available: false},
            {id: 'hazard-zones', label: '土砂災害警戒区域（XKT029）', available: true},
            {id: 'urban-plan', label: '都市計画レイヤー（準備中）', available: false},
            {id: 'population-heat', label: '人口ヒートマップ（準備中）', available: false},
            {id: 'nature-parks', label: '自然公園地域（XKT019）', available: true}
        ];
        const layerApiConfigs = {
            'medical-facilities': {apiId: 'XKT010', color: '#dc2626', title: '医療機関'},
            'hazard-zones': {apiId: 'XKT029', color: '#f59e0b', title: '土砂災害警戒区域'},
            'nature-parks': {apiId: 'XKT019', color: '#15803d', title: '自然公園地域'}
        };
        const modeDefaults = {
            living: ['price-points', 'medical-facilities', 'facility-poi'],
            safety: ['price-points', 'hazard-zones', 'medical-facilities'],
            invest: ['price-points', 'urban-plan', 'population-heat'],
            nature: ['price-points', 'nature-parks']
        };
        let currentMode = 'living';
        const activeLayers = new Set(modeDefaults[currentMode]);
        const layerMarkerRegistry = {
            'price-points': [],
            'medical-facilities': [],
            'hazard-zones': [],
            'nature-parks': []
        };

        const setMapStatus = function (message, variant) {
            const color = variant || 'secondary';
            mapStatus.className = 'alert alert-' + color + ' mt-3 mb-0';
            mapStatus.textContent = message;
        };

        const syncModeButtons = function () {
            modeButtons.forEach(function (button) {
                button.classList.toggle('is-active', button.dataset.mode === currentMode);
            });
        };

        const applyLayerVisibility = function () {
            Object.keys(layerMarkerRegistry).forEach(function (layerId) {
                const visible = activeLayers.has(layerId);
                layerMarkerRegistry[layerId].forEach(function (markerWrapper) {
                    markerWrapper.setVisible(visible);
                });
            });
        };

        const renderLayerControls = function () {
            let html = '';
            layerCatalog.filter(function (layer) { return layer.available; }).forEach(function (layer) {
                const checked = activeLayers.has(layer.id) ? 'checked' : '';
                const disabled = layer.available ? '' : 'disabled';
                const muted = layer.available ? '' : ' text-muted';
                html += '<div class="form-check mb-1' + muted + '">';
                html += '<input class="form-check-input layer-toggle" type="checkbox" id="layer-' + layer.id + '" data-layer-id="' + layer.id + '" ' + checked + ' ' + disabled + '>';
                html += '<label class="form-check-label" for="layer-' + layer.id + '">' + layer.label + '</label>';
                html += '</div>';
            });
            layerControls.innerHTML = html;

            const checkboxes = layerControls.querySelectorAll('.layer-toggle');
            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const targetLayerId = checkbox.dataset.layerId;
                    if (checkbox.checked) {
                        activeLayers.add(targetLayerId);
                    } else {
                        activeLayers.delete(targetLayerId);
                        clearLayerMarkers(targetLayerId);
                    }
                    applyLayerVisibility();
                    if (checkbox.checked) {
                        loadApiLayer(targetLayerId);
                    }
                });
            });
        };

        const sleep = function (ms) {
            return new Promise(function (resolve) {
                setTimeout(resolve, ms);
            });
        };

        const extractCoordinates = function (record) {
            if (record && record.geometry && Array.isArray(record.geometry.coordinates) && record.geometry.coordinates.length === 2) {
                const featureLng = Number(record.geometry.coordinates[0]);
                const featureLat = Number(record.geometry.coordinates[1]);
                if (Number.isFinite(featureLng) && Number.isFinite(featureLat)) {
                    return [featureLng, featureLat];
                }
            }
            const pairs = [
                ['Longitude', 'Latitude'],
                ['longitude', 'latitude'],
                ['lng', 'lat'],
                ['Lon', 'Lat'],
                ['LON', 'LAT']
            ];
            for (const pair of pairs) {
                const lng = Number(record[pair[0]]);
                const lat = Number(record[pair[1]]);
                if (Number.isFinite(lng) && Number.isFinite(lat)) {
                    return [lng, lat];
                }
            }

            return null;
        };

        const toRecordProperties = function (record) {
            if (record && record.properties && typeof record.properties === 'object') {
                return record.properties;
            }

            return record;
        };

        const buildAddress = function (record) {
            const parts = [
                record.Prefecture,
                record.Municipality,
                record.DistrictName
            ].filter(function (value) {
                return value && value !== 'N/A';
            });

            return parts.length > 0 ? parts.join('') : null;
        };

        const geocodeAddress = async function (address) {
            const endpoint = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' + encodeURIComponent(address);
            const response = await fetch(endpoint);
            if (!response.ok) {
                return null;
            }
            const geoJson = await response.json();
            if (!Array.isArray(geoJson) || geoJson.length === 0) {
                return null;
            }
            const coordinates = geoJson[0] && geoJson[0].geometry ? geoJson[0].geometry.coordinates : null;
            if (!Array.isArray(coordinates) || coordinates.length !== 2) {
                return null;
            }

            return coordinates;
        };

        const toPriceNumber = function (value) {
            if (typeof value === 'number') {
                return value;
            }
            if (typeof value === 'string') {
                return Number(value.replace(/[^\d.-]/g, ''));
            }

            return NaN;
        };

        const priceToColor = function (price) {
            if (!Number.isFinite(price)) {
                return '#2563eb';
            }
            if (price >= 100000000) {
                return '#b91c1c';
            }
            if (price >= 50000000) {
                return '#ea580c';
            }
            if (price >= 30000000) {
                return '#f59e0b';
            }

            return '#16a34a';
        };

        const escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const createPopupHtml = function (record, formattedPrice) {
            return '<div>' +
                '<strong>' + escapeHtml(record.Prefecture) + ' ' + escapeHtml(record.Municipality) + '</strong><br>' +
                '地区: ' + escapeHtml(record.DistrictName || 'N/A') + '<br>' +
                '価格: ' + escapeHtml(formattedPrice) + '<br>' +
                '建築年: ' + escapeHtml(record.BuildingYear || 'N/A') + '<br>' +
                '間取り: ' + escapeHtml(record.FloorPlan || 'N/A') + '<br>' +
                '建物構造: ' + escapeHtml(record.Structure || 'N/A') + '<br>' +
                '用途: ' + escapeHtml(record.Purpose || 'N/A') + '<br>' +
                '時期: ' + escapeHtml(record.Period || 'N/A') +
                '</div>';
        };

        const createLayerPopupHtml = function (record, title) {
            const keys = Object.keys(record || {}).slice(0, 6);
            if (keys.length === 0) {
                return '<div><strong>' + escapeHtml(title) + '</strong><br>属性情報なし</div>';
            }

            let body = '<div><strong>' + escapeHtml(title) + '</strong><br>';
            keys.forEach(function (key) {
                body += escapeHtml(key) + ': ' + escapeHtml(record[key]) + '<br>';
            });
            body += '</div>';
            return body;
        };

        const mapAdapter = (function () {
            if (useGoogleMaps) {
                let focusMarker = null;
                const googleMap = new google.maps.Map(document.getElementById('map'), {
                    center: {lat: defaultCenter[1], lng: defaultCenter[0]},
                    zoom: defaultZoom,
                    streetViewControl: true,
                    mapTypeControl: true,
                    fullscreenControl: true
                });

                return {
                    ready: function (callback) {
                        callback();
                    },
                    getCenter: function () {
                        const center = googleMap.getCenter();
                        return [center.lng(), center.lat()];
                    },
                    getBounds: function () {
                        const bounds = googleMap.getBounds();
                        if (!bounds) {
                            return null;
                        }
                        const sw = bounds.getSouthWest();
                        const ne = bounds.getNorthEast();
                        return [sw.lng(), sw.lat(), ne.lng(), ne.lat()];
                    },
                    onMoveEnd: function (callback) {
                        googleMap.addListener('idle', callback);
                    },
                    addPoint: function (record, coordinates, color, popupHtml) {
                        const marker = new google.maps.Marker({
                            map: googleMap,
                            position: {lat: coordinates[1], lng: coordinates[0]},
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 7,
                                fillColor: color,
                                fillOpacity: 0.9,
                                strokeColor: '#1f2937',
                                strokeWeight: 1
                            }
                        });
                        const infoWindow = new google.maps.InfoWindow({content: popupHtml});
                        marker.addListener('click', function () {
                            infoWindow.open({
                                anchor: marker,
                                map: googleMap,
                                shouldFocus: false
                            });
                        });
                        return {
                            setVisible: function (isVisible) {
                                marker.setMap(isVisible ? googleMap : null);
                            },
                            remove: function () {
                                marker.setMap(null);
                            }
                        };
                    },
                    fitToBounds: function (plottedCoordinates) {
                        const bounds = new google.maps.LatLngBounds();
                        plottedCoordinates.forEach(function (coordinate) {
                            bounds.extend({lat: coordinate[1], lng: coordinate[0]});
                        });
                        googleMap.fitBounds(bounds);
                    },
                    centerOn: function (coordinates, zoom) {
                        googleMap.setCenter({lat: coordinates[1], lng: coordinates[0]});
                        googleMap.setZoom(zoom);
                    },
                    setAerial: function (on) {
                        googleMap.setMapTypeId(on ? 'hybrid' : 'roadmap');
                    },
                    focus: function (coordinates, popupHtml) {
                        if (focusMarker) {
                            focusMarker.setMap(null);
                        }
                        const position = {lat: coordinates[1], lng: coordinates[0]};
                        focusMarker = new google.maps.Marker({map: googleMap, position: position});
                        googleMap.setCenter(position);
                        googleMap.setZoom(18);
                        new google.maps.InfoWindow({content: popupHtml}).open({anchor: focusMarker, map: googleMap, shouldFocus: false});
                    },
                    onMapClick: function (callback) {
                        googleMap.addListener('click', function (event) {
                            callback([event.latLng.lng(), event.latLng.lat()]);
                        });
                    },
                    setPickCursor: function (on) {
                        googleMap.setOptions({draggableCursor: on ? 'crosshair' : null});
                    }
                };
            }

            let focusMarker = null;
            const maplibreMap = new maplibregl.Map({
                container: 'map',
                style: {
                    version: 8,
                    sources: {
                        osm: {
                            type: 'raster',
                            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                            tileSize: 256,
                            attribution: '&copy; OpenStreetMap contributors'
                        },
                        aerial: {
                            type: 'raster',
                            tiles: ['https://cyberjapandata.gsi.go.jp/xyz/seamlessphoto/{z}/{x}/{y}.jpg'],
                            tileSize: 256,
                            maxzoom: 18,
                            attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">国土地理院 シームレス空中写真</a>'
                        }
                    },
                    layers: [
                        {
                            id: 'osm',
                            type: 'raster',
                            source: 'osm'
                        },
                        {
                            id: 'aerial',
                            type: 'raster',
                            source: 'aerial',
                            layout: {visibility: 'none'}
                        }
                    ]
                },
                center: defaultCenter,
                zoom: defaultZoom
            });
            maplibreMap.addControl(new maplibregl.NavigationControl(), 'top-right');

            return {
                ready: function (callback) {
                    maplibreMap.on('load', callback);
                },
                getCenter: function () {
                    const center = maplibreMap.getCenter();
                    return [center.lng, center.lat];
                },
                getBounds: function () {
                    const bounds = maplibreMap.getBounds();
                    if (!bounds) {
                        return null;
                    }
                    return [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
                },
                onMoveEnd: function (callback) {
                    maplibreMap.on('moveend', callback);
                },
                addPoint: function (record, coordinates, color, popupHtml) {
                    const marker = new maplibregl.Marker({color: color})
                        .setLngLat(coordinates)
                        .setPopup(new maplibregl.Popup({offset: 25}).setHTML(popupHtml))
                        .addTo(maplibreMap);
                    return {
                        setVisible: function (isVisible) {
                            marker.getElement().style.display = isVisible ? '' : 'none';
                        },
                        remove: function () {
                            marker.remove();
                        }
                    };
                },
                fitToBounds: function (plottedCoordinates) {
                    const bounds = new maplibregl.LngLatBounds();
                    plottedCoordinates.forEach(function (coordinate) {
                        bounds.extend(coordinate);
                    });
                    maplibreMap.fitBounds(bounds, {
                        padding: 40,
                        maxZoom: 13
                    });
                },
                centerOn: function (coordinates, zoom) {
                    maplibreMap.jumpTo({center: coordinates, zoom: zoom});
                },
                setAerial: function (on) {
                    maplibreMap.setLayoutProperty('aerial', 'visibility', on ? 'visible' : 'none');
                },
                focus: function (coordinates, popupHtml) {
                    if (focusMarker) {
                        focusMarker.remove();
                    }
                    focusMarker = new maplibregl.Marker({color: '#e11d48'})
                        .setLngLat(coordinates)
                        .setPopup(new maplibregl.Popup({offset: 25}).setHTML(popupHtml))
                        .addTo(maplibreMap);
                    maplibreMap.flyTo({center: coordinates, zoom: 17});
                    focusMarker.togglePopup();
                },
                onMapClick: function (callback) {
                    maplibreMap.on('click', function (event) {
                        callback([event.lngLat.lng, event.lngLat.lat]);
                    });
                },
                setPickCursor: function (on) {
                    maplibreMap.getCanvas().style.cursor = on ? 'crosshair' : '';
                }
            };
        })();

        const clearLayerMarkers = function (layerId) {
            if (!layerMarkerRegistry[layerId]) {
                return;
            }
            layerMarkerRegistry[layerId].forEach(function (markerWrapper) {
                markerWrapper.remove();
            });
            layerMarkerRegistry[layerId] = [];
        };

        const loadApiLayer = async function (layerId) {
            const config = layerApiConfigs[layerId];
            if (!config || !activeLayers.has(layerId)) {
                return;
            }
            clearLayerMarkers(layerId);

            const center = mapAdapter.getCenter();
            const params = new URLSearchParams({
                api_id: config.apiId,
                lat: String(center[1]),
                lon: String(center[0]),
                radius: '3000'
            });
            const response = await fetch(layerDataEndpoint + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            if (!payload.success || !Array.isArray(payload.data)) {
                return;
            }

            let plotted = 0;
            payload.data.forEach(function (row) {
                const coordinates = extractCoordinates(row);
                if (!coordinates) {
                    return;
                }
                const marker = mapAdapter.addPoint(row, coordinates, config.color, createLayerPopupHtml(row, config.title + '（' + config.apiId + '）'));
                layerMarkerRegistry[layerId].push(marker);
                plotted += 1;
            });

            if (plotted > 0) {
                applyLayerVisibility();
                setMapStatus(modeName[currentMode] + 'モードで ' + config.title + ' レイヤーを ' + plotted + ' 件表示しました。', 'info');
            }
        };

        const reloadActiveApiLayers = function () {
            Object.keys(layerApiConfigs).forEach(function (layerId) {
                if (activeLayers.has(layerId)) {
                    loadApiLayer(layerId);
                }
            });
        };

        const fetchGeoJsonRecords = async function () {
            const requiredParams = ['area', 'city', 'year'];
            const hasRequiredParams = requiredParams.every(function (param) {
                const value = pageQuery.get(param);
                return typeof value === 'string' && value !== '';
            });
            if (!hasRequiredParams) {
                return null;
            }

            const params = new URLSearchParams({
                area: pageQuery.get('area'),
                city: pageQuery.get('city'),
                year: pageQuery.get('year')
            });
            const bounds = mapAdapter.getBounds();
            if (Array.isArray(bounds) && bounds.length === 4) {
                params.set('bbox', bounds.join(','));
            }

            const response = await fetch(geoJsonEndpoint + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                return null;
            }
            const payload = await response.json();
            if (!payload || payload.type !== 'FeatureCollection' || !Array.isArray(payload.features)) {
                return null;
            }

            return payload.features;
        };

        const renderMapPoints = async function (fitBounds = true) {
            if (!Array.isArray(records) || records.length === 0) {
                setMapStatus('表示対象データがありません。', 'warning');
                return;
            }
            clearLayerMarkers('price-points');

            setMapStatus(modeName[currentMode] + 'モードでレイヤーを準備中です。', 'secondary');

            const geocodeCache = new Map();
            const geocodeLimit = 20;
            let geocodeCount = 0;
            let plottedCount = 0;
            const plottedCoordinates = [];

            for (const record of records) {
                let coordinates = extractCoordinates(record);
                const properties = toRecordProperties(record);
                if (!coordinates && geocodeCount < geocodeLimit) {
                    const address = buildAddress(properties);
                    if (address) {
                        if (geocodeCache.has(address)) {
                            coordinates = geocodeCache.get(address);
                        } else {
                            coordinates = await geocodeAddress(address);
                            geocodeCache.set(address, coordinates);
                            geocodeCount += 1;
                            setMapStatus('位置情報を取得中です…（' + geocodeCount + ' / ' + geocodeLimit + '）', 'secondary');
                            await sleep(200);
                        }
                    }
                }
                if (!coordinates) {
                    continue;
                }

                const price = toPriceNumber(properties.TradePrice);
                const formattedPrice = Number.isFinite(price) ? price.toLocaleString() + ' 円' : 'N/A';
                const popupHtml = createPopupHtml(properties, formattedPrice);
                const markerWrapper = mapAdapter.addPoint(properties, coordinates, priceToColor(price), popupHtml);
                layerMarkerRegistry['price-points'].push(markerWrapper);
                plottedCount += 1;
                plottedCoordinates.push(coordinates);
            }

            if (plottedCount === 0) {
                setMapStatus('位置情報を取得できるデータがなく、地図上に表示できませんでした。', 'warning');
                return;
            }

            if (fitBounds) {
                mapAdapter.fitToBounds(plottedCoordinates);
            }
            applyLayerVisibility();
            const mapText = useGoogleMaps ? 'Google Maps（Street View利用可）' : 'MapLibre';
            setMapStatus(modeName[currentMode] + 'モードで ' + plottedCount + ' 件を表示中です（' + mapText + '）。', 'success');
            reloadActiveApiLayers();
        };

        modeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                currentMode = button.dataset.mode;
                activeLayers.clear();
                (modeDefaults[currentMode] || []).forEach(function (layerId) {
                    activeLayers.add(layerId);
                });
                syncModeButtons();
                renderLayerControls();
                applyLayerVisibility();
                setMapStatus(modeName[currentMode] + 'モードに切り替えました。', 'info');
                reloadActiveApiLayers();
            });
        });

        syncModeButtons();
        renderLayerControls();

        mapAdapter.ready(function () {
            // データ取得を待たず、まず選択した市区町村へ地図を寄せる
            const firstRecord = tableRecords[0];
            const cityName = firstRecord ? buildAddress({Prefecture: firstRecord.Prefecture, Municipality: firstRecord.Municipality}) : null;
            if (cityName) {
                geocodeAddress(cityName).then(function (coordinates) {
                    if (coordinates) {
                        mapAdapter.centerOn(coordinates, 12);
                    }
                }).catch(function () {});
            }
            fetchGeoJsonRecords().then(function (initialRecords) {
                if (Array.isArray(initialRecords) && initialRecords.length > 0) {
                    records = initialRecords;
                }
                return renderMapPoints();
            }).catch(function () {
                setMapStatus('地図データの表示中にエラーが発生しました。', 'danger');
            });

            let moveRefreshTimer = null;
            mapAdapter.onMoveEnd(function () {
                if (moveRefreshTimer !== null) {
                    clearTimeout(moveRefreshTimer);
                }
                moveRefreshTimer = setTimeout(function () {
                    fetchGeoJsonRecords().then(function (nextRecords) {
                        if (!Array.isArray(nextRecords) || nextRecords.length === 0) {
                            reloadActiveApiLayers();
                            return;
                        }
                        records = nextRecords;
                        return renderMapPoints(false).then(function () {
                            reloadActiveApiLayers();
                        });
                    }).catch(function () {
                        setMapStatus('地図移動後の再取得に失敗しました。', 'warning');
                    });
                }, 700);
            });
        });

        const streetViewUrl = function (coordinates) {
            return 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' + coordinates[1] + ',' + coordinates[0];
        };

        // ストリートビューはモーダル内に埋め込む（キーがあれば公式Embed API、無ければキー不要の埋め込みURL）
        const embedStreetViewUrl = function (coordinates) {
            const location = coordinates[1] + ',' + coordinates[0];
            if (googleMapsApiKey) {
                return 'https://www.google.com/maps/embed/v1/streetview?key=' + encodeURIComponent(googleMapsApiKey) + '&location=' + location;
            }
            return 'https://maps.google.com/maps?layer=c&cbll=' + location + '&cbp=11,0,0,0,0&output=svembed';
        };
        const openStreetView = function (coordinates) {
            document.getElementById('streetViewFrame').src = embedStreetViewUrl(coordinates);
            document.getElementById('streetViewExternal').href = streetViewUrl(coordinates);
            $('#streetViewModal').modal('show');
        };
        $('#streetViewModal').on('hidden.bs.modal', function () {
            document.getElementById('streetViewFrame').src = 'about:blank';
        });
        document.addEventListener('click', function (event) {
            const link = event.target.closest('[data-streetview]');
            if (link) {
                event.preventDefault();
                openStreetView(link.dataset.streetview.split(',').map(Number));
            }
        });

        const basemapButtons = document.querySelectorAll('[data-basemap]');
        const setBasemap = function (name) {
            mapAdapter.setAerial(name === 'aerial');
            basemapButtons.forEach(function (button) {
                button.classList.toggle('active', button.dataset.basemap === name);
            });
        };
        basemapButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setBasemap(button.dataset.basemap);
            });
        });

        // ストリートビュー: ボタンを押してから地図をクリックすると、その地点をGoogleストリートビューで開く
        const streetViewBtn = document.getElementById('streetViewBtn');
        let picking = false;
        const setPicking = function (on) {
            picking = on;
            streetViewBtn.classList.toggle('active', on);
            streetViewBtn.setAttribute('aria-pressed', String(on));
            mapAdapter.setPickCursor(on);
            if (on) {
                setMapStatus('ストリートビューで見たい場所を地図上でクリックしてください。', 'info');
            }
        };
        streetViewBtn.addEventListener('click', function () {
            setPicking(!picking);
        });
        mapAdapter.onMapClick(function (coordinates) {
            if (!picking) {
                return;
            }
            setPicking(false);
            openStreetView(coordinates);
        });

        // 表の行クリック: その取引の場所を航空写真で拡大表示
        const tableCoordCache = new Map();
        const focusTableRow = async function (index) {
            const record = tableRecords[index];
            if (!record) {
                return;
            }
            let coordinates = tableCoordCache.get(index) || extractCoordinates(record);
            if (!coordinates) {
                const address = buildAddress(record);
                if (address) {
                    setMapStatus('位置を検索中です…', 'secondary');
                    coordinates = await geocodeAddress(address).catch(function () { return null; });
                }
            }
            if (!coordinates) {
                setMapStatus('この取引の位置を特定できませんでした。', 'warning');
                return;
            }
            tableCoordCache.set(index, coordinates);
            const price = toPriceNumber(record.TradePrice);
            const popupHtml = createPopupHtml(record, Number.isFinite(price) ? price.toLocaleString() + ' 円' : 'N/A') +
                '<div class="mt-2"><a href="' + streetViewUrl(coordinates) + '" data-streetview="' + coordinates[0] + ',' + coordinates[1] + '">ストリートビューで見る</a></div>';
            setBasemap('aerial');
            mapAdapter.focus(coordinates, popupHtml);
            document.getElementById('map').scrollIntoView({behavior: 'smooth', block: 'center'});
            setMapStatus('選択した取引の場所を表示中です。取引価格情報は町丁目単位のため、位置は地区の代表点付近です。', 'info');
        };
        document.querySelectorAll('#dataTable tbody tr.row-clickable').forEach(function (row) {
            const open = function () { focusTableRow(Number(row.dataset.index)); };
            row.addEventListener('click', open);
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    open();
                }
            });
        });

        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function () {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let match = false;
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(filter)) {
                        match = true;
                    }
                });
                row.style.display = match ? '' : 'none';
            });
        });
    });
</script>
<?php $this->end(); ?>
