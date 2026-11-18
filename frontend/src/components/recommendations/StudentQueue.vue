<script setup lang="ts">
import { useRecommendationsStore } from '@/stores/recommendations'
import { LEVEL_LABELS } from '@/types/skills'

/** Left card: "Students awaiting placement". */
const emit = defineEmits<{ draft: [] }>()
const store = useRecommendationsStore()
</script>

<template>
  <section
    aria-labelledby="queue-title"
    class="flex max-h-[calc(100vh-12rem)] flex-col rounded-[9px] border border-ink/10 bg-white"
  >
    <header class="border-b border-ink/10 px-4 py-3">
      <h2 id="queue-title" class="font-serif text-base font-semibold text-ink">
        Students awaiting placement
      </h2>
      <p class="mt-0.5 font-mono text-xs text-ink/70">
        {{ store.placed }} of {{ store.total }} placed
      </p>
    </header>

    <p v-if="store.loadingStudents && !store.students.length" class="px-4 py-6 text-sm text-ink/70">
      Loading students…
    </p>
    <p v-else-if="!store.students.length" class="px-4 py-6 text-sm text-ink/70">
      No active students in your scope yet.
    </p>

    <ul v-else class="min-h-0 flex-1 divide-y divide-ink/10 overflow-y-auto">
      <li v-for="student in store.students" :key="student.id">
        <button
          type="button"
          :aria-pressed="store.selectedStudentId === student.id"
          class="block w-full px-4 py-3 text-left hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
          :class="
            store.selectedStudentId === student.id
              ? 'bg-brand/5 shadow-[inset_3px_0_0_var(--color-brand)]'
              : ''
          "
          @click="store.select(student.id)"
        >
          <span class="block text-sm font-medium text-ink">{{ student.name }}</span>
          <span class="block truncate text-xs text-ink/70">
            {{ student.city }}
            <template v-if="student.top_skills.length">
              ·
              {{ student.top_skills.map((s) => `${s.name} (${LEVEL_LABELS[s.level]})`).join(', ') }}
            </template>
            <template v-else> · no skills yet</template>
          </span>
          <span
            class="mt-1 block text-xs font-medium"
            :class="student.placement ? 'text-brand' : 'text-accent-ink'"
          >
            {{ student.placement ? `Placed: ${student.placement.company_name}` : 'Not yet placed' }}
          </span>
        </button>
      </li>
    </ul>

    <footer class="space-y-2 border-t border-ink/10 px-4 py-3">
      <button
        type="button"
        :disabled="store.loadingDraft || store.placed === store.total"
        class="h-10 w-full rounded-md border border-brand px-3 text-sm font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
        @click="emit('draft')"
      >
        {{ store.loadingDraft ? 'Drafting…' : 'Draft placements for all' }}
      </button>
      <p class="text-xs leading-relaxed text-ink/70">
        Proposes a partner for every unplaced student using the selected algorithm, without going
        over any partner’s slots. Nothing is saved until you confirm.
      </p>
    </footer>
  </section>
</template>
