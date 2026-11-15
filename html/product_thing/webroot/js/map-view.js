// 調査画面用の共通地図：Googleマップ（キーがあるとき）／MapLibre（無いとき・読み込み失敗・認証失敗時）を同じ操作で使う。
// 座標は [経度, 緯度]。addMarker は {lngLat, remove, open, isOpen, setVisible} を返す。
// （賃貸検索は機能が多いため rental-map.js に同等の実装を持つ）
(function () {
    'use strict';

    let apiPromise = null;
    const loadGoogle = function (key) {
        if (!apiPromise) {
            apiPromise = new Promise(function (resolve, reject) {
                if (window.google && google.maps && google.maps.Map) { resolve(); return; }
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

    const createLibre = function (container, center, zoom) {
        const map = new maplibregl.Map({
            container: container,
            style: {
                version: 8,
                sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '&copy; OpenStreetMap contributors' } },
                layers: [{ id: 'osm', type: 'raster', source: 'osm' }]
            },
            center: center, zoom: zoom
        });
        map.addControl(new maplibregl.NavigationControl(), 'top-right');
        return {
            kind: 'maplibre',
            onReady: function (cb) { map.on('load', cb); },
            getCenter: function () { const c = map.getCenter(); return [c.lng, c.lat]; },
            getZoom: function () { return map.getZoom(); },
            flyTo: function (c, z) { map.flyTo({ center: c, zoom: z, essential: true }); },
            fit: function (coords, o) {
                const bounds = new maplibregl.LngLatBounds(coords[0], coords[0]);
                coords.forEach(function (c) { bounds.extend(c); });
                map.fitBounds(bounds, { padding: o.padding || 0, maxZoom: o.maxZoom });
            },
            addMarker: function (c, color, html) {
                const marker = new maplibregl.Marker({ color: color }).setLngLat(c)
                    .setPopup(new maplibregl.Popup({ offset: 24, maxWidth: '260px' }).setHTML(html)).addTo(map);
                return {
                    remove: function () { marker.remove(); },
                    open: function () { if (!marker.getPopup().isOpen()) { marker.togglePopup(); } },
                    isOpen: function () { return marker.getPopup().isOpen(); },
                    setVisible: function (v) { marker.getElement().style.display = v ? '' : 'none'; }
                };
            },
            destroy: function () { map.remove(); }
        };
    };

    const createGoogle = function (container, center, zoom) {
        const ll = function (c) { return { lat: c[1], lng: c[0] }; };
        const gmap = new google.maps.Map(container, {
            center: ll(center), zoom: zoom + 1, // MapLibre と Google は同じ縮尺で約1ずれる
            mapTypeControl: false, fullscreenControl: false, streetViewControl: true, clickableIcons: false, gestureHandling: 'greedy'
        });
        const info = new google.maps.InfoWindow({ maxWidth: 260 });
        let openFor = null;
        gmap.addListener('click', function () { info.close(); openFor = null; });
        info.addListener('closeclick', function () { openFor = null; });
        return {
            kind: 'google',
            onReady: function (cb) { google.maps.event.addListenerOnce(gmap, 'idle', cb); },
            getCenter: function () { const c = gmap.getCenter(); return [c.lng(), c.lat()]; },
            getZoom: function () { return gmap.getZoom() - 1; },
            flyTo: function (c, z) { gmap.panTo(ll(c)); gmap.setZoom(z + 1); },
            fit: function (coords, o) {
                const bounds = new google.maps.LatLngBounds();
                coords.forEach(function (c) { bounds.extend(ll(c)); });
                gmap.fitBounds(bounds, o.padding || 0);
                if (o.maxZoom) {
                    google.maps.event.addListenerOnce(gmap, 'idle', function () {
                        if (gmap.getZoom() > o.maxZoom + 1) { gmap.setZoom(o.maxZoom + 1); }
                    });
                }
            },
            addMarker: function (c, color, html) {
                const marker = new google.maps.Marker({
                    position: ll(c), map: gmap,
                    icon: { path: google.maps.SymbolPath.CIRCLE, fillColor: color, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2, scale: 8 }
                });
                const open = function () { info.setContent(html); info.open({ map: gmap, anchor: marker }); openFor = marker; };
                marker.addListener('click', open);
                return {
                    remove: function () { if (openFor === marker) { info.close(); openFor = null; } marker.setMap(null); },
                    open: open,
                    isOpen: function () { return openFor === marker; },
                    setVisible: function (v) { marker.setVisible(v); if (!v && openFor === marker) { info.close(); openFor = null; } }
                };
            },
            destroy: function () { google.maps.event.clearInstanceListeners(gmap); }
        };
    };

    window.MapView = {
        // opts: { googleKey, center: [経度, 緯度], zoom }。view.ready で描画可能になる。
        create: async function (container, opts) {
            let impl = null;
            if (opts.googleKey) {
                try {
                    await loadGoogle(opts.googleKey);
                    impl = createGoogle(container, opts.center, opts.zoom);
                } catch (e) { /* 読み込めなければMapLibre */ }
            }
            if (!impl) { impl = createLibre(container, opts.center, opts.zoom); }

            const items = []; // 切り替え時にマーカーを作り直すための控え
            const attach = function (it) {
                it.inner = impl.addMarker(it.lngLat, it.color, it.html);
                if (!it.visible) { it.inner.setVisible(false); }
            };
            // Googleマップが認証エラー等で使えなくなったら、MapLibre版に切り替えてマーカーを作り直す
            const switchToLibre = function () {
                if (impl.kind !== 'google') { return; }
                const c = impl.getCenter();
                const z = impl.getZoom();
                impl.destroy();
                items.forEach(function (it) { it.inner = null; });
                container.innerHTML = '';
                impl = createLibre(container, c, z);
                impl.onReady(function () { items.forEach(function (it) { if (!it.removed) { attach(it); } }); });
            };
            if (impl.kind === 'google') { window.gm_authFailure = switchToLibre; }

            const view = {
                get kind() { return impl.kind; },
                ready: new Promise(function (resolve) { impl.onReady(resolve); }),
                getCenter: function () { return impl.getCenter(); },
                getZoom: function () { return impl.getZoom(); },
                flyTo: function (c, z) { impl.flyTo(c, z); },
                fit: function (coords, o) { if (coords.length > 0) { impl.fit(coords, o || {}); } },
                addMarker: function (lngLat, color, html) {
                    const it = { lngLat: lngLat, color: color, html: html, visible: true, removed: false, inner: null };
                    items.push(it);
                    attach(it);
                    return {
                        lngLat: lngLat,
                        remove: function () { it.removed = true; if (it.inner) { it.inner.remove(); } },
                        open: function () { if (it.inner) { it.inner.open(); } },
                        isOpen: function () { return !!it.inner && it.inner.isOpen(); },
                        setVisible: function (v) { it.visible = v; if (it.inner) { it.inner.setVisible(v); } }
                    };
                }
            };
            return view;
        }
    };
})();
