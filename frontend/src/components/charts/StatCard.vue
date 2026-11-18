<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    value: string | number
    note?: string
    tone?: 'neutral' | 'warn' | 'ok'
    loading?: boolean
  }>(),
  { note: undefined, tone: 'neutral', loading: false },
)

const toneClass = {
  neutral: 'text-ink/65',
  warn: 'text-[#8a6a1f]',
  ok: 'text-[#1F6B3C]',
} as const
</script>

<template>
  <div class="rounded-[10px] border border-ink/10 bg-white px-5 py-4" :aria-busy="loading">
    <p class="font-mono text-[11px] font-medium tracking-wider text-ink/65 uppercase">
      {{ label }}
    </p>
    <template v-if="loading">
      <span class="mt-2 block h-7 w-20 animate-pulse rounded bg-ink/10" />
      <span class="mt-2 block h-3 w-32 animate-pulse rounded bg-ink/10" />
    </template>
    <template v-else>
      <p class="mt-1.5 font-serif text-2xl leading-tight font-semibold text-ink">{{ value }}</p>
      <p v-if="note" class="mt-1 text-[13px]" :class="toneClass[tone]">{{ note }}</p>
    </template>
  </div>
</template>
