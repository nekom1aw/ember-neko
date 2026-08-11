import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { PMTiles } from 'pmtiles';
import {
    GeomType,
    LineSymbolizer,
    PolygonSymbolizer,
    leafletLayer,
} from 'protomaps-leaflet';


const EmberMap = {
    map: null,
    markerLayer: null,
    boundaryLayers: [],
    boundaryHoverTooltip: null,
    boundaryHoverFrame: null,
    pendingBoundaryHoverEvent: null,
    boundaryPointFocus: false,
    focusedLocationLabel: null,
    boundarySelection: {
        province: null,
        regency: null,
        district: null,
        village: null,
    },
    locations: [],
    language: 'id',

    init() {
        const mapElement = document.getElementById('ember-map');
        const dataElement = document.getElementById('ember-map-data');

        if (!mapElement || !dataElement || mapElement.dataset.initialized === 'true') {
            return;
        }

        mapElement.dataset.initialized = 'true';
        const payload = JSON.parse(dataElement.textContent || '{}');
        const locations = Array.isArray(payload) ? payload : (payload.locations || []);
        this.locations = locations;
        this.language = payload.language === 'en' ? 'en' : 'id';

        this.map = L.map(mapElement, {
            scrollWheelZoom: false,
        }).setView([-2.5, 118], 5);

        L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            {
                maxZoom: 20,
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            },
        ).addTo(this.map);

        this.map.createPane('boundaries');
        this.map.getPane('boundaries').style.zIndex = '350';
        this.renderBoundaryLayers(payload.boundaryLayers || []);

        this.markerLayer = L.layerGroup().addTo(this.map);
        this.renderMarkers(locations, { fitView: true });
        this.initializeYearFilter();
        this.initializeBoundaryDrilldown();
        this.initializeBoundaryHover();
        this.map.on('click', (event) => {
            this.handleBoundaryClick(event);
            this.closeLocationDetail();
        });
    },

    async renderBoundaryLayers(layers) {
        for (const layerDefinition of layers) {
            try {
                const archive = new PMTiles(layerDefinition.url);
                const metadata = await archive.getMetadata();
                const sourceLayers = Array.isArray(metadata?.vector_layers)
                    ? metadata.vector_layers.map((layer) => layer.id).filter(Boolean)
                    : [];

                if (sourceLayers.length === 0) {
                    console.warn(`Layer ${layerDefinition.name} tidak memiliki vector_layers.`);
                    continue;
                }

                const paintRules = sourceLayers.flatMap((dataLayer) => [
                    {
                        dataLayer,
                        filter: (_zoom, feature) => feature.geomType === GeomType.Polygon
                            && this.featureMatchesBoundarySelection(feature.props, layerDefinition.level),
                        symbolizer: new PolygonSymbolizer({
                            fill: '#dc2626',
                            opacity: layerDefinition.level === 'province' ? 0.08 : 0.045,
                        }),
                    },
                    {
                        dataLayer,
                        filter: (_zoom, feature) => feature.geomType !== GeomType.Point
                            && this.featureMatchesBoundarySelection(feature.props, layerDefinition.level),
                        symbolizer: new LineSymbolizer({
                            color: '#b91c1c',
                            width: layerDefinition.level === 'province' ? 1.8 : 1,
                            opacity: 0.7,
                            lineJoin: 'round',
                        }),
                    },
                ]);

                const boundaryLayer = leafletLayer({
                    url: archive,
                    paintRules,
                    labelRules: [],
                    pane: 'boundaries',
                    minZoom: Number(layerDefinition.minZoom ?? 0),
                    maxZoom: Number(layerDefinition.maxZoom ?? 20),
                    noWrap: true,
                    attribution: 'Batas wilayah',
                });

                boundaryLayer.addTo(this.map);
                this.boundaryLayers.push({
                    definition: layerDefinition,
                    layer: boundaryLayer,
                });
            } catch (error) {
                console.error(`Layer ${layerDefinition.name} gagal dimuat.`, error);
            }
        }
    },

    initializeBoundaryDrilldown() {
        document.getElementById('map-boundary-reset')?.addEventListener('click', () => {
            this.resetBoundaryDrilldown();
        });

        this.map.on('zoomend', () => {
            const zoom = this.map.getZoom();

            if (zoom <= 6) {
                this.boundarySelection = { province: null, regency: null, district: null, village: null };
                this.boundaryPointFocus = false;
                this.focusedLocationLabel = null;
            } else if (zoom <= 8) {
                this.boundarySelection.regency = null;
                this.boundarySelection.district = null;
                this.boundarySelection.village = null;
            } else if (zoom <= 10) {
                this.boundarySelection.district = null;
                this.boundarySelection.village = null;
            }

            this.rerenderBoundaryLayers();
            this.updateBoundaryDrilldownUi();
        });

        this.updateBoundaryDrilldownUi();
    },

    initializeBoundaryHover() {
        this.boundaryHoverTooltip = L.tooltip({
            className: 'ember-boundary-tooltip',
            direction: 'top',
            offset: [0, -10],
            opacity: 1,
        });

        this.map.on('mousemove', (event) => {
            this.pendingBoundaryHoverEvent = event;

            if (this.boundaryHoverFrame !== null) {
                return;
            }

            this.boundaryHoverFrame = window.requestAnimationFrame(() => {
                this.handleBoundaryHover(this.pendingBoundaryHoverEvent);
                this.boundaryHoverFrame = null;
            });
        });

        this.map.getContainer().addEventListener('mouseleave', () => this.hideBoundaryHover());
        this.map.on('zoomstart', () => this.hideBoundaryHover());
    },

    handleBoundaryHover(event) {
        const activeBoundary = this.activeBoundaryForZoom();

        if (!activeBoundary || !event) {
            this.hideBoundaryHover();
            return;
        }

        try {
            const pickedBySource = activeBoundary.layer.queryTileFeaturesDebug(
                event.latlng.lng,
                event.latlng.lat,
                3,
            );
            const pickedFeatures = Array.from(pickedBySource.values()).flat();
            const picked = pickedFeatures.find(({ feature }) =>
                feature.geomType === GeomType.Polygon
                && this.featureMatchesBoundarySelection(feature.props, activeBoundary.definition.level)
            );

            if (!picked) {
                this.hideBoundaryHover();
                return;
            }

            const propertyByLevel = {
                province: 'LEVEL_3',
                regency: 'LEVEL_4',
                district: 'LEVEL_5',
                village: 'LEVEL_6',
            };
            const labelByLevel = this.language === 'en'
                ? { province: 'Province', regency: 'Regency/City', district: 'District', village: 'Village' }
                : { province: 'Provinsi', regency: 'Kabupaten/Kota', district: 'Kecamatan', village: 'Desa' };
            const level = activeBoundary.definition.level;
            const name = picked.feature.props[propertyByLevel[level]];

            if (!name) {
                this.hideBoundaryHover();
                return;
            }

            const content = document.createElement('div');
            const typeElement = document.createElement('span');
            const nameElement = document.createElement('strong');
            typeElement.className = 'ember-boundary-tooltip-type';
            nameElement.className = 'ember-boundary-tooltip-name';
            typeElement.textContent = labelByLevel[level] || '';
            nameElement.textContent = String(name);
            content.append(typeElement, nameElement);

            this.boundaryHoverTooltip
                .setLatLng(event.latlng)
                .setContent(content);

            if (!this.map.hasLayer(this.boundaryHoverTooltip)) {
                this.boundaryHoverTooltip.addTo(this.map);
            }

            this.map.getContainer().style.cursor = 'pointer';
        } catch {
            this.hideBoundaryHover();
        }
    },

    hideBoundaryHover() {
        if (this.boundaryHoverTooltip && this.map.hasLayer(this.boundaryHoverTooltip)) {
            this.map.removeLayer(this.boundaryHoverTooltip);
        }

        this.map.getContainer().style.cursor = '';
    },

    activeBoundaryForZoom() {
        const zoom = this.map.getZoom();

        return this.boundaryLayers.find(({ definition }) =>
            zoom >= Number(definition.minZoom) && zoom <= Number(definition.maxZoom)
        );
    },

    handleBoundaryClick(event) {
        const activeBoundary = this.activeBoundaryForZoom();

        if (!activeBoundary) {
            return;
        }

        const pickedBySource = activeBoundary.layer.queryTileFeaturesDebug(
            event.latlng.lng,
            event.latlng.lat,
            4,
        );
        const pickedFeatures = Array.from(pickedBySource.values()).flat();
        const picked = pickedFeatures.find(({ feature }) =>
            feature.geomType === GeomType.Polygon
            && this.featureMatchesBoundarySelection(feature.props, activeBoundary.definition.level)
        );

        if (!picked) {
            return;
        }

        const props = picked.feature.props;
        const level = activeBoundary.definition.level;

        if (level === 'province' && props.LEVEL_3) {
            this.boundarySelection = {
                province: String(props.LEVEL_3),
                regency: null,
                district: null,
                village: null,
            };
        } else if (level === 'regency' && props.LEVEL_4) {
            this.boundarySelection.regency = String(props.LEVEL_4);
            this.boundarySelection.district = null;
            this.boundarySelection.village = null;
        } else if (level === 'district' && props.LEVEL_5) {
            this.boundarySelection.district = String(props.LEVEL_5);
            this.boundarySelection.village = null;
        } else if (level === 'village' && props.LEVEL_6) {
            this.boundarySelection.village = String(props.LEVEL_6);
        } else {
            return;
        }

        this.rerenderBoundaryLayers();
        this.updateBoundaryDrilldownUi();

        const levelOrder = ['province', 'regency', 'district', 'village'];
        const nextLevel = levelOrder[levelOrder.indexOf(level) + 1];
        const nextBoundary = this.boundaryLayers.find(({ definition }) => definition.level === nextLevel);

        if (nextBoundary) {
            this.map.setView(event.latlng, Number(nextBoundary.definition.minZoom));
        }
    },

    featureMatchesBoundarySelection(props, level) {
        if (level === 'province' || level === 'other' || this.boundaryPointFocus) {
            return true;
        }

        if (!this.boundarySelection.province || String(props.LEVEL_3 ?? '') !== this.boundarySelection.province) {
            return false;
        }

        if (level === 'regency') {
            return true;
        }

        if (!this.boundarySelection.regency || String(props.LEVEL_4 ?? '') !== this.boundarySelection.regency) {
            return false;
        }

        if (level === 'district') {
            return true;
        }

        return Boolean(this.boundarySelection.district)
            && String(props.LEVEL_5 ?? '') === this.boundarySelection.district;
    },

    rerenderBoundaryLayers() {
        this.boundaryLayers.forEach(({ layer }) => layer.rerenderTiles());
    },

    resetBoundaryDrilldown() {
        this.boundarySelection = { province: null, regency: null, district: null, village: null };
        this.boundaryPointFocus = false;
        this.focusedLocationLabel = null;
        this.rerenderBoundaryLayers();
        this.updateBoundaryDrilldownUi();
        this.map.fitBounds([[-6.5, 94.5], [6.5, 106.5]], { padding: [36, 36], maxZoom: 6 });
    },

    updateBoundaryDrilldownUi() {
        const labels = {
            province: this.language === 'en' ? 'Province' : 'Provinsi',
            regency: this.language === 'en' ? 'Regency/City' : 'Kabupaten/Kota',
            district: this.language === 'en' ? 'District' : 'Kecamatan',
            village: this.language === 'en' ? 'Village' : 'Desa',
        };
        const zoom = this.map.getZoom();
        const activeLevel = this.boundaryLayers.find(({ definition }) =>
            zoom >= Number(definition.minZoom) && zoom <= Number(definition.maxZoom)
        )?.definition.level || 'province';
        const breadcrumb = (this.boundaryPointFocus
            ? [this.language === 'en' ? 'Location point' : 'Titik lokasi', this.focusedLocationLabel]
            : ['Sumatera', this.boundarySelection.province, this.boundarySelection.regency, this.boundarySelection.district, this.boundarySelection.village])
            .filter(Boolean)
            .join(' / ');
        const parentReady = this.boundaryPointFocus || activeLevel === 'province'
            || (activeLevel === 'regency' && this.boundarySelection.province)
            || (activeLevel === 'district' && this.boundarySelection.regency)
            || (activeLevel === 'village' && this.boundarySelection.district);
        const instruction = this.boundaryPointFocus
            ? (this.language === 'en'
                ? 'Showing village boundaries around the selected location.'
                : 'Menampilkan batas desa di sekitar titik yang dipilih.')
            : parentReady
            ? (this.language === 'en'
                ? `Click a ${labels[activeLevel].toLowerCase()} to continue.`
                : `Klik ${labels[activeLevel].toLowerCase()} untuk melanjutkan.`)
            : (this.language === 'en'
                ? 'Zoom out and select the parent region first.'
                : 'Perkecil peta dan pilih wilayah induk terlebih dahulu.');

        const levelElement = document.getElementById('map-boundary-level');
        const breadcrumbElement = document.getElementById('map-boundary-breadcrumb');
        const instructionElement = document.getElementById('map-boundary-instruction');
        const resetButton = document.getElementById('map-boundary-reset');

        if (levelElement) levelElement.textContent = labels[activeLevel] || labels.province;
        if (breadcrumbElement) breadcrumbElement.textContent = breadcrumb;
        if (instructionElement) instructionElement.textContent = instruction;
        resetButton?.classList.toggle('hidden', !this.boundarySelection.province && !this.boundaryPointFocus);
    },

    renderMarkers(locations, { fitView = false } = {}) {
        this.markerLayer.clearLayers();

        const bounds = [];

        locations.forEach((location) => {
            const latitude = Number(location.latitude);
            const longitude = Number(location.longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            const confidence = location.confidence;
            const status = this.statusFor(confidence);
            const marker = L.circleMarker([latitude, longitude], {
                radius: 10,
                color: '#ffffff',
                weight: 3,
                fillColor: status.color,
                fillOpacity: 1,
                interactive: true,
                bubblingMouseEvents: false,
            });

            marker.on('click', () => {
                this.showLocationDetail(location, status);
                this.focusLocationAtVillageLevel(location);
            });
            marker.bindTooltip(
                `${location.desa || (this.language === 'en' ? 'Location point' : 'Titik lokasi')} · ${this.language === 'en' ? 'Click for details' : 'Klik untuk detail'}`,
                { direction: 'top', offset: [0, -8] },
            );
            marker.addTo(this.markerLayer);
            bounds.push([latitude, longitude]);
        });

        const countElement = document.getElementById('map-result-count');
        const emptyElement = document.getElementById('map-filter-empty');

        if (countElement) {
            countElement.textContent = `${locations.length} ${this.language === 'en' ? 'locations' : 'lokasi'}`;
        }

        emptyElement?.classList.toggle('hidden', locations.length > 0);

        if (fitView && bounds.length === 1) {
            this.map.fitBounds([
                [-6.5, 94.5],
                [6.5, 106.5],
            ], {
                padding: [36, 36],
                maxZoom: 6,
            });
        } else if (fitView && bounds.length > 1) {
            this.map.fitBounds(bounds, {
                padding: [36, 36],
                maxZoom: 10,
            });
        } else if (fitView) {
            this.map.setView([-2.5, 118], 5);
        }
    },

    focusLocationAtVillageLevel(location) {
        const latitude = Number(location.latitude);
        const longitude = Number(location.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        const hasCompleteHierarchy = Boolean(
            location.provinsi && location.kabupaten_kota && location.kecamatan
        );

        this.boundaryPointFocus = !hasCompleteHierarchy;
        this.focusedLocationLabel = location.desa
            || `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
        this.boundarySelection = {
            province: location.provinsi || null,
            regency: location.kabupaten_kota || null,
            district: location.kecamatan || null,
            village: location.desa || null,
        };

        this.rerenderBoundaryLayers();
        this.updateBoundaryDrilldownUi();

        const villageLayer = this.boundaryLayers.find(({ definition }) => definition.level === 'village');
        const targetZoom = villageLayer
            ? Math.max(12, Number(villageLayer.definition.minZoom))
            : 12;

        this.map.setView([latitude, longitude], targetZoom, { animate: true });
    },

    initializeYearFilter() {
        const yearRange = document.getElementById('map-year-range');
        const yearLabel = document.getElementById('map-year-label');
        const yearControl = document.querySelector('.map-year-slider-control');
        const yearMarks = document.querySelectorAll('[data-year-mark]');
        const years = yearRange?.dataset.yearValues
            ? yearRange.dataset.yearValues.split(',').filter(Boolean)
            : [];

        if (!yearRange || !yearLabel || !yearControl) {
            return;
        }

        yearRange.addEventListener('input', () => {
            const selectedIndex = Number(yearRange.value);
            const selectedYear = selectedIndex === years.length ? 'all' : years[selectedIndex];
            const filteredLocations = selectedYear === 'all'
                ? this.locations
                : this.locations.filter((location) =>
                    location.date && String(location.date).slice(0, 4) === selectedYear
                );
            const ratio = Number(yearRange.max) > 0
                ? selectedIndex / Number(yearRange.max)
                : 1;
            const progress = ratio * 100;
            const edgeOffset = (1 - (2 * ratio)) * 1.125;

            yearControl.style.setProperty('--slider-progress', `calc(${progress}% + ${edgeOffset}rem)`);
            yearMarks.forEach((mark) => {
                mark.dataset.active = mark.dataset.yearMark === selectedYear ? 'true' : 'false';
            });
            yearLabel.textContent = selectedYear === 'all'
                ? (this.language === 'en' ? 'All' : 'Semua')
                : selectedYear;
            yearRange.setAttribute('aria-valuetext', yearLabel.textContent);
            this.renderMarkers(filteredLocations, { fitView: false });
            this.closeLocationDetail();
        });

        yearRange.setAttribute('aria-valuetext', yearLabel.textContent);

        document.getElementById('map-detail-close')?.addEventListener('click', () => this.closeLocationDetail());
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeLocationDetail();
            }
        });
    },

    statusFor(confidence) {
        if (confidence === null || String(confidence).trim() === '') {
            return { label: this.language === 'en' ? 'Unrated' : 'Belum dinilai', color: '#64748b' };
        }

        const value = String(confidence).trim().toLowerCase();
        const numericValue = Number(value);

        if (['high', 'tinggi'].includes(value) || (Number.isFinite(numericValue) && numericValue >= 80)) {
            return { label: this.language === 'en' ? 'High' : 'Tinggi', color: '#ef4444' };
        }

        if (['nominal', 'medium', 'sedang'].includes(value) || (Number.isFinite(numericValue) && numericValue >= 50)) {
            return { label: this.language === 'en' ? 'Medium' : 'Sedang', color: '#f59e0b' };
        }

        return { label: this.language === 'en' ? 'Low' : 'Rendah', color: '#10b981' };
    },

    showLocationDetail(location, status) {
        const panel = document.getElementById('map-detail-panel');

        if (!panel) {
            return;
        }

        const values = {
            title: location.desa || (this.language === 'en' ? 'Village not available' : 'Desa belum tersedia'),
            region: [location.kecamatan, location.kabupaten_kota, location.provinsi].filter(Boolean).join(', ') || '-',
            confidence: location.confidence ?? '-',
            status: status.label,
            province: location.provinsi || '-',
            regency: location.kabupaten_kota || '-',
            district: location.kecamatan || '-',
            village: location.desa || '-',
            date: this.formatDate(location.date),
            coordinates: `${location.latitude}, ${location.longitude}`,
        };

        Object.entries(values).forEach(([key, value]) => {
            const element = document.getElementById(`map-detail-${key}`);

            if (element) {
                element.textContent = value;
            }
        });

        const link = document.getElementById('map-detail-link');
        if (link) {
            link.href = location.detail_url;
        }

        panel.dataset.open = 'true';
        panel.setAttribute('aria-hidden', 'false');
        panel.scrollTop = 0;
        document.getElementById('map-detail-close')?.focus({ preventScroll: true });
    },

    closeLocationDetail() {
        const panel = document.getElementById('map-detail-panel');

        if (!panel) {
            return;
        }

        panel.dataset.open = 'false';
        panel.setAttribute('aria-hidden', 'true');
    },

    formatDate(date) {
        if (!date) {
            return '-';
        }

        return new Intl.DateTimeFormat(this.language === 'en' ? 'en-US' : 'id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(`${date}T00:00:00`));
    },
};

document.addEventListener('DOMContentLoaded', () => EmberMap.init());
document.addEventListener('livewire:navigated', () => EmberMap.init());

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-url]');

    if (!button) {
        return;
    }

    const originalLabel = button.textContent;

    try {
        await navigator.clipboard.writeText(button.dataset.copyUrl);
        button.textContent = 'URL tersalin';
        window.setTimeout(() => {
            button.textContent = originalLabel;
        }, 1600);
    } catch {
        window.prompt('Salin URL foto berikut:', button.dataset.copyUrl);
    }
});

const initializeBackToTop = () => {
    const button = document.getElementById('back-to-top');

    if (!button || button.dataset.initialized === 'true') {
        return;
    }

    button.dataset.initialized = 'true';

    const updateVisibility = () => {
        const isVisible = window.scrollY > 400;

        button.classList.toggle('pointer-events-none', !isVisible);
        button.classList.toggle('opacity-0', !isVisible);
        button.classList.toggle('translate-y-3', !isVisible);
        button.classList.toggle('opacity-100', isVisible);
        button.classList.toggle('translate-y-0', isVisible);
    };

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', updateVisibility, { passive: true });
    updateVisibility();
};

document.addEventListener('DOMContentLoaded', initializeBackToTop);
document.addEventListener('livewire:navigated', initializeBackToTop);

const initializeLocationDetailMap = () => {
    const mapElement = document.getElementById('location-detail-map');
    const dataElement = document.getElementById('location-detail-map-data');

    if (!mapElement || !dataElement || mapElement.dataset.initialized === 'true') {
        return;
    }

    const location = JSON.parse(dataElement.textContent || '{}');
    const latitude = Number(location.latitude);
    const longitude = Number(location.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    mapElement.dataset.initialized = 'true';
    const detailMap = L.map(mapElement, { scrollWheelZoom: false }).setView([latitude, longitude], 11);

    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        {
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        },
    ).addTo(detailMap);

    const confidence = location.confidence;
    const status = EmberMap.statusFor(confidence);

    L.circleMarker([latitude, longitude], {
        radius: 10,
        color: '#ffffff',
        weight: 3,
        fillColor: status.color,
        fillOpacity: 1,
    }).addTo(detailMap);
};

document.addEventListener('DOMContentLoaded', initializeLocationDetailMap);
document.addEventListener('livewire:navigated', initializeLocationDetailMap);

const initializeRichContentSliders = () => {
    document.querySelectorAll('.rich-content .tmce-slider').forEach((slider) => {
        if (slider.dataset.initialized === 'true') return;

        const slides = Array.from(slider.querySelectorAll('.tmce-slides > figure'));

        if (slides.length === 0) return;

        slider.dataset.initialized = 'true';
        let currentIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('active')));

        const showSlide = (index) => {
            currentIndex = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('active', slideIndex === currentIndex));
            slider.dataset.index = String(currentIndex);
        };

        slider.querySelector('.prev')?.addEventListener('click', () => showSlide(currentIndex - 1));
        slider.querySelector('.next')?.addEventListener('click', () => showSlide(currentIndex + 1));
        showSlide(currentIndex);
    });
};

document.addEventListener('DOMContentLoaded', initializeRichContentSliders);
document.addEventListener('livewire:navigated', initializeRichContentSliders);

const initializeRevealMotion = () => {
    const elements = document.querySelectorAll('[data-reveal]:not([data-motion-ready])');

    if (elements.length === 0) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -40px',
    });

    elements.forEach((element) => {
        element.dataset.motionReady = 'true';
        const delay = Number(element.dataset.revealDelay || 0);
        element.style.setProperty('--reveal-delay', `${Math.max(0, Math.min(delay, 500))}ms`);
        observer.observe(element);
    });
};

document.addEventListener('DOMContentLoaded', initializeRevealMotion);
document.addEventListener('livewire:navigated', initializeRevealMotion);

const initializeTeamCarousel = () => {
    document.querySelectorAll('[data-team-carousel]:not([data-initialized])').forEach((carousel) => {
        const cards = Array.from(carousel.querySelectorAll('[data-team-card]'));
        if (cards.length === 0) return;

        carousel.dataset.initialized = 'true';
        let activeIndex = 0;
        let touchStartX = null;

        const update = () => {
            cards.forEach((card, index) => {
                let offset = index - activeIndex;
                if (offset > cards.length / 2) offset -= cards.length;
                if (offset < -cards.length / 2) offset += cards.length;

                let position = 'hidden';
                if (offset === 0) position = 'active';
                else if (offset === -1) position = 'prev';
                else if (offset === 1) position = 'next';
                else if (offset === -2) position = 'prev-far';
                else if (offset === 2) position = 'next-far';

                card.dataset.position = position;
                card.setAttribute('aria-hidden', position === 'active' ? 'false' : 'true');
                card.tabIndex = position === 'active' ? 0 : -1;
            });

            const counter = carousel.querySelector('[data-team-current]');
            if (counter) counter.textContent = String(activeIndex + 1).padStart(2, '0');
        };

        const move = (direction) => {
            activeIndex = (activeIndex + direction + cards.length) % cards.length;
            update();
        };

        carousel.querySelector('[data-team-prev]')?.addEventListener('click', () => move(-1));
        carousel.querySelector('[data-team-next]')?.addEventListener('click', () => move(1));
        cards.forEach((card, index) => card.addEventListener('click', () => {
            if (index !== activeIndex) {
                activeIndex = index;
                update();
            }
        }));

        carousel.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') move(-1);
            if (event.key === 'ArrowRight') move(1);
        });
        carousel.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0]?.clientX ?? null;
        }, { passive: true });
        carousel.addEventListener('touchend', (event) => {
            if (touchStartX === null) return;
            const distance = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
            if (Math.abs(distance) > 45) move(distance > 0 ? -1 : 1);
            touchStartX = null;
        }, { passive: true });

        update();
    });
};

document.addEventListener('DOMContentLoaded', initializeTeamCarousel);
document.addEventListener('livewire:navigated', initializeTeamCarousel);
