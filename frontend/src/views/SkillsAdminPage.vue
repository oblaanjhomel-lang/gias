<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import * as recommendationsApi from '@/api/recommendations'
import * as skillsApi from '@/api/skills'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import type { RecommendationSettings, Skill, WsmWeights } from '@/types/skills'

/** /admin/skills: "Skills & matching". */
const skills = ref<Skill[]>([])
const settings = ref<RecommendationSettings | null>(null)
const loading = ref(true)
const notice = ref<string | null>(null)
const error = ref<string | null>(null)

function announce(message: string): void {
  error.value = null
  notice.value = message
}

function fail(e: unknown): void {
  notice.value = null
  error.value = errorMessage(e)
}

onMounted(async () => {
  try {
    const [list, current] = await Promise.all([skillsApi.listSkills(), skillsApi.getSettings()])
    skills.value = list
    settings.value = current
    Object.assign(form, pickWeights(current))
  } catch (e) {
    fail(e)
  } finally {
    loading.value = false
  }
})

// Skills list

const newSkill = ref('')
const newSkillError = ref<string | undefined>()
const adding = ref(false)

async function addSkill(): Promise<void> {
  newSkillError.value = undefined
  adding.value = true
  try {
    const created = await skillsApi.createSkill(newSkill.value.trim())
    skills.value = [...skills.value, created].sort((a, b) => a.name.localeCompare(b.name))
    announce(`Added “${created.name}”.`)
    newSkill.value = ''
  } catch (e) {
    newSkillError.value = validationErrors(e)?.name ?? errorMessage(e)
  } finally {
    adding.value = false
  }
}

function replaceSkill(updated: Skill): void {
  skills.value = skills.value.map((s) => (s.id === updated.id ? updated : s))
}

async function toggleActive(skill: Skill): Promise<void> {
  try {
    const updated = await skillsApi.updateSkill(skill.id, { is_active: !skill.is_active })
    replaceSkill(updated)
    announce(`“${updated.name}” is now ${updated.is_active ? 'active' : 'inactive'}.`)
  } catch (e) {
    fail(e)
  }
}

const renaming = ref<{ id: number; name: string; error?: string } | null>(null)

async function startRename(skill: Skill): Promise<void> {
  renaming.value = { id: skill.id, name: skill.name }
  await nextTick()
  document.getElementById(`rename-${skill.id}`)?.focus()
}

async function saveRename(): Promise<void> {
  const current = renaming.value
  if (!current) return
  try {
    const updated = await skillsApi.updateSkill(current.id, { name: current.name.trim() })
    replaceSkill(updated)
    renaming.value = null
    announce(`Renamed to “${updated.name}”.`)
  } catch (e) {
    current.error = validationErrors(e)?.name ?? errorMessage(e)
  }
}

async function removeSkill(skill: Skill): Promise<void> {
  try {
    await skillsApi.deleteSkill(skill.id)
    skills.value = skills.value.filter((s) => s.id !== skill.id)
    announce(`Deleted “${skill.name}”.`)
  } catch (e) {
    fail(e)
  }
}

// WSM weights

const WEIGHTS: { key: keyof WsmWeights; label: string; help: string }[] = [
  {
    key: 'weight_coverage',
    label: 'Skill coverage',
    help: 'Share of the needed skills the student has.',
  },
  {
    key: 'weight_proficiency',
    label: 'Proficiency',
    help: 'How strong the student is in those skills.',
  },
  {
    key: 'weight_proximity',
    label: 'Proximity',
    help: 'How close the partner is to the student’s home.',
  },
  { key: 'weight_slots', label: 'Open slots', help: 'How many slots the partner still has.' },
]

const form = reactive<WsmWeights>({
  weight_coverage: 40,
  weight_proficiency: 25,
  weight_proximity: 20,
  weight_slots: 15,
  proximity_zero_km: 100,
  slot_saturation: 5,
})
const formErrors = ref<Record<string, string>>({})
const savingWeights = ref(false)

function pickWeights(s: WsmWeights): WsmWeights {
  return {
    weight_coverage: s.weight_coverage,
    weight_proficiency: s.weight_proficiency,
    weight_proximity: s.weight_proximity,
    weight_slots: s.weight_slots,
    proximity_zero_km: s.proximity_zero_km,
    slot_saturation: s.slot_saturation,
  }
}

const weightSum = computed(
  () => form.weight_coverage + form.weight_proficiency + form.weight_proximity + form.weight_slots,
)

function normalised(key: keyof WsmWeights): string {
  return weightSum.value > 0 ? `${Math.round((Number(form[key]) / weightSum.value) * 100)}%` : '—'
}

function resetToDefaults(): void {
  if (settings.value) Object.assign(form, pickWeights(settings.value.defaults))
  formErrors.value = {}
}

async function saveWeights(): Promise<void> {
  savingWeights.value = true
  formErrors.value = {}
  try {
    settings.value = await skillsApi.updateSettings({ ...form })
    Object.assign(form, pickWeights(settings.value))
    announce('Weights saved. New recommendations use them right away.')
  } catch (e) {
    const fields = validationErrors(e)
    if (fields) formErrors.value = fields
    else fail(e)
  } finally {
    savingWeights.value = false
  }
}

// Export

const exporting = ref<'placements' | 'feedback' | null>(null)

async function download(type: 'placements' | 'feedback'): Promise<void> {
  exporting.value = type
  try {
    await recommendationsApi.downloadExport(type)
  } catch (e) {
    fail(e)
  } finally {
    exporting.value = null
  }
}

const card = 'rounded-[9px] border border-ink/10 bg-white p-5'
const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
const smallButton =
  'rounded px-2 py-1 text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand'
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">
        Skills &amp; matching
      </h1>
      <p class="mt-1 max-w-2xl text-sm text-ink/70">
        The skills list partners and students choose from, and how the recommendation algorithms
        weigh a match.
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

    <p v-if="loading" class="text-sm text-ink/70">Loading…</p>

    <div v-else class="grid items-start gap-5 xl:grid-cols-[1fr_420px]">
      <!-- Skills list -->
      <section aria-labelledby="skills-title" :class="card">
        <h2 id="skills-title" class="font-serif text-lg font-semibold text-ink">Skills list</h2>

        <form class="mt-3 flex flex-wrap items-start gap-2" novalidate @submit.prevent="addSkill">
          <div class="min-w-56 flex-1">
            <label for="new-skill" class="sr-only">New skill</label>
            <input
              id="new-skill"
              v-model="newSkill"
              type="text"
              placeholder="New skill"
              maxlength="100"
              :aria-invalid="newSkillError ? 'true' : undefined"
              :aria-describedby="newSkillError ? 'new-skill-error' : undefined"
              class="block h-10 w-full rounded-md border px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
              :class="
                newSkillError
                  ? 'border-red-700 focus-visible:outline-red-700'
                  : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
              "
            />
            <p v-if="newSkillError" id="new-skill-error" class="mt-1 text-sm text-red-700">
              {{ newSkillError }}
            </p>
          </div>
          <button
            type="submit"
            :disabled="adding || !newSkill.trim()"
            class="h-10 rounded-md bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
          >
            Add skill
          </button>
        </form>

        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-left text-sm">
            <caption class="sr-only">
              Skills
            </caption>
            <thead class="font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
              <tr class="border-b border-ink/10">
                <th scope="col" class="py-2 pr-3 font-medium">Skill</th>
                <th scope="col" class="py-2 pr-3 font-medium">Partners need</th>
                <th scope="col" class="py-2 pr-3 font-medium">Students have</th>
                <th scope="col" class="py-2 pr-3 font-medium">Active</th>
                <th scope="col" class="py-2 text-right font-medium">
                  <span class="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
              <tr
                v-for="skill in skills"
                :key="skill.id"
                :class="skill.is_active ? '' : 'text-ink/60'"
              >
                <td class="py-2 pr-3">
                  <form
                    v-if="renaming?.id === skill.id"
                    class="flex items-center gap-2"
                    @submit.prevent="saveRename"
                  >
                    <label :for="`rename-${skill.id}`" class="sr-only"
                      >New name for {{ skill.name }}</label
                    >
                    <input
                      :id="`rename-${skill.id}`"
                      v-model="renaming.name"
                      type="text"
                      maxlength="100"
                      class="h-8 w-full rounded-md border border-ink/25 px-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
                      @keydown.esc="renaming = null"
                    />
                    <button type="submit" :class="[smallButton, 'text-brand hover:bg-brand/5']">
                      Save
                    </button>
                    <button
                      type="button"
                      :class="[smallButton, 'text-ink hover:bg-ink/5']"
                      @click="renaming = null"
                    >
                      Cancel
                    </button>
                  </form>
                  <span v-else class="font-medium text-ink">{{ skill.name }}</span>
                  <p
                    v-if="renaming?.id === skill.id && renaming.error"
                    class="mt-1 text-xs text-red-700"
                  >
                    {{ renaming.error }}
                  </p>
                </td>
                <td class="py-2 pr-3 font-mono text-xs">{{ skill.partners_count ?? 0 }}</td>
                <td class="py-2 pr-3 font-mono text-xs">{{ skill.students_count ?? 0 }}</td>
                <td class="py-2 pr-3">
                  <button
                    type="button"
                    :aria-pressed="skill.is_active"
                    :aria-label="`${skill.name} active`"
                    class="rounded-full border px-2.5 py-0.5 text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    :class="
                      skill.is_active
                        ? 'border-brand bg-brand text-white'
                        : 'border-ink/25 bg-white text-ink/75'
                    "
                    @click="toggleActive(skill)"
                  >
                    {{ skill.is_active ? 'On' : 'Off' }}
                  </button>
                </td>
                <td class="py-2 text-right whitespace-nowrap">
                  <button
                    v-if="renaming?.id !== skill.id"
                    type="button"
                    :aria-label="`Rename ${skill.name}`"
                    :class="[smallButton, 'text-brand hover:bg-brand/5']"
                    @click="startRename(skill)"
                  >
                    Rename
                  </button>
                  <button
                    v-if="!skill.partners_count && !skill.students_count"
                    type="button"
                    :aria-label="`Delete ${skill.name}`"
                    :class="[
                      smallButton,
                      'text-red-700 hover:bg-red-50 focus-visible:outline-red-700',
                    ]"
                    @click="removeSkill(skill)"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="mt-3 text-xs text-ink/70">
            Skills in use can’t be deleted; switch them off instead. Inactive skills are ignored by
            the algorithms and hidden from partners and students.
          </p>
        </div>
      </section>

      <div class="space-y-5">
        <!-- WSM weights -->
        <section aria-labelledby="weights-title" :class="card">
          <h2 id="weights-title" class="font-serif text-lg font-semibold text-ink">
            Weighted Sum Model weights
          </h2>
          <p class="mt-0.5 text-xs text-ink/70">
            Weights are normalised, so they don’t need to add up to 100.
          </p>

          <form class="mt-4 space-y-4" novalidate @submit.prevent="saveWeights">
            <div v-for="w in WEIGHTS" :key="w.key">
              <div class="flex items-baseline justify-between gap-3">
                <label :for="w.key" class="text-sm font-medium text-ink">{{ w.label }}</label>
                <span class="font-mono text-xs text-ink">
                  {{ form[w.key] }} →
                  <span class="font-medium text-brand">{{ normalised(w.key) }}</span>
                </span>
              </div>
              <input
                :id="w.key"
                v-model.number="form[w.key]"
                type="range"
                min="0"
                max="100"
                step="5"
                :aria-describedby="`${w.key}-help`"
                :aria-valuetext="`${form[w.key]} (${normalised(w.key)} of the score)`"
                class="mt-1 w-full accent-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              />
              <p :id="`${w.key}-help`" class="text-xs text-ink/70">{{ w.help }}</p>
            </div>
            <p v-if="formErrors.weights" class="text-sm text-red-700">{{ formErrors.weights }}</p>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="proximity_zero_km" class="block text-sm font-medium text-ink"
                  >Proximity zero-point (km)</label
                >
                <input
                  id="proximity_zero_km"
                  v-model.number="form.proximity_zero_km"
                  type="number"
                  min="10"
                  max="300"
                  :aria-invalid="formErrors.proximity_zero_km ? 'true' : undefined"
                  class="mt-1.5 block h-11 w-full rounded-md border border-ink/25 px-3 text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
                />
                <p
                  class="mt-1 text-xs"
                  :class="formErrors.proximity_zero_km ? 'text-red-700' : 'text-ink/70'"
                >
                  {{ formErrors.proximity_zero_km ?? 'Distance that scores 0 (10–300).' }}
                </p>
              </div>
              <div>
                <label for="slot_saturation" class="block text-sm font-medium text-ink"
                  >Slot saturation</label
                >
                <input
                  id="slot_saturation"
                  v-model.number="form.slot_saturation"
                  type="number"
                  min="1"
                  max="50"
                  :aria-invalid="formErrors.slot_saturation ? 'true' : undefined"
                  class="mt-1.5 block h-11 w-full rounded-md border border-ink/25 px-3 text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
                />
                <p
                  class="mt-1 text-xs"
                  :class="formErrors.slot_saturation ? 'text-red-700' : 'text-ink/70'"
                >
                  {{ formErrors.slot_saturation ?? 'Open slots that score 1 (1–50).' }}
                </p>
              </div>
            </div>

            <div class="flex flex-wrap gap-3">
              <button
                type="submit"
                :disabled="savingWeights"
                class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60"
              >
                {{ savingWeights ? 'Saving…' : 'Save' }}
              </button>
              <button
                type="button"
                class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                @click="resetToDefaults"
              >
                Reset to defaults
              </button>
            </div>
            <p v-if="settings?.updated_by" class="text-xs text-ink/70">
              Last changed by {{ settings.updated_by }}.
            </p>
          </form>
        </section>

        <!-- Fuzzy rules (read-only) -->
        <section aria-labelledby="fuzzy-title" :class="card">
          <h2 id="fuzzy-title" class="font-serif text-lg font-semibold text-ink">
            Fuzzy logic rules
          </h2>
          <p class="mt-0.5 text-xs text-ink/70">
            Fixed in the system configuration. Fit = average of skill coverage and proficiency
            (0–100).
          </p>
          <table class="mt-3 w-full text-left text-sm">
            <caption class="sr-only">
              Fuzzy rules
            </caption>
            <thead :class="label">
              <tr>
                <th scope="col" class="py-1.5 font-medium">If fit is</th>
                <th scope="col" class="py-1.5 font-medium">And distance is</th>
                <th scope="col" class="py-1.5 font-medium">Then score is</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
              <tr v-for="(rule, i) in settings?.fuzzy.rules ?? []" :key="i">
                <td class="py-1.5 text-ink">{{ rule.fit }}</td>
                <td class="py-1.5 text-ink">{{ rule.distance }}</td>
                <td class="py-1.5 font-medium text-ink">{{ rule.then }}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- Cosine -->
        <section aria-labelledby="cosine-title" :class="card">
          <h2 id="cosine-title" class="font-serif text-lg font-semibold text-ink">
            Cosine similarity
          </h2>
          <p class="mt-1 text-sm text-ink/75">
            Needs no setup: it compares the student’s skill levels with the partner’s needed skills
            directly.
          </p>
        </section>

        <!-- Export -->
        <section aria-labelledby="export-title" :class="card">
          <h2 id="export-title" class="font-serif text-lg font-semibold text-ink">
            Export for evaluation
          </h2>
          <div class="mt-3 flex flex-wrap gap-3">
            <button
              type="button"
              :disabled="exporting !== null"
              class="h-10 rounded-md border border-brand px-4 text-sm font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60"
              @click="download('placements')"
            >
              {{ exporting === 'placements' ? 'Preparing…' : 'Placements CSV' }}
            </button>
            <button
              type="button"
              :disabled="exporting !== null"
              class="h-10 rounded-md border border-brand px-4 text-sm font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60"
              @click="download('feedback')"
            >
              {{ exporting === 'feedback' ? 'Preparing…' : 'Feedback CSV' }}
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
