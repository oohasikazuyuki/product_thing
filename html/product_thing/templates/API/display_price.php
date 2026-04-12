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
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4 text-center">
        不動産取引価格情報
    </h1>

    <div class="mb-4">
        <h2 class="h5 mb-3">地図表示</h2>
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
                            <td class="text-center"><?= htmlspecialchars($record['Period'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">Invalid data format: <?= htmlspecialchars($record); ?></td>
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
<script src="https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js"></script>
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
        const map = new maplibregl.Map({
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
        map.addControl(new maplibregl.NavigationControl(), 'top-right');

        const mapStatus = document.getElementById('mapStatus');
        const records = <?= json_encode($mapRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const setMapStatus = function (message, variant) {
            const color = variant || 'secondary';
            mapStatus.className = 'alert alert-' + color + ' mt-3 mb-0';
            mapStatus.textContent = message;
        };

        const sleep = function (ms) {
            return new Promise(function (resolve) {
                setTimeout(resolve, ms);
            });
        };

        const extractCoordinates = function (record) {
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

        const renderMapPoints = async function () {
            if (!Array.isArray(records) || records.length === 0) {
                setMapStatus('表示対象データがありません。', 'warning');
                return;
            }

            setMapStatus('取引データから地図ポイントを作成中です。', 'secondary');

            const geocodeCache = new Map();
            const geocodeLimit = 20;
            let geocodeCount = 0;
            let plottedCount = 0;
            const bounds = new maplibregl.LngLatBounds();

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
                const marker = new maplibregl.Marker({color: priceToColor(price)})
                    .setLngLat(coordinates)
                    .setPopup(new maplibregl.Popup({offset: 25}).setHTML(
                        '<div>' +
                        '<strong>' + escapeHtml(record.Prefecture) + ' ' + escapeHtml(record.Municipality) + '</strong><br>' +
                        '地区: ' + escapeHtml(record.DistrictName || 'N/A') + '<br>' +
                        '価格: ' + escapeHtml(formattedPrice) + '<br>' +
                        '時期: ' + escapeHtml(record.Period || 'N/A') +
                        '</div>'
                    ))
                    .addTo(map);

                if (marker) {
                    plottedCount += 1;
                    bounds.extend(coordinates);
                }
            }

            if (plottedCount === 0) {
                setMapStatus('位置情報を取得できるデータがなく、地図上に表示できませんでした。', 'warning');
                return;
            }

            map.fitBounds(bounds, {
                padding: 40,
                maxZoom: 13
            });
            setMapStatus('地図に ' + plottedCount + ' 件の取引データを表示しました。', 'success');
        };

        map.on('load', function () {
            renderMapPoints().catch(function () {
                setMapStatus('地図データの表示中にエラーが発生しました。', 'danger');
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
