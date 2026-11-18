<script setup lang="ts">
import { computed } from 'vue'

const model = defineModel<string>({ required: true })

const props = defineProps<{
  id: string
  label: string
  error?: string
  hint?: string
  type?: string
  autocomplete?: string
  placeholder?: string
  inputmode?: 'text' | 'email' | 'tel' | 'numeric' | 'decimal'
  min?: number
  max?: number
}>()

const describedBy = computed(() => {
  if (props.error) return `${props.id}-error`
  return props.hint ? `${props.id}-hint` : undefined
})
</script>

<template>
  <div>
    <label :for="id" class="block text-sm font-medium text-ink">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      :name="id"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :inputmode="inputmode"
      :min="min"
      :max="max"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      class="mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-ink placeholder:text-ink/45 focus-visible:outline-2 focus-visible:outline-offset-0"
      :class="
        error
          ? 'border-red-700 focus-visible:outline-red-700'
          : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
      "
    />
    <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
    <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-xs text-ink/70">{{ hint }}</p>
  </div>
</template>
