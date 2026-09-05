import proj4 from 'proj4';
import {register} from 'ol/proj/proj4.js';

import Map from 'ol/Map.js';
import View from 'ol/View.js';
import * as proj from 'ol/proj.js';

import WMTS from 'ol/source/WMTS.js';
import TileLayer from 'ol/layer/Tile.js';
import WMTSTileGrid from 'ol/tilegrid/WMTS.js';

import Style from 'ol/style/Style.js';
import Icon from 'ol/style/Icon.js';

import Feature from 'ol/Feature.js';
import Point from 'ol/geom/Point.js';
import VectorLayer from 'ol/layer/Vector.js';
import VectorSource from 'ol/source/Vector.js';


class SwisstopoMap {
    constructor(elementId, zoom = 5, center = [2600000, 1200000]) {
        this.map = null;
        this.#init(elementId, zoom, center);
    }

    addMarker(x, y, title, url, style = this.#getDefaultMarkerStyle()) {

        const feature = new Feature({
            geometry: new Point([x, y]),
            title: title,
            url: url
        });

        const markerLayer = new VectorLayer({
            source: new VectorSource({
                features: [feature]
            }),
            style: style,
        });

        this.map.addLayer(markerLayer);
    }

    #init(elementId, zoom, center) {

        const target = document.getElementById(elementId);

        // Inject the tooltip element
        target.insertAdjacentHTML("afterend", `<div id="mapTooltip" style="position: absolute;background: #000;padding: 6px 10px;white-space: nowrap;pointer-events: none;display: none;font-size: 12px;color: #fff;border-radius: 4px;"></div>`);

        // --- 1. PROJ4 REGISTRIEREN ---
        proj4.defs(
            'EPSG:2056',
            '+proj=somerc +lat_0=46.9524055555556 +lon_0=7.43958333333333 ' +
            '+k_0=1 +x_0=2600000 +y_0=1200000 +ellps=bessel ' +
            '+towgs84=674.374,15.056,405.346,0,0,0,0 +units=m +no_defs'
        );
        register(proj4);

        const swissProj = proj.get('EPSG:2056');
        swissProj.setExtent([2420000, 1030000, 2900000, 1350000]);

        // --- 2. WMTS TILEGRID ---
        const resolutions = [
            4000, 3750, 3500, 3250, 3000, 2750, 2500, 2250, 2000,
            1750, 1500, 1250, 1000, 750, 650, 500, 250, 100, 50,
            20, 10, 5, 2.5, 2, 1.5, 1, 0.5
        ];
        const matrixIds = resolutions.map((_, i) => String(i));

        const tileGrid = new WMTSTileGrid({
            origin: [2420000, 1350000],
            resolutions,
            matrixIds,
            tileSize: 256,
            extent: [2420000, 1030000, 2900000, 1350000],
        });

        // --- 3. MARKER STYLE ---
        const markerStyle = new Style({
            image: new Icon({
                src: 'bundles/markocupicsaceventtool/icons/swisstopo/map-marker-red.svg',
                anchor: [0.5, 1],
                width: 38,
                height: 40,
            })
        });

        // --- 4. MAP ---
        this.map = new Map({
            target: elementId,
            layers: [

                // Basiskarte
                new TileLayer({
                    source: new WMTS({
                        url: 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/2056/{TileMatrix}/{TileCol}/{TileRow}.jpeg',
                        layer: 'ch.swisstopo.pixelkarte-farbe',
                        matrixSet: '2056',
                        format: 'image/jpeg',
                        projection: swissProj,
                        tileGrid,
                        style: 'default',
                        requestEncoding: 'REST',
                        wrapX: false,
                        crossOrigin: 'anonymous',
                    }),
                }),

                // Wanderwege
                new TileLayer({
                    extent: [2420000, 1030000, 2900000, 1350000],
                    source: new WMTS({
                        url: 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swisstlm3d-wanderwege/default/current/2056/{TileMatrix}/{TileCol}/{TileRow}.png',
                        layer: 'ch.swisstopo.swisstlm3d-wanderwege',
                        matrixSet: '2056',
                        format: 'image/png',
                        projection: swissProj,
                        tileGrid,
                        style: 'default',
                        requestEncoding: 'REST',
                        wrapX: false,
                        crossOrigin: 'anonymous',
                    }),
                }),
            ],

            view: new View({
                projection: swissProj,
                center: [...center],
                resolution: zoom,
            }),
        });


        // --- 5. EVENTS ---
        this.map.on("singleclick", evt => {
            this.map.forEachFeatureAtPixel(evt.pixel, feature => {
                const url = feature.get("url");
                if (url) window.open(url, "_blank");
            });
        });

        this.map.on("pointermove", evt => {
            const hit = this.map.hasFeatureAtPixel(evt.pixel);
            this.map.getTargetElement().style.cursor = hit ? "pointer" : "";
        });

        const tooltip = document.getElementById("mapTooltip");

        this.map.on("pointermove", evt => {
            const feature = this.map.forEachFeatureAtPixel(evt.pixel, f => f);

            if (feature && feature.get("title")) {
                const x = evt.originalEvent.clientX;
                const y = evt.originalEvent.clientY;

                tooltip.style.left = x + 24 + "px";
                tooltip.style.top = y + 12 + "px";
                tooltip.innerText = feature.get("title");
                tooltip.style.display = "block";

                this.map.getTargetElement().style.cursor = "pointer";
            } else {
                tooltip.style.display = "none";
                this.map.getTargetElement().style.cursor = "";
            }
        });
    }

    #getDefaultMarkerStyle() {
        return new Style({
            image: new Icon({
                src: 'bundles/markocupicsaceventtool/icons/swisstopo/map-marker-red.svg',
                anchor: [0.5, 1],
                width: 38,
                height: 40,
            })
        });
    }
}

export default SwisstopoMap;

// Damit HTML sie direkt nutzen kann:
window.SwisstopoMap = SwisstopoMap;

