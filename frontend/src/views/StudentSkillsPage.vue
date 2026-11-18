<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as recommendationsApi from '@/api/recommendations'
import * as skillsApi from '@/api/skills'
import SkillChipCycler, { type ChipState } from '@/components/SkillChipCycler.vue'
import { errorMessage } from '@/lib/apiErrors'
import type { PartnerSuggestion } from '@/types/recommendations'
import type { Level, Skill } from '@/types/skills'

/** /student/skills: "My skills". */
const skills = ref<Skill[]>([])
const selection = ref<Record<number, number>>({})
const saved = ref<Record<number, number>>({})
const suggestions = ref<PartnerSuggestion[]>([])

const loading = ref(true)
const saving = ref(false)
const notice = ref<string | null>(null)
const error = ref<string | null>(null)

// Darker green = higher level.
const states: ChipState[] = [
  { value: 0, label: 'Not set', class: 'border-ink/20 bg-white text-ink hover:border-brand' },
  { value: 1, label: 'Beginner', class: 'border-brand/40 bg-brand/10 text-brand' },
  { value: 2, label: 'Intermediate', class: 'border-brand bg-brand text-white' },
  { value: 3, label: 'Advanced', class: 'border-brand-dark bg-brand-dark text-white' },
]

const dirty = computed(
  () => JSON.stringify(sorted(selection.value)) !== JSON.stringify(sorted(saved.value)),
)

function sorted(map: Record<number, number>): [number, number][] {
  return Object.entries(map)
    .map(([k, v]) => [Number(k), v] as [number, number])
    .sort((a, b) => a[0] - b[0])
}

onMounted(async () => {
  try {
    const [allSkills, mine, top] = await Promise.all([
      skillsApi.listSkills(),
      skillsApi.getStudentSkills(),
      recommendationsApi.studentSuggestions(),
    ])
    skills.value = allSkills
    saved.value = Object.fromEntries(mine.map((s) => [s.skill_id, s.level]))
    selection.value = { ...saved.value }
    suggestions.value = top
  } catch (e) {
    error.value = errorMessage(e, 'Could not load skills.')
  } finally {
    loading.value = false
  }
})

async function save(): Promise<void> {
  saving.value = true
  error.value = null
  notice.value = null
  try {
    const result = await skillsApi.saveStudentSkills(
      sorted(selection.value).map(([skill_id, level]) => ({ skill_id, level: level as Level })),
    )
    saved.value = Object.fromEntries(result.map((s) => [s.skill_id, s.level]))
    selection.value = { ...saved.value }
    notice.value = 'Saved. Your suggestions below are updated.'
    suggestions.value = await recommendationsApi.studentSuggestions()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">My skills</h1>
      <p class="mt-1 max-w-2xl text-sm text-ink/70">
        Tap a skill to set your level: beginner → intermediate → advanced → not set. Your OJT
        Coordinator uses these to find Industry Partners that fit you.
      </p>
    </div>

    <div role="status">
      <p
        v-if="notice"
        class="rounded-md border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-ink"
      >
        {{ notice }}
      </p>
    </div>
    <div role="alert">
      <p
        v-if="error"
        class="rounded-md border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-800"
      >
        {{ error }}
      </p>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[1fr_300px]">
      <section
        aria-labelledby="skills-title"
        class="rounded-[9px] border border-ink/10 bg-white p-5"
      >
        <h2
          id="skills-title"
          class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase"
        >
          Skills
        </h2>
        <p v-if="loading" class="mt-3 text-sm text-ink/70">Loading…</p>
        <SkillChipCycler
          v-else
          v-model="selection"
          class="mt-3"
          :skills="skills"
          :states="states"
          label="My skills"
        />

        <div class="mt-5 flex flex-wrap items-center gap-3">
          <button
            type="button"
            :disabled="saving || !dirty"
            class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
            @click="save"
          >
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
          <p v-if="dirty" class="text-sm text-accent-ink">You have unsaved changes.</p>
        </div>
      </section>

      <aside aria-labelledby="fit-title" class="rounded-[9px] border border-ink/10 bg-white p-5">
        <h2 id="fit-title" class="font-serif text-base font-semibold text-ink">
          Partners that fit your skills
        </h2>
        <p v-if="!suggestions.length" class="mt-2 text-sm text-ink/70">
          No partners with open slots right now.
        </p>
        <ol v-else class="mt-2 divide-y divide-ink/10">
          <li
            v-for="s in suggestions"
            :key="s.id"
            class="flex items-center justify-between gap-3 py-2"
          >
            <span>
              <span class="block text-sm font-medium text-ink">{{ s.company_name }}</span>
              <span class="block text-xs text-ink/70">{{ s.city }}</span>
            </span>
            <span class="font-mono text-xs font-medium text-brand">{{ s.score.toFixed(2) }}</span>
          </li>
        </ol>
        <p class="mt-3 text-xs leading-relaxed text-ink/70">
          These are suggestions only. Your coordinator makes the final placement.
        </p>
      </aside>
    </div>
  </div>
</template>
