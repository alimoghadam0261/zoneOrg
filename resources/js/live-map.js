import L from 'leaflet';
import { layerFromGeometry } from './geo-layer.js';

/**
 * ZONE — Live security monitoring map.
 *
 * Renders ~100 personnel markers from `positions` payloads pushed by the
 * LiveDashboard Livewire component every polling tick.
 */

const maps = new Map();

const ONLINE_SVG = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>`;

function initials(name = '') {
    const parts = String(name).trim().split(/\s+/);
    const first = parts[0] ? parts[0][0] : '؟';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last) || '؟';
}

function createLiveMap(el) {
    const opts = (() => {
        try { return JSON.parse(el.dataset.options || '{}'); } catch (e) { return {}; }
    })();

    const canvas = el.querySelector('[data-map-canvas]');

    const map = L.map(canvas, { zoomControl: false, preferCanvas: true })
        .setView(opts.center || [35.6892, 51.389], opts.zoom || 13);

    L.control.zoom({ position: 'topleft' }).addTo(map);
    L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);

    const baseLayers = {};
    Object.entries(opts.layers || {}).forEach(([key, def]) => {
        baseLayers[key] = L.tileLayer(def.url, {
            attribution: def.attribution,
            maxZoom: def.max_zoom || 19,
            subdomains: def.url.includes('{s}') ? 'abc' : 'abc',
        });
    });

    const setTheme = (key) => {
        if (!baseLayers[key]) return;
        Object.values(baseLayers).forEach((l) => map.hasLayer(l) && map.removeLayer(l));
        baseLayers[key].addTo(map);
    };

    setTheme(opts.theme || 'satellite');

    const peopleLayer = L.layerGroup().addTo(map);
    const zoneLayer = L.layerGroup().addTo(map);
    const markers = new Map();
    let selected = null;

    const offlineMinutes = opts.offlineAfterMinutes || 5;

    const popupHtml = (p) => `
        <div dir="rtl" style="min-width:210px;font-family:Vazirmatn,Tahoma,sans-serif">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <strong style="font-size:14px">${p.name || ''}</strong>
                <span style="font-size:11px;opacity:.7">${p.code || ''}</span>
            </div>
            <div style="font-size:12px;line-height:1.9;opacity:.85">
                واحد: ${p.department || '—'}<br/>
                نوع قرارداد: ${p.contract_label || '—'}<br/>
                دقت: ${p.accuracy !== null && p.accuracy !== undefined ? p.accuracy + ' متر' : '—'}<br/>
                آخرین پینگ: ${p.captured_at || '—'}<br/>
                شارژ: ${p.battery !== null && p.battery !== undefined ? p.battery + '٪' : '—'}
            </div>
            <div style="margin-top:8px;font-size:12px;font-weight:700;color:${p.status === 'violation' ? '#ef4444' : p.status === 'offline' ? '#94a3b8' : '#16a34a'}">
                وضعیت: ${p.status_label || '—'}
                ${p.zone ? `<div style="font-weight:600;opacity:.85;color:${p.zone.color}">منطقه: ${p.zone.name} (${p.zone.access === 'deny' ? 'ممنوع' : 'مجاز'})</div>` : ''}
            </div>
        </div>`;

    const buildIcon = (p) => {
        const cls = p.status === 'violation'
            ? 'is-violation'
            : p.status === 'offline'
                ? 'is-offline'
                : 'is-allowed';

        const inner = p.avatar
            ? `<img src="${p.avatar}" alt="" onerror="this.outerHTML='<div class=&quot;zn-fallback&quot;>${initials(p.name)}</div>'"/>`
            : `<div class="zn-fallback">${initials(p.name)}</div>`;

        const html = `
            <div class="zn-marker ${cls} ${selected === p.id ? 'is-selected' : ''}">
                ${p.status === 'violation' ? '<span class="zn-pulse"></span>' : ''}
                ${p.status !== 'offline' && p.accuracy ? '<span class="zn-accuracy"></span>' : ''}
                ${inner}
                <span class="zn-badge">${p.code || ''}</span>
            </div>`;

        return L.divIcon({ html, className: '', iconSize: [46, 46], iconAnchor: [23, 23] });
    };

    const setPositions = (positions) => {
        const seen = new Set();

        (positions || []).forEach((p) => {
            if (p.lat === null || p.lng === null) {
                if (markers.has(p.id)) {
                    peopleLayer.removeLayer(markers.get(p.id));
                    markers.delete(p.id);
                }
                return;
            }

            seen.add(p.id);
            const latlng = [p.lat, p.lng];
            let marker = markers.get(p.id);

            if (!marker) {
                marker = L.marker(latlng, { icon: buildIcon(p), zIndexOffset: p.status === 'violation' ? 1000 : 0 });
                marker.on('click', () => {
                    selected = p.id;
                    refreshIcons();
                    window.dispatchEvent(new CustomEvent('zn-person-selected', { detail: { id: p.id } }));
                });
                marker.addTo(peopleLayer);
                markers.set(p.id, marker);
            } else {
                marker.setLatLng(latlng);
                marker.setIcon(buildIcon(p));
                marker.setZIndexOffset(p.status === 'violation' ? 1000 : 0);
            }

            marker.__payload = p;

            marker.bindPopup(popupHtml(p), { direction: 'top', offset: [0, -24] });
        });

        markers.forEach((marker, id) => {
            if (!seen.has(id)) {
                peopleLayer.removeLayer(marker);
                markers.delete(id);
            }
        });
    };

    const refreshIcons = () => {
        markers.forEach((marker) => {
            if (marker.__payload) marker.setIcon(buildIcon(marker.__payload));
        });
    };

    const setZones = (zones) => {
        zoneLayer.clearLayers();

        (zones || []).forEach((z) => {
            const layer = layerFromGeometry(z.geometry, {
                color: z.color,
                weight: 2,
                dashArray: z.is_active ? null : '6 5',
                fillColor: z.color,
                fillOpacity: z.severity_level === 'critical' || z.severity_level === 'high' ? 0.14 : 0.08,
            });

            if (!layer) return;

            layer.bindTooltip(`${z.name} (${z.severity_label})`, {
                sticky: true,
                direction: 'top',
                className: 'zn-zone-label',
            });

            zoneLayer.addLayer(layer);
        });
    };

    const focusPerson = (id) => {
        const marker = markers.get(id);
        if (!marker) return;
        selected = id;
        map.setView(marker.getLatLng(), Math.max(map.getZoom(), 17), { animate: true });
        marker.openPopup();
        refreshIcons();
    };

    // Seed from the initial payload so the first paint shows zone outlines and
    // positions before the first wire:poll tick pushes fresh data.
    if (Array.isArray(opts.zones)) setZones(opts.zones);
    if (Array.isArray(opts.positions)) setPositions(opts.positions);

    const listeners = [];
    const on = (name, handler) => {
        const wrapped = (e) => {
            if (!el.isConnected) return;
            handler(e.detail || {});
        };
        window.addEventListener(name, wrapped);
        listeners.push([name, wrapped]);
    };

    on('lm-positions', (d) => setPositions(d.positions));
    on('lm-zones', (d) => setZones(d.zones));
    on('lm-theme', (d) => setTheme(d.theme));
    on('lm-focus', (d) => focusPerson(d.id));

    const api = { map, setPositions, setZones, setTheme, focusPerson, markers };

    listeners.push(['resize', () => map.invalidateSize()]);
    window.addEventListener('resize', () => map.invalidateSize());

    maps.set(el, api);
    setTimeout(() => map.invalidateSize(), 250);

    return api;
}

/* --- Audio alert ---------------------------------------------------------- */

let audioCtx = null;

function beep(kind = 'critical') {
    const enabled = localStorage.getItem('zn.sound') !== 'off';
    if (!enabled) return;

    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const now = audioCtx.currentTime;
        const pattern = kind === 'critical' ? [880, 660, 880] : [660];

        pattern.forEach((freq, i) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'square';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, now + i * 0.16);
            gain.gain.exponentialRampToValueAtTime(0.25, now + i * 0.16 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.16 + 0.15);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(now + i * 0.16);
            osc.stop(now + i * 0.16 + 0.16);
        });
    } catch (e) {
        console.warn('[ZONE] audio alert failed', e);
    }
}

window.ZONE = Object.assign(window.ZONE || {}, {
    bootLiveMap(el) {
        return maps.get(el) || createLiveMap(el);
    },
    live(el) {
        return maps.get(el);
    },
    beep,
    toggleSound() {
        const next = localStorage.getItem('zn.sound') === 'off' ? 'on' : 'off';
        localStorage.setItem('zn.sound', next);
        if (next === 'on') beep('info');
        return next;
    },
    soundOn() {
        return localStorage.getItem('zn.sound') !== 'off';
    },
    initials,
});
