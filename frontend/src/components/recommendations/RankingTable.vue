<script setup lang="ts">
import { computed } from 'vue'
import { useRecommendationsStore } from '@/stores/recommendations'
import type { Algorithm } from '@/types/recommendations'

/** Ranked partners for the selected student under one algorithm. */
const emit = defineEmits<{
  place: [partnerId: number, company: string]
  withdraw: [deploymentId: number]
}>()

const store = useRecommendationsStore()

const SCORE_LABEL: Record<Algorithm, string> = {
  cos: 'Cosine score',
  wsm: 'WSM score',
  fuzzy: 'Fuzzy score',
}

const rows = computed(() => {
  const result = store.result
  if (!result) return []
  return result.rankings[store.algorithm].flatMap((entry) => {
    const partner = store.partnerById.get(entry.partner_id)
    return partner ? [{ ...entry, partner }] : []
  })
})

const placement = computed(() => store.result?.student.placement ?? null)

const th = 'px-3 py-2.5 font-medium'
</script>

<template>
  <div class="overflow-x-auto rounded-[9px] border border-ink/10 bg-white">
    <table class="w-full text-left text-sm">
      <caption class="sr-only">
        Industry Partners ranked by
        {{
          SCORE_LABEL[store.algorithm]
        }}
      </caption>
      <thead class="bg-canvas/60 font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
        <tr>
          <th scope="col" :class="th">Rank</th>
          <th scope="col" :class="th">Industry partner</th>
          <th scope="col" :class="th">Skills matched</th>
          <th scope="col" :class="th">Slots</th>
          <th scope="col" :class="th">{{ SCORE_LABEL[store.algorithm] }}</th>
          <th scope="col" :class="[th, 'text-right']"><span class="sr-only">Action</span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-ink/10">
        <tr v-if="!rows.length">
          <td colspan="6" class="px-3 py-8 text-center text-ink/70">
            No partners with open slots to rank.
          </td>
        </tr>
        <tr
          v-for="row in rows"
          :key="row.partner_id"
          :class="store.explainPartnerId === row.partner_id ? 'bg-brand/5' : ''"
        >
          <td class="px-3 py-3 font-mono text-sm font-medium text-ink">#{{ row.rank }}</td>
          <td class="px-3 py-3">
            <button
              type="button"
              class="text-left font-medium text-ink underline-offset-2 hover:text-brand hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              :aria-label="`Why ${row.partner.company_name} scored ${row.score.toFixed(2)}`"
              @click="store.explain(row.partner_id)"
            >
              {{ row.partner.company_name }}
            </button>
            <span class="block text-xs text-ink/70">
              {{ row.partner.city }} ·
              {{
                row.partner.distance_km === null
                  ? 'distance unknown'
                  : `${row.partner.distance_km} km from home`
              }}
            </span>
          </td>
          <td class="px-3 py-3 whitespace-nowrap text-ink">
            {{ row.partner.skills_matched }} of {{ row.partner.skills_needed }} needed
          </td>
          <td class="px-3 py-3 whitespace-nowrap text-ink">{{ row.partner.open }} left</td>
          <td class="px-3 py-3">
            <span class="font-mono text-sm font-medium text-ink">{{ row.score.toFixed(2) }}</span>
            <span
              class="mt-1 block h-1.5 w-24 overflow-hidden rounded-full bg-ink/10"
              aria-hidden="true"
            >
              <span
                class="block h-full rounded-full bg-brand"
                :style="{ width: `${Math.round(row.score * 100)}%` }"
              />
            </span>
          </td>
          <td class="px-3 py-3 text-right whitespace-nowrap">
            <template v-if="row.partner.is_current_placement && placement">
              <span class="block text-sm font-medium text-brand">✓ Placed</span>
              <button
                type="button"
                :disabled="store.working"
                class="text-xs font-medium text-red-700 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
                :aria-label="`Withdraw placement at ${row.partner.company_name}`"
                @click="emit('withdraw', placement.deployment_id)"
              >
                Withdraw
              </button>
            </template>
            <button
              v-else
              type="button"
              :disabled="placement !== null || store.working"
              :title="placement ? 'Withdraw the current placement first' : undefined"
              class="h-9 rounded-md bg-brand px-3 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-40"
              :aria-label="`Place here: ${row.partner.company_name}`"
              @click="emit('place', row.partner_id, row.partner.company_name)"
            >
              Place here
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
