<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { useDialog } from '@/composables/useDialog'
import { useRecommendationsStore } from '@/stores/recommendations'
import { ALGORITHM_LABELS, type DraftPlan } from '@/types/recommendations'

/** Preview of the greedy draft. Nothing is saved until "Place selected". Mount with v-if. */
const props = defineProps<{ plan: DraftPlan }>()
const emit = defineEmits<{ close: [] }>()

const store = useRecommendationsStore()
const uid = useId()
const dialog = ref<HTMLElement | null>(null)

const selected = ref<Set<number>>(new Set(props.plan.proposals.map((p) => p.student.id)))
const startDate = ref(new Date().toISOString().slice(0, 10))
const error = ref<string | null>(null)

const count = computed(() => selected.value.size)

function toggle(studentId: number): void {
  const next = new Set(selected.value)
  if (next.has(studentId)) next.delete(studentId)
  else next.add(studentId)
  selected.value = next
}

async function placeSelected(): Promise<void> {
  error.value = null
  error.value = await store.placeBatch(
    props.plan.proposals
      .filter((p) => selected.value.has(p.student.id))
      .map((p) => ({
        student_id: p.student.id,
        partner_id: p.partner.id,
        algorithm: props.plan.algorithm,
        start_date: startDate.value,
      })),
  )
  if (error.value === null) emit('close')
}

useDialog(dialog, {
  onClose: () => {
    if (!store.working) emit('close')
  },
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4">
      <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${uid}-title`"
        :aria-describedby="`${uid}-desc`"
        class="flex max-h-[calc(100vh-2rem)] w-full max-w-[640px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
      >
        <header class="border-b border-ink/10 px-6 py-4">
          <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
            Draft placements
          </h2>
          <p :id="`${uid}-desc`" class="mt-1 text-sm text-ink/75">
            Using <strong>{{ ALGORITHM_LABELS[plan.algorithm] }}</strong
            >. Highest scores are assigned first without going over any partner’s slots. Untick any
            you don’t want.
          </p>
        </header>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
          <div
            v-if="error"
            role="alert"
            class="rounded-md border border-red-700/30 bg-red-50 px-3 py-2.5 text-sm text-red-800"
          >
            {{ error }}
          </div>

          <p v-if="!plan.proposals.length" class="text-sm text-ink/70">
            No proposals: there are no unplaced students with a usable match.
          </p>
          <table v-else class="w-full text-left text-sm">
            <caption class="sr-only">
              Proposed placements
            </caption>
            <thead class="font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
              <tr>
                <th scope="col" class="py-2 pr-2 font-medium">
                  <span class="sr-only">Include</span>
                </th>
                <th scope="col" class="py-2 pr-3 font-medium">Student → partner</th>
                <th scope="col" class="py-2 font-medium">Score</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
              <tr v-for="p in plan.proposals" :key="p.student.id">
                <td class="py-2 pr-2">
                  <input
                    :id="`${uid}-p-${p.student.id}`"
                    type="checkbox"
                    class="size-4 accent-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    :checked="selected.has(p.student.id)"
                    @change="toggle(p.student.id)"
                  />
                </td>
                <td class="py-2 pr-3">
                  <label :for="`${uid}-p-${p.student.id}`" class="cursor-pointer text-ink">
                    <span class="font-medium">{{ p.student.name }}</span>
                    <span class="text-ink/70"> ({{ p.student.program ?? '—' }})</span>
                    → {{ p.partner.company_name }}
                    <span class="text-xs text-ink/70">· {{ p.partner.city }}</span>
                  </label>
                </td>
                <td class="py-2 font-mono text-xs text-ink">{{ p.score.toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>

          <div v-if="plan.unassigned.length">
            <h3 class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase">
              Not assigned ({{ plan.unassigned.length }})
            </h3>
            <p class="mt-1 text-sm text-ink">
              {{ plan.unassigned.map((s) => s.name).join(', ') }}
            </p>
            <p class="mt-1 text-xs text-ink/70">
              No partner with open slots left, or no usable match.
            </p>
          </div>

          <div>
            <label :for="`${uid}-date`" class="block text-sm font-medium text-ink"
              >Start date for all</label
            >
            <input
              :id="`${uid}-date`"
              v-model="startDate"
              type="date"
              class="mt-1.5 block h-11 w-48 rounded-md border border-ink/25 px-3 text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
            />
          </div>
        </div>

        <footer class="flex justify-end gap-3 border-t border-ink/10 bg-canvas/60 px-6 py-4">
          <button
            type="button"
            :disabled="store.working"
            class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            @click="emit('close')"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="store.working || count === 0 || !startDate"
            class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60"
            @click="placeSelected"
          >
            {{ store.working ? 'Placing…' : `Place selected (${count})` }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
