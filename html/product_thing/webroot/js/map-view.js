// 共通の地図部品：Googleマップ（キーがあるとき）／MapLibre（無いとき・読み込み失敗・認証失敗時）を同じ操作で使う。
// 座標は [経度, 緯度]。縮尺はMapLibre基準（Googleは内部で+1する）。
// 使い方: const view = await MapView.create(el, {googleKey, center, zoom}); await view.ready; view.addMarker(...)
(function () {
    'use strict';

    // ---- Googleマップ JS API の読み込み（ページ内で1回）と認証失敗の通知 ----
    let apiPromise = null;
    const authCallbacks = [];
    const loadGoogle = function (key) {
        if (!apiPromise) {
            apiPromise = new Promise(function (resolve, reject) {
                if (window.google && google.maps && google.maps.Map) { resolve(); return; }
                // キー無効・リファラー不許可・API未有効のとき Google が呼ぶ
                window.gm_authFailure = function () { authCallbacks.forEach(function (cb) { cb(); }); };
                const script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&v=weekly&language=ja&region=JP';
                script.async = true;
                script.onload = resolve;
                script.onerror = function () { reject(new Error('maps api')); };
                document.head.appendChild(script);
            });
        }
        return apiPromise;
    };

    // OSの「視差効果を減らす」設定のとき、地図の移動アニメーションを使わない
    const reduceMotion = function () { return !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches); };

    // ---- MapLibre（OSM / 国土地理院の航空写真）----
    const createLibre = function (container, o) {
        const map = new maplibregl.Map({
            container: container,
            style: {
                version: 8,
                sources: {
                    osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '&copy; OpenStreetMap contributors' },
                    'gsi-photo': { type: 'raster', tiles: ['https://cyberjapandata.gsi.go.jp/xyz/seamlessphoto/{z}/{x}/{y}.jpg'], tileSize: 256, attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">国土地理院</a>' }
                },
                layers: [
                    { id: 'osm', type: 'raster', source: 'osm' },
                    { id: 'gsi-photo', type: 'raster', source: 'gsi-photo', layout: { visibility: 'none' } }
                ]
            },
            center: o.center, zoom: o.zoom, minZoom: o.minZoom, maxZoom: o.maxZoom
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        let station = null;
        let walk = null;
        return {
            kind: 'maplibre',
            onReady: function (cb) { map.on('load', cb); },
            resize: function () { map.resize(); },
            reset: function () { map.jumpTo({ center: o.center, zoom: o.zoom }); },
            getCenter: function () { const c = map.getCenter(); return [c.lng, c.lat]; },
            getZoom: function () { return map.getZoom(); },
            flyTo: function (c, z) { map.flyTo({ center: c, zoom: z, essential: !reduceMotion() }); },
            fit: function (coords, f) {
                const bounds = new maplibregl.LngLatBounds(coords[0], coords[0]);
                coords.forEach(function (c) { bounds.extend(c); });
                map.fitBounds(bounds, { padding: f.padding || 0, maxZoom: f.maxZoom, minZoom: f.minZoom, essential: !reduceMotion() });
            },
            addMarker: function (c, color, html, onClick) {
                const marker = new maplibregl.Marker({ color: color }).setLngLat(c)
                    .setPopup(new maplibregl.Popup({ offset: 20, maxWidth: '260px' }).setHTML(html)).addTo(map);
                if (onClick) { marker.getElement().addEventListener('click', onClick); }
                return {
                    remove: function () { marker.remove(); },
                    open: function () { if (!marker.getPopup().isOpen()) { marker.togglePopup(); } },
                    isOpen: function () { return marker.getPopup().isOpen(); },
                    setVisible: function (v) { marker.getElement().style.display = v ? '' : 'none'; }
                };
            },
            setRoute: function (line) {
                const geojson = { type: 'Feature', geometry: { type: 'LineString', coordinates: line } };
                if (map.getSource('walk-route')) {
                    map.getSource('walk-route').setData(geojson);
                } else {
                    map.addSource('walk-route', { type: 'geojson', data: geojson });
                    map.addLayer({ id: 'walk-route-line', type: 'line', source: 'walk-route', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#2563eb', 'line-width': 6, 'line-opacity': 0.85 } });
                }
            },
            setStation: function (c, name) {
                if (station) { station.remove(); }
                station = new maplibregl.Marker({ color: '#2563eb' }).setLngLat(c)
                    .setPopup(new maplibregl.Popup({ offset: 18, closeButton: false }).setText(name)).addTo(map);
            },
            clearRoute: function () {
                if (station) { station.remove(); station = null; }
                if (map.getSource('walk-route')) { map.getSource('walk-route').setData({ type: 'FeatureCollection', features: [] }); }
            },
            setWalk: function (c) {
                if (!c) { if (walk) { walk.remove(); } return; }
                if (!walk) { walk = new maplibregl.Marker({ color: '#f59e0b' }); }
                walk.setLngLat(c).addTo(map);
            },
            setBasemap: function (aerial) { map.setLayoutProperty('gsi-photo', 'visibility', aerial ? 'visible' : 'none'); },
            destroy: function () { map.remove(); }
        };
    };

    // ---- Googleマップ ----
    const createGoogle = function (container, o) {
        const ll = function (c) { return { lat: c[1], lng: c[0] }; };
        const dot = function (color, scale) {
            return { path: google.maps.SymbolPath.CIRCLE, fillColor: color, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2, scale: scale };
        };
        const gmap = new google.maps.Map(container, {
            center: ll(o.center), zoom: o.zoom + 1, // MapLibre と Google は同じ縮尺で約1ずれる
            minZoom: o.minZoom + 1, maxZoom: o.maxZoom + 2,
            mapTypeControl: false, fullscreenControl: false, streetViewControl: true, // ペグマンでストリートビューへ
            clickableIcons: false, gestureHandling: 'greedy'
        });
        const info = new google.maps.InfoWindow({ maxWidth: 260 });
        let openFor = null;
        let line = null;
        let station = null;
        let walk = null;
        gmap.addListener('click', function () { info.close(); openFor = null; });
        info.addListener('closeclick', function () { openFor = null; });
        const show = function (marker, html) { info.setContent(html); info.open({ map: gmap, anchor: marker }); openFor = marker; };
        const esc = function (v) { return String(v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
        return {
            kind: 'google',
            onReady: function (cb) { google.maps.event.addListenerOnce(gmap, 'idle', cb); },
            resize: function () { google.maps.event.trigger(gmap, 'resize'); },
            reset: function () { gmap.setCenter(ll(o.center)); gmap.setZoom(o.zoom + 1); },
            getCenter: function () { const c = gmap.getCenter(); return [c.lng(), c.lat()]; },
            getZoom: function () { return gmap.getZoom() - 1; },
            flyTo: function (c, z) { gmap.panTo(ll(c)); gmap.setZoom(z + 1); },
            fit: function (coords, f) {
                const bounds = new google.maps.LatLngBounds();
                coords.forEach(function (c) { bounds.extend(ll(c)); });
                gmap.fitBounds(bounds, f.padding || 0);
                if (f.maxZoom) {
                    google.maps.event.addListenerOnce(gmap, 'idle', function () {
                        if (gmap.getZoom() > f.maxZoom + 1) { gmap.setZoom(f.maxZoom + 1); }
                    });
                }
            },
            addMarker: function (c, color, html, onClick) {
                const marker = new google.maps.Marker({ position: ll(c), map: gmap, icon: dot(color, 9) });
                const open = function () { show(marker, html); };
                marker.addListener('click', function () { open(); if (onClick) { onClick(); } });
                return {
                    remove: function () { if (openFor === marker) { info.close(); openFor = null; } marker.setMap(null); },
                    open: open,
                    isOpen: function () { return openFor === marker; },
                    setVisible: function (v) { marker.setVisible(v); if (!v && openFor === marker) { info.close(); openFor = null; } }
                };
            },
            setRoute: function (path) {
                if (line) { line.setMap(null); }
                line = new google.maps.Polyline({ path: path.map(ll), strokeColor: '#2563eb', strokeOpacity: 0.85, strokeWeight: 6, map: gmap });
            },
            setStation: function (c, name) {
                if (station) { station.setMap(null); }
                station = new google.maps.Marker({ position: ll(c), map: gmap, icon: dot('#2563eb', 11), title: name, zIndex: 5 });
                station.addListener('click', function () { show(station, '<strong>' + esc(name) + '</strong>'); });
            },
            clearRoute: function () {
                if (line) { line.setMap(null); line = null; }
                if (station) { station.setMap(null); station = null; }
            },
            setWalk: function (c) {
                if (!c) { if (walk) { walk.setMap(null); } return; }
                if (!walk) { walk = new google.maps.Marker({ icon: dot('#f59e0b', 8), zIndex: 10 }); }
                walk.setPosition(ll(c));
                walk.setMap(gmap);
            },
            setBasemap: function (aerial) { gmap.setMapTypeId(aerial ? 'hybrid' : 'roadmap'); },
            destroy: function () { google.maps.event.clearInstanceListeners(gmap); }
        };
    };

    window.MapView = {
        loadGoogle: loadGoogle,
        // Googleマップの認証失敗（キー無効など）を知らせてほしい処理を登録する
        onAuthFailure: function (cb) { authCallbacks.push(cb); },
        // opts: { googleKey, center: [経度, 緯度], zoom, minZoom?, maxZoom? }。view.ready で描画可能になる。
        create: async function (container, opts) {
            const o = { center: opts.center, zoom: opts.zoom, minZoom: opts.minZoom || 1, maxZoom: opts.maxZoom || 18 };
            let impl = null;
            if (opts.googleKey) {
                try {
                    await loadGoogle(opts.googleKey);
                    impl = createGoogle(container, o);
                } catch (e) { /* 読み込めなければMapLibre */ }
            }
            if (!impl) { impl = createLibre(container, o); }

            // 切り替え時に作り直すための控え
            const items = [];
            const state = { route: null, station: null, walk: null, aerial: false };
            const attach = function (it) {
                it.inner = impl.addMarker(it.lngLat, it.color, it.html, it.onClick);
                if (!it.visible) { it.inner.setVisible(false); }
            };
            // Googleマップが認証エラー等で使えなくなったら、MapLibre版に切り替えて表示を作り直す
            const switchToLibre = function () {
                if (impl.kind !== 'google') { return; }
                const c = impl.getCenter();
                const z = impl.getZoom();
                impl.destroy();
                items.forEach(function (it) { it.inner = null; });
                container.innerHTML = '';
                impl = createLibre(container, { center: c, zoom: z, minZoom: o.minZoom, maxZoom: o.maxZoom });
                impl.onReady(function () {
                    items.forEach(function (it) { if (!it.removed) { attach(it); } });
                    if (state.route) { impl.setRoute(state.route); }
                    if (state.station) { impl.setStation(state.station.c, state.station.name); }
                    if (state.walk) { impl.setWalk(state.walk); }
                    if (state.aerial) { impl.setBasemap(true); }
                });
            };
            if (impl.kind === 'google') { authCallbacks.push(switchToLibre); }

            return {
                get kind() { return impl.kind; },
                ready: new Promise(function (resolve) { impl.onReady(resolve); }),
                resize: function () { impl.resize(); },
                reset: function () { impl.reset(); },
                getCenter: function () { return impl.getCenter(); },
                getZoom: function () { return impl.getZoom(); },
                flyTo: function (c, z) { impl.flyTo(c, z); },
                fit: function (coords, f) { if (coords.length > 0) { impl.fit(coords, f || {}); } },
                addMarker: function (lngLat, color, html, onClick) {
                    const it = { lngLat: lngLat, color: color, html: html, onClick: onClick || null, visible: true, removed: false, inner: null };
                    items.push(it);
                    attach(it);
                    return {
                        lngLat: lngLat,
                        remove: function () {
                            it.removed = true;
                            const i = items.indexOf(it);
                            if (i >= 0) { items.splice(i, 1); } // 控えにも残さない
                            if (it.inner) { it.inner.remove(); }
                        },
                        open: function () { if (it.inner) { it.inner.open(); } },
                        isOpen: function () { return !!it.inner && it.inner.isOpen(); },
                        setVisible: function (v) { it.visible = v; if (it.inner) { it.inner.setVisible(v); } }
                    };
                },
                setRoute: function (line) { state.route = line; impl.setRoute(line); },
                setStation: function (c, name) { state.station = { c: c, name: name }; impl.setStation(c, name); },
                clearRoute: function () { state.route = null; state.station = null; impl.clearRoute(); },
                setWalk: function (c) { state.walk = c; impl.setWalk(c); },
                setBasemap: function (aerial) { state.aerial = aerial; impl.setBasemap(aerial); }
            };
        }
    };
})();
