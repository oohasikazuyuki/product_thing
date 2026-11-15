<?php
$this->assign('title', '取引価格結果');
$this->assign('containerClass', 'container-fluid px-md-4');
$query = $this->request->getQuery();
$first = $rows[0] ?? [];
$summary = trim(($first['Prefecture'] ?? '') . ' ' . ($first['Municipality'] ?? '') . ' ' . ($query['year'] ?? '') . '年');
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-end">
    <div>
        <h1>取引価格結果</h1>
        <p><?= h($summary) ?></p>
    </div>
    <?php $carry = ['area' => $query['area'] ?? '', 'city' => $query['city'] ?? '', 'year' => $query['year'] ?? '']; ?>
    <div class="btn-group btn-group-sm" role="group" aria-label="この条件で他の調査へ">
        <?= $this->Html->link('この条件で防災調査', ['controller' => 'AreaAnalysis', 'action' => 'safetySurvey', '?' => $carry], ['class' => 'btn btn-outline-success']) ?>
        <?= $this->Html->link('この条件で学校調査', ['controller' => 'AreaAnalysis', 'action' => 'schoolSurvey', '?' => $carry], ['class' => 'btn btn-outline-success']) ?>
        <?= $this->Html->link('条件を変更', ['controller' => 'PriceSearch', 'action' => 'selectAPI', '?' => ['prefecture' => $carry['area'], 'city' => $carry['city'], 'year' => $carry['year']]], ['class' => 'btn btn-outline-secondary']) ?>
    </div>
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
            <small class="text-muted ml-2 mb-1">行クリックで航空写真に拡大</small>
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
        <div id="mapStatus" class="alert alert-secondary mt-2 mb-0" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>読み込み中…</div>
    </div>
</div>

<?php if (!empty($rows)): ?>
    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-6 col-md-2 mb-2">
                    <label class="small mb-1" for="fType">種類</label>
                    <select id="fType" class="form-control form-control-sm"></select>
                </div>
                <div class="form-group col-6 col-md-2 mb-2">
                    <label class="small mb-1" for="fDistrict">地区</label>
                    <select id="fDistrict" class="form-control form-control-sm"></select>
                </div>
                <?php
                // 数字を打たずに選べるよう、よく使う区切りをプルダウンにする
                $rangeSelects = [
                    ['id' => 'fPrice', 'label' => '取引価格', 'steps' => [500, 1000, 1500, 2000, 3000, 4000, 5000, 7000, 10000, 15000, 20000, 30000, 50000],
                        'text' => fn($v) => $v >= 10000 ? rtrim(rtrim(number_format($v / 10000, 1), '0'), '.') . '億円' : number_format($v) . '万円'],
                    ['id' => 'fArea', 'label' => '面積', 'steps' => [20, 30, 40, 50, 60, 70, 80, 90, 100, 120, 150, 200, 300],
                        'text' => fn($v) => $v . '㎡'],
                ];
                ?>
                <?php foreach ($rangeSelects as $range): ?>
                    <div class="form-group col-12 col-md-2 mb-2">
                        <span class="small d-block mb-1"><?= h($range['label']) ?></span>
                        <div class="input-group input-group-sm">
                            <?php foreach (['Min' => '下限なし', 'Max' => '上限なし'] as $edge => $none): ?>
                                <select id="<?= h($range['id'] . $edge) ?>" class="form-control" aria-label="<?= h($range['label'] . ($edge === 'Min' ? 'の下限' : 'の上限')) ?>">
                                    <option value=""><?= h($none) ?></option>
                                    <?php foreach ($range['steps'] as $step): ?>
                                        <option value="<?= (int)$step ?>"><?= h($range['text']($step)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="form-group col-8 col-md-3 mb-2">
                    <label class="small mb-1" for="searchInput">キーワード</label>
                    <input type="search" id="searchInput" class="form-control form-control-sm" placeholder="例：岩本町、RC、1K">
                </div>
                <div class="form-group col-4 col-md-1 mb-2">
                    <button type="button" id="fClear" class="btn btn-outline-secondary btn-sm btn-block">クリア</button>
                </div>
            </div>
        </div>
    </div>
    <div id="resultCount" class="small text-muted mb-2" role="status" aria-live="polite"></div>
    <div class="table-card mb-3">
        <table class="table table-striped table-hover table-sm" id="dataTable">
            <thead>
            <tr>
                <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="Type">種類 <span class="sort-icon" aria-hidden="true"></span></button></th>
                <th scope="col" class="d-none d-xl-table-cell">都道府県</th>
                <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="Municipality">市区町村・地区 <span class="sort-icon" aria-hidden="true"></span></button></th>
                <th scope="col" class="text-right" aria-sort="none"><button type="button" class="sort-btn" data-sort="TradePrice">取引価格 <span class="sort-icon" aria-hidden="true"></span></button></th>
                <th scope="col" class="text-right" aria-sort="none"><button type="button" class="sort-btn" data-sort="Area">面積 <span class="sort-icon" aria-hidden="true"></span></button></th>
                <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="BuildingYear">建築年 <span class="sort-icon" aria-hidden="true"></span></button></th>
                <th scope="col">間取り</th>
                <th scope="col" class="d-none d-xl-table-cell">構造</th>
                <th scope="col" class="d-none d-xl-table-cell">用途</th>
                <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="Period">取引時期 <span class="sort-icon" aria-hidden="true"></span></button></th>
            </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>
    <nav id="pager" aria-label="ページ送り"></nav>
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
<?php // 地図はMapLibre(OSM)を使い、Googleマップ(JS API)は読み込まない（地図表示の課金を避ける）。
// $googleMapsApiKey はストリートビューの公式埋め込み(Maps Embed API)にだけ使う。 ?>
<script src="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        const defaultCenter = [139.7671, 35.6812];
        const defaultZoom = 10;
        const googleMapsApiKey = <?= json_encode($googleMapsApiKey ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const useGoogleMaps = Boolean(googleMapsApiKey && window.google && window.google.maps);
        const layerDataEndpoint = <?= json_encode('/layer-data', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const geoJsonEndpoint = <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'geojson'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const mapStatus = document.getElementById('mapStatus');
        const allRows = <?= json_encode($rows ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
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
                    addPoint: function (record, coordinates, color, popupHtml, onClick) {
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
                            if (onClick) {
                                onClick();
                            }
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
                addPoint: function (record, coordinates, color, popupHtml, onClick) {
                    const marker = new maplibregl.Marker({color: color})
                        .setLngLat(coordinates)
                        .setPopup(new maplibregl.Popup({offset: 25}).setHTML(popupHtml))
                        .addTo(maplibreMap);
                    if (onClick) {
                        marker.getElement().addEventListener('click', onClick);
                    }
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
        const focusTableRow = async function (id) {
            const record = allRows[id];
            if (!record) {
                return;
            }
            let coordinates = extractCoordinates(record);
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
            const price = toPriceNumber(record.TradePrice);
            const popupHtml = createPopupHtml(record, Number.isFinite(price) ? price.toLocaleString() + ' 円' : 'N/A') +
                '<div class="mt-2"><a href="' + streetViewUrl(coordinates) + '" data-streetview="' + coordinates[0] + ',' + coordinates[1] + '">ストリートビューで見る</a></div>';
            setBasemap('aerial');
            mapAdapter.focus(coordinates, popupHtml);
            document.getElementById('map').scrollIntoView({behavior: 'smooth', block: 'center'});
            setMapStatus('位置は町丁目の代表点付近です。', 'info');
        };
        // ---- 表（絞り込み・並べ替え・ページ送り）と地図を同じデータで動かす ----
        const PAGE_SIZE = 30;
        const MAX_MARKERS = 2000;
        const tableBody = document.getElementById('tableBody');
        const pager = document.getElementById('pager');
        const resultCount = document.getElementById('resultCount');
        const filterIds = ['fType', 'fDistrict', 'fPriceMin', 'fPriceMax', 'fAreaMin', 'fAreaMax', 'searchInput'];
        const sortButtons = document.querySelectorAll('.sort-btn');
        const state = {sortKey: null, sortDir: 1, page: 1, selectedId: null};
        let filteredRows = allRows;
        let mapReady = false;

        allRows.forEach(function (row, index) { row._id = index; });

        const num = function (value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }
            const parsed = Number(String(value).replace(/,/g, ''));
            return Number.isFinite(parsed) ? parsed : null;
        };
        const yearOf = function (value) {
            const match = String(value || '').match(/(\d{4})/);
            return match ? Number(match[1]) : null;
        };
        const formatYen = function (value) {
            const yen = num(value);
            if (yen === null) {
                return '—';
            }
            return yen >= 100000000
                ? String(Math.round(yen / 1000000) / 100) + '億円'
                : Math.round(yen / 10000).toLocaleString() + '万円';
        };
        const cell = function (value) {
            return value === null || value === undefined || value === '' ? '—' : escapeHtml(value);
        };
        const sortValue = {
            Type: function (r) { return r.Type || null; },
            Municipality: function (r) { return (r.Municipality || '') + (r.DistrictName || '') || null; },
            TradePrice: function (r) { return num(r.TradePrice); },
            Area: function (r) { return num(r.Area); },
            BuildingYear: function (r) { return yearOf(r.BuildingYear); },
            Period: function (r) { return r.Period || null; }
        };

        const fillSelect = function (id, label, values) {
            const select = document.getElementById(id);
            select.innerHTML = '<option value="">' + label + '</option>' + values.map(function (v) {
                return '<option value="' + escapeHtml(v) + '">' + escapeHtml(v) + '</option>';
            }).join('');
        };
        const distinct = function (key) {
            return Array.from(new Set(allRows.map(function (r) { return r[key]; }).filter(Boolean))).sort(function (a, b) {
                return String(a).localeCompare(String(b), 'ja');
            });
        };

        const readFilters = function () {
            const value = function (id) { return document.getElementById(id).value.trim(); };
            const toYen = function (id) { const v = num(value(id)); return v === null ? null : v * 10000; };
            return {
                type: value('fType'), district: value('fDistrict'),
                priceMin: toYen('fPriceMin'), priceMax: toYen('fPriceMax'),
                areaMin: num(value('fAreaMin')), areaMax: num(value('fAreaMax')),
                q: value('searchInput').toLowerCase()
            };
        };
        const matches = function (row, f) {
            if (f.type && row.Type !== f.type) { return false; }
            if (f.district && row.DistrictName !== f.district) { return false; }
            const price = num(row.TradePrice);
            if (f.priceMin !== null && (price === null || price < f.priceMin)) { return false; }
            if (f.priceMax !== null && (price === null || price > f.priceMax)) { return false; }
            const area = num(row.Area);
            if (f.areaMin !== null && (area === null || area < f.areaMin)) { return false; }
            if (f.areaMax !== null && (area === null || area > f.areaMax)) { return false; }
            if (f.q) {
                const text = [row.Type, row.PriceCategory, row.Prefecture, row.Municipality, row.DistrictName, row.FloorPlan,
                    row.Structure, row.Purpose, row.BuildingYear, row.Period].join(' ').toLowerCase();
                if (text.indexOf(f.q) === -1) { return false; }
            }
            return true;
        };
        const sortRows = function () {
            if (!state.sortKey) {
                filteredRows.sort(function (a, b) { return a._id - b._id; });
                return;
            }
            const getter = sortValue[state.sortKey];
            filteredRows.sort(function (a, b) {
                const x = getter(a);
                const y = getter(b);
                if (x === null && y === null) { return a._id - b._id; }
                if (x === null) { return 1; }
                if (y === null) { return -1; }
                const diff = typeof x === 'number' ? x - y : String(x).localeCompare(String(y), 'ja');
                return diff === 0 ? a._id - b._id : diff * state.sortDir;
            });
        };

        const renderRows = function () {
            const start = (state.page - 1) * PAGE_SIZE;
            const pageRows = filteredRows.slice(start, start + PAGE_SIZE);
            if (pageRows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">条件に合う取引がありません。絞り込みを緩めてお試しください。</td></tr>';
                return;
            }
            tableBody.innerHTML = pageRows.map(function (r) {
                const area = num(r.Area);
                return '<tr class="row-clickable' + (r._id === state.selectedId ? ' table-active' : '') + '" data-id="' + r._id + '" tabindex="0" title="クリックで航空写真に拡大">' +
                    '<td>' + cell(r.Type) + '<div class="small text-muted">' + cell(r.PriceCategory) + '</div></td>' +
                    '<td class="d-none d-xl-table-cell">' + cell(r.Prefecture) + '</td>' +
                    '<td>' + cell(r.Municipality) + ' ' + escapeHtml(r.DistrictName || '') +
                        '<div class="small text-muted d-xl-none">' + cell(r.Structure) + ' / ' + cell(r.Purpose) + '</div></td>' +
                    '<td class="text-right text-nowrap" title="' + escapeHtml(num(r.TradePrice) === null ? '' : num(r.TradePrice).toLocaleString() + ' 円') + '">' + formatYen(r.TradePrice) + '</td>' +
                    '<td class="text-right text-nowrap">' + (area === null ? '—' : escapeHtml(r.Area) + ' m²') + '</td>' +
                    '<td>' + cell(r.BuildingYear) + '</td>' +
                    '<td>' + cell(r.FloorPlan) + '</td>' +
                    '<td class="d-none d-xl-table-cell">' + cell(r.Structure) + '</td>' +
                    '<td class="d-none d-xl-table-cell">' + cell(r.Purpose) + '</td>' +
                    '<td class="text-nowrap">' + cell(r.Period) + '</td></tr>';
            }).join('');
        };
        const renderPager = function () {
            const pages = Math.max(1, Math.ceil(filteredRows.length / PAGE_SIZE));
            if (pages <= 1) {
                pager.innerHTML = '';
                return;
            }
            const item = function (label, page, active, disabled) {
                return '<li class="page-item' + (active ? ' active' : '') + (disabled ? ' disabled' : '') + '">' +
                    '<button type="button" class="page-link" data-page="' + page + '"' + (disabled ? ' disabled' : '') + (active ? ' aria-current="page"' : '') + '>' + label + '</button></li>';
            };
            const cur = state.page;
            let html = item('« 前へ', cur - 1, false, cur <= 1);
            if (cur > 3) { html += item('1', 1, false, false); if (cur > 4) { html += item('…', cur, false, true); } }
            for (let n = Math.max(1, cur - 2); n <= Math.min(pages, cur + 2); n++) { html += item(String(n), n, n === cur, false); }
            if (cur < pages - 2) { if (cur < pages - 3) { html += item('…', cur, false, true); } html += item(String(pages), pages, false, false); }
            html += item('次へ »', cur + 1, false, cur >= pages);
            pager.innerHTML = '<ul class="pagination pagination-sm justify-content-center mb-0">' + html + '</ul>';
        };
        const renderSortState = function () {
            sortButtons.forEach(function (button) {
                const active = button.dataset.sort === state.sortKey;
                button.parentElement.setAttribute('aria-sort', active ? (state.sortDir === 1 ? 'ascending' : 'descending') : 'none');
                button.querySelector('.sort-icon').textContent = active ? (state.sortDir === 1 ? '▲' : '▼') : '';
            });
        };
        const renderTable = function () {
            renderRows();
            renderPager();
            renderSortState();
            const pages = Math.max(1, Math.ceil(filteredRows.length / PAGE_SIZE));
            resultCount.textContent = filteredRows.length.toLocaleString() + ' 件' +
                (filteredRows.length !== allRows.length ? '（全 ' + allRows.length.toLocaleString() + ' 件中）' : '') +
                '　ページ ' + state.page + ' / ' + pages;
        };

        // 地図: 絞り込み後の全件をマーカーにする（座標のある行のみ）
        const renderMap = function (fit) {
            if (!mapReady) {
                return;
            }
            clearLayerMarkers('price-points');
            const coordinatesList = [];
            let missing = 0;
            let capped = false;
            filteredRows.forEach(function (row) {
                const coordinates = extractCoordinates(row);
                if (!coordinates) {
                    missing += 1;
                    return;
                }
                if (coordinatesList.length >= MAX_MARKERS) {
                    capped = true;
                    return;
                }
                const price = toPriceNumber(row.TradePrice);
                const formattedPrice = Number.isFinite(price) ? price.toLocaleString() + ' 円' : 'N/A';
                layerMarkerRegistry['price-points'].push(mapAdapter.addPoint(row, coordinates, priceToColor(price), createPopupHtml(row, formattedPrice), function () {
                    selectRow(row._id, false);
                }));
                coordinatesList.push(coordinates);
            });
            if (fit && coordinatesList.length > 0) {
                mapAdapter.fitToBounds(coordinatesList);
            }
            applyLayerVisibility();
            if (filteredRows.length === 0) {
                setMapStatus('条件に合う取引がないため、地図に表示するものがありません。', 'warning');
            } else if (coordinatesList.length === 0) {
                setMapStatus('位置情報を取得できた取引がありません。', 'warning');
            } else {
                setMapStatus(coordinatesList.length.toLocaleString() + ' 件を表示中' +
                    (capped ? '（多いため先頭' + MAX_MARKERS.toLocaleString() + '件まで。絞り込んでください）' : '') +
                    (missing > 0 ? ' ・ 位置未取得 ' + missing.toLocaleString() + ' 件は地図に出ません' : ''), 'success');
            }
        };

        const applyFilters = function () {
            const filters = readFilters();
            filteredRows = allRows.filter(function (row) { return matches(row, filters); });
            sortRows();
            state.page = 1;
            renderTable();
            renderMap(true);
        };

        // 表の行を選択（マーカークリック時は表を該当ページへ送ってハイライト、行クリック時は地図を拡大）
        const selectRow = function (id, zoomMap) {
            const index = filteredRows.findIndex(function (r) { return r._id === id; });
            if (index === -1) {
                return;
            }
            state.selectedId = id;
            state.page = Math.floor(index / PAGE_SIZE) + 1;
            renderTable();
            const tr = tableBody.querySelector('tr[data-id="' + id + '"]');
            if (tr && !zoomMap) {
                tr.scrollIntoView({block: 'center', behavior: 'smooth'});
            }
            if (zoomMap) {
                focusTableRow(id);
            }
        };

        // ---- イベント ----
        fillSelect('fType', 'すべて', distinct('Type'));
        fillSelect('fDistrict', 'すべて', distinct('DistrictName'));
        // 下限が上限を超えたら、もう一方の選択を外す（常に有効な範囲にする）
        [['fPriceMin', 'fPriceMax'], ['fAreaMin', 'fAreaMax']].forEach(function (pair) {
            pair.forEach(function (id, index) {
                document.getElementById(id).addEventListener('change', function () {
                    const min = num(document.getElementById(pair[0]).value);
                    const max = num(document.getElementById(pair[1]).value);
                    if (min !== null && max !== null && min > max) {
                        document.getElementById(pair[1 - index]).value = '';
                    }
                });
            });
        });

        let filterTimer = null;
        filterIds.forEach(function (id) {
            const element = document.getElementById(id);
            element.addEventListener(element.tagName === 'SELECT' ? 'change' : 'input', function () {
                clearTimeout(filterTimer);
                filterTimer = setTimeout(applyFilters, element.tagName === 'SELECT' ? 0 : 250);
            });
        });
        document.getElementById('fClear').addEventListener('click', function () {
            filterIds.forEach(function (id) { document.getElementById(id).value = ''; });
            applyFilters();
        });
        sortButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const key = button.dataset.sort;
                state.sortDir = state.sortKey === key ? -state.sortDir : 1;
                state.sortKey = key;
                sortRows();
                state.page = 1;
                renderTable();
            });
        });
        pager.addEventListener('click', function (event) {
            const button = event.target.closest('[data-page]');
            if (!button || button.disabled) {
                return;
            }
            state.page = Number(button.dataset.page);
            renderTable();
            document.getElementById('dataTable').scrollIntoView({block: 'start', behavior: 'smooth'});
        });
        const onRowActivate = function (event) {
            const tr = event.target.closest('tr[data-id]');
            if (tr && (event.type === 'click' || event.key === 'Enter')) {
                selectRow(Number(tr.dataset.id), true);
            }
        };
        tableBody.addEventListener('click', onRowActivate);
        tableBody.addEventListener('keydown', onRowActivate);

        renderTable();

        mapAdapter.ready(function () {
            mapReady = true;
            // まず選択した市区町村へ地図を寄せ、続けてマーカーを描画する
            const firstRow = allRows[0];
            const cityName = firstRow ? buildAddress({Prefecture: firstRow.Prefecture, Municipality: firstRow.Municipality}) : null;
            if (cityName) {
                geocodeAddress(cityName).then(function (coordinates) {
                    if (coordinates) {
                        mapAdapter.centerOn(coordinates, 12);
                    }
                }).catch(function () {});
            }
            renderMap(true);
            reloadActiveApiLayers();

            let moveRefreshTimer = null;
            mapAdapter.onMoveEnd(function () {
                clearTimeout(moveRefreshTimer);
                moveRefreshTimer = setTimeout(reloadActiveApiLayers, 700);
            });
        });
    });
</script>
<?php $this->end(); ?>
