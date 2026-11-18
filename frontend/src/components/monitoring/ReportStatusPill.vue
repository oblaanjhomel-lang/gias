<script setup lang="ts">
import type { ReportStatus } from '@/types/monitoring'

defineProps<{ status: ReportStatus }>()

const styles: Record<ReportStatus, { label: string; class: string }> = {
  draft: { label: 'Draft', class: 'bg-ink/[0.07] text-ink/75' },
  submitted: { label: 'Submitted', class: 'bg-blue-50 text-blue-800' },
  signed: { label: 'Signed', class: 'bg-brand/10 text-brand' },
  returned: { label: 'Returned', class: 'bg-accent/20 text-accent-ink' },
}
</script>

<template>
  <span
    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
    :class="styles[status].class"
  >
    {{ styles[status].label }}
  </span>
</template>
