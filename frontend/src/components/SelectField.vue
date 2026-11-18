<script setup lang="ts">
export interface SelectOption {
  value: string
  label: string
  disabled?: boolean
}

const model = defineModel<string>({ required: true })

defineProps<{
  id: string
  label: string
  options: SelectOption[]
  /** Shown as an empty first option (value ""). */
  placeholder?: string
  error?: string
}>()
</script>

<template>
  <div>
    <label :for="id" class="block text-sm font-medium text-ink">{{ label }}</label>
    <select
      :id="id"
      v-model="model"
      :name="id"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
      class="mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
      :class="
        error
          ? 'border-red-700 focus-visible:outline-red-700'
          : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
      "
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
        :disabled="option.disabled"
      >
        {{ option.label }}
      </option>
    </select>
    <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
  </div>
</template>
