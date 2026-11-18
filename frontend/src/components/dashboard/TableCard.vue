<script setup lang="ts">
import { useId } from 'vue'
import { RouterLink } from 'vue-router'

defineProps<{ title: string; subtitle?: string; linkTo?: string; linkLabel?: string }>()

const id = useId()
</script>

<template>
  <section class="rounded-[10px] border border-ink/10 bg-white" :aria-labelledby="`${id}-title`">
    <div class="flex items-start justify-between gap-4 px-5 pt-5 pb-3">
      <div>
        <h2 :id="`${id}-title`" class="font-serif text-lg font-semibold text-ink">{{ title }}</h2>
        <p v-if="subtitle" class="mt-0.5 text-[13px] text-ink/65">{{ subtitle }}</p>
      </div>
      <RouterLink
        v-if="linkTo"
        :to="linkTo"
        class="shrink-0 text-[13px] font-medium text-brand hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      >
        {{ linkLabel ?? 'Open on map' }} <span aria-hidden="true">→</span>
      </RouterLink>
    </div>
    <div class="overflow-x-auto px-5 pb-4">
      <slot />
    </div>
  </section>
</template>
