(function () {
    'use strict';

    const config = window.RENTAL_MAP_CONFIG || {};
    const defaultCenter = [139.6917, 35.6895];
    const defaultZoom = 12;
    let map;
    let markers = [];
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
    };

    const resetMapView = function () {
        if (!map || !mapReady) {
            return;
        }
        map.jumpTo({ center: defaultCenter, zoom: defaultZoom });
    };

    const popupHtml = function (item) {
        return '<div style="font:500 13px/1.45 \'IBM Plex Sans JP\',sans-serif;max-width:220px;">' +
            '<strong style="font-size:14px;">' + escapeHtml(item.name) + '</strong><br>' +
            '<span style="color:#3d4f5f;">' + escapeHtml(item.address) + '</span><br>' +
            '<span style="color:#0d6b63;font-weight:700;">' + escapeHtml(item.rent_text || 'N/A') + '</span><br>' +
            escapeHtml(item.layout || 'N/A') +
            (item.station ? ' · ' + escapeHtml(item.station) : '') +
            (item.walk_minutes ? ' 徒歩' + escapeHtml(item.walk_minutes) + '分' : '') +
            '</div>';
    };

    const renderMap = function (items) {
        if (!map || !mapReady) {
            return;
        }
        clearMarkers();
        const plotted = [];
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
            plotted.push([lng, lat]);
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
        if (items.length === 0) {
            root.innerHTML = '<div class="listing-empty">該当する物件がありません。条件を広げて再検索してください。</div>';
            setResultCount('0 件');
            return;
        }

        setResultCount((payload.total || items.length) + ' 件');
        const frag = document.createDocumentFragment();
        items.forEach(function (item) {
            const el = document.createElement(item.detail_url ? 'a' : 'div');
            if (item.detail_url) {
                el.href = item.detail_url;
                el.target = '_blank';
                el.rel = 'noopener';
            }
            el.className = 'listing-item';
            const tags = [];
            if (item.layout) {
                tags.push(item.layout);
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
                '</div>';
            frag.appendChild(el);
        });
        root.appendChild(frag);
    };

    const searchRentals = async function () {
        const form = document.getElementById('rentalSearchForm');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        formData.forEach(function (value, key) {
            if (String(value).trim() !== '') {
                params.set(key, String(value).trim());
            }
        });

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
            renderResults(payload);
            renderMap(payload.items || []);
            const mode = payload.demo ? 'デモ' : 'ライブ';
            setStatus(
                mode + ' · ' + (payload.provider_label || payload.provider || '') +
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
            searchRentals();
        });

        document.getElementById('prefecture').addEventListener('change', function () {
            loadCities('').then(function () {
                searchRentals();
            });
        });

        map.on('load', function () {
            map.resize();
            mapReady = true;
            resetMapView();
            loadCities(config.defaultCity || '').then(function () {
                searchRentals();
            });
        });

        window.addEventListener('resize', function () {
            if (map && mapReady) {
                map.resize();
            }
        });
    });
})();
