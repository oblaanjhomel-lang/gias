<script setup lang="ts">
import { computed } from 'vue'
import { useMapStore } from '@/stores/map'
import type { GeocodeStatus } from '@/types/accounts'

/** Partner's own site: address, geocode status and "Fix my pin". */
const emit = defineEmits<{ focus: [id: number]; place: [id: number, company: string] }>()

const store = useMapStore()
const own = computed(() => store.features[0] ?? null)

const statusText: Record<GeocodeStatus, string> = {
  located: 'Located automatically from your address.',
  manual: 'Pinned manually.',
  failed: 'We couldn’t find your address on the map. Place your pin so students can find you.',
  pending: 'Not located yet.',
}

function toggleFix(): void {
  if (!own.value) return
  if (!own.value.geometry) {
    emit('place', own.value.id, own.value.properties.company_name)
    return
  }
  store.editMode = !store.editMode
}

const sectionLabel = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <div class="space-y-6 p-5">
    <header>
      <p class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-accent-ink uppercase">
        Our site on map
      </p>
      <h1 class="mt-1 font-serif text-xl leading-snug font-semibold text-ink">
        {{ own?.properties.company_name ?? 'Your company' }}
      </h1>
    </header>

    <template v-if="own">
      <section>
        <h2 :class="sectionLabel">Address</h2>
        <p class="mt-1 text-sm text-ink">
          {{ own.properties.address }}<br />{{ own.properties.city }}, {{ own.properties.province }}
        </p>
      </section>

      <section>
        <h2 :class="sectionLabel">Map location</h2>
        <p
          class="mt-1 text-sm"
          :class="own.properties.geocode_status === 'failed' ? 'text-accent-ink' : 'text-ink'"
        >
          {{ statusText[own.properties.geocode_status] }}
        </p>
        <p v-if="own.geometry" class="mt-1 font-mono text-xs text-ink/70">
          {{ own.geometry.coordinates[1].toFixed(5) }}, {{ own.geometry.coordinates[0].toFixed(5) }}
        </p>
      </section>

      <section>
        <h2 :class="sectionLabel">Interns</h2>
        <p class="mt-1 text-sm text-ink">
          <span class="font-mono font-medium">{{ own.properties.filled }}</span> of
          {{ own.properties.intern_slots }} slots filled
        </p>
      </section>

      <div class="space-y-2">
        <button
          type="button"
          :aria-pressed="own.geometry ? store.editMode : undefined"
          class="flex h-10 w-full items-center justify-center rounded-md border px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          :class="
            store.editMode
              ? 'border-brand bg-brand/10 text-brand'
              : 'border-ink/20 text-ink hover:border-brand'
          "
          @click="toggleFix"
        >
          {{ own.geometry ? 'Fix my pin' : 'Place my pin' }}
        </button>
        <p v-if="store.editMode" class="text-xs text-ink/70">
          Drag your marker to the right spot, then save.
        </p>
        <button
          v-if="own.geometry"
          type="button"
          class="text-sm font-medium text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          @click="emit('focus', own.id)"
        >
          Show on map
        </button>
      </div>
    </template>
  </div>
</template>
