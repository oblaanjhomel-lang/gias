<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import * as partnerApi from '@/api/partnerEvaluations'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import RatingScale from '@/components/RatingScale.vue'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { formatHours } from '@/lib/format'
import {
  CRITERIA,
  descriptor,
  EVALUATION_TYPES,
  SCALE,
  type EvaluationStatus,
  type EvaluationType,
  type PartnerEvaluationForm,
  type PartnerEvaluationRow,
  type Ratings,
} from '@/types/evaluations'

/** /partner/evaluations: "Evaluate interns". */
const rows = ref<PartnerEvaluationRow[]>([])
const finalsSubmitted = ref(0)
const total = ref(0)
const selectedId = ref<number | null>(null)
const type = ref<EvaluationType>('midterm')
const form = ref<PartnerEvaluationForm | null>(null)

const ratings = ref<Ratings>({})
const comment = ref('')
const loading = ref(true)
const loadingForm = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const saveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle')
const confirming = ref(false)
const submitting = ref(false)

async function loadList(): Promise<void> {
  const result = await partnerApi.list()
  rows.value = result.rows
  finalsSubmitted.value = result.finalsSubmitted
  total.value = result.total
}

onMounted(async () => {
  try {
    await loadList()
    const first = rows.value[0]
    if (first) await select(first.deployment_id, first.final.open ? 'final' : 'midterm')
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your interns.')
  } finally {
    loading.value = false
  }
})

const selectedRow = computed(
  () => rows.value.find((r) => r.deployment_id === selectedId.value) ?? null,
)

async function select(deploymentId: number, nextType: EvaluationType = type.value): Promise<void> {
  await flushSave()
  selectedId.value = deploymentId
  type.value = nextType
  notice.value = null
  loadingForm.value = true
  try {
    applyForm(await partnerApi.get(deploymentId, nextType))
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loadingForm.value = false
  }
}

function applyForm(next: PartnerEvaluationForm): void {
  form.value = next
  const values: Ratings = {}
  for (const c of CRITERIA) {
    values[c.key] = next.evaluation?.ratings.find((r) => r.key === c.key)?.value ?? null
  }
  ratings.value = values
  comment.value = next.evaluation?.comment ?? ''
  saveState.value = 'idle'
}

// State of the form

const status = computed<EvaluationStatus | null>(() => form.value?.evaluation?.status ?? null)
const editable = computed(
  () =>
    !!form.value?.open.open &&
    form.value.deployment_status === 'active' &&
    (status.value === null || status.value === 'draft' || status.value === 'returned'),
)
const ratedCount = computed(() => CRITERIA.filter((c) => ratings.value[c.key] != null).length)
const complete = computed(() => ratedCount.value === CRITERIA.length)
const average = computed(() => {
  const values = CRITERIA.map((c) => ratings.value[c.key]).filter((v): v is number => v != null)
  return values.length
    ? Math.round((values.reduce((a, b) => a + b, 0) / values.length) * 100) / 100
    : null
})

// Auto-save 800 ms after each change

let timer: ReturnType<typeof setTimeout> | undefined
let pending = false

function scheduleSave(): void {
  if (!editable.value) return
  pending = true
  saveState.value = 'idle'
  clearTimeout(timer)
  timer = setTimeout(() => void save(), 800)
}

async function save(): Promise<boolean> {
  if (!form.value || !pending) return true
  pending = false
  saveState.value = 'saving'
  try {
    const result = await partnerApi.saveDraft(
      form.value.deployment_id,
      form.value.type,
      ratings.value,
      comment.value.trim() || null,
    )
    form.value = result
    saveState.value = 'saved'
    updateRowStatus(result.evaluation?.status ?? null)
    return true
  } catch (e) {
    saveState.value = 'error'
    error.value = validationErrors(e)
      ? (Object.values(validationErrors(e)!)[0] ?? errorMessage(e))
      : errorMessage(e)
    return false
  }
}

async function flushSave(): Promise<boolean> {
  clearTimeout(timer)
  return save()
}

onBeforeUnmount(() => void flushSave())

function onRatings(value: Ratings): void {
  ratings.value = value
  scheduleSave()
}

function updateRowStatus(next: EvaluationStatus | null): void {
  const row = selectedRow.value
  if (row && form.value) row[form.value.type].status = next
}

// Submit

async function submit(): Promise<void> {
  if (!form.value) return
  submitting.value = true
  error.value = null
  try {
    if (!(await flushSave())) return
    const result = await partnerApi.submit(form.value.deployment_id, form.value.type)
    applyForm(result)
    confirming.value = false
    notice.value = `Submitted ${form.value.intern.name}’s ${form.value.type} evaluation. The OJT Coordinator will review it.`
    await loadList()
  } catch (e) {
    error.value = errorMessage(e)
    confirming.value = false
  } finally {
    submitting.value = false
  }
}

async function onTabKeydown(event: KeyboardEvent, index: number): Promise<void> {
  if (!['ArrowLeft', 'ArrowRight'].includes(event.key) || selectedId.value === null) return
  event.preventDefault()
  const next = EVALUATION_TYPES[(index + 1) % EVALUATION_TYPES.length]!
  await select(selectedId.value, next.value)
  await nextTick()
  document.getElementById(`eval-tab-${next.value}`)?.focus()
}

function statusLabel(entry: { status: EvaluationStatus | null; open: boolean }): string {
  if (entry.status)
    return { draft: 'Draft', submitted: 'Submitted', returned: 'Returned', reviewed: 'Reviewed' }[
      entry.status
    ]
  return entry.open ? 'Not started' : 'Not open'
}

const STATUS_CLASS: Record<string, string> = {
  Draft: 'bg-ink/[0.07] text-ink/75',
  Submitted: 'bg-blue-50 text-blue-800',
  Returned: 'bg-accent/20 text-accent-ink',
  Reviewed: 'bg-brand/10 text-brand',
  'Not started': 'bg-white text-ink/70 ring-1 ring-ink/15',
  'Not open': 'bg-white text-ink/50 ring-1 ring-ink/10',
}

const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">
          Evaluate interns
        </h1>
        <p class="mt-1 text-sm leading-relaxed text-ink/70">
          Rate each intern on six criteria from 1 to 5. After you submit, the intern’s OJT
          Coordinator reviews it and adds their own rating before the student sees the result.
        </p>
      </div>
      <p class="font-mono text-xs font-medium tracking-[0.08em] text-ink/70 uppercase">
        {{ finalsSubmitted }} of {{ total }} final evaluations submitted
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
    <p
      v-else-if="!rows.length"
      class="rounded-[9px] border border-ink/10 bg-white px-5 py-10 text-center text-sm text-ink/70"
    >
      You have no active interns to evaluate.
    </p>

    <div v-else class="grid items-start gap-5 lg:grid-cols-[290px_1fr]">
      <!-- Interns -->
      <nav aria-label="Interns" class="overflow-hidden rounded-[9px] border border-ink/10 bg-white">
        <ul class="divide-y divide-ink/10">
          <li v-for="row in rows" :key="row.deployment_id">
            <button
              type="button"
              :aria-pressed="selectedId === row.deployment_id"
              class="block w-full px-4 py-3 text-left hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
              :class="
                selectedId === row.deployment_id
                  ? 'bg-brand/5 shadow-[inset_3px_0_0_var(--color-brand)]'
                  : ''
              "
              @click="select(row.deployment_id)"
            >
              <span class="block text-sm font-medium text-ink">{{ row.intern.name }}</span>
              <span class="block text-xs text-ink/70"
                >{{ row.intern.program ?? '—' }} · {{ formatHours(row.signed_hours) }} h</span
              >
              <span class="mt-1.5 flex flex-wrap gap-1.5 text-[11px]">
                <span class="rounded px-1.5 py-0.5" :class="STATUS_CLASS[statusLabel(row.midterm)]"
                  >Midterm: {{ statusLabel(row.midterm) }}</span
                >
                <span class="rounded px-1.5 py-0.5" :class="STATUS_CLASS[statusLabel(row.final)]"
                  >Final: {{ statusLabel(row.final) }}</span
                >
              </span>
            </button>
          </li>
        </ul>
      </nav>

      <!-- Form -->
      <section
        v-if="form"
        aria-labelledby="eval-intern"
        class="overflow-hidden rounded-[9px] border border-ink/10 bg-white"
      >
        <header class="px-5 pt-4">
          <h2 id="eval-intern" class="font-serif text-xl font-semibold text-ink">
            {{ form.intern.name }}
          </h2>
          <p class="font-mono text-[11px] tracking-[0.06em] text-ink/70 uppercase">
            {{ form.intern.program ?? '—' }} · {{ formatHours(form.open.signed_hours) }} of
            {{ form.open.required_hours }} hours
          </p>
          <div
            role="tablist"
            aria-label="Evaluation"
            class="mt-3 flex gap-6 border-b border-ink/10"
          >
            <button
              v-for="(t, index) in EVALUATION_TYPES"
              :id="`eval-tab-${t.value}`"
              :key="t.value"
              type="button"
              role="tab"
              :aria-selected="type === t.value"
              aria-controls="eval-panel"
              :tabindex="type === t.value ? 0 : -1"
              class="-mb-px border-b-2 px-0.5 pb-2.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              :class="
                type === t.value
                  ? 'border-brand text-brand'
                  : 'border-transparent text-ink/70 hover:text-ink'
              "
              @click="selectedId !== null && select(selectedId, t.value)"
              @keydown="onTabKeydown($event, index)"
            >
              {{ t.label }}
            </button>
          </div>
        </header>

        <div
          id="eval-panel"
          role="tabpanel"
          :aria-labelledby="`eval-tab-${type}`"
          :aria-busy="loadingForm"
        >
          <p
            v-if="!form.open.open"
            class="m-5 rounded-md border border-ink/10 bg-canvas px-4 py-3 text-sm text-ink"
          >
            Opens at {{ formatHours(form.open.threshold_hours) }} signed hours.
            {{ form.intern.name }} has {{ formatHours(form.open.signed_hours) }}.
          </p>

          <template v-else>
            <div class="space-y-4 px-5 py-4">
              <p
                v-if="status === 'returned'"
                class="rounded-md border-l-[3px] border-accent bg-accent/10 px-3 py-2 text-sm text-ink"
              >
                <span class="font-medium text-accent-ink">Returned by the OJT Coordinator:</span>
                {{ form.evaluation?.return_note }}
              </p>
              <p
                v-if="status === 'submitted'"
                class="rounded-md border border-brand/30 bg-brand/5 px-3 py-2 text-sm text-brand"
              >
                Submitted. Waiting for the OJT Coordinator to review it.
              </p>
              <p
                v-else-if="status === 'reviewed'"
                class="rounded-md border border-brand/30 bg-brand/5 px-3 py-2 text-sm text-brand"
              >
                Reviewed by the OJT Coordinator.
              </p>

              <p class="font-mono text-[11px] text-ink/70">
                <template v-for="n in 5" :key="n"
                  >{{ n }} {{ SCALE[n] }}<template v-if="n < 5"> · </template></template
                >
              </p>

              <RatingScale
                :model-value="ratings"
                :criteria="CRITERIA"
                :readonly="!editable"
                @update:model-value="onRatings"
              />

              <div>
                <label for="eval-comment" class="block text-sm font-medium text-ink"
                  >Comments for the intern and coordinator</label
                >
                <textarea
                  id="eval-comment"
                  v-model="comment"
                  rows="3"
                  maxlength="2000"
                  :readonly="!editable"
                  class="mt-1.5 block w-full rounded-md border border-ink/25 px-3 py-2 text-sm text-ink read-only:bg-canvas focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
                  @input="scheduleSave"
                />
              </div>
            </div>

            <footer
              class="flex flex-wrap items-center justify-between gap-4 border-t border-ink/10 bg-canvas/60 px-5 py-4"
            >
              <p>
                <span :class="label">Average rating</span>
                <span class="ml-2 font-mono text-lg font-medium text-ink">{{
                  average?.toFixed(2) ?? '—'
                }}</span>
                <span v-if="average !== null" class="ml-1 text-sm text-ink/75">{{
                  descriptor(average)
                }}</span>
              </p>
              <div v-if="editable" class="flex flex-wrap items-center gap-3">
                <p class="text-xs text-ink/70" aria-live="polite">
                  <template v-if="!complete"
                    >Rate all 6 criteria to submit. {{ ratedCount }} of 6 done.
                  </template>
                  Ratings save as a draft as you go.
                  <span v-if="saveState === 'saving'" class="text-ink"> Saving…</span>
                  <span v-else-if="saveState === 'saved'" class="text-brand"> Saved</span>
                  <span v-else-if="saveState === 'error'" class="text-red-700"> Not saved</span>
                </p>
                <button
                  type="button"
                  :aria-disabled="!complete"
                  class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                  @click="complete && (confirming = true)"
                >
                  Submit evaluation
                </button>
              </div>
            </footer>
          </template>
        </div>
      </section>
    </div>

    <ConfirmDialog
      v-if="confirming && form"
      :title="`Submit ${form.intern.name}’s ${form.type} evaluation?`"
      message="The OJT Coordinator will review it and add their rating. You can’t change it unless they return it to you."
      confirm-label="Submit evaluation"
      :busy="submitting"
      @cancel="confirming = false"
      @confirm="submit"
    />
  </div>
</template>
