<script setup lang="ts">
import { computed } from 'vue'
import ChartCard from '@/components/charts/ChartCard.vue'

export interface Column {
  label: string
  value: number
  highlight?: boolean
}

const props = withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    columns: Column[]
    labelHeader?: string
    valueHeader?: string
    loading?: boolean
    /** Histogram style: columns touch. */
    joined?: boolean
  }>(),
  { subtitle: undefined, labelHeader: 'Term', valueHeader: 'Value', loading: false, joined: false },
)

const max = computed(() => Math.max(1, ...props.columns.map((c) => c.value)))
const tableRows = computed(() => props.columns.map((c) => [c.label, c.value]))
</script>

<template>
  <ChartCard
    :title="title"
    :subtitle="subtitle"
    :columns="[labelHeader, valueHeader]"
    :rows="tableRows"
    :loading="loading"
  >
    <div class="flex h-48 items-end" :class="joined ? 'gap-0.5' : 'gap-3'">
      <div
        v-for="column in columns"
        :key="column.label"
        class="flex h-full min-w-0 flex-1 flex-col items-center justify-end"
      >
        <span class="mb-1 font-mono text-[12px] text-ink">{{ column.value }}</span>
        <div
          class="w-full"
          :class="[
            joined ? 'rounded-t-sm' : 'max-w-14 rounded-t-md',
            column.highlight ? 'bg-accent' : 'bg-brand',
          ]"
          :style="{ height: `${Math.max(column.value > 0 ? 2 : 0, (column.value / max) * 82)}%` }"
          aria-hidden="true"
        />
      </div>
    </div>
    <div class="mt-2 flex border-t border-ink/15 pt-2" :class="joined ? 'gap-0.5' : 'gap-3'">
      <span
        v-for="column in columns"
        :key="column.label"
        class="min-w-0 flex-1 text-center text-[11.5px] leading-tight text-ink/70"
      >
        {{ column.label }}
      </span>
    </div>
    <template v-if="$slots.footer" #footer><slot name="footer" /></template>
  </ChartCard>
</template>
