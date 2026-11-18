<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import FindPartnerPanel from '@/components/map/FindPartnerPanel.vue'
import GiasMap from '@/components/map/GiasMap.vue'
import NetworkPanel from '@/components/map/NetworkPanel.vue'
import PartnerSitePanel from '@/components/map/PartnerSitePanel.vue'
import { useAuthStore } from '@/stores/auth'
import { useMapStore } from '@/stores/map'
import type { LatLng } from '@/types/map'

/**
 * /admin/map, /coordinator/map, /partner/map ("Our site on map"), /student/map ("Find a partner").
 * Fills the content area: a 300px panel, then the map.
 */
const auth = useAuthStore()
const store = useMapStore()
const role = computed(() => auth.role!)
const isNetwork = computed(() => role.value === 'admin' || role.value === 'coordinator')

const map = ref<InstanceType<typeof GiasMap> | null>(null)

function load(): void {
  void store.load(role.value, auth.user?.program?.id ?? null)
}

onMounted(() => {
  store.$reset()
  load()
})

// Which markers can be dragged right now
const draggableIds = computed<number[]>(() => {
  if (!store.editMode) return []
  if (role.value === 'admin') return store.features.map((f) => f.id)
  if (role.value === 'partner') return store.features.map((f) => f.id) // only their own is returned
  return []
})

function focus(id: number): void {
  map.value?.focusPartner(id)
}

// Moving an existing pin: drag → "Move {company} here?" → Save / Undo

function onMarkerMoved(id: number, at: LatLng): void {
  const previous = store.pendingMove
  if (previous && previous.id !== id) map.value?.resetMarker(previous.id)

  const company = store.features.find((f) => f.id === id)?.properties.company_name ?? 'this partner'
  store.proposeMove(id, company, at)
}

function undoMove(): void {
  if (store.pendingMove) map.value?.resetMarker(store.pendingMove.id)
  store.cancelMove()
}

async function saveMove(): Promise<void> {
  const move = store.pendingMove
  if (move) await store.saveLocation(move.id, { lat: move.lat, lng: move.lng })
}

// Turning edit mode off discards an unsaved drag.
watch(
  () => store.editMode,
  (on) => {
    if (!on) undoMove()
  },
)

// Placing a partner that has no location: click the map, adjust, Save / Cancel

function startPlacing(id: number, company: string): void {
  undoMove()
  store.editMode = false
  store.startPlacing(id, company)
}

async function savePlacement(): Promise<void> {
  const placing = store.placing
  const at = store.draft
  if (!placing || !at) return
  if (await store.saveLocation(placing.id, at)) focus(placing.id)
}

const barButton =
  'h-9 rounded-md px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60'
</script>

<template>
  <div class="flex h-full min-h-0">
    <!-- Panel -->
    <aside class="w-[300px] shrink-0 overflow-y-auto border-r border-ink/10 bg-white">
      <!-- Skeleton -->
      <div v-if="store.loading && !store.config" class="space-y-4 p-5" aria-hidden="true">
        <div class="h-3 w-2/3 animate-pulse rounded bg-ink/10" />
        <div class="h-6 w-full animate-pulse rounded bg-ink/10" />
        <div class="grid grid-cols-2 gap-2">
          <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded bg-ink/10" />
        </div>
        <div v-for="i in 6" :key="`row-${i}`" class="h-9 animate-pulse rounded bg-ink/10" />
      </div>

      <template v-else>
        <div
          v-if="store.error"
          role="alert"
          class="m-5 mb-0 rounded-md border border-red-700/30 bg-red-50 px-3 py-2.5 text-sm text-red-800"
        >
          <p>{{ store.error }}</p>
          <button
            type="button"
            class="mt-2 rounded border border-red-700 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
            @click="load"
          >
            Retry
          </button>
        </div>

        <NetworkPanel
          v-if="isNetwork"
          :is-admin="role === 'admin'"
          @focus="focus"
          @place="startPlacing"
        />
        <PartnerSitePanel v-else-if="role === 'partner'" @focus="focus" @place="startPlacing" />
        <FindPartnerPanel v-else @focus="focus" />
      </template>
    </aside>

    <!-- Map -->
    <section class="relative min-w-0 flex-1 bg-canvas" aria-label="Map">
      <div
        v-if="!store.config"
        class="absolute inset-0 grid place-items-center"
        :class="store.loading ? 'animate-pulse bg-ink/5' : ''"
      >
        <p v-if="store.loading" class="font-mono text-xs tracking-[0.08em] text-ink/70 uppercase">
          Loading map…
        </p>
        <div v-else-if="store.error" role="alert" class="text-center">
          <p class="text-sm text-ink">The map could not be loaded.</p>
          <button
            type="button"
            class="mt-3 h-10 rounded-md bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            @click="load"
          >
            Retry
          </button>
        </div>
      </div>

      <GiasMap
        v-else
        ref="map"
        :config="store.config"
        :features="store.features"
        :home="store.home"
        :show-coverage="isNetwork && store.showCoverage"
        :radius-km="store.radiusKm"
        :draggable-ids="draggableIds"
        :placing="store.placing !== null"
        :draft="store.draft"
        @marker-moved="onMarkerMoved"
        @place="store.placeDraft"
        @draft-moved="store.placeDraft"
      />

      <!-- Pin action bars (above Leaflet's panes and controls) -->
      <div class="pointer-events-none absolute inset-x-0 top-3 z-[1100] flex justify-center px-4">
        <div
          v-if="store.placing"
          role="status"
          class="pointer-events-auto flex flex-wrap items-center gap-3 rounded-lg border border-ink/10 bg-white px-4 py-2.5 shadow-lg"
        >
          <p class="text-sm text-ink">
            <template v-if="!store.draft">
              Click the map where <strong>{{ store.placing.company }}</strong> is.
            </template>
            <template v-else>Drag the marker to adjust, then save.</template>
          </p>
          <button
            type="button"
            :disabled="!store.draft || store.saving"
            :class="[
              barButton,
              'bg-brand text-white hover:bg-brand-dark focus-visible:outline-brand',
            ]"
            @click="savePlacement"
          >
            {{ store.saving ? 'Saving…' : 'Save' }}
          </button>
          <button
            type="button"
            :disabled="store.saving"
            :class="[barButton, 'text-ink hover:bg-ink/5 focus-visible:outline-brand']"
            @click="store.cancelPlacing()"
          >
            Cancel
          </button>
          <p v-if="store.saveError" class="w-full text-sm text-red-700">{{ store.saveError }}</p>
        </div>

        <div
          v-else-if="store.pendingMove"
          role="status"
          class="pointer-events-auto flex flex-wrap items-center gap-3 rounded-lg border border-ink/10 bg-white px-4 py-2.5 shadow-lg"
        >
          <p class="text-sm text-ink">
            Move <strong>{{ store.pendingMove.company }}</strong> here?
          </p>
          <button
            type="button"
            :disabled="store.saving"
            :class="[
              barButton,
              'bg-brand text-white hover:bg-brand-dark focus-visible:outline-brand',
            ]"
            @click="saveMove"
          >
            {{ store.saving ? 'Saving…' : 'Save' }}
          </button>
          <button
            type="button"
            :disabled="store.saving"
            :class="[barButton, 'text-ink hover:bg-ink/5 focus-visible:outline-brand']"
            @click="undoMove"
          >
            Undo
          </button>
          <p v-if="store.saveError" class="w-full text-sm text-red-700">{{ store.saveError }}</p>
        </div>
      </div>
    </section>
  </div>
</template>
