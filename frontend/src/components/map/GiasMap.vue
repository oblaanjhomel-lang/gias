<script setup lang="ts">
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { LatLng, MapConfig, PartnerFeature } from '@/types/map'
import {
  campusIcon,
  draftIcon,
  escapeHtml,
  homeIcon,
  partnerIcon,
  partnerPopup,
} from './mapMarkers'

/**
 * Reusable Leaflet map of Industry Partners. Leaflet objects live outside Vue's reactivity.
 * Parent controls data and modes through props; the map reports user actions as events.
 */
const props = withDefaults(
  defineProps<{
    config: MapConfig
    features: PartnerFeature[]
    /** Student's home (house icon). */
    home?: LatLng | null
    showCoverage?: boolean
    radiusKm?: number
    /** Partner ids whose markers can be dragged (edit mode). */
    draggableIds?: number[]
    /** Clicking the map reports a `place` event (placing an unlocated partner). */
    placing?: boolean
    /** The marker being placed; draggable. */
    draft?: LatLng | null
  }>(),
  {
    home: null,
    showCoverage: false,
    radiusKm: 25,
    draggableIds: () => [],
    placing: false,
    draft: null,
  },
)

const emit = defineEmits<{
  /** An existing partner marker was dragged to a new spot. */
  markerMoved: [id: number, at: LatLng]
  place: [at: LatLng]
  draftMoved: [at: LatLng]
}>()

const container = ref<HTMLElement | null>(null)

let map: L.Map | null = null
const partnerLayer = L.layerGroup()
const coverageLayer = L.layerGroup()
const markers = new Map<number, L.Marker>()
let homeMarker: L.Marker | null = null
let draftMarker: L.Marker | null = null
let fitted = false

onMounted(() => {
  if (!container.value) return

  const { region_bounds: b, campus } = props.config
  map = L.map(container.value, { zoomControl: true })
  map.fitBounds([
    [b.min_lat, b.min_lng],
    [b.max_lat, b.max_lng],
  ])

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map)

  coverageLayer.addTo(map)
  partnerLayer.addTo(map)

  L.marker([campus.lat, campus.lng], {
    icon: campusIcon(),
    title: campus.name,
    keyboard: true,
    zIndexOffset: 1000,
  })
    .bindPopup(
      `<div class="gias-popup"><div class="gias-popup__title">${escapeHtml(campus.name)}</div></div>`,
    )
    .addTo(map)

  map.on('click', (event: L.LeafletMouseEvent) => {
    if (props.placing) emit('place', { lat: event.latlng.lat, lng: event.latlng.lng })
  })

  renderPartners()
  renderCoverage()
  renderHome()
  renderDraft()
  fitToData()

  // The flex layout settles after mount; let Leaflet re-measure.
  void nextTick(() => map?.invalidateSize())
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
  markers.clear()
})

// Rendering

function latLngOf(feature: PartnerFeature): L.LatLngExpression | null {
  if (!feature.geometry) return null
  const [lng, lat] = feature.geometry.coordinates
  return [lat, lng]
}

function renderPartners(): void {
  partnerLayer.clearLayers()
  markers.clear()

  for (const feature of props.features) {
    const at = latLngOf(feature)
    if (!at) continue
    const [lng, lat] = feature.geometry!.coordinates

    const marker = L.marker(at, {
      icon: partnerIcon(feature.properties),
      title: feature.properties.company_name,
      keyboard: true,
      riseOnHover: true,
      draggable: props.draggableIds.includes(feature.id),
    }).bindPopup(partnerPopup(feature.properties, lat, lng), { maxWidth: 280 })

    marker.on('dragend', () => {
      const position = marker.getLatLng()
      emit('markerMoved', feature.id, { lat: position.lat, lng: position.lng })
    })

    marker.addTo(partnerLayer)
    markers.set(feature.id, marker)
  }
}

function renderCoverage(): void {
  coverageLayer.clearLayers()
  if (!props.showCoverage) return

  for (const feature of props.features) {
    const at = latLngOf(feature)
    if (!at || feature.properties.status !== 'active' || feature.properties.open <= 0) continue

    L.circle(at, {
      radius: props.radiusKm * 1000,
      color: '#006B2B',
      weight: 1,
      opacity: 0.35,
      fillColor: '#006B2B',
      fillOpacity: 0.06,
      interactive: false,
    }).addTo(coverageLayer)
  }
}

function renderHome(): void {
  homeMarker?.remove()
  homeMarker = null
  if (!map || !props.home) return

  homeMarker = L.marker([props.home.lat, props.home.lng], {
    icon: homeIcon(),
    title: 'Your home',
    keyboard: true,
    zIndexOffset: 900,
  })
    .bindPopup('<div class="gias-popup"><div class="gias-popup__title">Your home</div></div>')
    .addTo(map)
}

function renderDraft(): void {
  if (!map || !props.draft) {
    draftMarker?.remove()
    draftMarker = null
    return
  }

  if (draftMarker) {
    draftMarker.setLatLng([props.draft.lat, props.draft.lng])
    return
  }

  draftMarker = L.marker([props.draft.lat, props.draft.lng], {
    icon: draftIcon(),
    title: 'New location (drag to adjust)',
    draggable: true,
    keyboard: true,
    zIndexOffset: 2000,
  }).addTo(map)

  draftMarker.on('dragend', () => {
    const position = draftMarker!.getLatLng()
    emit('draftMoved', { lat: position.lat, lng: position.lng })
  })
}

/** Fit once, when data first arrives, so later filtering doesn't keep jumping the view. */
function fitToData(): void {
  if (!map || fitted) return

  const points: L.LatLngExpression[] = props.features
    .map(latLngOf)
    .filter((p): p is L.LatLngExpression => p !== null)
  if (props.home) points.push([props.home.lat, props.home.lng])
  if (points.length === 0) return

  fitted = true
  if (points.length === 1) {
    map.setView(points[0]!, 13)
  } else {
    map.fitBounds(L.latLngBounds(points).pad(0.15), { maxZoom: 13 })
  }
}

watch(
  () => props.features,
  () => {
    renderPartners()
    renderCoverage()
    fitToData()
  },
)
watch(() => [props.showCoverage, props.radiusKm], renderCoverage)
watch(
  () => props.home,
  () => {
    renderHome()
    fitToData()
  },
)
watch(() => props.draft, renderDraft)

watch(
  () => props.draggableIds,
  (ids) => {
    markers.forEach((marker, id) => {
      if (ids.includes(id)) marker.dragging?.enable()
      else marker.dragging?.disable()
    })
  },
)

watch(
  () => props.placing,
  (placing) => container.value?.classList.toggle('gias-map--placing', placing),
)

// Exposed to the page

/** Fly to a partner and open its popup (keyboard alternative: the panel list). */
function focusPartner(id: number): void {
  const marker = markers.get(id)
  if (!map || !marker) return

  let opened = false
  const open = () => {
    if (opened) return
    opened = true
    marker.openPopup()
  }
  map.once('moveend', open)
  map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 13), { duration: 0.6 })
  setTimeout(open, 800) // flyTo to the current view may not fire moveend
}

/** Put a dragged marker back where its saved location is (Undo). */
function resetMarker(id: number): void {
  const feature = props.features.find((f) => f.id === id)
  const marker = markers.get(id)
  const at = feature ? latLngOf(feature) : null
  if (marker && at) marker.setLatLng(at)
}

function focusPoint(at: LatLng): void {
  map?.flyTo([at.lat, at.lng], Math.max(map.getZoom(), 13), { duration: 0.6 })
}

defineExpose({ focusPartner, resetMarker, focusPoint })
</script>

<template>
  <div
    ref="container"
    role="region"
    aria-label="Map of Industry Partners in the Davao Region"
    class="gias-map h-full w-full"
  />
</template>
