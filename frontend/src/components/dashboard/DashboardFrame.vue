<script setup lang="ts">
import { computed } from 'vue'
import { greeting } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

/**
 * Common dashboard layout: greeting + lede, 4 stat cards, then a main card (1.45fr) beside a
 * "Needs your action" card (1fr).
 */
defineProps<{ lede?: string }>()

const auth = useAuthStore()
const firstName = computed(() => auth.user?.name.trim().split(/\s+/)[0] ?? '')
const hello = greeting()
</script>

<template>
  <div class="mx-auto flex max-w-[1200px] flex-col gap-6">
    <header>
      <h1 class="font-serif text-[28px] leading-tight font-semibold text-ink">
        {{ hello }}, {{ firstName }}.
      </h1>
      <p v-if="lede" class="mt-1.5 max-w-3xl text-[15px] text-ink/75">{{ lede }}</p>
      <slot name="lede" />
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <slot name="stats" />
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.45fr_1fr]">
      <slot name="main" />
      <slot name="actions" />
    </div>
  </div>
</template>
