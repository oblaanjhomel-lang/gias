<script setup lang="ts">
/** Criterion | bar | value rows for 1–5 ratings or averages. `lowest` highlights one row in gold. */
defineProps<{
  rows: { key: string; label: string; value: number | null }[]
  lowest?: string | null
}>()
</script>

<template>
  <ul class="space-y-2">
    <li
      v-for="row in rows"
      :key="row.key"
      class="grid grid-cols-[minmax(0,11rem)_1fr_2.5rem] items-center gap-3 text-sm"
    >
      <span
        class="truncate text-ink"
        :class="row.key === lowest ? 'font-medium text-accent-ink' : ''"
      >
        {{ row.label }}
      </span>
      <span class="h-2 overflow-hidden rounded-full bg-ink/10" aria-hidden="true">
        <span
          class="block h-full rounded-full"
          :class="row.key === lowest ? 'bg-accent' : 'bg-brand'"
          :style="{ width: `${((row.value ?? 0) / 5) * 100}%` }"
        />
      </span>
      <span class="text-right font-mono text-xs text-ink">
        {{
          row.value === null ? '—' : Number.isInteger(row.value) ? row.value : row.value.toFixed(2)
        }}
      </span>
    </li>
  </ul>
</template>
