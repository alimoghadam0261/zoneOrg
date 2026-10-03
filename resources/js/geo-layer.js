import L from 'leaflet';

/**
 * ZONE — GeoJSON → Leaflet layer factory (shared by zone-builder + live-map).
 *
 * Point geometries (circle zones) carry their radius in `properties.radius`;
 * every other geometry is rendered through L.geoJSON with the given style.
 */
export function layerFromGeometry(geometry, style) {
    if (!geometry) return null;

    if (geometry.type === 'Point') {
        const [lng, lat] = geometry.coordinates;
        return L.circle([lat, lng], {
            radius: geometry.properties?.radius || 50,
            ...style,
        });
    }

    try {
        const group = L.geoJSON(geometry, { style: () => style });
        return group.getLayers()[0] || null;
    } catch (e) {
        console.warn('[ZONE] invalid GeoJSON', e);
        return null;
    }
}
