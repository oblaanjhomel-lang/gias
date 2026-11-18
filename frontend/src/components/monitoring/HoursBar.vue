<script setup lang="ts">
import { computed } from 'vue'
import { formatHours } from '@/lib/format'
import type { Progress } from '@/types/monitoring'

/** "signed / required h" with a bar: green, gold when overdue, dark green when complete. */
const props = withDefaults(defineProps<{ progress: Progress; compact?: boolean }>(), {
  compact: false,
})

const tone = computed(() => {
  if (props.progress.status === 'completed' || props.progress.percent >= 100) return 'bg-brand-dark'
  if (props.progress.is_overdue) return 'bg-accent'
  return 'bg-brand'
})
</script>

<template>
  <div>
    <p class="font-mono text-xs text-ink" :class="compact ? '' : 'text-sm'">
      {{ formatHours(progress.signed_hours) }} / {{ progress.required_hours }} h
    </p>
    <div
      class="mt-1 h-1.5 overflow-hidden rounded-full bg-ink/10"
      :class="compact ? 'w-28' : 'w-full'"
      role="progressbar"
      :aria-valuenow="progress.percent"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="`${progress.percent}% of required hours signed`"
    >
      <div class="h-full rounded-full" :class="tone" :style="{ width: `${progress.percent}%` }" />
    </div>
  </div>
</template>
