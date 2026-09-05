import proj4 from 'proj4';
import {register} from 'ol/proj/proj4.js';

// Aliased, so it does not shadow the native Map used for the style cache.
import OlMap from 'ol/Map.js';
import View from 'ol/View.js';
import * as proj from 'ol/proj.js';

import WMTS from 'ol/source/WMTS.js';
import TileLayer from 'ol/layer/Tile.js';
import WMTSTileGrid from 'ol/tilegrid/WMTS.js';

import Style from 'ol/style/Style.js';
import Icon from 'ol/style/Icon.js';
import CircleStyle from 'ol/style/Circle.js';
import Fill from 'ol/style/Fill.js';
import Stroke from 'ol/style/Stroke.js';
import Text from 'ol/style/Text.js';

import Feature from 'ol/Feature.js';
import Point from 'ol/geom/Point.js';
import VectorLayer from 'ol/layer/Vector.js';
import VectorSource from 'ol/source/Vector.js';
import Cluster from 'ol/source/Cluster.js';
import {boundingExtent} from 'ol/extent.js';


const DEFAULT_MARKER_SRC = 'bundles/markocupicsaceventtool/icons/swisstopo/map-marker-red.svg';
const DEFAULT_BUBBLE_SRC = 'bundles/markocupicsaceventtool/icons/swisstopo/tourtypes/bubble.svg';

// Speech bubble geometry (px). The tail tip is the anchor that sits on the coordinate.
const BUBBLE_WIDTH = 56;
const BUBBLE_HEIGHT = 66;   // 56 body + 10 tail
const BUBBLE_BODY = 56;
const TOUR_TYPE_ICON_SIZE = 40;

// Cluster bubble.
const CLUSTER_COLOR = 'rgba(214, 40, 40, 0.92)';
const CLUSTER_HALO_COLOR = 'rgba(214, 40, 40, 0.25)';
const CLUSTER_TEXT_COLOR = '#ffffff';

// Smallest resolution of the swisstopo tile grid, used as a zoom-in limit.
const MIN_RESOLUTION = 0.5;


class SwisstopoMap {
  /**
   * @param {string}   elementId
   * @param {number}   zoom
   * @param {number[]} center
   * @param {{
   *   tourTypeIcons?: Object<number, string>,
   *   bubbleSrc?: string,
   *   cluster?: boolean,
   *   clusterDistance?: number,
   *   clusterMinDistance?: number,
   *   clusterMaxResolution?: number,
   * }} options
   *   tourTypeIcons         Maps a tour type ID to the URL of its icon.
   *   cluster               Bundle overlapping markers into a counter bubble.
   *   clusterMaxResolution  Bundling stops once the view is zoomed in past this
   *                         resolution (m/px), so every icon becomes visible on
   *                         its own. Smaller value = you have to zoom in further.
   */
  constructor(elementId, zoom = 5, center = [2600000, 1200000], options = {}) {
    this.map = null;
    this.bubbleSrc = options.bubbleSrc ?? DEFAULT_BUBBLE_SRC;
    this.tourTypeIcons = options.tourTypeIcons ?? {};
    this.clusterEnabled = options.cluster ?? true;
    this.clusterMaxResolution = options.clusterMaxResolution ?? 20;

    // All markers live in one source, so the map stays fast with many tours.
    this.markerSource = new VectorSource();

    this.clusterSource = new Cluster({
      source: this.markerSource,
      distance: options.clusterDistance ?? 44,
      minDistance: options.clusterMinDistance ?? 24,
    });

    this.markerLayer = null;
    this.styleCache = new Map();

    this.#init(elementId, zoom, center);
  }

  /**
   * @param {number} x
   * @param {number} y
   * @param {string} title
   * @param {string} url
   * @param {number|string|Array|Style|Style[]} tourType  Tour type ID (resolved via
   *        the tourTypeIcons option), an icon URL, or a ready-made style.
   */
  addMarker(x, y, title, url, tourType = null) {

    const feature = new Feature({
      geometry: new Point([x, y]),
      title: title,
      url: url,
      tourType: tourType,
    });

    // A ready-made style stays attached to the feature (previous API).
    if (tourType instanceof Style || (Array.isArray(tourType) && tourType[0] instanceof Style)) {
      feature.setStyle(tourType);
    }

    this.markerSource.addFeature(feature);
  }

  /**
   * Turns the bundling of overlapping markers on or off at runtime.
   *
   * @param {boolean} enabled
   */
  setClustering(enabled) {
    this.clusterEnabled = !!enabled;
    this.#applyClusterState();
  }

  isClustering() {
    return this.clusterEnabled;
  }

  /**
   * Sets the resolution below which markers are never bundled any more.
   *
   * @param {number} resolution  m/px
   */
  setClusterMaxResolution(resolution) {
    this.clusterMaxResolution = resolution;
    this.#applyClusterState();
  }

  /**
   * Bundling is active only while it is switched on AND the view is still
   * zoomed out far enough. Below clusterMaxResolution every marker is drawn
   * on its own, so no icon can hide behind a counter bubble.
   */
  #applyClusterState() {
    if (!this.markerLayer) {
      return;
    }

    const resolution = this.map.getView().getResolution();
    const bundle = this.clusterEnabled && resolution > this.clusterMaxResolution;
    const source = bundle ? this.clusterSource : this.markerSource;

    if (this.markerLayer.getSource() !== source) {
      this.markerLayer.setSource(source);
    }
  }

  /**
   * Removes every marker from the map.
   */
  clearMarkers() {
    this.markerSource.clear();
  }

  /**
   * Resolves a tour type to an icon URL. Accepts the ID itself, an array of
   * IDs (the API delivers tourType as an array) or a ready-made URL.
   *
   * @param {number|string|Array} tourType
   * @return {string|null}
   */
  getTourTypeIcon(tourType) {

    if (Array.isArray(tourType)) {
      tourType = tourType[0] ?? null;
    }

    if (null === tourType || '' === tourType) {
      return null;
    }

    // A path or URL was passed in directly.
    if (typeof tourType === 'string' && !/^\d+$/.test(tourType)) {
      return tourType;
    }

    return this.tourTypeIcons[parseInt(tourType, 10)] ?? null;
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

    // --- 3. MARKER LAYER ---
    this.markerLayer = new VectorLayer({
      source: this.markerSource,
      style: feature => this.#getStyle(feature),
    });

    // --- 4. MAP ---
    this.map = new OlMap({
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

        this.markerLayer,
      ],

      view: new View({
        projection: swissProj,
        center: [...center],
        resolution: zoom,
      }),
    });


    // Bundling depends on the current resolution, so re-evaluate on every zoom.
    this.#applyClusterState();
    this.map.getView().on('change:resolution', () => this.#applyClusterState());

    // --- 5. EVENTS ---
    this.map.on("singleclick", evt => {
      this.map.forEachFeatureAtPixel(evt.pixel, feature => {

        const members = this.#getMembers(feature);

        // A bundle -> zoom in until it falls apart instead of opening a tour.
        if (members.length > 1) {
          this.#zoomToCluster(members);

          return true;
        }

        const url = members[0].get("url");
        if (url) window.open(url, "_blank");

        return true;
      });
    });

    const tooltip = document.getElementById("mapTooltip");

    this.map.on("pointermove", evt => {
      const feature = this.map.forEachFeatureAtPixel(evt.pixel, f => f);
      const label = feature ? this.#getTooltipLabel(feature) : null;

      if (label) {
        tooltip.style.left = evt.originalEvent.clientX + 24 + "px";
        tooltip.style.top = evt.originalEvent.clientY + 12 + "px";
        tooltip.innerText = label;
        tooltip.style.display = "block";

        this.map.getTargetElement().style.cursor = "pointer";
      } else {
        tooltip.style.display = "none";
        this.map.getTargetElement().style.cursor = "";
      }
    });
  }

  /**
   * The features behind a rendered feature. When clustering is off the
   * feature is its own single member.
   *
   * @return {Feature[]}
   */
  #getMembers(feature) {
    const members = feature.get('features');

    return Array.isArray(members) ? members : [feature];
  }

  #getTooltipLabel(feature) {
    const members = this.#getMembers(feature);

    if (members.length > 1) {
      return members.length + ' Touren';
    }

    return members[0].get('title') ?? null;
  }

  /**
   * Zooms onto the bundled markers so that they separate.
   */
  #zoomToCluster(members) {
    const view = this.map.getView();
    const coordinates = members.map(f => f.getGeometry().getCoordinates());
    const extent = boundingExtent(coordinates);

    // All markers on the very same spot -> fitting would zoom in endlessly.
    if (extent[0] === extent[2] && extent[1] === extent[3]) {
      view.animate({
        center: [extent[0], extent[1]],
        resolution: Math.max(view.getResolution() / 3, MIN_RESOLUTION),
        duration: 400,
      });

      return;
    }

    view.fit(extent, {
      padding: [70, 70, 70, 70],
      duration: 400,
      minResolution: MIN_RESOLUTION,
    });
  }

  #getStyle(feature) {
    const members = this.#getMembers(feature);

    if (members.length > 1) {
      return this.#getClusterStyle(members.length);
    }

    const single = members[0];

    // A style attached to the feature wins (previous API).
    return single.getStyle() ?? this.#resolveMarkerStyle(single.get('tourType'));
  }

  /**
   * The red counter bubble, mirroring the SAC tour portal.
   */
  #getClusterStyle(count) {
    const cacheKey = 'cluster-' + count;

    if (!this.styleCache.has(cacheKey)) {
      // Grows slowly, so 100 markers do not produce a huge blob.
      const radius = 15 + Math.min(11, Math.log2(count) * 3.2);

      this.styleCache.set(cacheKey, new Style({
        image: new CircleStyle({
          radius: radius,
          fill: new Fill({color: CLUSTER_COLOR}),
          // A wide translucent stroke gives the soft halo.
          stroke: new Stroke({color: CLUSTER_HALO_COLOR, width: 10}),
        }),
        text: new Text({
          text: String(count),
          font: 'bold 13px "Helvetica Neue", Arial, sans-serif',
          fill: new Fill({color: CLUSTER_TEXT_COLOR}),
        }),
      }));
    }

    return this.styleCache.get(cacheKey);
  }

  #resolveMarkerStyle(tourType) {

    // A ready-made style (keeps the previous API working). Careful: the API
    // delivers tourType as an array of IDs, so only an array *of styles*
    // may be passed straight through.
    if (tourType instanceof Style) {
      return tourType;
    }

    if (Array.isArray(tourType) && tourType[0] instanceof Style) {
      return tourType;
    }

    const iconSrc = this.getTourTypeIcon(tourType);

    // No icon for this tour type -> fall back to the plain marker.
    return iconSrc ? this.#getTourTypeMarkerStyle(iconSrc) : this.#getDefaultMarkerStyle();
  }

  /**
   * A light grey speech bubble with the tour type icon centred in its body.
   * Both layers share the same anchor point: the tip of the tail.
   */
  #getTourTypeMarkerStyle(iconSrc) {
    const cacheKey = 'icon-' + iconSrc;

    if (!this.styleCache.has(cacheKey)) {
      // Distance from the tail tip up to the centre of the bubble body.
      const iconOffsetY = BUBBLE_HEIGHT - (BUBBLE_BODY / 2);

      this.styleCache.set(cacheKey, [
        new Style({
          image: new Icon({
            src: this.bubbleSrc,
            anchor: [0.5, 1],
            width: BUBBLE_WIDTH,
            height: BUBBLE_HEIGHT,
          }),
        }),
        new Style({
          image: new Icon({
            src: iconSrc,
            anchor: [0.5, 0.5],
            width: TOUR_TYPE_ICON_SIZE,
            height: TOUR_TYPE_ICON_SIZE,
            // Positive y shifts the icon upwards, into the bubble body.
            displacement: [0, iconOffsetY],
          }),
        }),
      ]);
    }

    return this.styleCache.get(cacheKey);
  }

  #getDefaultMarkerStyle() {
    if (!this.styleCache.has('default')) {
      this.styleCache.set('default', new Style({
        image: new Icon({
          src: DEFAULT_MARKER_SRC,
          anchor: [0.5, 1],
          width: 38,
          height: 40,
        })
      }));
    }

    return this.styleCache.get('default');
  }
}

export default SwisstopoMap;

// Damit HTML sie direkt nutzen kann:
window.SwisstopoMap = SwisstopoMap;
