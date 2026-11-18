<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import * as evaluationsApi from '@/api/evaluations'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import CriterionBars from '@/components/CriterionBars.vue'
import RatingScale from '@/components/RatingScale.vue'
import { errorMessage } from '@/lib/apiErrors'
import { formatHours } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useEvaluationsStore } from '@/stores/evaluations'
import {
  COORDINATOR_CRITERION,
  descriptor,
  EVALUATION_TYPES,
  finalRating,
  SCALE,
  type EvaluationType,
  type QueueStatus,
} from '@/types/evaluations'

/** /coordinator/evaluations and /admin/evaluations (admin: Overview + Review tabs). */
const auth = useAuthStore()
const store = useEvaluationsStore()
const isAdmin = computed(() => auth.role === 'admin')
const tab = ref<'overview' | 'review'>('review')

onMounted(async () => {
  store.$reset()
  tab.value = isAdmin.value ? 'overview' : 'review'
  await Promise.all([store.fetch(), isAdmin.value ? store.fetchSummary() : Promise.resolve()])
})

const typeModel = computed({
  get: () => store.type,
  set: (value: EvaluationType) => {
    void store.setType(value)
    if (isAdmin.value) void store.fetchSummary()
  },
})
const statusModel = computed({
  get: () => store.status,
  set: (value: QueueStatus | '') => void store.setStatus(value),
})

const searchInput = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(searchInput, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => void store.setSearch(value), 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const STATUS_LABEL: Record<QueueStatus, string> = {
  not_started: 'Not started',
  draft: 'Draft',
  submitted: 'To review',
  returned: 'Returned',
  reviewed: 'Reviewed',
}
const STATUS_CLASS: Record<QueueStatus, string> = {
  not_started: 'text-ink/60',
  draft: 'text-ink/70',
  submitted: 'text-blue-800',
  returned: 'text-accent-ink',
  reviewed: 'text-brand',
}

// Review panel

const myRating = ref<Record<string, number | null>>({ coordinator: null })
const myNote = ref('')
watch(
  () => store.detail?.id,
  () => {
    myRating.value = { coordinator: store.detail?.coordinator_rating ?? null }
    myNote.value = store.detail?.coordinator_note ?? ''
  },
)

const liveFinal = computed(() => {
  const mean = store.detail?.partner_mean
  const rating = myRating.value.coordinator
  if (store.detail?.status === 'reviewed') return store.detail.final_rating ?? null
  return mean != null && rating != null ? finalRating(mean, rating) : null
})

const returning = ref(false)
const returnError = ref<string | null>(null)

async function sendReturn(note: string): Promise<void> {
  returnError.value = await store.returnToPartner(note)
  if (!returnError.value) returning.value = false
}

// Admin export
const exporting = ref(false)
async function exportCsv(): Promise<void> {
  exporting.value = true
  try {
    await evaluationsApi.exportCsv(store.type)
  } catch (e) {
    store.error = errorMessage(e)
  } finally {
    exporting.value = false
  }
}

const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
const card = 'rounded-[9px] border border-ink/10 bg-white'
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">Evaluations</h1>
        <p class="mt-1 text-sm leading-relaxed text-ink/70">
          The Industry Partner rates each intern on six criteria. You add one rating for OJT
          requirements and documentation. The final OJT rating is 70% the partner’s average and 30%
          your rating.
        </p>
      </div>
      <p class="font-mono text-xs font-medium tracking-[0.08em] text-ink/70 uppercase">
        {{ store.counts.to_review }} to review · {{ store.counts.reviewed }} reviewed ·
        {{ store.counts.not_submitted }} not yet submitted
      </p>
    </div>

    <div role="status">
      <p
        v-if="store.notice"
        class="rounded-md border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-ink"
      >
        {{ store.notice }}
      </p>
    </div>
    <div role="alert">
      <p
        v-if="store.error"
        class="rounded-md border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-800"
      >
        {{ store.error }}
      </p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div
        v-if="isAdmin"
        role="group"
        aria-label="View"
        class="inline-flex rounded-md border border-ink/20 bg-white p-0.5"
      >
        <button
          v-for="t in [
            { key: 'overview', label: 'Overview' },
            { key: 'review', label: 'Review' },
          ] as const"
          :key="t.key"
          type="button"
          :aria-pressed="tab === t.key"
          class="rounded px-3.5 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand"
          :class="tab === t.key ? 'bg-brand text-white' : 'text-ink/75 hover:text-ink'"
          @click="tab = t.key"
        >
          {{ t.label }}
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <label for="eval-type" class="sr-only">Evaluation</label>
        <select
          id="eval-type"
          v-model="typeModel"
          class="h-10 rounded-md border border-ink/25 bg-white px-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
        >
          <option v-for="t in EVALUATION_TYPES" :key="t.value" :value="t.value">
            {{ t.label }}
          </option>
        </select>
        <template v-if="tab === 'review'">
          <label for="eval-status" class="sr-only">Status</label>
          <select
            id="eval-status"
            v-model="statusModel"
            class="h-10 rounded-md border border-ink/25 bg-white px-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
          >
            <option value="">All statuses</option>
            <option v-for="(text, key) in STATUS_LABEL" :key="key" :value="key">{{ text }}</option>
          </select>
          <label for="eval-search" class="sr-only">Search interns</label>
          <input
            id="eval-search"
            v-model="searchInput"
            type="search"
            placeholder="Search name, student no., company"
            class="h-10 w-60 rounded-md border border-ink/25 bg-white px-3 text-sm text-ink placeholder:text-ink/50 focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
          />
        </template>
        <button
          v-if="isAdmin && tab === 'overview'"
          type="button"
          :disabled="exporting"
          class="h-10 rounded-md border border-brand px-4 text-sm font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60"
          @click="exportCsv"
        >
          {{ exporting ? 'Preparing…' : 'Export CSV' }}
        </button>
      </div>
    </div>

    <!-- Admin overview -->
    <template v-if="isAdmin && tab === 'overview'">
      <p v-if="!store.summary" class="text-sm text-ink/70">Loading…</p>
      <template v-else>
        <dl class="grid gap-3 sm:grid-cols-4">
          <div :class="[card, 'px-4 py-3']">
            <dt :class="label">Evaluations submitted</dt>
            <dd class="font-mono text-2xl font-medium text-ink">
              {{ store.summary.evaluations_submitted }}
            </dd>
          </div>
          <div :class="[card, 'px-4 py-3']">
            <dt :class="label">Average partner rating</dt>
            <dd class="font-mono text-2xl font-medium text-ink">
              {{ store.summary.average_partner_mean?.toFixed(2) ?? '—' }}
              <span class="block font-sans text-xs font-normal text-ink/70">{{
                store.summary.average_descriptor ?? ''
              }}</span>
            </dd>
          </div>
          <div :class="[card, 'px-4 py-3']">
            <dt :class="label">Awaiting review</dt>
            <dd class="font-mono text-2xl font-medium text-ink">
              {{ store.summary.awaiting_review }}
            </dd>
          </div>
          <div :class="[card, 'border-accent/60 px-4 py-3']">
            <dt
              class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-accent-ink uppercase"
            >
              Lowest criterion
            </dt>
            <dd class="text-sm font-medium text-ink">
              {{ store.summary.lowest_criterion?.label ?? '—' }}
              <span v-if="store.summary.lowest_criterion" class="font-mono text-ink/75"
                >· {{ store.summary.lowest_criterion.average.toFixed(2) }}</span
              >
            </dd>
          </div>
        </dl>

        <div class="grid gap-5 lg:grid-cols-2">
          <section :class="[card, 'p-5']" aria-labelledby="by-criterion">
            <h2 id="by-criterion" class="font-serif text-lg font-semibold text-ink">
              Average by criterion
            </h2>
            <div class="mt-3">
              <CriterionBars
                :rows="
                  store.summary.criteria.map((c) => ({
                    key: c.key,
                    label: c.label,
                    value: c.average,
                  }))
                "
                :lowest="store.summary.lowest_criterion?.key"
              />
            </div>
            <p class="mt-3 font-mono text-[11px] text-ink/70">
              <template v-for="n in 5" :key="n"
                >{{ n }} {{ SCALE[n] }}<template v-if="n < 5"> · </template></template
              >
            </p>
          </section>

          <section :class="[card, 'p-5']" aria-labelledby="by-program">
            <h2 id="by-program" class="font-serif text-lg font-semibold text-ink">By program</h2>
            <table class="mt-3 w-full text-left text-sm">
              <caption class="sr-only">
                Evaluations by program
              </caption>
              <thead :class="label">
                <tr class="border-b border-ink/10">
                  <th scope="col" class="py-2 pr-3 font-medium">Program</th>
                  <th scope="col" class="py-2 pr-3 font-medium">Evaluated</th>
                  <th scope="col" class="py-2 pr-3 font-medium">Avg</th>
                  <th scope="col" class="py-2 font-medium">Rating</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-ink/10">
                <tr v-if="!store.summary.programs.length">
                  <td colspan="4" class="py-4 text-center text-ink/70">No deployments yet.</td>
                </tr>
                <tr v-for="p in store.summary.programs" :key="p.program.code">
                  <td
                    class="py-2 pr-3 font-mono text-xs font-medium text-ink"
                    :title="p.program.name"
                  >
                    {{ p.program.code }}
                  </td>
                  <td class="py-2 pr-3 text-ink">
                    {{ p.evaluated }} / {{ p.total_active_or_completed }}
                  </td>
                  <td class="py-2 pr-3 font-mono text-xs text-ink">
                    {{ (p.avg_final ?? p.avg_partner_mean)?.toFixed(2) ?? '—' }}
                  </td>
                  <td class="py-2 text-ink">{{ p.descriptor ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>
      </template>
    </template>

    <!-- Review -->
    <div v-else class="grid items-start gap-5 lg:grid-cols-[290px_1fr]">
      <nav aria-label="Interns" :class="[card, 'overflow-hidden']" :aria-busy="store.loading">
        <p v-if="!store.rows.length" class="px-4 py-6 text-sm text-ink/70">
          {{ store.loading ? 'Loading…' : 'No interns match.' }}
        </p>
        <ul v-else class="divide-y divide-ink/10">
          <li v-for="row in store.rows" :key="row.deployment_id">
            <button
              type="button"
              :aria-pressed="store.selectedDeploymentId === row.deployment_id"
              class="block w-full px-4 py-3 text-left hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
              :class="
                store.selectedDeploymentId === row.deployment_id
                  ? 'bg-brand/5 shadow-[inset_3px_0_0_var(--color-brand)]'
                  : ''
              "
              @click="store.select(row.deployment_id)"
            >
              <span class="flex items-baseline justify-between gap-2">
                <span class="text-sm font-medium text-ink">{{ row.student.name }}</span>
                <span
                  v-if="row.evaluation.partner_mean !== null"
                  class="font-mono text-xs text-ink"
                  >{{ row.evaluation.partner_mean.toFixed(2) }}</span
                >
              </span>
              <span class="block truncate text-xs text-ink/70"
                >{{ row.student.program ?? '—' }} · {{ row.partner.company_name }}</span
              >
              <span
                class="mt-0.5 block text-xs font-medium"
                :class="STATUS_CLASS[row.evaluation.status]"
              >
                {{ STATUS_LABEL[row.evaluation.status] }}
              </span>
            </button>
          </li>
        </ul>
        <div
          v-if="store.meta && store.meta.last_page > 1"
          class="flex items-center justify-between border-t border-ink/10 px-3 py-2 text-xs"
        >
          <button
            type="button"
            :disabled="store.meta.current_page <= 1"
            class="rounded border border-ink/20 px-2 py-1 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand"
            @click="store.setPage(store.meta.current_page - 1)"
          >
            Previous
          </button>
          <span class="font-mono text-ink/70"
            >{{ store.meta.current_page }} / {{ store.meta.last_page }}</span
          >
          <button
            type="button"
            :disabled="store.meta.current_page >= store.meta.last_page"
            class="rounded border border-ink/20 px-2 py-1 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand"
            @click="store.setPage(store.meta.current_page + 1)"
          >
            Next
          </button>
        </div>
      </nav>

      <section :class="[card, 'min-h-64']" aria-live="polite">
        <p v-if="!store.selectedRow" class="px-5 py-10 text-center text-sm text-ink/70">
          Select an intern to see their evaluation.
        </p>

        <div
          v-else-if="['not_started', 'draft'].includes(store.selectedRow.evaluation.status)"
          class="p-5"
        >
          <h2 class="font-serif text-xl font-semibold text-ink">
            {{ store.selectedRow.student.name }}
          </h2>
          <p class="mt-3 rounded-md border border-accent bg-accent/10 px-4 py-3 text-sm text-ink">
            <template v-if="store.selectedRow.evaluation.status === 'draft'">
              {{ store.selectedRow.partner.company_name }} has started a draft but hasn’t submitted
              it yet.
            </template>
            <template v-else
              >{{ store.selectedRow.partner.company_name }} hasn’t started this evaluation
              yet.</template
            >
            ({{ formatHours(store.selectedRow.progress.signed_hours) }} of
            {{ store.selectedRow.progress.required_hours }} hours signed)
          </p>
        </div>

        <p
          v-else-if="store.loadingDetail || !store.detail"
          class="px-5 py-10 text-center text-sm text-ink/70"
        >
          Loading…
        </p>

        <template v-else>
          <header class="border-b border-ink/10 px-5 py-4">
            <h2 class="font-serif text-xl font-semibold text-ink">
              {{ store.detail.deployment?.student.name }}
            </h2>
            <p class="font-mono text-[11px] tracking-[0.06em] text-ink/70 uppercase">
              {{ store.detail.deployment?.student.program }} ·
              {{ store.detail.deployment?.partner.company_name }} · {{ store.detail.type }}
            </p>
          </header>

          <p
            v-if="store.detail.status === 'returned'"
            class="mx-5 mt-4 rounded-md border-l-[3px] border-accent bg-accent/10 px-3 py-2 text-sm text-ink"
          >
            <span class="font-medium text-accent-ink">Returned to the partner:</span>
            {{ store.detail.return_note }}
          </p>

          <div class="grid gap-6 p-5 xl:grid-cols-2">
            <div>
              <p :class="label">Industry partner rating</p>
              <p class="mt-1">
                <span class="font-mono text-2xl font-medium text-ink">{{
                  store.detail.partner_mean?.toFixed(2)
                }}</span>
                <span class="ml-1.5 text-sm text-ink/75">{{
                  store.detail.partner_descriptor
                }}</span>
              </p>
              <div class="mt-3"><CriterionBars :rows="store.detail.ratings" /></div>
              <p :class="[label, 'mt-4']">Supervisor’s comment</p>
              <p class="mt-1 text-sm whitespace-pre-line text-ink">
                {{ store.detail.comment || '—' }}
              </p>
            </div>

            <div class="space-y-4">
              <div>
                <p :class="label">Your rating</p>
                <RatingScale
                  v-model="myRating"
                  :criteria="[{ key: 'coordinator', label: COORDINATOR_CRITERION }]"
                  :readonly="store.detail.status !== 'submitted'"
                />
                <label for="coordinator-note" class="mt-2 block text-sm text-ink/75"
                  >Note (optional, not shown to the student)</label
                >
                <textarea
                  id="coordinator-note"
                  v-model="myNote"
                  rows="2"
                  maxlength="1000"
                  :readonly="store.detail.status !== 'submitted'"
                  class="mt-1 block w-full rounded-md border border-ink/25 px-3 py-2 text-sm text-ink read-only:bg-canvas focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
                />
              </div>

              <div class="rounded-md bg-brand/5 px-4 py-3">
                <p :class="label">Final rating · 70% partner + 30% you</p>
                <p class="mt-1">
                  <span class="font-mono text-2xl font-medium text-brand">{{
                    liveFinal?.toFixed(2) ?? '—'
                  }}</span>
                  <span v-if="liveFinal !== null" class="ml-1.5 text-sm text-ink/75">{{
                    descriptor(liveFinal)
                  }}</span>
                </p>
              </div>

              <div
                v-if="store.detail.status === 'submitted'"
                class="flex flex-wrap items-center gap-3"
              >
                <button
                  type="button"
                  :disabled="myRating.coordinator == null || store.working"
                  class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50"
                  @click="store.markReviewed(myRating.coordinator!, myNote.trim() || null)"
                >
                  {{ store.working ? 'Saving…' : 'Mark as reviewed' }}
                </button>
                <button
                  type="button"
                  class="text-sm font-medium text-accent-ink underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand"
                  @click="returning = true"
                >
                  Return to partner
                </button>
              </div>
              <div v-else-if="store.detail.status === 'reviewed'" class="flex items-center gap-3">
                <span class="text-sm font-medium text-brand">✓ Reviewed</span>
                <button
                  v-if="store.detail.deployment?.status !== 'completed'"
                  type="button"
                  :disabled="store.working"
                  class="text-sm font-medium text-ink/75 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand"
                  @click="store.reopen()"
                >
                  Reopen
                </button>
              </div>
            </div>
          </div>
        </template>
      </section>
    </div>

    <ConfirmDialog
      v-if="returning && store.detail"
      title="Return to the partner?"
      :message="`${store.detail.deployment?.partner.company_name} can edit and resubmit the evaluation. Your note is shown above their form.`"
      confirm-label="Return evaluation"
      note-label="What should the partner change?"
      :busy="store.working"
      :error="returnError"
      @cancel="returning = false"
      @confirm="sendReturn"
    />
  </div>
</template>
