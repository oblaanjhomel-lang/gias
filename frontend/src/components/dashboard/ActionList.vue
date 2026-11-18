<script setup lang="ts">
import { RouterLink } from 'vue-router'

export interface ActionItem {
  label: string
  /** e.g. "Review and approve" */
  detail?: string
  count: number
  to: string
}

defineProps<{ items: ActionItem[]; title?: string }>()
</script>

<template>
  <section
    class="rounded-[10px] border border-ink/10 bg-white p-5"
    aria-labelledby="needs-action-title"
  >
    <h2 id="needs-action-title" class="font-serif text-lg font-semibold text-ink">
      {{ title ?? 'Needs your action' }}
    </h2>
    <ul class="mt-3 space-y-2">
      <li v-for="item in items" :key="item.label">
        <RouterLink
          :to="item.to"
          class="flex items-center justify-between gap-3 rounded-md border px-3.5 py-2.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          :class="
            item.count > 0
              ? 'border-[#E8D9A8] bg-[#FBF6E8] text-ink hover:border-accent'
              : 'border-ink/10 bg-white text-ink/70 hover:border-ink/25'
          "
        >
          <span>
            <span class="block font-medium">{{ item.label }}</span>
            <span v-if="item.detail" class="block text-[12.5px] text-ink/65">{{
              item.detail
            }}</span>
          </span>
          <span class="flex shrink-0 items-center gap-2">
            <span
              class="min-w-7 rounded-full px-2 py-0.5 text-center font-mono text-xs font-medium"
              :class="item.count > 0 ? 'bg-accent text-ink' : 'bg-ink/[0.07] text-ink/70'"
            >
              {{ item.count }}
            </span>
            <span aria-hidden="true" class="text-ink/50">→</span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
