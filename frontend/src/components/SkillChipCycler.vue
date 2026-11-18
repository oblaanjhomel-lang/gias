<script setup lang="ts">
import type { Skill } from '@/types/skills'

/**
 * Skill chips that cycle through states on activation (click / Enter / Space).
 * `states[0]` is the "not set" state (value 0); the model maps skill id → state value.
 */
export interface ChipState {
  value: number
  label: string
  /** Tailwind classes for the chip in this state. */
  class: string
}

const props = defineProps<{
  skills: Skill[]
  states: ChipState[]
  /** Accessible name for the group, e.g. "Skills we need". */
  label: string
}>()

const model = defineModel<Record<number, number>>({ required: true })

function stateOf(skillId: number): ChipState {
  const value = model.value[skillId] ?? 0
  return props.states.find((s) => s.value === value) ?? props.states[0]!
}

function cycle(skillId: number): void {
  const index = props.states.findIndex((s) => s.value === (model.value[skillId] ?? 0))
  const next = props.states[(index + 1) % props.states.length]!
  const updated = { ...model.value }
  if (next.value === 0) delete updated[skillId]
  else updated[skillId] = next.value
  model.value = updated
}
</script>

<template>
  <ul :aria-label="label" class="flex flex-wrap gap-2">
    <li v-for="skill in skills" :key="skill.id">
      <button
        type="button"
        :aria-label="`${skill.name}: ${stateOf(skill.id).label}. Activate to change.`"
        class="rounded-full border px-3 py-1.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="stateOf(skill.id).class"
        @click="cycle(skill.id)"
      >
        {{ skill.name }}
        <span v-if="stateOf(skill.id).value !== 0" class="ml-1 text-xs opacity-90">
          · {{ stateOf(skill.id).label }}
        </span>
      </button>
    </li>
  </ul>
</template>
