<script setup lang="ts">
import { computed } from 'vue'
import ChartCard from '@/components/charts/ChartCard.vue'

export interface BarRow {
  label: string
  value: number
  /** Shown beside the bar, e.g. "12 interns · 3 partners". Defaults to the value. */
  text?: string
  /** Gold bar (e.g. underserved province, lowest criterion). */
  highlight?: boolean
}

const props = withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    rows: BarRow[]
    /** Scale maximum; defaults to the largest value. */
    max?: number
    labelHeader?: string
    valueHeader?: string
    loading?: boolean
  }>(),
  {
    subtitle: undefined,
    max: undefined,
    labelHeader: 'Label',
    valueHeader: 'Value',
    loading: false,
  },
)

const scale = computed(() => props.max ?? Math.max(1, ...props.rows.map((r) => r.value)))
const tableRows = computed(() => props.rows.map((r) => [r.label, r.text ?? r.value]))
</script>

<template>
  <ChartCard
    :title="title"
    :subtitle="subtitle"
    :columns="[labelHeader, valueHeader]"
    :rows="tableRows"
    :loading="loading"
  >
    <p v-if="rows.length === 0" class="py-6 text-center text-sm text-ink/65">
      No data for this selection.
    </p>
    <ul v-else class="space-y-3">
      <li v-for="row in rows" :key="row.label">
        <div class="flex items-baseline justify-between gap-3 text-sm">
          <span class="text-ink">{{ row.label }}</span>
          <span class="shrink-0 font-mono text-[12px] text-ink/75">{{
            row.text ?? row.value
          }}</span>
        </div>
        <div class="mt-1 h-2.5 rounded-full bg-ink/[0.06]" aria-hidden="true">
          <div
            class="h-full rounded-full"
            :class="row.highlight ? 'bg-accent' : 'bg-brand'"
            :style="{ width: `${Math.max(row.value > 0 ? 2 : 0, (row.value / scale) * 100)}%` }"
          />
        </div>
      </li>
    </ul>
    <template v-if="$slots.footer" #footer><slot name="footer" /></template>
  </ChartCard>
</template>
