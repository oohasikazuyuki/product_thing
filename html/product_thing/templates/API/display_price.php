<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>不動産取引価格情報</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css" rel="stylesheet">
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .table th {
            background-color: #343a40;
            color: #fff;
        }
        #map {
            width: 100%;
            height: 420px;
            border-radius: 8px;
        }
        .mode-btn.is-active {
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4 text-center">
        不動産取引価格情報
    </h1>

    <div class="mb-4">
        <h2 class="h5 mb-3">地図表示</h2>
        <div class="d-flex flex-wrap align-items-center mb-3">
            <span class="mr-3">分析モード:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="mode-switch">
                <button type="button" class="btn btn-outline-primary mode-btn is-active" data-mode="living">お買い物・生活</button>
                <button type="button" class="btn btn-outline-danger mode-btn" data-mode="safety">安心・防災</button>
                <button type="button" class="btn btn-outline-dark mode-btn" data-mode="invest">プロ・投資</button>
                <button type="button" class="btn btn-outline-success mode-btn" data-mode="nature">自然環境</button>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body p-3">
                <h3 class="h6 mb-2">レイヤー表示</h3>
                <div id="layerControls" class="mb-0"></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body p-3">
                <h3 class="h6 mb-2">ワンクリック物件調査</h3>
                <p class="small text-muted mb-2">地図をクリックすると、都市計画・防災の判定サマリーを表示します。</p>
                <div id="dueDiligenceResult" class="small mb-0">未実行</div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body p-3">
                <h3 class="h6 mb-2">AI査定・相場分析（簡易）</h3>
                <div id="appraisalSummary" class="small mb-0">算出待ち</div>
            </div>
        </div>
        <div id="map" aria-label="不動産データの地図"></div>
        <div id="mapStatus" class="alert alert-secondary mt-3 mb-0">
            地図を準備中です。
        </div>
        <p class="small text-muted mt-2 mb-0">出典：不動産情報ライブラリ（国土交通省）</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <input type="text" id="searchInput" class="form-control" placeholder="検索例：神奈川区">
        </div>
    </div>

    <!-- ページナビゲーション (上部) -->
    <?php if ($page > 1 || $page < $pages): ?>
        <nav aria-label="Page navigation example" class="mb-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <?= $this->Html->link('< ' . __('前へ'), ['?' => array_merge($this->request->getQuery(), ['page' => $page - 1])], ['class' => 'page-link', 'escape' => false]) ?>
                    </li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link(__('次へ') . ' >', ['?' => array_merge($this->request->getQuery(), ['page' => $page + 1])], ['class' => 'page-link', 'escape' => false]) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if (!empty($data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover" id="dataTable">
                <thead>
                <tr>
                    <th class="text-center bg-success">カテゴリ</th>
                    <th class="text-center bg-success">種類</th>
                    <th class="text-center bg-success">都道府県</th>
                    <th class="text-center bg-success">市区町村</th>
                    <th class="text-center bg-success">地区名</th>
                    <th class="text-center bg-success">取引価格 (円)</th>
                    <th class="text-center bg-success">面積 (m²)</th>
                    <th class="text-center bg-success">建築年</th>
                    <th class="text-center bg-success">間取り</th>
                    <th class="text-center bg-success">建物構造</th>
                    <th class="text-center bg-success">用途</th>
                    <th class="text-center bg-success">取引時期</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $record): ?>
                    <?php if (is_array($record)): ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars($record['PriceCategory'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Type'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Prefecture'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Municipality'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['DistrictName'] ?? 'N/A'); ?></td>
                            <td class="text-right"><?= htmlspecialchars(isset($record['TradePrice']) ? number_format($record['TradePrice']) . ' 円' : 'N/A'); ?></td>
                            <td class="text-right"><?= htmlspecialchars($record['Area'] ?? 'N/A'); ?> m²</td>
                            <td class="text-center"><?= htmlspecialchars($record['BuildingYear'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['FloorPlan'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Structure'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Purpose'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Period'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="12">Invalid data format: <?= htmlspecialchars($record); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ページナビゲーション (下部) -->
        <nav aria-label="Page navigation example" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <?= $this->Html->link('< ' . __('前へ'), ['?' => array_merge($this->request->getQuery(), ['page' => $page - 1])], ['class' => 'page-link', 'escape' => false]) ?>
                    </li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link(__('次へ') . ' >', ['?' => array_merge($this->request->getQuery(), ['page' => $page + 1])], ['class' => 'page-link', 'escape' => false]) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <p class="text-center mt-3"><?= __('ページ {0} / {1} (全{2}件)', $page, $pages, $total) ?></p>
    <?php else: ?>
        <p class="alert alert-warning text-center">データがありません。</p>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
        const layerDataEndpoint = <?= json_encode($this->Url->build(['controller' => 'API', 'action' => 'layerData'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const selectedArea = <?= json_encode((string)$this->request->getQuery('area'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const selectedYear = <?= json_encode((string)$this->request->getQuery('year'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const mapStatus = document.getElementById('mapStatus');
        const dueDiligenceResult = document.getElementById('dueDiligenceResult');
        const appraisalSummary = document.getElementById('appraisalSummary');
        const records = <?= json_encode($mapRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
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
            {id: 'xpt001-points', label: '取引・成約ポイント（XPT001）', available: true},
            {id: 'xpt002-points', label: '地価公示ポイント（XPT002）', available: true},
            {id: 'xkt002-zoning', label: '用途地域（XKT002）', available: true},
            {id: 'xkt003-location', label: '立地適正化計画（XKT003）', available: true},
            {id: 'xkt010-medical', label: '医療機関（XKT010）', available: true},
            {id: 'xkt011-welfare', label: '福祉施設（XKT011）', available: true},
            {id: 'xkt004-school', label: '小学校区（XKT004）', available: true},
            {id: 'xkt005-school', label: '中学校区（XKT005）', available: true},
            {id: 'xkt006-school', label: '学校（XKT006）', available: true},
            {id: 'xkt007-childcare', label: '保育園・幼稚園（XKT007）', available: true},
            {id: 'xkt013-population', label: '将来推計人口（XKT013）', available: true},
            {id: 'xkt001-planning', label: '都市計画区域/区域区分（XKT001）', available: true}
        ];
        const modeDefaults = {
            living: ['price-points', 'xkt010-medical', 'xkt011-welfare', 'xkt004-school', 'xkt005-school', 'xkt006-school', 'xkt007-childcare'],
            safety: ['price-points', 'xkt001-planning'],
            invest: ['price-points', 'xpt001-points', 'xpt002-points', 'xkt002-zoning', 'xkt003-location', 'xkt013-population'],
            nature: ['price-points']
        };
        let currentMode = 'living';
        const activeLayers = new Set(modeDefaults[currentMode]);
        const layerMarkerRegistry = {
            'price-points': [],
            'xpt001-points': [],
            'xpt002-points': [],
            'xkt002-zoning': [],
            'xkt003-location': [],
            'xkt010-medical': [],
            'xkt011-welfare': [],
            'xkt004-school': [],
            'xkt005-school': [],
            'xkt006-school': [],
            'xkt007-childcare': [],
            'xkt013-population': [],
            'xkt001-planning': []
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
            layerCatalog.forEach(function (layer) {
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
                    }
                    applyLayerVisibility();
                    if (checkbox.checked) {
                        ensureLayerLoaded(targetLayerId);
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
            if (record && record.geometry && Array.isArray(record.geometry.coordinates)) {
                const geometry = record.geometry;
                if (geometry.type === 'Point' && geometry.coordinates.length >= 2) {
                    return [Number(geometry.coordinates[0]), Number(geometry.coordinates[1])];
                }
                if (geometry.type === 'Polygon' && Array.isArray(geometry.coordinates[0])) {
                    const p = geometry.coordinates[0][0];
                    if (Array.isArray(p) && p.length >= 2) {
                        return [Number(p[0]), Number(p[1])];
                    }
                }
                if (geometry.type === 'MultiPolygon' && Array.isArray(geometry.coordinates[0]) && Array.isArray(geometry.coordinates[0][0])) {
                    const p = geometry.coordinates[0][0][0];
                    if (Array.isArray(p) && p.length >= 2) {
                        return [Number(p[0]), Number(p[1])];
                    }
                }
            }

            const pairs = [
                ['Longitude', 'Latitude'],
                ['longitude', 'latitude'],
                ['lng', 'lat'],
                ['Lon', 'Lat'],
                ['LON', 'LAT'],
                ['経度', '緯度'],
                ['lon', 'lat']
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

        const createGenericPopupHtml = function (title, record) {
            const keys = Object.keys(record || {}).slice(0, 6);
            if (keys.length === 0) {
                return '<div><strong>' + escapeHtml(title) + '</strong><br>属性情報なし</div>';
            }

            let body = '<div><strong>' + escapeHtml(title) + '</strong><br>';
            keys.forEach(function (key) {
                const value = record[key];
                body += escapeHtml(key) + ': ' + escapeHtml(typeof value === 'object' ? JSON.stringify(value) : value) + '<br>';
            });
            body += '</div>';
            return body;
        };

        const lonToTile = function (lon, zoom) {
            return Math.floor((lon + 180) / 360 * Math.pow(2, zoom));
        };
        const latToTile = function (lat, zoom) {
            return Math.floor((1 - Math.log(Math.tan(lat * Math.PI / 180) + 1 / Math.cos(lat * Math.PI / 180)) / Math.PI) / 2 * Math.pow(2, zoom));
        };

        const mapAdapter = (function () {
            if (useGoogleMaps) {
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
                    onClick: function (handler) {
                        googleMap.addListener('click', function (event) {
                            handler([event.latLng.lng(), event.latLng.lat()]);
                        });
                    },
                    getCenter: function () {
                        const center = googleMap.getCenter();
                        return [center.lng(), center.lat()];
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
                    }
                };
            }

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
                        }
                    },
                    layers: [
                        {
                            id: 'osm',
                            type: 'raster',
                            source: 'osm'
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
                onClick: function (handler) {
                    maplibreMap.on('click', function (event) {
                        handler([event.lngLat.lng, event.lngLat.lat]);
                    });
                },
                getCenter: function () {
                    const center = maplibreMap.getCenter();
                    return [center.lng, center.lat];
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

        const layerDefinitions = {
            'xpt001-points': {apiId: 'XPT001', title: '取引・成約ポイント（XPT001）', color: '#f59e0b', tile: true, extra: function () { return {from: '20241', to: '20244'}; }},
            'xpt002-points': {apiId: 'XPT002', title: '地価公示ポイント（XPT002）', color: '#a855f7', tile: true},
            'xkt001-planning': {apiId: 'XKT001', title: '都市計画区域/区域区分（XKT001）', color: '#4b5563', tile: true},
            'xkt002-zoning': {apiId: 'XKT002', title: '用途地域（XKT002）', color: '#2563eb', tile: true},
            'xkt003-location': {apiId: 'XKT003', title: '立地適正化計画（XKT003）', color: '#0ea5e9', tile: true},
            'xkt004-school': {apiId: 'XKT004', title: '小学校区（XKT004）', color: '#16a34a', tile: true},
            'xkt005-school': {apiId: 'XKT005', title: '中学校区（XKT005）', color: '#22c55e', tile: true},
            'xkt006-school': {apiId: 'XKT006', title: '学校（XKT006）', color: '#14b8a6', tile: true},
            'xkt007-childcare': {apiId: 'XKT007', title: '保育園・幼稚園（XKT007）', color: '#10b981', tile: true},
            'xkt010-medical': {apiId: 'XKT010', title: '医療機関（XKT010）', color: '#ef4444', tile: true},
            'xkt011-welfare': {apiId: 'XKT011', title: '福祉施設（XKT011）', color: '#ec4899', tile: true},
            'xkt013-population': {apiId: 'XKT013', title: '将来推計人口（XKT013）', color: '#7c3aed', tile: true}
        };

        const requestLayerData = async function (apiId, query) {
            const params = new URLSearchParams(Object.assign({api_id: apiId}, query || {}));
            const response = await fetch(layerDataEndpoint + '?' + params.toString(), {headers: {'Accept': 'application/json'}});
            if (!response.ok) {
                return null;
            }
            const payload = await response.json();
            return payload.success ? payload : null;
        };

        const ensureLayerLoaded = async function (layerId) {
            const def = layerDefinitions[layerId];
            if (!def || !activeLayers.has(layerId)) {
                return;
            }
            if (layerMarkerRegistry[layerId].length > 0) {
                return;
            }

            const center = mapAdapter.getCenter();
            const query = {};
            if (def.tile) {
                const z = 14;
                query.response_format = 'geojson';
                query.z = String(z);
                query.x = String(lonToTile(center[0], z));
                query.y = String(latToTile(center[1], z));
            }
            if (typeof def.extra === 'function') {
                Object.assign(query, def.extra());
            }
            const payload = await requestLayerData(def.apiId, query);
            if (!payload) {
                return;
            }

            const rows = Array.isArray(payload.body && payload.body.features)
                ? payload.body.features
                : (Array.isArray(payload.data) ? payload.data : []);
            let plotted = 0;
            rows.forEach(function (row) {
                const coordinates = extractCoordinates(row);
                if (!coordinates) {
                    return;
                }
                const props = row.properties && typeof row.properties === 'object' ? row.properties : row;
                const marker = mapAdapter.addPoint(props, coordinates, def.color, createGenericPopupHtml(def.title, props));
                layerMarkerRegistry[layerId].push(marker);
                plotted += 1;
            });
            if (plotted > 0) {
                applyLayerVisibility();
                setMapStatus(def.title + ' を ' + plotted + ' 件表示しました。', 'info');
            }
        };

        const runDueDiligence = async function (coordinates) {
            dueDiligenceResult.textContent = '調査中...';
            const z = 14;
            const baseQuery = {
                response_format: 'geojson',
                z: String(z),
                x: String(lonToTile(coordinates[0], z)),
                y: String(latToTile(coordinates[1], z))
            };
            const [zoning, urbanArea, flood] = await Promise.all([
                requestLayerData('XKT002', baseQuery),
                requestLayerData('XKT001', baseQuery),
                requestLayerData('XKT013', baseQuery)
            ]);
            const zoningCount = zoning && zoning.body && Array.isArray(zoning.body.features) ? zoning.body.features.length : 0;
            const urbanCount = urbanArea && urbanArea.body && Array.isArray(urbanArea.body.features) ? urbanArea.body.features.length : 0;
            const popCount = flood && flood.body && Array.isArray(flood.body.features) ? flood.body.features.length : 0;
            dueDiligenceResult.innerHTML =
                '都市計画判定(XKT001): <strong>' + urbanCount + '件</strong> / ' +
                '用途地域(XKT002): <strong>' + zoningCount + '件</strong> / ' +
                '将来人口メッシュ(XKT013): <strong>' + popCount + '件</strong>';
        };

        const loadAppraisalSummary = async function () {
            if (selectedArea === '' || selectedYear === '') {
                appraisalSummary.textContent = '査定条件（area/year）が不足しています。';
                return;
            }

            const payload = await requestLayerData('XCT001', {
                year: selectedYear,
                area: selectedArea,
                division: '00'
            });
            if (!payload || !Array.isArray(payload.data) || payload.data.length === 0) {
                appraisalSummary.textContent = '鑑定評価書データ（XCT001）が取得できませんでした。';
                return;
            }

            const values = [];
            payload.data.forEach(function (row) {
                Object.keys(row).forEach(function (key) {
                    const v = row[key];
                    if (typeof v === 'string' && /価格|price/i.test(key)) {
                        const n = Number(v.replace(/[^\d.-]/g, ''));
                        if (Number.isFinite(n) && n > 0) {
                            values.push(n);
                        }
                    }
                });
            });
            if (values.length === 0) {
                appraisalSummary.textContent = '鑑定価格の数値項目を抽出できませんでした。';
                return;
            }

            const sum = values.reduce(function (acc, n) { return acc + n; }, 0);
            const avg = Math.round(sum / values.length);
            appraisalSummary.innerHTML = 'XCT001件数: <strong>' + payload.data.length + '件</strong> / 推定平均単価: <strong>' + avg.toLocaleString() + '</strong>';
        };

        const renderMapPoints = async function () {
            if (!Array.isArray(records) || records.length === 0) {
                setMapStatus('表示対象データがありません。', 'warning');
                return;
            }

            setMapStatus(modeName[currentMode] + 'モードでレイヤーを準備中です。', 'secondary');

            const geocodeCache = new Map();
            const geocodeLimit = 20;
            let geocodeCount = 0;
            let plottedCount = 0;
            const plottedCoordinates = [];

            for (const record of records) {
                let coordinates = extractCoordinates(record);
                if (!coordinates && geocodeCount < geocodeLimit) {
                    const address = buildAddress(record);
                    if (address) {
                        if (geocodeCache.has(address)) {
                            coordinates = geocodeCache.get(address);
                        } else {
                            coordinates = await geocodeAddress(address);
                            geocodeCache.set(address, coordinates);
                            geocodeCount += 1;
                            await sleep(200);
                        }
                    }
                }
                if (!coordinates) {
                    continue;
                }

                const price = toPriceNumber(record.TradePrice);
                const formattedPrice = Number.isFinite(price) ? price.toLocaleString() + ' 円' : 'N/A';
                const popupHtml = createPopupHtml(record, formattedPrice);
                const markerWrapper = mapAdapter.addPoint(record, coordinates, priceToColor(price), popupHtml);
                layerMarkerRegistry['price-points'].push(markerWrapper);
                plottedCount += 1;
                plottedCoordinates.push(coordinates);
            }

            if (plottedCount === 0) {
                setMapStatus('位置情報を取得できるデータがなく、地図上に表示できませんでした。', 'warning');
                return;
            }

            mapAdapter.fitToBounds(plottedCoordinates);
            applyLayerVisibility();
            const mapText = useGoogleMaps ? 'Google Maps（Street View利用可）' : 'MapLibre';
            setMapStatus(modeName[currentMode] + 'モードで ' + plottedCount + ' 件を表示中です（' + mapText + '）。', 'success');
            await Promise.all(Array.from(activeLayers).map(function (layerId) {
                return ensureLayerLoaded(layerId);
            }));
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
                Array.from(activeLayers).forEach(function (layerId) {
                    ensureLayerLoaded(layerId);
                });
            });
        });

        syncModeButtons();
        renderLayerControls();
        loadAppraisalSummary().catch(function () {
            appraisalSummary.textContent = '査定サマリーの取得に失敗しました。';
        });

        mapAdapter.ready(function () {
            renderMapPoints().catch(function () {
                setMapStatus('地図データの表示中にエラーが発生しました。', 'danger');
            });
        });

        mapAdapter.onClick(function (coordinates) {
            runDueDiligence(coordinates).catch(function () {
                dueDiligenceResult.textContent = '調査に失敗しました。';
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
</body>
</html>
