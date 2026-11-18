<script setup lang="ts">
import ColumnTrend from '@/components/charts/ColumnTrend.vue'

/** Buckets as touching columns (same rendering as ColumnTrend). */
withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    buckets: { label: string; value: number }[]
    labelHeader?: string
    valueHeader?: string
    loading?: boolean
  }>(),
  { subtitle: undefined, labelHeader: 'Bucket', valueHeader: 'Count', loading: false },
)
</script>

<template>
  <ColumnTrend
    :title="title"
    :subtitle="subtitle"
    :columns="buckets"
    :label-header="labelHeader"
    :value-header="valueHeader"
    :loading="loading"
    joined
  >
    <template v-if="$slots.footer" #footer><slot name="footer" /></template>
  </ColumnTrend>
</template>
