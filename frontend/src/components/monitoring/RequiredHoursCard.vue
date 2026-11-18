<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import * as monitoringApi from '@/api/monitoring'
import * as programsApi from '@/api/programs'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import type { Program } from '@/types/accounts'

/** Admin: "Required OJT hours per program". */
const emit = defineEmits<{ saved: [message: string] }>()

const programs = ref<Program[]>([])
const values = reactive<Record<number, number>>({})
const errors = reactive<Record<number, string | undefined>>({})
const saving = ref<number | null>(null)

onMounted(async () => {
  programs.value = await programsApi.list()
  for (const p of programs.value) values[p.id] = p.required_ojt_hours ?? 486
})

async function save(program: Program): Promise<void> {
  saving.value = program.id
  errors[program.id] = undefined
  try {
    const updated = await monitoringApi.updateRequiredHours(program.id, Number(values[program.id]))
    program.required_ojt_hours = updated.required_ojt_hours
    emit('saved', `${program.code} now requires ${updated.required_ojt_hours} hours.`)
  } catch (e) {
    errors[program.id] = validationErrors(e)?.required_ojt_hours ?? errorMessage(e)
  } finally {
    saving.value = null
  }
}
</script>

<template>
  <section aria-labelledby="hours-title" class="rounded-[9px] border border-ink/10 bg-white p-5">
    <h2 id="hours-title" class="font-serif text-lg font-semibold text-ink">
      Required OJT hours per program
    </h2>
    <p class="mt-0.5 text-xs text-ink/70">
      100–2000 hours. Changes apply to progress and completion right away.
    </p>
    <div class="mt-3 max-h-96 overflow-y-auto">
      <table class="w-full text-left text-sm">
        <caption class="sr-only">
          Required OJT hours
        </caption>
        <thead class="font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
          <tr class="border-b border-ink/10">
            <th scope="col" class="py-2 pr-3 font-medium">Program</th>
            <th scope="col" class="py-2 pr-3 font-medium">Hours</th>
            <th scope="col" class="py-2 font-medium"><span class="sr-only">Save</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink/10">
          <tr v-for="program in programs" :key="program.id">
            <td class="py-2 pr-3">
              <span class="font-mono text-xs font-medium text-ink">{{ program.code }}</span>
              <span class="block text-xs text-ink/70">{{ program.name }}</span>
            </td>
            <td class="py-2 pr-3 align-top">
              <label :for="`hours-${program.id}`" class="sr-only"
                >Required hours for {{ program.code }}</label
              >
              <input
                :id="`hours-${program.id}`"
                v-model.number="values[program.id]"
                type="number"
                min="100"
                max="2000"
                step="1"
                :aria-invalid="errors[program.id] ? 'true' : undefined"
                class="h-9 w-24 rounded-md border px-2 font-mono text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
                :class="
                  errors[program.id]
                    ? 'border-red-700 focus-visible:outline-red-700'
                    : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
                "
              />
              <p v-if="errors[program.id]" class="mt-1 text-xs text-red-700">
                {{ errors[program.id] }}
              </p>
            </td>
            <td class="py-2 text-right align-top">
              <button
                type="button"
                :disabled="
                  saving === program.id || values[program.id] === program.required_ojt_hours
                "
                :aria-label="`Save required hours for ${program.code}`"
                class="h-9 rounded-md border border-brand px-3 text-xs font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-40"
                @click="save(program)"
              >
                {{ saving === program.id ? 'Saving…' : 'Save' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
