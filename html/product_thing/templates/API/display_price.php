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
        <div id="map" aria-label="不動産データの地図"></div>
        <div id="mapStatus" class="alert alert-secondary mt-3 mb-0">
            地図を準備中です。
        </div>
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
        const layerDataEndpoint = <?= json_encode($this->Url->build(['controller' => 'LayerData', 'action' => 'layerData'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const geoJsonEndpoint = <?= json_encode($this->Url->build(['controller' => 'MlitProxy', 'action' => 'geojson'], ['fullBase' => false]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const mapStatus = document.getElementById('mapStatus');
        let records = <?= json_encode($mapRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
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
            {id: 'hazard-zones', label: '防災リスクレイヤー（準備中）', available: false},
            {id: 'urban-plan', label: '都市計画レイヤー（準備中）', available: false},
            {id: 'population-heat', label: '人口ヒートマップ（準備中）', available: false},
            {id: 'nature-parks', label: '自然公園地域（XKT019）', available: true}
        ];
        const modeDefaults = {
            living: ['price-points', 'medical-facilities', 'facility-poi'],
            safety: ['price-points', 'hazard-zones'],
            invest: ['price-points', 'urban-plan', 'population-heat'],
            nature: ['price-points', 'nature-parks']
        };
        let currentMode = 'living';
        const activeLayers = new Set(modeDefaults[currentMode]);
        const layerMarkerRegistry = {
            'price-points': [],
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
                    if (targetLayerId === 'nature-parks' && checkbox.checked) {
                        loadNatureParksLayer();
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

        const createNaturePopupHtml = function (record) {
            const keys = Object.keys(record || {}).slice(0, 6);
            if (keys.length === 0) {
                return '<div><strong>自然公園地域</strong><br>属性情報なし</div>';
            }

            let body = '<div><strong>自然公園地域（XKT019）</strong><br>';
            keys.forEach(function (key) {
                body += escapeHtml(key) + ': ' + escapeHtml(record[key]) + '<br>';
            });
            body += '</div>';
            return body;
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

        const loadNatureParksLayer = async function () {
            if (currentMode !== 'nature' || !activeLayers.has('nature-parks')) {
                return;
            }
            if (layerMarkerRegistry['nature-parks'].length > 0) {
                return;
            }

            const center = mapAdapter.getCenter();
            const params = new URLSearchParams({
                api_id: 'XKT019',
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
                const marker = mapAdapter.addPoint(row, coordinates, '#15803d', createNaturePopupHtml(row));
                layerMarkerRegistry['nature-parks'].push(marker);
                plotted += 1;
            });
            if (plotted > 0) {
                applyLayerVisibility();
                setMapStatus('自然環境モードで自然公園地域レイヤーを ' + plotted + ' 件表示しました。', 'info');
            }
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
            if (currentMode === 'nature') {
                loadNatureParksLayer();
            }
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
                if (currentMode === 'nature') {
                    loadNatureParksLayer();
                }
            });
        });

        syncModeButtons();
        renderLayerControls();

        mapAdapter.ready(function () {
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
                            return;
                        }
                        records = nextRecords;
                        return renderMapPoints(false);
                    }).catch(function () {
                        setMapStatus('地図移動後の再取得に失敗しました。', 'warning');
                    });
                }, 700);
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
