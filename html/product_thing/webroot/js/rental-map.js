(function () {
    'use strict';

    const config = window.RENTAL_MAP_CONFIG || {};
    const defaultCenter = [139.6917, 35.6895];
    const defaultZoom = 12;
    let map;
    let markers = [];
    let markersById = {};
    let currentPage = 1;
    let plottedCount = 0;
    let itemsById = {};
    let lastPayload = null;
    let walkPoints = [];
    let walkIndex = 0;
    let walkTimer = null;
    let walkMarker = null;
    let favMode = false;
    let stationMarker = null;
    const PER_PAGE = 20;
    let mapReady = false;

    const escapeHtml = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const setStatus = function (message) {
        const el = document.getElementById('searchStatus');
        if (el) {
            el.textContent = message;
        }
    };

    const setResultCount = function (text) {
        const el = document.getElementById('resultCount');
        if (el) {
            el.textContent = text;
        }
    };

    const setLoading = function (loading) {
        const btn = document.getElementById('searchBtn');
        if (!btn) {
            return;
        }
        btn.classList.toggle('is-loading', loading);
        btn.textContent = loading ? '検索中…' : '検索';
    };

    const isJapanCoordinate = function (lat, lng) {
        return Number.isFinite(lat) && Number.isFinite(lng) &&
            lat >= 20 && lat <= 46 &&
            lng >= 122 && lng <= 154;
    };

    const fillCityOptions = function (cities, preferredCity) {
        const citySelect = document.getElementById('city');
        if (!citySelect) {
            return;
        }
        citySelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '指定なし';
        citySelect.appendChild(empty);

        (cities || []).forEach(function (city) {
            const opt = document.createElement('option');
            opt.value = city.id;
            opt.textContent = city.name;
            if (preferredCity && city.id === preferredCity) {
                opt.selected = true;
            }
            citySelect.appendChild(opt);
        });
    };

    const loadCities = async function (preferredCity) {
        const prefecture = document.getElementById('prefecture');
        const citySelect = document.getElementById('city');
        if (!prefecture || !citySelect || !config.citiesEndpoint) {
            return;
        }
        citySelect.disabled = true;
        citySelect.innerHTML = '<option value="">読み込み中…</option>';
        try {
            const params = new URLSearchParams({
                prefecture: prefecture.value,
                year: '2024'
            });
            const response = await fetch(config.citiesEndpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                citySelect.innerHTML = '<option value="">市区町村を取得できません</option>';
                setStatus((payload && payload.message) || '市区町村の取得に失敗しました');
                return;
            }
            fillCityOptions(payload.cities || [], preferredCity || '');
        } catch (error) {
            citySelect.innerHTML = '<option value="">市区町村を取得できません</option>';
            setStatus('市区町村の取得中にエラーが発生しました');
        } finally {
            citySelect.disabled = false;
        }
    };

    const clearMarkers = function () {
        markers.forEach(function (marker) {
            marker.remove();
        });
        markers = [];
        markersById = {};
    };

    // 一覧のカードと地図のマーカーを連動させる（地図で見る／マーカークリック）
    const activateItem = function (id, flyToMarker) {
        document.querySelectorAll('.listing-item.is-active').forEach(function (el) {
            el.classList.remove('is-active');
        });
        const card = document.querySelector('.listing-item[data-id="' + String(id).replace(/"/g, '') + '"]');
        if (card) {
            card.classList.add('is-active');
            if (!flyToMarker) {
                card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
        const entry = markersById[id];
        if (flyToMarker && entry && map && mapReady) {
            map.flyTo({ center: entry.lngLat, zoom: 16, essential: true });
            if (!entry.marker.getPopup().isOpen()) {
                entry.marker.togglePopup();
            }
        }
    };

    const resetMapView = function () {
        if (!map || !mapReady) {
            return;
        }
        map.jumpTo({ center: defaultCenter, zoom: defaultZoom });
    };

    // ステータスに説明と「Googleマップで開く」リンクを出す（別タブは利用者が選んだときだけ開く）
    const setStatusWithLink = function (message, url) {
        const el = document.getElementById('searchStatus');
        if (!el) {
            return;
        }
        el.textContent = message + ' ';
        if (url) {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = 'Googleマップで開く';
            el.appendChild(link);
        }
    };

    const clearRoute = function () {
        walkPoints = [];
        const walkBtn = document.getElementById('walkBtn');
        if (walkBtn) { walkBtn.hidden = true; }
        if (stationMarker) {
            stationMarker.remove();
            stationMarker = null;
        }
        if (map && mapReady && map.getSource('walk-route')) {
            map.getSource('walk-route').setData({ type: 'FeatureCollection', features: [] });
        }
    };

    // ---- ストリートビューで道のりを歩く ----
    const toRad = function (d) { return d * Math.PI / 180; };
    const distanceM = function (a, b) {
        const dLat = toRad(b[1] - a[1]);
        const dLng = toRad(b[0] - a[0]);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[1])) * Math.cos(toRad(b[1])) * Math.sin(dLng / 2) ** 2;
        return 12742000 * Math.asin(Math.sqrt(h));
    };
    const bearing = function (a, b) {
        const y = Math.sin(toRad(b[0] - a[0])) * Math.cos(toRad(b[1]));
        const x = Math.cos(toRad(a[1])) * Math.sin(toRad(b[1])) - Math.sin(toRad(a[1])) * Math.cos(toRad(b[1])) * Math.cos(toRad(b[0] - a[0]));
        return Math.round((Math.atan2(y, x) * 180 / Math.PI + 360) % 360);
    };
    // 経路を約stepMごとの地点に間引き、各地点に「次の地点への向き」を付ける
    const sampleRoute = function (line, stepM) {
        const pts = [line[0]];
        let acc = 0;
        for (let i = 1; i < line.length; i += 1) {
            acc += distanceM(line[i - 1], line[i]);
            if (acc >= stepM) {
                pts.push(line[i]);
                acc = 0;
            }
        }
        const last = line[line.length - 1];
        if (pts[pts.length - 1] !== last) { pts.push(last); }
        return pts.map(function (p, i) {
            const next = pts[i + 1];
            const prev = pts[i - 1];
            return { lng: p[0], lat: p[1], heading: next ? bearing(p, next) : (prev ? bearing(prev, p) : 0) };
        });
    };
    const setWalkRoute = function (route) {
        walkPoints = Array.isArray(route.line) && route.line.length > 1 ? sampleRoute(route.line, 30) : [];
        const btn = document.getElementById('walkBtn');
        if (btn) { btn.hidden = walkPoints.length === 0; }
    };
    const walkFrameUrl = function (pt, external) {
        const loc = pt.lat + ',' + pt.lng;
        if (external) {
            return 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' + loc + '&heading=' + pt.heading;
        }
        if (config.googleMapsApiKey) {
            return 'https://www.google.com/maps/embed/v1/streetview?key=' + encodeURIComponent(config.googleMapsApiKey) +
                '&location=' + loc + '&heading=' + pt.heading + '&pitch=0&fov=90';
        }
        return 'https://maps.google.com/maps?layer=c&cbll=' + loc + '&cbp=11,' + pt.heading + ',0,0,0&output=svembed';
    };
    const stopWalk = function () {
        if (walkTimer) { clearInterval(walkTimer); walkTimer = null; }
        const play = document.getElementById('walkPlay');
        if (play) { play.textContent = '▶ 自動再生'; play.setAttribute('aria-pressed', 'false'); }
    };
    const showWalkPoint = function (i) {
        if (walkPoints.length === 0) { return; }
        walkIndex = Math.max(0, Math.min(walkPoints.length - 1, i));
        const pt = walkPoints[walkIndex];
        document.getElementById('walkFrame').src = walkFrameUrl(pt, false);
        document.getElementById('walkExternal').href = walkFrameUrl(pt, true);
        document.getElementById('walkInfo').textContent = (walkIndex + 1) + ' / ' + walkPoints.length + ' 地点' +
            (walkIndex === 0 ? '（駅）' : (walkIndex === walkPoints.length - 1 ? '（物件）' : ''));
        document.getElementById('walkPrev').disabled = walkIndex === 0;
        document.getElementById('walkNext').disabled = walkIndex === walkPoints.length - 1;
        if (map) {
            if (!walkMarker) {
                walkMarker = new maplibregl.Marker({ color: '#f59e0b' });
            }
            walkMarker.setLngLat([pt.lng, pt.lat]).addTo(map);
        }
        if (walkIndex === walkPoints.length - 1) { stopWalk(); }
    };
    const toggleWalkPlay = function () {
        if (walkTimer) { stopWalk(); return; }
        if (walkIndex >= walkPoints.length - 1) { showWalkPoint(0); }
        const play = document.getElementById('walkPlay');
        play.textContent = '⏸ 停止';
        play.setAttribute('aria-pressed', 'true');
        walkTimer = setInterval(function () { showWalkPoint(walkIndex + 1); }, 2500);
    };

    const drawRoute = function (route) {
        const geojson = { type: 'Feature', geometry: { type: 'LineString', coordinates: route.line } };
        if (map.getSource('walk-route')) {
            map.getSource('walk-route').setData(geojson);
        } else {
            map.addSource('walk-route', { type: 'geojson', data: geojson });
            map.addLayer({
                id: 'walk-route-line', type: 'line', source: 'walk-route',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#2563eb', 'line-width': 6, 'line-opacity': 0.85 }
            });
        }
        if (stationMarker) {
            stationMarker.remove();
        }
        stationMarker = new maplibregl.Marker({ color: '#2563eb' })
            .setLngLat([route.station.lng, route.station.lat])
            .setPopup(new maplibregl.Popup({ offset: 18, closeButton: false }).setText(route.station.name))
            .addTo(map);
        const bounds = new maplibregl.LngLatBounds(route.line[0], route.line[0]);
        route.line.forEach(function (c) { bounds.extend(c); });
        map.fitBounds(bounds, { padding: 80, maxZoom: 17, essential: true });
    };

    // 駅から物件までの徒歩ルートを地図に表示（取得できない場合はGoogleマップを開く）
    const showRoute = async function (item) {
        if (!item || !item.station) {
            return;
        }
        const lat = Number(item.lat);
        const lng = Number(item.lng);
        if (!isJapanCoordinate(lat, lng) || !config.routeEndpoint) {
            setStatusWithLink('この物件は位置情報がないため、地図にルートを表示できません。', routeUrl(item));
            return;
        }
        activateItem(item.id, false);
        setStatus('駅からの道のりを計算中…');
        try {
            const params = new URLSearchParams({ station: item.station, lat: String(lat), lng: String(lng) });
            const response = await fetch(config.routeEndpoint + '?' + params.toString(), { headers: { Accept: 'application/json' } });
            const route = await response.json();
            if (!route.ok) {
                setStatusWithLink(route.message || '駅からの道のりを取得できませんでした。', routeUrl(item));
                return;
            }
            drawRoute(route);
            setWalkRoute(route);
            setStatus(route.station.name + 'から約 ' + route.distance_m.toLocaleString() + ' m（徒歩 約' + route.minutes + '分）' +
                (item.walk_minutes ? ' ／ 掲載情報: 徒歩' + item.walk_minutes + '分' : '') + ' ・ 経路は地図データによる目安です');
        } catch (error) {
            setStatus('駅からの道のりの取得中にエラーが発生しました。');
        }
    };

    // 駅→物件の徒歩ルートをGoogleマップで開くURL（駅名が無い物件はnull）
    const routeUrl = function (item) {
        if (!item.station) {
            return null;
        }
        const prefecture = (String(item.address || '').match(/^(北海道|.{2,3}?[都道府県])/) || [''])[0];
        const station = String(item.station).replace(/駅$/, '') + '駅';
        const lat = Number(item.lat);
        const lng = Number(item.lng);
        const destination = isJapanCoordinate(lat, lng) ? lat + ',' + lng : String(item.address || item.name || '');
        return 'https://www.google.com/maps/dir/?api=1&travelmode=walking' +
            '&origin=' + encodeURIComponent(prefecture + station) +
            '&destination=' + encodeURIComponent(destination);
    };

    // 元ページ（SUUMO）へのリンク。http(s) 以外は出さない
    const detailLink = function (item, cls) {
        return /^https?:\/\//.test(item.detail_url || '')
            ? '<a class="' + cls + '" href="' + escapeHtml(item.detail_url) + '" target="_blank" rel="noopener">SUUMOで詳細を見る</a>'
            : '';
    };

    const popupHtml = function (item) {
        return '<div style="font:500 13px/1.45 \'IBM Plex Sans JP\',sans-serif;max-width:220px;">' +
            '<strong style="font-size:14px;">' + escapeHtml(item.name) + '</strong><br>' +
            '<span style="color:#3d4f5f;">' + escapeHtml(item.address) + '</span><br>' +
            '<span style="color:#0d6b63;font-weight:700;">' + escapeHtml(item.rent_text || 'N/A') + '</span><br>' +
            escapeHtml(item.layout || 'N/A') + (item.area_m2 ? ' · ' + escapeHtml(item.area_m2) + '㎡' : '') +
            (item.station ? ' · ' + escapeHtml(item.station) : '') +
            (item.walk_minutes ? ' 徒歩' + escapeHtml(item.walk_minutes) + '分' : '') +
            (detailLink(item, 'small') ? '<br>' + detailLink(item, 'small') : '') +
            (item.station ? '<br><button type="button" class="btn btn-link btn-sm p-0" data-route-id="' + escapeHtml(item.id) + '">駅からの道のりを地図に表示</button>' +
                ' <a class="small" href="' + escapeHtml(routeUrl(item)) + '" target="_blank" rel="noopener">Googleマップ</a>' : '') +
            '</div>';
    };

    const renderMap = function (items) {
        if (!map || !mapReady) {
            return;
        }
        clearMarkers();
        clearRoute();
        const plotted = [];
        plottedCount = 0;
        (items || []).forEach(function (item) {
            const lat = Number(item.lat);
            const lng = Number(item.lng);
            if (!isJapanCoordinate(lat, lng)) {
                return;
            }
            const marker = new maplibregl.Marker({ color: '#14532d' })
                .setLngLat([lng, lat])
                .setPopup(new maplibregl.Popup({ offset: 18, closeButton: false }).setHTML(popupHtml(item)))
                .addTo(map);
            markers.push(marker);
            markersById[item.id] = { marker: marker, lngLat: [lng, lat] };
            marker.getElement().addEventListener('click', function () {
                activateItem(item.id, false);
            });
            plotted.push([lng, lat]);
            plottedCount += 1;
        });

        if (plotted.length === 0) {
            resetMapView();
            return;
        }
        if (plotted.length === 1) {
            map.flyTo({ center: plotted[0], zoom: 14, essential: true });
            return;
        }

        const bounds = new maplibregl.LngLatBounds(plotted[0], plotted[0]);
        plotted.slice(1).forEach(function (coordinate) {
            bounds.extend(coordinate);
        });
        if (!bounds.isEmpty()) {
            map.fitBounds(bounds, {
                padding: 56,
                maxZoom: 14,
                minZoom: 10,
                essential: true
            });
        } else {
            resetMapView();
        }
    };

    // お気に入り：このブラウザ(localStorage)に物件ごと保存する。使えない環境では保存せず動作は続ける
    const FAV_KEY = 'rental.favorites';
    const loadFavs = function () {
        try {
            const data = JSON.parse(localStorage.getItem(FAV_KEY) || '{}');
            return data && typeof data === 'object' ? data : {};
        } catch (e) {
            return {};
        }
    };
    let favs = loadFavs();
    const saveFavs = function () {
        try { localStorage.setItem(FAV_KEY, JSON.stringify(favs)); } catch (e) { /* 保存できなくても閲覧は続行 */ }
    };
    const favButton = function (item) {
        const on = !!favs[item.id];
        return '<button type="button" class="btn btn-link btn-sm p-0 listing-locate fav-btn" data-fav-id="' + escapeHtml(item.id) +
            '" aria-pressed="' + on + '">' + (on ? '★ 保存済み' : '☆ お気に入り') + '</button>';
    };
    const updateFavToggle = function () {
        const toggle = document.getElementById('favToggle');
        if (toggle) {
            toggle.textContent = '★ お気に入り (' + Object.keys(favs).length + ')';
            toggle.setAttribute('aria-pressed', String(favMode));
            toggle.classList.toggle('active', favMode);
        }
    };
    const showFavorites = function () {
        const items = Object.keys(favs).map(function (id) { return favs[id]; });
        const payload = { items: items, total: items.length, per_page: Math.max(items.length, 1) };
        renderMap(items);
        renderResults(payload);
        renderPager({ total: 0 });
        if (items.length === 0) {
            document.getElementById('rentalResults').innerHTML = '<div class="listing-empty">お気に入りはまだありません。物件の「☆ お気に入り」で保存できます。</div>';
        }
        setStatus('お気に入り · このブラウザに保存した物件です');
    };
    const setFavMode = function (on) {
        favMode = on;
        updateFavToggle();
        if (on) {
            showFavorites();
        } else if (lastPayload) {
            renderMap(lastPayload.items || []);
            renderResults(lastPayload);
            renderPager(lastPayload);
            setStatus('検索結果に戻りました。');
        } else {
            document.getElementById('rentalResults').innerHTML = '<div class="listing-empty">条件を指定して検索してください。</div>';
            renderMap([]);
            setResultCount('—');
            setStatus('条件を選んで「検索」を押してください。');
        }
    };

    const thumbHtml = function (item) {
        if (item.photo_url) {
            return '<img class="listing-thumb" src="' + escapeHtml(item.photo_url) + '" alt="" loading="lazy">';
        }
        return '<div class="listing-thumb is-empty" aria-hidden="true">No image</div>';
    };

    const renderResults = function (payload) {
        const root = document.getElementById('rentalResults');
        root.innerHTML = '';
        const items = Array.isArray(payload.items) ? payload.items : [];
        itemsById = {};
        items.forEach(function (item) { itemsById[item.id] = item; });
        if (items.length === 0) {
            root.innerHTML = '<div class="listing-empty">該当する物件がありません。条件を広げて再検索してください。</div>';
            setResultCount('0 件');
            return;
        }

        const total = payload.total || items.length;
        const from = (currentPage - 1) * (payload.per_page || PER_PAGE) + 1;
        setResultCount(from.toLocaleString() + '–' + (from + items.length - 1).toLocaleString() + ' / ' + Number(total).toLocaleString() + ' 件' +
            (payload.scanned ? '（先頭' + payload.scanned + '件中）' : ''));
        const frag = document.createDocumentFragment();
        items.forEach(function (item) {
            const el = document.createElement('div');
            el.className = 'listing-item';
            el.dataset.id = item.id;
            const tags = [];
            if (item.layout) {
                tags.push(item.layout + (item.area_m2 ? ' / ' + item.area_m2 + '㎡' : ''));
            } else if (item.area_m2) {
                tags.push(item.area_m2 + '㎡');
            }
            if (item.station) {
                tags.push(item.station + (item.walk_minutes ? ' 徒歩' + item.walk_minutes + '分' : ''));
            }
            if (item.deposit_text || item.key_money_text) {
                tags.push('敷 ' + (item.deposit_text || '-') + ' / 礼 ' + (item.key_money_text || '-'));
            }
            el.innerHTML =
                thumbHtml(item) +
                '<div class="listing-body">' +
                '<h3>' + escapeHtml(item.name || '物件名なし') + '</h3>' +
                '<p class="listing-meta">' + escapeHtml(item.address || '') + '</p>' +
                '<p class="listing-price">' + escapeHtml(item.rent_text || '賃料不明') +
                (item.maintenance_text ? '<span style="color:var(--muted);font-weight:500;font-size:0.85rem;"> / 管理費 ' + escapeHtml(item.maintenance_text) + '</span>' : '') +
                '</p>' +
                (tags.length
                    ? '<div class="listing-tags">' + tags.map(function (t) {
                        return '<span>' + escapeHtml(t) + '</span>';
                    }).join('') + '</div>'
                    : '') +
                '<div class="listing-actions">' +
                favButton(item) +
                detailLink(item, 'btn btn-link btn-sm p-0 listing-locate') +
                (markersById[item.id]
                    ? '<button type="button" class="btn btn-link btn-sm p-0 listing-locate" data-locate="' + escapeHtml(item.id) + '">地図で見る</button>'
                    : '') +
                (routeUrl(item)
                    ? '<button type="button" class="btn btn-link btn-sm p-0 listing-locate" data-route-id="' + escapeHtml(item.id) + '">駅からの道のり</button>'
                    : '') +
                '</div>' +
                '</div>';
            frag.appendChild(el);
        });
        root.appendChild(frag);
    };

    const renderPager = function (payload) {
        const pager = document.getElementById('rentalPager');
        if (!pager) {
            return;
        }
        const total = Number(payload.total) || 0;
        const pages = Math.ceil(total / (Number(payload.per_page) || PER_PAGE));
        if (pages <= 1) {
            pager.innerHTML = '';
            return;
        }
        const page = currentPage;
        pager.innerHTML = '<ul class="pagination pagination-sm justify-content-center mb-0">' +
            '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><button type="button" class="page-link" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>« 前へ</button></li>' +
            '<li class="page-item disabled"><span class="page-link">' + page + ' / ' + pages + '</span></li>' +
            '<li class="page-item' + (page >= pages ? ' disabled' : '') + '"><button type="button" class="page-link" data-page="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>次へ »</button></li>' +
            '</ul>';
    };

    const searchRentals = async function (page) {
        currentPage = page || 1;
        favMode = false;
        updateFavToggle();
        const form = document.getElementById('rentalSearchForm');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        params.set('page', String(currentPage));
        const layouts = [];
        formData.forEach(function (value, key) {
            if (key === 'layouts') {
                layouts.push(String(value));
            } else if (String(value).trim() !== '') {
                params.set(key, String(value).trim());
            }
        });
        if (layouts.length > 0) {
            params.set('layouts', layouts.join(','));
        }

        setLoading(true);
        setStatus('検索中…');
        try {
            const response = await fetch(config.searchEndpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok) {
                setStatus((payload && payload.message) || '検索に失敗しました');
                setResultCount('—');
                return;
            }
            lastPayload = payload;
            renderMap(payload.items || []);
            renderResults(payload);
            renderPager(payload);
            const mode = payload.demo ? 'デモ' : 'ライブ';
            setStatus(
                mode + ' · ' + (payload.provider_label || payload.provider || '') +
                ((payload.items || []).length > 0 ? ' · 地図に ' + plottedCount + ' / ' + (payload.items || []).length + ' 件（位置情報のない物件は一覧のみ）' : '') +
                (payload.message ? ' — ' + payload.message : '')
            );
            const chip = document.getElementById('providerChip');
            if (chip) {
                chip.classList.toggle('badge-warning', !!payload.demo);
                chip.classList.toggle('badge-success', !payload.demo);
                chip.textContent = (payload.demo ? 'デモモード' : 'ライブ接続') +
                    ' · ' + (payload.provider_label || payload.provider || '');
            }
        } catch (error) {
            setStatus('検索中にエラーが発生しました');
            setResultCount('—');
        } finally {
            setLoading(false);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        map = new maplibregl.Map({
            container: 'rentalMap',
            style: {
                version: 8,
                sources: {
                    osm: {
                        type: 'raster',
                        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                        tileSize: 256,
                        attribution: '&copy; OpenStreetMap contributors'
                    },
                    'gsi-photo': {
                        type: 'raster',
                        tiles: ['https://cyberjapandata.gsi.go.jp/xyz/seamlessphoto/{z}/{x}/{y}.jpg'],
                        tileSize: 256,
                        attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">国土地理院</a>'
                    }
                },
                layers: [
                    { id: 'osm', type: 'raster', source: 'osm' },
                    { id: 'gsi-photo', type: 'raster', source: 'gsi-photo', layout: { visibility: 'none' } }
                ]
            },
            center: defaultCenter,
            zoom: defaultZoom,
            minZoom: 5,
            maxZoom: 18
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        document.querySelectorAll('[data-basemap]').forEach(function (button) {
            button.addEventListener('click', function () {
                const aerial = button.dataset.basemap === 'aerial';
                map.setLayoutProperty('gsi-photo', 'visibility', aerial ? 'visible' : 'none');
                document.querySelectorAll('[data-basemap]').forEach(function (b) {
                    b.classList.toggle('active', b === button);
                });
            });
        });

        document.getElementById('rentalSearchForm').addEventListener('submit', function (event) {
            event.preventDefault();
            searchRentals(1);
        });

        document.getElementById('prefecture').addEventListener('change', function () {
            loadCities('');
        });

        // 下限が上限を超えたら、もう一方の選択を外す
        [['month_money_room', 'month_money_room_h'], ['area_min', 'area_max']].forEach(function (pair) {
            pair.forEach(function (id, index) {
                document.getElementById(id).addEventListener('change', function () {
                    const min = Number(document.getElementById(pair[0]).value);
                    const max = Number(document.getElementById(pair[1]).value);
                    if (min && max && min > max) {
                        document.getElementById(pair[1 - index]).value = '';
                    }
                });
            });
        });

        document.getElementById('rentalPager').addEventListener('click', function (event) {
            const button = event.target.closest('[data-page]');
            if (button && !button.disabled) {
                searchRentals(Number(button.dataset.page));
                document.getElementById('rentalResults').scrollTop = 0;
            }
        });

        // 地図のポップアップ内ボタン（動的に作られるのでdocumentで受ける）
        document.addEventListener('click', function (event) {
            const route = event.target.closest('.maplibregl-popup [data-route-id]');
            if (route) {
                showRoute(itemsById[route.dataset.routeId]);
            }
        });

        document.getElementById('rentalResults').addEventListener('click', function (event) {
            const route = event.target.closest('[data-route-id]');
            if (route) {
                event.preventDefault();
                event.stopPropagation();
                showRoute(itemsById[route.dataset.routeId]);
                return;
            }
            const fav = event.target.closest('[data-fav-id]');
            if (fav) {
                event.preventDefault();
                const id = fav.dataset.favId;
                if (favs[id]) {
                    delete favs[id];
                } else if (itemsById[id]) {
                    favs[id] = itemsById[id];
                }
                saveFavs();
                if (favMode) {
                    showFavorites();
                } else {
                    const on = !!favs[id];
                    fav.textContent = on ? '★ 保存済み' : '☆ お気に入り';
                    fav.setAttribute('aria-pressed', String(on));
                }
                updateFavToggle();
                return;
            }
            const locate = event.target.closest('[data-locate]');
            if (locate) {
                event.preventDefault();
                event.stopPropagation();
                activateItem(locate.dataset.locate, true);
            }
        });

        const walkBtn = document.getElementById('walkBtn');
        if (walkBtn) {
            walkBtn.addEventListener('click', function () {
                if (walkPoints.length === 0) { return; }
                $('#walkModal').modal('show');
                showWalkPoint(0);
            });
            document.getElementById('walkPrev').addEventListener('click', function () { stopWalk(); showWalkPoint(walkIndex - 1); });
            document.getElementById('walkNext').addEventListener('click', function () { stopWalk(); showWalkPoint(walkIndex + 1); });
            document.getElementById('walkPlay').addEventListener('click', toggleWalkPlay);
            $('#walkModal').on('hidden.bs.modal', function () {
                stopWalk();
                document.getElementById('walkFrame').src = 'about:blank';
                if (walkMarker) { walkMarker.remove(); }
            });
            document.addEventListener('keydown', function (event) {
                if (!document.getElementById('walkModal').classList.contains('show')) { return; }
                if (event.key === 'ArrowRight') { stopWalk(); showWalkPoint(walkIndex + 1); }
                if (event.key === 'ArrowLeft') { stopWalk(); showWalkPoint(walkIndex - 1); }
            });
        }

        const favToggle = document.getElementById('favToggle');
        if (favToggle) {
            favToggle.addEventListener('click', function () { setFavMode(!favMode); });
        }
        updateFavToggle();

        map.on('load', function () {
            map.resize();
            mapReady = true;
            resetMapView();
            loadCities(config.defaultCity || '').then(function () {
                setStatus('条件を選んで「検索」を押してください。');
            });
        });

        window.addEventListener('resize', function () {
            if (map && mapReady) {
                map.resize();
            }
        });
    });
})();
