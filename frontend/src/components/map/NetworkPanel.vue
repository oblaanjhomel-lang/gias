<script setup lang="ts">
import { computed } from 'vue'
import { useMapStore } from '@/stores/map'
import { PROVINCES, type Province } from '@/types/accounts'
import { SATURATIONS, type Saturation } from '@/types/map'
import { SATURATION_STYLE } from './mapMarkers'

/** Admin / coordinator side panel: summary, filters, legend, partner list, pin tools. */
const props = defineProps<{ isAdmin: boolean }>()
const emit = defineEmits<{ focus: [id: number]; place: [id: number, company: string] }>()

const store = useMapStore()

const legend = computed<Saturation[]>(() =>
  props.isAdmin ? SATURATIONS : SATURATIONS.filter((s) => s !== 'pending'),
)

const legendLabels: Record<Saturation, string> = {
  open: 'Open (3+ slots)',
  near: 'Nearly full (1–2 slots)',
  full: 'Full',
  pending: 'Pending approval',
}

const province = computed({
  get: () => store.filters.province,
  set: (value: Province | '') => void store.setProvince(value),
})

const radius = computed({
  get: () => store.radiusKm,
  set: (value: number) => void store.setRadius(Number(value)),
})

const coverage = computed(() => store.summary?.coverage)
const coveragePercent = computed(() =>
  coverage.value?.percent === null || coverage.value?.percent === undefined
    ? '—'
    : `${coverage.value.percent}%`,
)

const sectionLabel = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
const chip =
  'rounded-full border px-3 py-1 text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand'
</script>

<template>
  <div class="space-y-6 p-5">
    <header>
      <p class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-accent-ink uppercase">
        GIS module · PostGIS + Leaflet
      </p>
      <h1 class="mt-1 font-serif text-xl leading-snug font-semibold text-ink">
        Industry Partners &amp; Deployment Sites
      </h1>
    </header>

    <!-- Summary -->
    <section aria-label="Summary">
      <dl class="grid grid-cols-2 gap-2">
        <div class="rounded-md bg-canvas px-3 py-2.5">
          <dt :class="sectionLabel">Partners</dt>
          <dd class="font-mono text-xl font-medium text-ink">
            {{ store.summary?.partners ?? '–' }}
          </dd>
        </div>
        <div class="rounded-md bg-canvas px-3 py-2.5">
          <dt :class="sectionLabel">Interns deployed</dt>
          <dd class="font-mono text-xl font-medium text-ink">
            {{ store.summary?.interns_deployed ?? '–' }}
          </dd>
        </div>
        <div class="rounded-md bg-canvas px-3 py-2.5">
          <dt :class="sectionLabel">Open slots</dt>
          <dd class="font-mono text-xl font-medium text-ink">
            {{ store.summary?.open_slots ?? '–' }}
          </dd>
        </div>
        <div class="rounded-md bg-canvas px-3 py-2.5">
          <dt :class="sectionLabel">Coverage</dt>
          <dd class="font-mono text-xl font-medium text-brand">{{ coveragePercent }}</dd>
        </div>
      </dl>
      <p v-if="coverage" class="mt-2 text-xs leading-relaxed text-ink/70">
        {{ coverage.within_radius }} of {{ coverage.students_unplaced }} unplaced students live
        within {{ coverage.radius_km }} km of a partner with open slots.
      </p>
    </section>

    <!-- Program -->
    <section>
      <h2 :class="sectionLabel">Program</h2>
      <div role="group" aria-label="Filter by program" class="mt-2 flex flex-wrap gap-1.5">
        <button
          type="button"
          :aria-pressed="store.filters.programId === null"
          :class="[
            chip,
            store.filters.programId === null
              ? 'border-brand bg-brand text-white'
              : 'border-ink/20 text-ink hover:border-brand',
          ]"
          @click="store.setProgram(null)"
        >
          All
        </button>
        <button
          v-for="program in store.programs"
          :key="program.id"
          type="button"
          :aria-pressed="store.filters.programId === program.id"
          :title="program.name"
          :class="[
            chip,
            store.filters.programId === program.id
              ? 'border-brand bg-brand text-white'
              : 'border-ink/20 text-ink hover:border-brand',
          ]"
          @click="store.setProgram(program.id)"
        >
          {{ program.code }}
        </button>
      </div>
    </section>

    <!-- Province -->
    <section>
      <label for="map-province" :class="sectionLabel">Province</label>
      <select
        id="map-province"
        v-model="province"
        class="mt-2 block h-10 w-full rounded-md border border-ink/25 bg-white px-3 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
      >
        <option value="">All provinces</option>
        <option v-for="p in PROVINCES" :key="p" :value="p">{{ p }}</option>
      </select>
    </section>

    <!-- Saturation legend (each row filters) -->
    <fieldset>
      <legend :class="sectionLabel">Saturation</legend>
      <div class="mt-2 space-y-1.5">
        <label
          v-for="s in legend"
          :key="s"
          class="flex cursor-pointer items-center gap-2.5 text-sm text-ink"
        >
          <input
            type="checkbox"
            class="size-4 accent-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            :checked="store.filters.saturation.includes(s)"
            @change="store.toggleSaturation(s)"
          />
          <span
            aria-hidden="true"
            class="size-3 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(20_24_29/0.25)]"
            :style="{ background: SATURATION_STYLE[s].fill }"
          />
          {{ legendLabels[s] }}
        </label>
      </div>
    </fieldset>

    <!-- Coverage radius -->
    <section class="space-y-2">
      <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink">
        <input
          v-model="store.showCoverage"
          type="checkbox"
          class="size-4 accent-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        />
        Show service radius
      </label>
      <div class="flex items-center gap-2">
        <label for="map-radius" class="text-sm text-ink/75">Radius</label>
        <select
          id="map-radius"
          v-model.number="radius"
          class="h-9 rounded-md border border-ink/25 bg-white px-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
        >
          <option :value="10">10 km</option>
          <option :value="25">25 km</option>
          <option :value="50">50 km</option>
        </select>
      </div>
    </section>

    <!-- Admin pin tools -->
    <section v-if="isAdmin" class="space-y-3">
      <button
        type="button"
        :aria-pressed="store.editMode"
        class="flex h-10 w-full items-center justify-between rounded-md border px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="
          store.editMode
            ? 'border-brand bg-brand/10 text-brand'
            : 'border-ink/20 text-ink hover:border-brand'
        "
        @click="store.editMode = !store.editMode"
      >
        Edit pins
        <span class="font-mono text-xs">{{ store.editMode ? 'ON — drag a marker' : 'OFF' }}</span>
      </button>

      <div v-if="store.unlocated.length">
        <h2 :class="sectionLabel">Needs location ({{ store.unlocated.length }})</h2>
        <ul class="mt-2 space-y-2">
          <li
            v-for="partner in store.unlocated"
            :key="partner.id"
            class="rounded-md border border-accent/50 bg-accent/10 p-2.5"
          >
            <p class="text-sm font-medium text-ink">{{ partner.company_name }}</p>
            <p class="text-xs text-ink/70">
              {{ partner.address }}, {{ partner.city }}, {{ partner.province }}
              <template v-if="partner.status === 'pending'"> · pending approval</template>
            </p>
            <button
              type="button"
              :aria-label="`Place ${partner.company_name} on map`"
              class="mt-2 rounded border border-brand px-2.5 py-1 text-xs font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              @click="emit('place', partner.id, partner.company_name)"
            >
              Place on map
            </button>
          </li>
        </ul>
      </div>
    </section>

    <!-- Partner list: the keyboard alternative to the map -->
    <section>
      <h2 :class="sectionLabel">Partners on the map ({{ store.partnerList.length }})</h2>
      <p v-if="!store.partnerList.length" class="mt-2 text-sm text-ink/70">
        No partners match these filters.
      </p>
      <ul v-else class="mt-2 divide-y divide-ink/10 border-y border-ink/10">
        <li v-for="feature in store.partnerList" :key="feature.id">
          <button
            type="button"
            class="flex w-full items-start justify-between gap-3 px-1 py-2.5 text-left hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
            @click="emit('focus', feature.id)"
          >
            <span class="min-w-0">
              <span class="block truncate text-sm font-medium text-ink">
                {{ feature.properties.company_name }}
              </span>
              <span class="block text-xs text-ink/70">
                {{ feature.properties.city }} · {{ feature.properties.province }}
              </span>
            </span>
            <span
              class="shrink-0 font-mono text-xs font-medium"
              :style="{ color: SATURATION_STYLE[feature.properties.saturation].text }"
            >
              {{ feature.properties.filled }}/{{ feature.properties.intern_slots }}
              <span class="sr-only">
                interns, {{ SATURATION_STYLE[feature.properties.saturation].label }}
              </span>
            </span>
          </button>
        </li>
      </ul>
    </section>

    <p class="text-xs leading-relaxed text-ink/70">
      Marker size grows with interns deployed · addresses located at registration
    </p>
  </div>
</template>
