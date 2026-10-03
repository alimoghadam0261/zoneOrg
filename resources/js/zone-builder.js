import L from 'leaflet';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';
import { layerFromGeometry } from './geo-layer.js';

/**
 * ZONE — Zone Builder map bridge.
 *
 * Lives inside a `wire:ignore` wrapper, so it talks to Livewire through:
 *   · DOM CustomEvents on window  (Livewire → JS)  via `x-on:zb-*.window`
 *   · Livewire.dispatch(...)      (JS → Livewire)  via `#[On('...')]`
 */

const builders = new Map();

function optionsOf(el) {
    if (el.__znOptions) return el.__znOptions;
    try {
        el.__znOptions = JSON.parse(el.dataset.options || '{}');
    } catch (e) {
        el.__znOptions = {};
    }
    return el.__znOptions;
}

function bboxOf(geometry) {
    if (!geometry) return null;

    if (geometry.type === 'Point') {
        const [lng, lat] = geometry.coordinates;
        const d = (geometry.properties?.radius || 0) / 111320;
        const cos = Math.max(Math.cos((lat * Math.PI) / 180), 0.01);
        return [lat - d, lng - d / cos, lat + d, lng + d / cos];
    }

    let minLat = Infinity, minLng = Infinity, maxLat = -Infinity, maxLng = -Infinity;

    const walk = (coords, depth) => {
        if (depth === 0) {
            const [lng, lat] = coords;
            minLat = Math.min(minLat, lat);
            maxLat = Math.max(maxLat, lat);
            minLng = Math.min(minLng, lng);
            maxLng = Math.max(maxLng, lng);
            return;
        }
        coords.forEach((c) => walk(c, depth - 1));
    };

    const depth = geometry.type === 'Polygon' ? 2 : 1;
    walk(geometry.coordinates, depth);

    return [minLat, minLng, maxLat, maxLng];
}

function createBuilder(el) {
    const opts = optionsOf(el);
    const canvas = el.querySelector('[data-map-canvas]');

    const map = L.map(canvas, {
        zoomControl: false,
        attributionControl: true,
        preferCanvas: true,
    }).setView(opts.center || [35.6892, 51.389], opts.zoom || 13);

    L.control.zoom({ position: 'topleft' }).addTo(map);
    L.control.scale({ position: 'bottomleft', imperial: false, metric: true }).addTo(map);

    const baseLayers = {};
    Object.entries(opts.layers || {}).forEach(([key, def]) => {
        baseLayers[key] = L.tileLayer(def.url, {
            attribution: def.attribution,
            maxZoom: def.max_zoom || 19,
            subdomains: def.url.includes('{s}') ? 'abc' : 'abc',
        });
    });

    const api = {
        el,
        map,
        baseLayers,
        theme: opts.theme || 'satellite',
        mode: null,
        handler: null,
        zonesLayer: L.layerGroup().addTo(map),
        draftLayer: L.layerGroup().addTo(map),
        draft: null,
        draftType: null,
        selectedId: null,
    };

    const setTheme = (key) => {
        if (!baseLayers[key]) return;
        Object.values(baseLayers).forEach((l) => map.hasLayer(l) && map.removeLayer(l));
        baseLayers[key].addTo(map);
        api.theme = key;
        el.dispatchEvent(new CustomEvent('zn-theme-changed', { detail: { theme: key } }));
    };

    setTheme(api.theme);

    const emit = (geometry, type) => {
        Livewire.dispatch('zone-geometry', {
            geometry,
            type,
            bbox: bboxOf(geometry),
        });
    };

    const fromLayer = (layer, layerType) => {
        if (layer instanceof L.Circle) {
            const c = layer.getLatLng();
            return [
                {
                    type: 'Point',
                    coordinates: [c.lng, c.lat],
                    properties: { radius: Math.round(layer.getRadius()) },
                },
                'circle',
            ];
        }

        const gj = layer.toGeoJSON();
        return [
            {
                type: 'Polygon',
                coordinates: gj.geometry.coordinates,
            },
            layerType === 'rectangle' ? 'rectangle' : 'polygon',
        ];
    };

    const clearHandler = () => {
        if (api.handler) {
            try { api.handler.disable(); } catch (e) { /* noop */ }
            api.handler = null;
        }
    };

    const setDraft = (geometry, type) => {
        api.draftLayer.clearLayers();
        api.draft = geometry;
        api.draftType = type;

        if (!geometry) return;

        const layer = layerFromGeometry(geometry, {
            color: '#6366f1',
            weight: 3,
            dashArray: '8 6',
            fillColor: '#6366f1',
            fillOpacity: 0.15,
        });

        if (layer) api.draftLayer.addLayer(layer);
        setTimeout(() => map.invalidateSize(), 120);
    };

    const setDrawMode = (mode) => {
        clearHandler();
        api.mode = mode;

        if (!mode) return;

        if (mode === 'polygon' || mode === 'rectangle' || mode === 'circle') {
            const common = { shapeOptions: { color: '#6366f1', weight: 2, fillColor: '#6366f1', fillOpacity: .18 } };

            api.handler = mode === 'polygon'
                ? new L.Draw.Polygon(map, { ...common, allowIntersection: false, showArea: false })
                : mode === 'rectangle'
                    ? new L.Draw.Rectangle(map, { ...common, metric: true })
                    : new L.Draw.Circle(map, { ...common, metric: true });

            api.handler.enable();

            map.once(L.Draw.Event.CREATED, (e) => {
                api.draftLayer.clearLayers();
                api.draftLayer.addLayer(e.layer);
                const [geometry, type] = fromLayer(e.layer, e.layerType);
                api.draft = geometry;
                api.draftType = type;
                emit(geometry, type);
                api.mode = null;
                api.handler = null;
            });

            return;
        }

        if (mode === 'edit') {
            if (api.draftLayer.getLayers().length === 0) return;
            api.handler = new L.EditToolbar.Edit(map, { featureGroup: api.draftLayer });
            api.handler.enable();

            map.once(L.Draw.Event.EDITED, (e) => {
                const layers = e.layers.getLayers();
                if (layers[0]) {
                    const [geometry, type] = fromLayer(layers[0], api.draftType);
                    api.draft = geometry;
                    api.draftType = type;
                    emit(geometry, type);
                }
                api.mode = null;
                api.handler = null;
            });

            return;
        }

        if (mode === 'clear') {
            api.draftLayer.clearLayers();
            api.draft = null;
            api.draftType = null;
            Livewire.dispatch('zone-geometry-cleared', {});
        }
    };

    const renderZones = (zones) => {
        api.zonesLayer.clearLayers();

        (zones || []).forEach((z) => {
            const style = {
                color: z.color || '#6366f1',
                weight: z.is_active ? 2.5 : 1.5,
                dashArray: z.is_active ? null : '6 5',
                fillColor: z.color || '#6366f1',
                fillOpacity: z.is_active ? 0.16 : 0.06,
                opacity: z.is_active ? 0.9 : 0.5,
            };

            const layer = layerFromGeometry(z.geometry, style);
            if (!layer) return;

            layer.on('click', () => Livewire.dispatch('zone-select', { id: z.id }));
            layer.bindTooltip(`${z.name} · ${z.code}`, {
                sticky: true,
                direction: 'top',
                className: 'zn-zone-label',
            });

            api.zonesLayer.addLayer(layer);
        });

        setTimeout(() => map.invalidateSize(), 100);
    };

    const focus = (geometry, padding = 40) => {
        if (!geometry) return;
        const layer = layerFromGeometry(geometry, {});
        if (!layer) return;
        try {
            map.fitBounds(layer.getBounds(), { padding: [padding, padding], maxZoom: 17 });
        } catch (e) { /* noop */ }
    };

    const fitZones = () => {
        const group = L.featureGroup(api.zonesLayer.getLayers());
        if (group.getLayers().length) {
            map.fitBounds(group.getBounds(), { padding: [40, 40], maxZoom: 16 });
        }
    };

    const onClickAway = (e) => {
        if (api.mode) return;
        if (e.originalEvent && e.originalEvent.target.closest('.zn-marker')) return;
    };

    map.on('click', onClickAway);

    api.setTheme = setTheme;
    api.setDraft = setDraft;
    api.setDrawMode = setDrawMode;
    api.renderZones = renderZones;
    api.focus = focus;
    api.fitZones = fitZones;

    // ---- window events coming from Livewire ------------------------------
    const listeners = [];
    const on = (name, handler) => {
        const wrapped = (e) => {
            if (!el.isConnected) return;
            handler(e.detail || {});
        };
        window.addEventListener(name, wrapped);
        listeners.push([name, wrapped]);
    };

    on('zb-zones', (d) => renderZones(d.zones));
    on('zb-draft', (d) => setDraft(d.geometry, d.type));
    on('zb-theme', (d) => setTheme(d.theme));
    on('zb-draw', (d) => setDrawMode(d.mode));
    on('zb-focus', (d) => focus(d.geometry));
    on('zb-fit', () => fitZones());

    api.destroy = () => {
        listeners.forEach(([name, wrapped]) => window.removeEventListener(name, wrapped));
        clearHandler();
        map.remove();
        builders.delete(el);
    };

    builders.set(el, api);

    setTimeout(() => map.invalidateSize(), 200);
    window.addEventListener('resize', () => map.invalidateSize());

    return api;
}

window.ZONE = Object.assign(window.ZONE || {}, {
    bootBuilder(el) {
        return builders.get(el) || createBuilder(el);
    },
    builder(el) {
        return builders.get(el);
    },
    layerFromGeometry,
    bboxOf,
    draw(el, mode) {
        const b = builders.get(el);
        if (b) b.setDrawMode(mode);
    },
    theme(el, key) {
        const b = builders.get(el);
        if (b) b.setTheme(key);
    },
    fit(el) {
        const b = builders.get(el);
        if (b) b.fitZones();
    },
});
