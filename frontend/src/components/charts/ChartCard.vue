<script setup lang="ts">
import { ref, useId } from 'vue'

/**
 * Shared frame for charts: title, one-line subtitle, and a "View as table" toggle that swaps
 * the visual for a real <table> (screen readers, and screenshots for the paper).
 */
defineProps<{
  title: string
  subtitle?: string
  columns: string[]
  rows: (string | number)[][]
  loading?: boolean
}>()

const asTable = ref(false)
const id = useId()
</script>

<template>
  <section
    class="flex flex-col rounded-[10px] border border-ink/10 bg-white p-5"
    :aria-labelledby="`${id}-title`"
    :aria-busy="loading"
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 :id="`${id}-title`" class="font-serif text-lg leading-tight font-semibold text-ink">
          {{ title }}
        </h2>
        <p v-if="subtitle" class="mt-0.5 text-[13px] text-ink/65">{{ subtitle }}</p>
      </div>
      <button
        type="button"
        class="shrink-0 rounded-md border border-ink/15 px-2.5 py-1 text-xs font-medium text-ink/80 hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :aria-pressed="asTable"
        :disabled="loading"
        @click="asTable = !asTable"
      >
        {{ asTable ? 'View as chart' : 'View as table' }}
      </button>
    </div>

    <div class="mt-4 flex-1">
      <div v-if="loading" class="space-y-2.5" aria-hidden="true">
        <span
          v-for="n in 5"
          :key="n"
          class="block h-5 animate-pulse rounded bg-ink/10"
          :style="{ width: `${90 - n * 11}%` }"
        />
      </div>

      <table v-else-if="asTable" class="w-full text-left text-sm">
        <caption class="sr-only">
          {{
            title
          }}
        </caption>
        <thead>
          <tr
            class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
          >
            <th
              v-for="(column, i) in columns"
              :key="column"
              scope="col"
              class="py-2 font-medium"
              :class="i > 0 ? 'text-right' : ''"
            >
              {{ column }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, r) in rows" :key="r" class="border-b border-ink/5">
            <component
              :is="c === 0 ? 'th' : 'td'"
              v-for="(cell, c) in row"
              :key="c"
              :scope="c === 0 ? 'row' : undefined"
              class="py-2"
              :class="
                c === 0 ? 'font-normal text-ink' : 'text-right font-mono text-[13px] text-ink/85'
              "
            >
              {{ cell }}
            </component>
          </tr>
        </tbody>
      </table>

      <slot v-else />
    </div>

    <div v-if="$slots.footer && !loading" class="mt-3 text-[13px] text-ink/70">
      <slot name="footer" />
    </div>
  </section>
</template>
