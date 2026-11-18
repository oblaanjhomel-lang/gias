<script setup lang="ts">
import { useMapStore } from '@/stores/map'

/** Student: the 10 nearest partners with open slots. */
const emit = defineEmits<{ focus: [id: number] }>()

const store = useMapStore()

const sectionLabel = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <div class="space-y-6 p-5">
    <header>
      <p class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-accent-ink uppercase">
        Partners near you
      </p>
      <h1 class="mt-1 font-serif text-xl leading-snug font-semibold text-ink">Find a partner</h1>
    </header>

    <p
      v-if="store.homeMissing"
      role="status"
      class="rounded-md border border-accent bg-accent/10 px-3 py-2.5 text-sm text-ink"
    >
      We couldn’t locate your home address. Ask your OJT Coordinator to update it.
    </p>

    <section v-else>
      <h2 :class="sectionLabel">Nearest with open slots</h2>
      <p v-if="!store.nearby.length" class="mt-2 text-sm text-ink/70">
        No partners with open slots right now.
      </p>
      <ol v-else class="mt-2 divide-y divide-ink/10 border-y border-ink/10">
        <li v-for="partner in store.nearby" :key="partner.id">
          <button
            type="button"
            class="flex w-full items-start justify-between gap-3 px-1 py-2.5 text-left hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
            @click="emit('focus', partner.id)"
          >
            <span class="min-w-0">
              <span class="block truncate text-sm font-medium text-ink">{{
                partner.company_name
              }}</span>
              <span class="block text-xs text-ink/70">
                {{ partner.city }} · {{ partner.open }} open
                {{ partner.open === 1 ? 'slot' : 'slots' }}
              </span>
            </span>
            <span class="shrink-0 font-mono text-xs font-medium text-brand">
              {{ partner.distance_km }} km
            </span>
          </button>
        </li>
      </ol>
    </section>

    <p class="text-xs leading-relaxed text-ink/70">Your OJT Coordinator decides your placement.</p>
  </div>
</template>
