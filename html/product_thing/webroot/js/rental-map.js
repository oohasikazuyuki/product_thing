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
    let walkLine = [];
    let walkNotice = '';
    let walkSpeed = 1;
    let walkTurnRaf = 0;
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
        walkNotice = '';
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
    // 経路上を stepM ごとの地点に分け（線分の途中も補間）、各地点に「lookahead 個先の地点への向き」を付ける
    const sampleRoute = function (line, stepM, lookahead) {
        const pts = [line[0]];
        let carry = 0; // 最後に置いた地点からの距離
        for (let i = 1; i < line.length; i += 1) {
            const a = line[i - 1];
            const b = line[i];
            const seg = distanceM(a, b);
            if (seg === 0) { continue; }
            let d = stepM - carry;
            let emitted = false;
            while (d <= seg) {
                const t = d / seg;
                pts.push([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]);
                d += stepM;
                emitted = true;
            }
            carry = emitted ? seg - (d - stepM) : carry + seg;
        }
        const last = line[line.length - 1];
        if (distanceM(pts[pts.length - 1], last) > 1) { pts.push(last); }
        return pts.map(function (p, i) {
            const next = pts[Math.min(i + lookahead, pts.length - 1)];
            const prev = pts[i - 1];
            return { lng: p[0], lat: p[1], heading: next !== p ? bearing(p, next) : (prev ? bearing(prev, p) : 0) };
        });
    };
    const setWalkRoute = function (route) {
        walkLine = Array.isArray(route.line) && route.line.length > 1 ? route.line : [];
        walkPoints = walkLine.length > 0 ? sampleRoute(walkLine, WALK_FRAME_STEP_M, 1) : [];
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
    // 2枚のiframeを重ねて使う：裏で次の地点を読み込み、完了した瞬間に入れ替える（読み込み中の黒い画面を見せない）
    const walkFrames = [];
    let walkFront = 0;
    let walkPlaying = false;
    const walkBack = { index: -1, ready: false, token: 0, timer: null, onready: null };
    let walkPano = null; // Google Maps JavaScript API の StreetViewPanorama（キーがあり読み込めたときだけ）
    let mapsApiPromise = null;
    const WALK_STEP_MS = 1500; // iframe方式：映像が出てから次の地点へ進むまでの間
    const WALK_PANO_STEP_MS = 700; // JS API方式：次の地点へ進む間隔
    const WALK_FRAME_STEP_M = 30; // iframe方式の地点間隔
    const WALK_PANO_STEP_M = 10; // JS API方式の地点間隔（ストリートビューの撮影間隔が約10〜15m）

    const loadMapsApi = function () {
        if (!mapsApiPromise) {
            mapsApiPromise = new Promise(function (resolve, reject) {
                if (window.google && google.maps && google.maps.StreetViewPanorama) { resolve(); return; }
                window.gm_authFailure = function () { fallbackToFrames(); }; // キー無効・API未有効のとき
                const script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(config.googleMapsApiKey) + '&v=weekly';
                script.async = true;
                script.onload = resolve;
                script.onerror = function () { reject(new Error('maps api')); };
                document.head.appendChild(script);
            });
        }
        return mapsApiPromise;
    };
    const setWalkSurface = function (usePano) {
        document.getElementById('walkPano').style.display = usePano ? 'block' : 'none';
        walkFrames.forEach(function (frame, n) {
            frame.style.display = usePano ? 'none' : 'block';
            frame.style.visibility = n === walkFront ? 'visible' : 'hidden';
        });
    };
    // JS API が使えないときは、従来のiframe方式に戻す
    const fallbackToFrames = function () {
        walkPano = null;
        config.googleMapsApiKey = null; // 使えないキーは以後使わず、キー無しの埋め込みに戻す
        if (walkLine.length > 0) { walkPoints = sampleRoute(walkLine, WALK_FRAME_STEP_M, 1); walkIndex = 0; }
        setWalkSurface(false);
        resetWalkFrames();
        if (walkPoints.length > 0) { showWalkPoint(walkIndex); }
    };
    const ensureWalkPano = async function () {
        if (!config.googleMapsApiKey) { return; }
        try {
            await loadMapsApi();
            if (!walkPano) {
                walkPano = new google.maps.StreetViewPanorama(document.getElementById('walkPano'), {
                    addressControl: false, fullscreenControl: false, enableCloseButton: false,
                    motionTracking: false, showRoadLabels: false, zoomControl: true, panControl: true
                });
            }
        } catch (e) {
            walkPano = null;
            config.googleMapsApiKey = null;
        }
    };

    // 向きを、いまの向きから目標へ最短方向で滑らかに回す
    const turnTo = function (heading, ms) {
        cancelAnimationFrame(walkTurnRaf);
        const from = walkPano.getPov().heading || 0;
        const delta = ((heading - from + 540) % 360) - 180;
        const start = performance.now();
        const step = function (now) {
            const t = Math.min(1, (now - start) / Math.max(ms, 1));
            walkPano.setPov({ heading: (from + delta * t + 360) % 360, pitch: 0 });
            if (t < 1) { walkTurnRaf = requestAnimationFrame(step); }
        };
        walkTurnRaf = requestAnimationFrame(step);
    };

    const stopWalk = function () {
        walkPlaying = false;
        if (walkTimer) { clearTimeout(walkTimer); walkTimer = null; }
        const play = document.getElementById('walkPlay');
        if (play) { play.textContent = '▶ 自動再生'; play.setAttribute('aria-pressed', 'false'); }
    };
    const scheduleNextWalk = function () {
        if (walkTimer) { clearTimeout(walkTimer); }
        walkTimer = setTimeout(function () { showWalkPoint(walkIndex + 1); }, (walkPano ? WALK_PANO_STEP_MS : WALK_STEP_MS) / walkSpeed);
    };
    const loadInBack = function (i, onready) {
        const frame = walkFrames[1 - walkFront];
        const token = ++walkBack.token;
        walkBack.index = i;
        walkBack.ready = false;
        walkBack.onready = onready || null;
        const done = function () {
            if (token !== walkBack.token || walkBack.ready) { return; }
            walkBack.ready = true;
            clearTimeout(walkBack.timer);
            if (walkBack.onready) { walkBack.onready(); }
        };
        frame.onload = done;
        clearTimeout(walkBack.timer);
        walkBack.timer = setTimeout(done, 8000); // 読み込み完了が来ない場合も進める
        frame.src = walkFrameUrl(walkPoints[i], false);
    };
    const swapWalkFrames = function () {
        const shown = walkFrames[1 - walkFront];
        shown.style.visibility = 'visible';
        walkFrames[walkFront].style.visibility = 'hidden';
        walkFront = 1 - walkFront;
        walkBack.index = -1;
        walkBack.onready = null;
        if (walkIndex < walkPoints.length - 1) {
            loadInBack(walkIndex + 1); // 次の地点を先読み
            if (walkPlaying) { scheduleNextWalk(); }
        } else {
            stopWalk();
        }
    };
    const resetWalkFrames = function () {
        walkBack.token += 1;
        clearTimeout(walkBack.timer);
        walkBack.index = -1;
        walkBack.onready = null;
        walkFrames.forEach(function (frame, n) {
            frame.onload = null;
            frame.src = 'about:blank';
            frame.style.visibility = n === walkFront ? 'visible' : 'hidden';
        });
    };
    const showWalkPoint = function (i) {
        if (walkPoints.length === 0) { return; }
        walkIndex = Math.max(0, Math.min(walkPoints.length - 1, i));
        const pt = walkPoints[walkIndex];
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
        if (walkPano) {
            // JS API：iframeを作り直さず、位置と向きだけ動かす
            walkPano.setPosition({ lat: pt.lat, lng: pt.lng });
            turnTo(pt.heading, WALK_PANO_STEP_MS / walkSpeed * 0.9);
            if (walkPlaying) {
                if (walkIndex < walkPoints.length - 1) { scheduleNextWalk(); } else { stopWalk(); }
            }
            return;
        }
        if (walkBack.index === walkIndex) {
            // 先読み済み（または読み込み中）の地点：完了したら入れ替える
            walkBack.onready = swapWalkFrames;
            if (walkBack.ready) { swapWalkFrames(); }
        } else {
            loadInBack(walkIndex, swapWalkFrames);
        }
    };
    const toggleWalkPlay = function () {
        if (walkPlaying) { stopWalk(); return; }
        if (walkIndex >= walkPoints.length - 1) { showWalkPoint(0); }
        walkPlaying = true;
        const play = document.getElementById('walkPlay');
        play.textContent = '⏸ 停止';
        play.setAttribute('aria-pressed', 'true');
        if (walkBack.index === -1 || walkBack.ready) { scheduleNextWalk(); }
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
    // Googleの徒歩ルート（Routes API、だめなら Directions API）。使えない・失敗したら null を返し、呼び出し側は無料ルートのまま進める
    const googleWalkRoute = async function (origin, dest) {
        if (!config.googleMapsApiKey) { return null; }
        const num = function (v) { return typeof v === 'function' ? v() : v; };
        try {
            await loadMapsApi();
            try {
                const lib = await google.maps.importLibrary('routes');
                if (lib.Route && lib.Route.computeRoutes) {
                    const result = await lib.Route.computeRoutes({
                        origin: origin, destination: dest, travelMode: 'WALKING',
                        fields: ['path', 'distanceMeters', 'durationMillis']
                    });
                    const r = result.routes && result.routes[0];
                    if (r && r.path && r.path.length > 1) {
                        return {
                            line: r.path.map(function (p) { return [num(p.lng), num(p.lat)]; }),
                            distance_m: Math.round(r.distanceMeters),
                            minutes: Math.max(1, Math.round(r.durationMillis / 60000))
                        };
                    }
                }
            } catch (e) { /* Routes API が使えなければ Directions API を試す */ }
            const response = await new google.maps.DirectionsService().route({
                origin: origin, destination: dest, travelMode: google.maps.TravelMode.WALKING
            });
            const leg = response.routes[0].legs[0];
            const line = [];
            leg.steps.forEach(function (step) {
                step.path.forEach(function (p) { line.push([p.lng(), p.lat()]); });
            });
            return line.length > 1 ? { line: line, distance_m: leg.distance.value, minutes: Math.max(1, Math.round(leg.duration.value / 60)) } : null;
        } catch (e) {
            return null;
        }
    };

    // 同じ物件の経路は使い回し、取得中の連打は無視する（Googleのルート取得は呼び出しごとに課金対象のため）
    const routeCache = {};
    let routeBusy = false;
    const showRoute = async function (item) {
        if (!item || !item.station || routeBusy) {
            return;
        }
        const lat = Number(item.lat);
        const lng = Number(item.lng);
        if (!isJapanCoordinate(lat, lng) || !config.routeEndpoint) {
            setStatusWithLink('この物件は位置情報がないため、地図にルートを表示できません。', routeUrl(item));
            return;
        }
        activateItem(item.id, false);
        routeBusy = true;
        setStatus('駅からの道のりを計算中…');
        try {
            let entry = routeCache[item.id];
            if (!entry) {
                const params = new URLSearchParams({ station: item.station, lat: String(lat), lng: String(lng) });
                const response = await fetch(config.routeEndpoint + '?' + params.toString(), { headers: { Accept: 'application/json' } });
                const route = await response.json();
                if (!route.ok) {
                    setStatusWithLink(route.message || '駅からの道のりを取得できませんでした。', routeUrl(item));
                    return;
                }
                let source = '地図データ(OSM)による目安';
                const google_ = await googleWalkRoute({ lat: route.station.lat, lng: route.station.lng }, { lat: lat, lng: lng });
                if (google_) {
                    route.line = google_.line;
                    route.distance_m = google_.distance_m;
                    route.minutes = google_.minutes;
                    source = 'Googleの徒歩ルート';
                }
                entry = routeCache[item.id] = { route: route, source: source };
            }
            const route = entry.route;
            const source = entry.source;
            drawRoute(route);
            setWalkRoute(route);
            // 掲載の徒歩時間と3分以上ずれるときは、位置や経路が不正確な可能性を伝える
            walkNotice = item.walk_minutes && Math.abs(route.minutes - Number(item.walk_minutes)) >= 3
                ? '⚠ 掲載（徒歩' + item.walk_minutes + '分）と差が大きいため、物件の位置や経路が実際と異なる可能性があります。参考程度にご覧ください。'
                : '';
            entry.mismatch = walkNotice !== '';
            refreshWarnTag(item.id);
            setStatus(route.station.name + 'から約 ' + route.distance_m.toLocaleString() + ' m（徒歩 約' + route.minutes + '分）' +
                (item.walk_minutes ? ' ／ 掲載情報: 徒歩' + item.walk_minutes + '分' : '') + ' ・ 経路: ' + source + (walkNotice ? ' ' + walkNotice : ''));
        } catch (error) {
            setStatus('駅からの道のりの取得中にエラーが発生しました。');
        } finally {
            routeBusy = false;
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
            renderView();
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

    // 掲載の徒歩時間と経路が合わなかった物件には「位置が不確か」の印を付ける（道のりを確認した物件のみ）
    const WARN_TAG = '<span class="tag-warn" title="掲載の徒歩時間と経路が大きく違います。物件の位置が実際と異なる可能性があります">⚠ 位置が不確か</span>';
    const isUncertain = function (id) {
        return !!(routeCache[id] && routeCache[id].mismatch);
    };
    const refreshWarnTag = function (id) {
        const card = document.querySelector('.listing-item[data-id="' + String(id).replace(/"/g, '') + '"]');
        if (!card || !isUncertain(id) || card.querySelector('.tag-warn')) { return; }
        let box = card.querySelector('.listing-tags');
        if (!box) {
            box = document.createElement('div');
            box.className = 'listing-tags';
            card.querySelector('.listing-actions').before(box);
        }
        box.insertAdjacentHTML('beforeend', WARN_TAG);
    };

    // 表示中のページ内での並べ替え・駅徒歩の絞り込み（APIに条件が無いため、取得済みの20件が対象）
    const viewState = { sort: '', maxWalk: 0 };
    const sortKeys = {
        rent: function (i) { return i.rent_yen > 0 ? i.rent_yen : Infinity; },
        area: function (i) { return i.area_m2 > 0 ? -i.area_m2 : Infinity; },
        walk: function (i) { return i.walk_minutes > 0 ? Number(i.walk_minutes) : Infinity; }
    };
    const viewItems = function (items) {
        let out = items.slice();
        if (viewState.maxWalk > 0) {
            out = out.filter(function (i) { return i.walk_minutes > 0 && Number(i.walk_minutes) <= viewState.maxWalk; });
        }
        const key = sortKeys[viewState.sort];
        if (key) {
            out.sort(function (a, b) { return key(a) - key(b); }); // 値の無い物件は末尾（Infinity）
        }
        return out;
    };
    const renderView = function () {
        if (!lastPayload) { return; }
        const all = lastPayload.items || [];
        const active = viewState.sort !== '' || viewState.maxWalk > 0;
        const payload = active ? Object.assign({}, lastPayload, { items: viewItems(all), viewFiltered: all.length }) : lastPayload;
        renderMap(payload.items || []);
        renderResults(payload);
    };

    const renderResults = function (payload) {
        const root = document.getElementById('rentalResults');
        root.innerHTML = '';
        const items = Array.isArray(payload.items) ? payload.items : [];
        itemsById = {};
        items.forEach(function (item) { itemsById[item.id] = item; });
        const viewFiltered = payload.viewFiltered !== undefined; // 並べ替え・絞り込み中（このページ内だけが対象）
        if (items.length === 0) {
            root.innerHTML = viewFiltered
                ? '<div class="listing-empty">このページには該当する物件がありません。並べ替え・絞り込みを解除するか、ほかのページをご覧ください。</div>'
                : '<div class="listing-empty">該当する物件がありません。条件を広げて再検索してください。</div>';
            setResultCount(viewFiltered ? 'このページ ' + payload.viewFiltered + ' 件のうち 0 件' : '0 件');
            return;
        }

        const total = payload.total || items.length;
        const from = (currentPage - 1) * (payload.per_page || PER_PAGE) + 1;
        setResultCount(viewFiltered
            ? 'このページ ' + payload.viewFiltered + ' 件のうち ' + items.length + ' 件'
            : from.toLocaleString() + '–' + (from + items.length - 1).toLocaleString() + ' / ' + Number(total).toLocaleString() + ' 件' +
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
                (tags.length || isUncertain(item.id)
                    ? '<div class="listing-tags">' + tags.map(function (t) {
                        return '<span>' + escapeHtml(t) + '</span>';
                    }).join('') + (isUncertain(item.id) ? WARN_TAG : '') + '</div>'
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

    // ---- 売買相場との比較（同じ市区町村の取引価格 × いま表示中の賃料）----
    const median = function (values) {
        const sorted = values.slice().sort(function (a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    };
    const saleCache = {};
    // 直近2年のうち、取引データがある年の「中古マンション等」の㎡単価（円）の中央値
    const fetchSaleSqm = async function (area, city) {
        const key = area + '/' + city;
        if (saleCache[key] !== undefined) { return saleCache[key]; }
        let result = null;
        const thisYear = new Date().getFullYear();
        for (const year of [thisYear - 1, thisYear - 2]) {
            try {
                const response = await fetch(config.transactionsEndpoint + '?' + new URLSearchParams({ area: area, city: city, year: String(year) }));
                if (!response.ok) { continue; }
                const rows = ((await response.json()).data || []).filter(function (r) { return r.Type === '中古マンション等'; });
                const values = rows.map(function (r) { return Number(r.TradePrice) / Number(r.Area); }).filter(function (v) { return isFinite(v) && v > 0; });
                if (values.length >= 10) {
                    result = { sqm: median(values), count: values.length, year: year };
                    break;
                }
            } catch (e) { /* 次の年を試す */ }
        }
        saleCache[key] = result;
        return result;
    };
    const renderMarketCompare = async function (payload, area, city) {
        const box = document.getElementById('marketCompare');
        if (!box) { return; }
        box.hidden = true;
        if (payload.demo || !area || !city || !config.transactionsEndpoint) { return; }
        const rents = (payload.items || []).filter(function (i) { return i.rent_yen > 0 && i.area_m2 > 0; })
            .map(function (i) { return i.rent_yen / i.area_m2; });
        if (rents.length < 3) { return; }
        const sale = await fetchSaleSqm(area, city);
        if (!sale) { return; }
        const rentSqm = median(rents);
        const yieldPct = rentSqm * 12 / sale.sqm * 100;
        const cityName = (document.querySelector('#city option:checked') || {}).textContent || '';
        box.innerHTML = '<strong>売買相場との比較' + (cityName ? '（' + escapeHtml(cityName) + '）' : '') + '</strong>' +
            '<div class="mt-1">売買 約 ' + Math.round(sale.sqm / 10000).toLocaleString() + ' 万円/㎡ <span class="text-muted">（' + sale.year + '年の中古マンション等 ' + sale.count.toLocaleString() + ' 件の中央値）</span></div>' +
            '<div>賃貸 約 ' + Math.round(rentSqm).toLocaleString() + ' 円/㎡・月 <span class="text-muted">（表示中 ' + rents.length + ' 件の中央値）</span></div>' +
            '<div>表面利回りの目安 約 <strong>' + yieldPct.toFixed(1) + '%</strong> <span class="text-muted">（賃料×12 ÷ 売買価格。管理費・空室・諸経費は含まない参考値）</span></div>';
        box.hidden = false;
    };

    // URLの検索条件をフォームに戻す。条件が無ければ null
    const restoreFromUrl = function () {
        const q = new URLSearchParams(location.search);
        if (!q.get('prefecture')) { return null; }
        const form = document.getElementById('rentalSearchForm');
        ['prefecture', 'month_money_room', 'month_money_room_h', 'area_min', 'area_max'].forEach(function (name) {
            const el = form.elements[name];
            if (el && q.get(name) !== null) { el.value = q.get(name); }
        });
        const layouts = (q.get('layouts') || '').split(',');
        form.querySelectorAll('input[name="layouts"]').forEach(function (input) {
            input.checked = layouts.indexOf(input.value) !== -1;
        });
        return { city: q.get('city') || '', page: Math.max(1, Number(q.get('page')) || 1) };
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
            const layoutNote = document.getElementById('layoutNote');
            if (layoutNote) {
                layoutNote.hidden = !payload.scanned;
                layoutNote.textContent = payload.scanned
                    ? '間取りの絞り込みは、検索結果の先頭' + payload.scanned + '件が対象です。見つからないときは、賃料や面積の範囲を絞って再検索すると、見落としが減ります。'
                    : '';
            }
            try { history.replaceState(null, '', '?' + params.toString()); } catch (e) { /* URLを更新できなくても検索は続行 */ }
            renderMarketCompare(payload, params.get('prefecture'), params.get('city'));
            renderView();
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
                // 無料枠の残り（少なくなったら色を変える）
                const quota = payload.quota;
                if (quota && typeof quota.remaining === 'number') {
                    chip.textContent += ' · 今月の無料枠 残り ' + quota.remaining + ' / ' + quota.limit + ' 回';
                    chip.classList.toggle('badge-danger', quota.remaining <= 0);
                    chip.classList.toggle('badge-warning', !!payload.demo || (quota.remaining > 0 && quota.remaining <= 30));
                    chip.classList.toggle('badge-success', !payload.demo && quota.remaining > 30);
                }
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
            walkBtn.addEventListener('click', async function () {
                if (walkPoints.length === 0) { return; }
                walkFrames.length = 0;
                walkFrames.push(document.getElementById('walkFrame'), document.getElementById('walkFrameB'));
                walkFront = 0;
                resetWalkFrames();
                await ensureWalkPano();
                walkPoints = sampleRoute(walkLine, walkPano ? WALK_PANO_STEP_M : WALK_FRAME_STEP_M, walkPano ? 2 : 1);
                setWalkSurface(!!walkPano);
                document.getElementById('walkNotice').textContent = walkNotice;
                document.getElementById('walkNotice').hidden = walkNotice === '';
                $('#walkModal').modal('show');
                showWalkPoint(0);
            });
            document.getElementById('walkPrev').addEventListener('click', function () { stopWalk(); showWalkPoint(walkIndex - 1); });
            document.getElementById('walkNext').addEventListener('click', function () { stopWalk(); showWalkPoint(walkIndex + 1); });
            document.getElementById('walkPlay').addEventListener('click', toggleWalkPlay);
            document.querySelectorAll('[data-walk-speed]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    walkSpeed = Number(btn.dataset.walkSpeed);
                    document.querySelectorAll('[data-walk-speed]').forEach(function (b) {
                        b.classList.toggle('active', b === btn);
                        b.setAttribute('aria-pressed', String(b === btn));
                    });
                    if (walkPlaying) { scheduleNextWalk(); }
                });
            });
            $('#walkModal').on('shown.bs.modal', function () {
                if (walkPano) { google.maps.event.trigger(walkPano, 'resize'); }
            });
            $('#walkModal').on('hidden.bs.modal', function () {
                stopWalk();
                resetWalkFrames();
                if (walkMarker) { walkMarker.remove(); }
            });
            document.addEventListener('keydown', function (event) {
                if (!document.getElementById('walkModal').classList.contains('show')) { return; }
                if (event.key === 'ArrowRight') { stopWalk(); showWalkPoint(walkIndex + 1); }
                if (event.key === 'ArrowLeft') { stopWalk(); showWalkPoint(walkIndex - 1); }
            });
        }

        const sortBy = document.getElementById('sortBy');
        const maxWalk = document.getElementById('maxWalk');
        if (sortBy && maxWalk) {
            sortBy.addEventListener('change', function () { viewState.sort = sortBy.value; if (!favMode) { renderView(); } });
            maxWalk.addEventListener('change', function () { viewState.maxWalk = Number(maxWalk.value) || 0; if (!favMode) { renderView(); } });
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
            // リロードしても直前の検索が消えないよう、URLに条件があれば復元して再検索する（ページ単位でサーバー側キャッシュされる）
            const restored = restoreFromUrl();
            loadCities(restored ? restored.city : (config.defaultCity || '')).then(function () {
                if (restored) {
                    searchRentals(restored.page);
                } else {
                    setStatus('条件を選んで「検索」を押してください。');
                }
            });
        });

        window.addEventListener('resize', function () {
            if (map && mapReady) {
                map.resize();
            }
        });
    });
})();
