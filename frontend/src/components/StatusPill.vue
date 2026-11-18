<script setup lang="ts">
import type { Status } from '@/types/auth'

defineProps<{ status: Status }>()

const styles: Record<Status, { label: string; pill: string; dot: string }> = {
  active: { label: 'Active', pill: 'bg-brand/10 text-brand', dot: 'bg-brand' },
  inactive: { label: 'Inactive', pill: 'bg-ink/[0.07] text-ink/75', dot: 'bg-ink/45' },
  pending: { label: 'Pending', pill: 'bg-accent/20 text-accent-ink', dot: 'bg-accent' },
  rejected: { label: 'Returned', pill: 'bg-red-50 text-red-800', dot: 'bg-red-700' },
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
    :class="styles[status].pill"
  >
    <span aria-hidden="true" class="size-1.5 rounded-full" :class="styles[status].dot" />
    {{ styles[status].label }}
  </span>
</template>
