<script setup lang="ts">
import { useId } from 'vue'
import { SCALE } from '@/types/evaluations'

/**
 * 1–5 ratings: one fieldset per criterion with five NATIVE radio inputs, drawn as 34×32 squares.
 * Each radio is named "{criterion}: {n} — {descriptor}". Readonly disables the inputs.
 */
defineProps<{
  criteria: { key: string; label: string }[]
  readonly?: boolean
}>()

const model = defineModel<Record<string, number | null>>({ required: true })

const uid = useId()

function set(key: string, value: number): void {
  model.value = { ...model.value, [key]: value }
}
</script>

<template>
  <div class="divide-y divide-ink/10">
    <fieldset
      v-for="criterion in criteria"
      :key="criterion.key"
      class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2.5"
    >
      <legend class="float-left text-sm font-medium text-ink">{{ criterion.label }}</legend>
      <div class="flex gap-1.5">
        <label v-for="n in 5" :key="n" class="relative">
          <input
            type="radio"
            class="peer absolute inset-0 cursor-pointer opacity-0 disabled:cursor-default"
            :name="`${uid}-${criterion.key}`"
            :value="n"
            :checked="model[criterion.key] === n"
            :disabled="readonly"
            :aria-label="`${criterion.label}: ${n} — ${SCALE[n]}`"
            @change="set(criterion.key, n)"
          />
          <span
            aria-hidden="true"
            class="grid h-8 w-[34px] place-items-center rounded-md border font-mono text-sm transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand"
            :class="
              model[criterion.key] === n
                ? 'border-brand bg-brand font-medium text-white'
                : readonly
                  ? 'border-ink/10 bg-canvas text-ink/50'
                  : 'border-ink/25 bg-white text-ink peer-hover:border-brand'
            "
          >
            {{ n }}
          </span>
        </label>
      </div>
    </fieldset>
  </div>
</template>
