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

import Overlay from 'ol/Overlay.js';
import {defaults as defaultInteractions} from 'ol/interaction/defaults.js';
import DragPan from 'ol/interaction/DragPan.js';
import MouseWheelZoom from 'ol/interaction/MouseWheelZoom.js';
import {platformModifierKeyOnly} from 'ol/events/condition.js';
import Feature from 'ol/Feature.js';
import Point from 'ol/geom/Point.js';
import VectorLayer from 'ol/layer/Vector.js';
import VectorSource from 'ol/source/Vector.js';
import Cluster from 'ol/source/Cluster.js';
import {boundingExtent} from 'ol/extent.js';


const DEFAULT_MARKER_SRC = 'bundles/markocupicsaceventtool/icons/swisstopo/map-marker-red.svg';

// ---------------------------------------------------------------------------
// Marker appearance - adjust here.
// ---------------------------------------------------------------------------

// Speech bubble holding the tour type icon. Used for every event type that
// EVENT_TYPE_COLORS does not cover.
const BUBBLE_FILL = '#D62828';          // background
const BUBBLE_STROKE = '#D62828';        // border
const BUBBLE_STROKE_WIDTH = 1.6;        // border width

// Bubble colour per event type. The keys are the values of
// Markocupic\SacEventToolBundle\Config\EventType. Override or extend this
// from the outside with the eventTypeColors option.
const EVENT_TYPE_COLORS = {
  course: {fill: '#1B6FD6', stroke: '#1B6FD6'},          // Kurs -> blau
  tour: {fill: '#D62828', stroke: '#D62828'},            // Tour -> rot
  lastMinuteTour: {fill: '#D62828', stroke: '#D62828'},  // Last-Minute-Tour -> rot
  generalEvent: {fill: '#2E7D32', stroke: '#2E7D32'},    // Anlass -> gruen
};

// Speech bubble geometry (px). The tail tip is the anchor that sits on the coordinate.
const BUBBLE_WIDTH = 56;
const BUBBLE_HEIGHT = 66;   // 56 body + 10 tail
const BUBBLE_BODY = 56;
const TOUR_TYPE_ICON_SIZE = 40;

// Outline of the bubble: rounded body plus the tail, drawn in one path so the
// border does not cut across the tail.
const BUBBLE_PATH = 'M10 1 H46 A9 9 0 0 1 55 10 V46 A9 9 0 0 1 46 55 H34 L28 64.6 L22 55 '
    + 'H10 A9 9 0 0 1 1 46 V10 A9 9 0 0 1 10 1 Z';

/**
 * Builds the speech bubble as an inline SVG data URI. An external SVG loaded
 * through Icon({src}) cannot be recoloured, so it is generated here instead.
 *
 * @param {string} fill
 * @param {string} stroke
 * @param {number} strokeWidth
 * @return {string}
 */
function buildBubbleSrc(fill, stroke, strokeWidth) {
  // width/height are required: without an intrinsic size the browser cannot
  // scale the data URI correctly and the bubble is drawn distorted.
  const svg = '<svg xmlns="http://www.w3.org/2000/svg"'
      + ' width="' + BUBBLE_WIDTH + '" height="' + BUBBLE_HEIGHT + '"'
      + ' viewBox="0 0 ' + BUBBLE_WIDTH + ' ' + BUBBLE_HEIGHT + '">'
      + '<path d="' + BUBBLE_PATH + '" fill="' + fill + '" stroke="' + stroke
      + '" stroke-width="' + strokeWidth + '" stroke-linejoin="round"/></svg>';

  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

// Cluster bubble. Its colour follows the event type as long as a bundle holds
// one type only; a mixed bundle uses the default bubble colour.
const CLUSTER_TEXT_COLOR = '#ffffff';
const CLUSTER_OPACITY = 0.92;

// Two coronas around the counter circle, growing fainter outwards.
const CLUSTER_HALO_WIDTH = 7;           // inner corona thickness (px)
const CLUSTER_HALO_OPACITY = 0.35;
const CLUSTER_HALO_OUTER_WIDTH = 7;     // outer corona thickness (px)
const CLUSTER_HALO_OUTER_OPACITY = 0.16;

/**
 * Turns #rgb/#rrggbb into rgba(). Anything else (rgb(), a colour name) is
 * handed back untouched, so custom colours keep working.
 *
 * @param {string} color
 * @param {number} alpha
 * @return {string}
 */
function hexToRgba(color, alpha) {
  const match = /^#?([\da-f]{3}|[\da-f]{6})$/i.exec(String(color).trim());

  if (!match) {
    return color;
  }

  let hex = match[1];

  if (3 === hex.length) {
    hex = hex.split('').map(c => c + c).join('');
  }

  const value = parseInt(hex, 16);

  return 'rgba(' + ((value >> 16) & 255) + ', ' + ((value >> 8) & 255) + ', '
      + (value & 255) + ', ' + alpha + ')';
}

// Smallest resolution of the swisstopo tile grid, used as a zoom-in limit.
const MIN_RESOLUTION = 0.5;

// Zoom level (m/px) used when the view is moved onto a single marker. Below
// the default clusterMaxResolution, so the icon is shown on its own.
const SINGLE_MARKER_RESOLUTION = 5;

// The route overlays (hiking trails, ski tours) only appear once the view is
// zoomed in further than this resolution (m/px) - zoomed out they are just
// noise. Same unit as the zoom argument of the constructor.
const TRAIL_LAYER_MAX_RESOLUTION = 4;

// Extent of the swisstopo tile grid (LV95).
const SWISS_EXTENT = [2420000, 1030000, 2900000, 1350000];

// Label of the button leading to the event page.
const POPUP_LINK_LABEL = 'Zur Detailseite';

// Hints shown when a gesture is held back by the cooperative gesture handling.
const GESTURE_HINT_TOUCH = 'Karte mit zwei Fingern verschieben';
const GESTURE_HINT_WHEEL_WIN = 'Zum Zoomen Strg + Scrollen benutzen';
const GESTURE_HINT_WHEEL_MAC = 'Zum Zoomen ⌘ + Scrollen benutzen';
const GESTURE_HINT_TIMEOUT = 1400;

/**
 * Styles of the popup. Injected once, so the map works without an extra
 * stylesheet; every rule can be overridden from the project CSS.
 */
function injectPopupStyles() {
  if (document.getElementById('swisstopoEventMapPopupStyles')) {
    return;
  }

  const style = document.createElement('style');

  style.id = 'swisstopoEventMapPopupStyles';
  style.textContent = `
.swisstopo-event-map-popup {
	/* One width for every popup, so it does not jump around with the length of
	   the title. Override --swisstopo-popup-width from the project CSS. */
	--swisstopo-popup-width: 280px;

	position: relative;
	box-sizing: border-box;
	width: var(--swisstopo-popup-width);
	/* Never wider than the map, whatever the width above says. */
	max-width: calc(100vw - 32px);
	padding: 12px 32px 12px 14px;
	border-radius: 6px;
	background: #fff;
	box-shadow: 0 4px 14px rgba(0, 0, 0, .25);
	color: #1d1d1d;
	font-size: 13px;
	line-height: 1.45;
	/* A long word must not push the popup open. */
	overflow-wrap: anywhere;
	/* The height is capped to the map in #showPopup(). */
	overflow-y: auto;
}
/* Narrow screens: a bit smaller, so the map stays visible behind it. */
@media (max-width: 575.98px) {
	.swisstopo-event-map-popup {
		--swisstopo-popup-width: 220px;
		padding: 10px 30px 10px 12px;
		font-size: 12px;
	}
}
/* The arrow pointing down at the marker. */
.swisstopo-event-map-popup::after {
	content: '';
	position: absolute;
	left: 50%;
	bottom: -8px;
	width: 0;
	height: 0;
	margin-left: -8px;
	border-left: 8px solid transparent;
	border-right: 8px solid transparent;
	border-top: 8px solid #fff;
}
.swisstopo-event-map-popup__close {
	position: absolute;
	top: 4px;
	right: 4px;
	width: 26px;
	height: 26px;
	padding: 0;
	border: 0;
	background: none;
	color: inherit;
	font-size: 20px;
	line-height: 1;
	opacity: .5;
	cursor: pointer;
}
.swisstopo-event-map-popup__close:hover {
	opacity: 1;
}
.swisstopo-event-map-popup__dates {
	font-size: 12px;
	opacity: .75;
}
.swisstopo-event-map-popup__title {
	margin: 2px 0;
	font-weight: 700;
}
.swisstopo-event-map-popup__meta {
	font-size: 12px;
	opacity: .8;
}
.swisstopo-event-map-popup__link {
	display: inline-block;
	margin-top: 10px;
	/* Comfortable tap target on touch devices. */
	min-height: 38px;
	padding: 9px 14px;
	border-radius: 4px;
	background: #D62828;
	color: #fff;
	font-size: 13px;
	font-weight: 600;
	line-height: 20px;
	text-decoration: none;
}
.swisstopo-event-map-popup__link:hover,
.swisstopo-event-map-popup__link:focus {
	background: #b51f1f;
	color: #fff;
	text-decoration: none;
}
/* Hint of the cooperative gesture handling. */
.swisstopo-map-gesture-hint {
	position: absolute;
	inset: 0;
	z-index: 4;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 12px;
	background: rgba(0, 0, 0, .45);
	color: #fff;
	font-size: 15px;
	font-weight: 600;
	text-align: center;
	pointer-events: none;
	opacity: 0;
	transition: opacity .2s ease-out;
}
`;

  document.head.appendChild(style);
}

// How long the zoom indicator stays visible after the last zoom change (ms).
const ZOOM_INDICATOR_TIMEOUT = 1500;

// Separator between the single dates of an event.
const DATE_SEPARATOR = ', ';

/**
 * Escapes text before it goes into the tooltip markup.
 *
 * @param {*} value
 * @return {string}
 */
function escapeHtml(value) {
  return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
}

/**
 * Formats a UNIX timestamp (seconds, as Contao stores it) as dd.mm.YYYY.
 *
 * @param {number|string} timestamp
 * @return {string}
 */
function formatDate(timestamp) {
  const seconds = parseInt(timestamp, 10);

  if (!seconds) {
    return '';
  }

  const date = new Date(seconds * 1000);
  const pad = value => String(value).padStart(2, '0');

  return pad(date.getDate()) + '.' + pad(date.getMonth() + 1) + '.' + date.getFullYear();
}

/**
 * Turns the eventDates field of the API into "01.02.2026, 02.02.2026". Each
 * entry is either a bare timestamp or a row of the repeat wizard
 * ({new_repeat: <timestamp>}).
 *
 * @param {Array|null} eventDates
 * @return {string}
 */
function formatEventDates(eventDates) {
  if (!Array.isArray(eventDates)) {
    return '';
  }

  return eventDates
      .map(entry => (entry && typeof entry === 'object' ? entry.new_repeat ?? entry.value : entry))
      .map(timestamp => formatDate(timestamp))
      .filter(Boolean)
      .join(DATE_SEPARATOR);
}


class SwisstopoEventMap {
  /**
   * @param {string}   elementId
   * @param {number}   zoom
   * @param {number[]} center
   * @param {{
   *   tourTypeIcons?: Object<number, string>,
   *   tourTypeNames?: Object<number, string>,
   *   eventOrganizerNames?: Object<number, string>,
   *   bubbleFill?: string,
   *   bubbleStroke?: string,
   *   bubbleStrokeWidth?: number,
   *   bubbleSrc?: string,
   *   eventTypeColors?: Object<string, {fill: string, stroke?: string}>,
   *   cluster?: boolean,
   *   clusterDistance?: number,
   *   clusterMinDistance?: number,
   *   clusterMaxResolution?: number,
   *   trailLayerMaxResolution?: number,
   *   popupLinkLabel?: string,
   *   cooperativeGestures?: boolean,
   *   zoomIndicator?: boolean,
   *   zoomIndicatorTimeout?: number,
   * }} options
   *   tourTypeIcons         Maps a tour type ID to the URL of its icon.
   *   tourTypeNames         Maps a tour type ID (tl_tour_type) to its title.
   *   eventOrganizerNames   Maps an organizer ID (tl_event_organizer) to its
   *                         title. Both are only used for the tooltip.
   *   eventTypeColors       Maps an event type (course, tour, lastMinuteTour,
   *                         generalEvent) to its bubble colour. Merged into the
   *                         defaults, so single entries can be overridden.
   *   cluster               Bundle overlapping markers into a counter bubble.
   *   popupLinkLabel        Label of the button in the popup.
   *   cooperativeGestures   One finger scrolls the page, two fingers move the
   *                         map; the mouse wheel only zooms with Ctrl/Cmd. A
   *                         hint is shown when a gesture is held back.
   *   zoomIndicator         Briefly shows the current zoom level whenever it
   *                         changes. Handy for choosing the thresholds above.
   *   zoomIndicatorTimeout  How long it stays visible (ms).
   *   trailLayerMaxResolution  The hiking trail and ski tour overlays are only
   *                         drawn below this resolution (m/px). 0 shows them at
   *                         every zoom level.
   *   clusterMaxResolution  Bundling stops once the view is zoomed in past this
   *                         resolution (m/px), so every icon becomes visible on
   *                         its own. Smaller value = you have to zoom in further.
   */
  constructor(elementId, zoom = 5, center = [2600000, 1200000], options = {}) {
    this.map = null;

    // Bubble colour per event type, defaults extended by the caller's map.
    this.eventTypeColors = {...EVENT_TYPE_COLORS, ...(options.eventTypeColors ?? {})};

    // Colour for events without (or with an unknown) event type.
    this.defaultBubbleColor = {
      fill: options.bubbleFill ?? BUBBLE_FILL,
      stroke: options.bubbleStroke ?? options.bubbleFill ?? BUBBLE_STROKE,
    };
    this.bubbleStrokeWidth = options.bubbleStrokeWidth ?? BUBBLE_STROKE_WIDTH;

    // A ready-made image handed in via bubbleSrc wins over every colour and is
    // then used for all event types.
    this.bubbleSrcOverride = options.bubbleSrc ?? null;
    this.bubbleSrcCache = new Map();

    // Kept for backwards compatibility: the bubble of the default colour.
    this.bubbleSrc = this.#getBubbleSrc(null);

    this.tourTypeIcons = options.tourTypeIcons ?? {};

    // Foreign key => title, for the tooltip.
    this.tourTypeNames = options.tourTypeNames ?? {};
    this.eventOrganizerNames = options.eventOrganizerNames ?? {};
    this.clusterEnabled = options.cluster ?? true;
    this.clusterMaxResolution = options.clusterMaxResolution ?? 20;
    this.trailLayerMaxResolution = options.trailLayerMaxResolution ?? TRAIL_LAYER_MAX_RESOLUTION;

    this.popupLinkLabel = options.popupLinkLabel ?? POPUP_LINK_LABEL;
    this.cooperativeGestures = options.cooperativeGestures ?? true;
    this.gestureHint = null;
    this.gestureHintTimer = null;
    this.popupOverlay = null;

    this.zoomIndicatorEnabled = options.zoomIndicator ?? true;
    this.zoomIndicatorTimeout = options.zoomIndicatorTimeout ?? ZOOM_INDICATOR_TIMEOUT;
    this.zoomIndicator = null;
    this.zoomIndicatorTimer = null;

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
   * @param {string|null} eventType  course, tour, lastMinuteTour or generalEvent.
   *        Picks the bubble colour; unknown values fall back to the default one.
   * @param {{eventDates?: Array, organizers?: Array}} details  Additional data
   *        shown in the tooltip.
   */
  addMarker(x, y, title, url, tourType = null, eventType = null, details = {}) {

    const feature = new Feature({
      geometry: new Point([x, y]),
      title: title,
      url: url,
      tourType: tourType,
      eventType: eventType,
      eventDates: details.eventDates ?? null,
      organizers: details.organizers ?? null,
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
   * Removes every marker from the map. An open popup is closed as well - its
   * event may be gone after the redraw (filter change).
   */
  clearMarkers() {
    this.closePopup();
    this.markerSource.clear();
  }

  /**
   * Closes the popup from the outside.
   */
  closePopup() {
    this.#hidePopup();
  }

  /**
   * Moves the view onto the marker closest to a reference coordinate - the
   * configured centre of the map, usually. Without markers the view goes back
   * to that reference instead.
   *
   * @param {number[]} reference  [east, north] in LV95
   * @param {{duration?: number, resolution?: number}} options
   * @return {number[]|null}  The coordinate the view was moved to.
   */
  centerOnNearestMarker(reference, options = {}) {
    const view = this.map.getView();
    const duration = options.duration ?? 400;
    const features = this.markerSource.getFeatures();

    let target = [...reference];

    if (features.length) {
      let shortest = Infinity;

      for (const feature of features) {
        const coordinate = feature.getGeometry().getCoordinates();
        // Squared distance is enough for a comparison.
        const distance = (coordinate[0] - reference[0]) ** 2 + (coordinate[1] - reference[1]) ** 2;

        if (distance < shortest) {
          shortest = distance;
          target = [...coordinate];
        }
      }
    }

    const animation = {center: target, duration: duration};

    if (options.resolution) {
      animation.resolution = Math.max(options.resolution, MIN_RESOLUTION);
    }

    view.animate(animation);

    return features.length ? target : null;
  }

  /**
   * Moves the view onto the current markers.
   *
   * With a single marker the view is centred on it and zoomed in, because a
   * lone marker is easily off screen. With several markers their extent is
   * fitted. Without markers nothing happens.
   *
   * @param {{resolution?: number, padding?: number[]}} options
   *   resolution  Zoom level (m/px) for the single marker case. Pass 0 to keep
   *               the current one.
   * @return {boolean}  Whether the view was moved.
   */
  zoomToMarkers(options = {}) {
    const features = this.markerSource.getFeatures();

    if (!features.length) {
      return false;
    }

    const view = this.map.getView();

    if (1 === features.length) {
      const resolution = options.resolution ?? SINGLE_MARKER_RESOLUTION;

      view.setCenter([...features[0].getGeometry().getCoordinates()]);

      if (resolution) {
        view.setResolution(Math.max(resolution, MIN_RESOLUTION));
      }

      return true;
    }

    view.fit(this.markerSource.getExtent(), {
      padding: options.padding ?? [60, 60, 60, 60],
      minResolution: MIN_RESOLUTION,
    });

    return true;
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
    injectPopupStyles();

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
      interactions: this.cooperativeGestures ? this.#createCooperativeInteractions() : undefined,
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
        this.#createTrailLayer('ch.swisstopo.swisstlm3d-wanderwege', swissProj, tileGrid),

        // Skitouren
        this.#createTrailLayer('ch.swisstopo-karto.skitouren', swissProj, tileGrid),

        this.markerLayer,
      ],

      view: new View({
        projection: swissProj,
        center: [...center],
        resolution: zoom,
      }),
    });


    this.#createZoomIndicator(target);
    this.#createGestureHint(target);

    // Bundling depends on the current resolution, so re-evaluate on every zoom.
    this.#applyClusterState();

    this.map.getView().on('change:resolution', () => {
      this.#applyClusterState();
      this.#showZoomIndicator();
    });

    // --- 5. EVENTS ---
    this.#createPopup();

    // Click instead of hover, so the popup also works on touch devices.
    this.map.on("singleclick", evt => {
      const feature = this.map.forEachFeatureAtPixel(evt.pixel, f => f);

      if (!feature) {
        this.#hidePopup();

        return;
      }

      const members = this.#getMembers(feature);

      // A bundle -> zoom in until it falls apart instead of opening a popup.
      if (members.length > 1) {
        this.#hidePopup();
        this.#zoomToCluster(members);

        return;
      }

      this.#showPopup(members[0]);
    });

    // Hover only changes the cursor now.
    this.map.on("pointermove", evt => {
      const hit = this.map.hasFeatureAtPixel(evt.pixel);

      this.map.getTargetElement().style.cursor = hit ? "pointer" : "";
    });

    document.addEventListener('keydown', event => {
      if ('Escape' === event.key) {
        this.#hidePopup();
      }
    });
  }

  /**
   * Cooperative gestures, as OpenLayers has no ready-made option for it:
   * on a touch screen one finger scrolls the page and only two fingers move
   * the map, and the wheel zooms only together with Ctrl/Cmd. Mouse and pen
   * keep dragging the map as before.
   */
  #createCooperativeInteractions() {
    return defaultInteractions({dragPan: false, mouseWheelZoom: false}).extend([
      new DragPan({
        // A normal function, so "this" is the interaction and its pointers
        // can be counted.
        condition: function (event) {
          const pointerType = event.originalEvent?.pointerType;

          // Mouse and pen drag the map as usual.
          if (pointerType && 'touch' !== pointerType) {
            return true;
          }

          return 2 === this.getPointerCount();
        },
      }),
      new MouseWheelZoom({condition: platformModifierKeyOnly}),
    ]);
  }

  /**
   * The hint shown when a gesture was held back.
   */
  #createGestureHint(target) {
    if (!this.cooperativeGestures) {
      return;
    }

    if ('static' === window.getComputedStyle(target).position) {
      target.style.position = 'relative';
    }

    const hint = document.createElement('div');

    hint.className = 'swisstopo-map-gesture-hint';
    hint.setAttribute('aria-hidden', 'true');

    target.appendChild(hint);

    this.gestureHint = hint;

    const isMac = /Mac|iPhone|iPad|iPod/.test(window.navigator.platform || '');

    // Wheel without the modifier key: the page scrolls, we only explain why.
    target.addEventListener('wheel', event => {
      if (!platformModifierKeyOnly({originalEvent: event})) {
        this.#showGestureHint(isMac ? GESTURE_HINT_WHEEL_MAC : GESTURE_HINT_WHEEL_WIN);
      }
    }, {passive: true});

    target.addEventListener('touchmove', event => {
      if (1 === event.touches.length) {
        this.#showGestureHint(GESTURE_HINT_TOUCH);
      } else {
        this.#hideGestureHint();
      }
    }, {passive: true});
  }

  #showGestureHint(text) {
    if (!this.gestureHint) {
      return;
    }

    this.gestureHint.textContent = text;
    this.gestureHint.style.opacity = '1';

    window.clearTimeout(this.gestureHintTimer);

    this.gestureHintTimer = window.setTimeout(() => this.#hideGestureHint(), GESTURE_HINT_TIMEOUT);
  }

  #hideGestureHint() {
    if (this.gestureHint) {
      window.clearTimeout(this.gestureHintTimer);
      this.gestureHint.style.opacity = '0';
    }
  }

  /**
   * The popup is an OpenLayers overlay, so it stays glued to its marker while
   * the map is panned or zoomed.
   */
  #createPopup() {
    const element = document.createElement('div');

    element.className = 'swisstopo-event-map-popup';
    element.hidden = true;

    this.popupOverlay = new Overlay({
      element: element,
      // Sits above the marker, its arrow pointing at the tip of the bubble.
      offset: [0, -(BUBBLE_HEIGHT + 6)],
      positioning: 'bottom-center',
      autoPan: {animation: {duration: 250}, margin: 20},
      // Clicks inside the popup must not reach the map.
      stopEvent: true,
    });

    this.map.addOverlay(this.popupOverlay);
  }

  /**
   * Opens the popup of one event above its marker.
   */
  #showPopup(feature) {
    if (!this.popupOverlay) {
      return;
    }

    const element = this.popupOverlay.getElement();

    element.innerHTML = this.#getPopupHtml(feature);
    element.hidden = false;

    // On a short map the popup would be cut off at the top edge, so it never
    // grows taller than the space above the marker and scrolls instead.
    const mapHeight = this.map.getSize()?.[1] ?? 0;

    element.style.maxHeight = mapHeight
        ? Math.max(120, mapHeight - BUBBLE_HEIGHT - 20) + 'px'
        : '';

    element.querySelector('.swisstopo-event-map-popup__close')
        .addEventListener('click', () => this.#hidePopup());

    this.popupOverlay.setPosition(feature.getGeometry().getCoordinates());
  }

  #hidePopup() {
    if (!this.popupOverlay) {
      return;
    }

    this.popupOverlay.getElement().hidden = true;
    this.popupOverlay.setPosition(undefined);
  }

  /**
   * A small badge in the corner of the map showing the current zoom level.
   * It is only visible right after a zoom change.
   */
  #createZoomIndicator(target) {
    if (!this.zoomIndicatorEnabled) {
      return;
    }

    // The badge is positioned against the map element.
    if ('static' === window.getComputedStyle(target).position) {
      target.style.position = 'relative';
    }

    const indicator = document.createElement('div');

    indicator.className = 'swisstopo-map-zoom-indicator';
    indicator.style.cssText = 'position:absolute;top:8px;right:8px;z-index:5;'
        + 'padding:4px 8px;border-radius:4px;background:rgba(0, 0, 0, 0.75);'
        + 'color:#fff;font-size:11px;line-height:1.3;white-space:nowrap;'
        + 'pointer-events:none;opacity:0;transition:opacity .25s ease-out;';

    target.appendChild(indicator);

    this.zoomIndicator = indicator;
  }

  /**
   * Shows the zoom level and hides it again after zoomIndicatorTimeout. The
   * resolution is given as well, because the thresholds of this class
   * (clusterMaxResolution, trailLayerMaxResolution) are resolutions.
   */
  #showZoomIndicator() {
    if (!this.zoomIndicator) {
      return;
    }

    const view = this.map.getView();
    const zoom = view.getZoom();
    const resolution = view.getResolution();

    this.zoomIndicator.textContent = 'Zoom '
        + (undefined === zoom ? '-' : zoom.toFixed(1))
        + ' | ' + resolution.toFixed(resolution < 10 ? 1 : 0) + ' m/px';

    this.zoomIndicator.style.opacity = '1';

    // Every further change restarts the countdown.
    window.clearTimeout(this.zoomIndicatorTimer);

    this.zoomIndicatorTimer = window.setTimeout(() => {
      this.zoomIndicator.style.opacity = '0';
    }, this.zoomIndicatorTimeout);
  }

  /**
   * One of the route overlays. They share everything but the layer name and
   * are hidden while the view is zoomed out further than
   * trailLayerMaxResolution.
   *
   * @param {string} layer  swisstopo WMTS layer name
   * @return {TileLayer}
   */
  #createTrailLayer(layer, projection, tileGrid) {
    return new TileLayer({
      extent: SWISS_EXTENT,
      // OpenLayers draws a layer while resolution < maxResolution, so this
      // hides the overlay as soon as the view is zoomed out beyond it.
      maxResolution: this.trailLayerMaxResolution || undefined,
      source: new WMTS({
        url: 'https://wmts.geo.admin.ch/1.0.0/' + layer + '/default/current/2056/{TileMatrix}/{TileCol}/{TileRow}.png',
        layer: layer,
        matrixSet: '2056',
        format: 'image/png',
        projection: projection,
        tileGrid,
        style: 'default',
        requestEncoding: 'REST',
        wrapX: false,
        crossOrigin: 'anonymous',
      }),
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

  /**
   * Popup content: dates, title, tour type, organizers and the button to the
   * event page. The foreign keys are resolved through the maps handed in as
   * options.
   *
   * @return {string} HTML
   */
  #getPopupHtml(event) {
    const rows = ['<button type="button" class="swisstopo-event-map-popup__close"'
    + ' aria-label="Schliessen">&times;</button>'];

    const dates = formatEventDates(event.get('eventDates'));

    if (dates) {
      rows.push('<div class="swisstopo-event-map-popup__dates">' + escapeHtml(dates) + '</div>');
    }

    const title = event.get('title');

    if (title) {
      rows.push('<div class="swisstopo-event-map-popup__title">' + escapeHtml(title) + '</div>');
    }

    const tourTypes = this.#resolveNames(event.get('tourType'), this.tourTypeNames);

    if (tourTypes.length) {
      rows.push('<div class="swisstopo-event-map-popup__meta">'
          + escapeHtml(tourTypes.join(', ')) + '</div>');
    }

    const organizers = this.#resolveNames(event.get('organizers'), this.eventOrganizerNames);

    if (organizers.length) {
      rows.push('<div class="swisstopo-event-map-popup__meta">'
          + escapeHtml(organizers.join(', ')) + '</div>');
    }

    const url = event.get('url');

    if (url) {
      rows.push('<a class="swisstopo-event-map-popup__link" href="' + escapeHtml(url) + '"'
          + ' target="_blank" rel="noopener">' + escapeHtml(this.popupLinkLabel) + '</a>');
    }

    return rows.join('');
  }

  /**
   * Resolves one or more foreign keys through a map of ID => title. Unknown
   * IDs are dropped rather than shown as a number.
   *
   * @param {number|string|Array|null} ids
   * @param {Object<number, string>}   names
   * @return {string[]}
   */
  #resolveNames(ids, names) {
    if (null === ids || undefined === ids || '' === ids) {
      return [];
    }

    return (Array.isArray(ids) ? ids : [ids])
        .map(id => names[id] ?? null)
        .filter(Boolean);
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
      return this.#getClusterStyle(members);
    }

    const single = members[0];

    // A style attached to the feature wins (previous API).
    return single.getStyle()
        ?? this.#resolveMarkerStyle(single.get('tourType'), single.get('eventType'));
  }

  /**
   * The counter bubble, mirroring the SAC tour portal. It takes on the colour
   * of the event type when every bundled marker shares the same one.
   *
   * @param {Feature[]} members
   */
  #getClusterStyle(members) {
    const count = members.length;
    const color = this.#getBubbleColor(this.#getSharedEventType(members)).fill;
    const cacheKey = 'cluster-' + count + '-' + color;

    if (!this.styleCache.has(cacheKey)) {
      // Grows slowly, so 100 markers do not produce a huge blob.
      const radius = 15 + Math.min(11, Math.log2(count) * 3.2);

      // A stroke sits centred on its radius, so each ring is placed half its
      // own width further out than the one before it.
      const innerHaloRadius = radius + (CLUSTER_HALO_WIDTH / 2);
      const outerHaloRadius = radius + CLUSTER_HALO_WIDTH + (CLUSTER_HALO_OUTER_WIDTH / 2);

      this.styleCache.set(cacheKey, [
        // Outer corona, drawn first so the others sit on top.
        new Style({
          image: new CircleStyle({
            radius: outerHaloRadius,
            stroke: new Stroke({
              color: hexToRgba(color, CLUSTER_HALO_OUTER_OPACITY),
              width: CLUSTER_HALO_OUTER_WIDTH,
            }),
          }),
        }),
        // Inner corona.
        new Style({
          image: new CircleStyle({
            radius: innerHaloRadius,
            stroke: new Stroke({
              color: hexToRgba(color, CLUSTER_HALO_OPACITY),
              width: CLUSTER_HALO_WIDTH,
            }),
          }),
        }),
        // The solid circle with the counter.
        new Style({
          image: new CircleStyle({
            radius: radius,
            fill: new Fill({color: hexToRgba(color, CLUSTER_OPACITY)}),
          }),
          text: new Text({
            text: String(count),
            font: 'bold 13px "Helvetica Neue", Arial, sans-serif',
            fill: new Fill({color: CLUSTER_TEXT_COLOR}),
          }),
        }),
      ]);
    }

    return this.styleCache.get(cacheKey);
  }

  /**
   * The event type shared by every bundled marker, or null for a mixed bundle.
   *
   * @param {Feature[]} members
   * @return {string|null}
   */
  #getSharedEventType(members) {
    const first = members[0].get('eventType') ?? null;

    return members.every(f => (f.get('eventType') ?? null) === first) ? first : null;
  }

  /**
   * Bubble colour of an event type, falling back to the default colour.
   *
   * @param {string|null} eventType
   * @return {{fill: string, stroke: string}}
   */
  #getBubbleColor(eventType) {
    const color = eventType ? this.eventTypeColors[eventType] : null;

    return {
      fill: color?.fill ?? this.defaultBubbleColor.fill,
      stroke: color?.stroke ?? color?.fill ?? this.defaultBubbleColor.stroke,
    };
  }

  /**
   * The bubble image of an event type, built once per colour combination.
   *
   * @param {string|null} eventType
   * @return {string}
   */
  #getBubbleSrc(eventType) {
    if (this.bubbleSrcOverride) {
      return this.bubbleSrcOverride;
    }

    const {fill, stroke} = this.#getBubbleColor(eventType);
    const cacheKey = fill + '|' + stroke;

    if (!this.bubbleSrcCache.has(cacheKey)) {
      this.bubbleSrcCache.set(cacheKey, buildBubbleSrc(fill, stroke, this.bubbleStrokeWidth));
    }

    return this.bubbleSrcCache.get(cacheKey);
  }

  #resolveMarkerStyle(tourType, eventType = null) {

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
    return iconSrc
        ? this.#getTourTypeMarkerStyle(iconSrc, eventType)
        : this.#getDefaultMarkerStyle();
  }

  /**
   * A light grey speech bubble with the tour type icon centred in its body.
   * Both layers share the same anchor point: the tip of the tail.
   */
  #getTourTypeMarkerStyle(iconSrc, eventType = null) {
    const bubbleSrc = this.#getBubbleSrc(eventType);
    const cacheKey = 'icon-' + iconSrc + '-' + (eventType ?? '');

    if (!this.styleCache.has(cacheKey)) {
      // Distance from the tail tip up to the centre of the bubble body.
      const iconOffsetY = BUBBLE_HEIGHT - (BUBBLE_BODY / 2);

      this.styleCache.set(cacheKey, [
        new Style({
          image: new Icon({
            src: bubbleSrc,
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

export default SwisstopoEventMap;

// Damit HTML sie direkt nutzen kann:
window.SwisstopoEventMap = SwisstopoEventMap;
