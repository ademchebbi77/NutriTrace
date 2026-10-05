import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet resolves its marker images relative to the CSS file, which breaks once bundled.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.bootstrap = bootstrap;
window.Chart = Chart;
window.L = L;

// Make the template images available to Vite::asset() in Blade.
import.meta.glob('../../img/public/**', { eager: true });
