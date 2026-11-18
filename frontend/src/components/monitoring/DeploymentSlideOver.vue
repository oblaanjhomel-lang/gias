<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue'
import DailyHoursGrid from '@/components/monitoring/DailyHoursGrid.vue'
import HoursBar from '@/components/monitoring/HoursBar.vue'
import ReportStatusPill from '@/components/monitoring/ReportStatusPill.vue'
import { useDialog } from '@/composables/useDialog'
import { formatDate, formatHours, formatPeriod } from '@/lib/format'
import { useMonitoringStore } from '@/stores/monitoring'

/** Right-side panel for one deployment: progress, reports, complete / withdraw. Mount with v-if. */
defineProps<{ isAdmin: boolean }>()
const emit = defineEmits<{ close: [] }>()

const store = useMonitoringStore()
const uid = useId()
const panel = ref<HTMLElement | null>(null)

const d = computed(() => store.detail)
const isActive = computed(() => d.value?.status === 'active')
const missing = computed(() => d.value?.progress.missing_hours ?? 0)

const expanded = ref<Set<number>>(new Set())
function toggle(id: number): void {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

// Inline confirmations (kept inside this dialog rather than stacking a second one)
type Confirm = 'complete' | 'force' | 'withdraw' | null
const confirming = ref<Confirm>(null)
const reason = ref('')
const actionError = ref<string | null>(null)
const confirmButton = ref<HTMLButtonElement | null>(null)

async function ask(kind: Confirm): Promise<void> {
  confirming.value = kind
  reason.value = ''
  actionError.value = null
  await nextTick()
  if (kind === 'force') document.getElementById(`${uid}-reason`)?.focus()
  else confirmButton.value?.focus()
}

async function confirm(): Promise<void> {
  actionError.value = null
  if (confirming.value === 'force' && reason.value.trim().length < 10) {
    actionError.value = 'Give a reason of at least 10 characters.'
    return
  }
  const error =
    confirming.value === 'withdraw'
      ? await store.withdraw()
      : await store.complete(
          confirming.value === 'force' ? { force: true, note: reason.value.trim() } : {},
        )
  if (error) actionError.value = error
  else confirming.value = null
}

useDialog(panel, {
  onClose: () => {
    if (!store.working) emit('close')
  },
})

const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 bg-ink/40" @click.self="!store.working && emit('close')">
      <aside
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${uid}-title`"
        class="absolute inset-y-0 right-0 flex w-full max-w-[480px] flex-col bg-white shadow-2xl"
      >
        <header class="flex items-start justify-between gap-3 border-b border-ink/10 px-5 py-4">
          <div>
            <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
              {{ d?.student.name ?? 'Loading…' }}
            </h2>
            <p v-if="d" class="font-mono text-[11px] text-ink/70 uppercase">
              {{ d.student.program?.code ?? '—' }} · {{ d.student.student_number }}
            </p>
          </div>
          <button
            type="button"
            aria-label="Close"
            class="grid size-9 place-items-center rounded-md text-2xl leading-none text-ink/70 hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-brand"
            @click="emit('close')"
          >
            <span aria-hidden="true">×</span>
          </button>
        </header>

        <p v-if="store.loadingDetail" class="px-5 py-8 text-sm text-ink/70">Loading…</p>

        <div v-else-if="d" class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
          <section>
            <h3 :class="label">Host training establishment</h3>
            <p class="mt-0.5 font-medium text-ink">{{ d.partner.company_name }}</p>
            <p class="text-sm text-ink/75">{{ d.partner.city }}, {{ d.partner.province }}</p>
            <p class="text-sm text-ink/75">
              Supervisor: {{ d.partner.contact_person }} · {{ d.partner.contact_number ?? '—' }}
            </p>
            <p class="mt-1 text-xs text-ink/70">Started {{ formatDate(d.start_date) }}</p>
          </section>

          <section>
            <h3 :class="label">Progress</h3>
            <div class="mt-1"><HoursBar :progress="d.progress" /></div>
            <p class="mt-1 text-xs text-ink/70">
              {{ d.progress.percent }}% · {{ formatHours(d.progress.pending_hours) }} h awaiting
              signature · {{ d.progress.weeks_elapsed }} weeks elapsed
            </p>
            <p v-if="d.progress.is_overdue" class="mt-2 text-sm text-accent-ink">
              No report in over 14 days.
            </p>
            <p v-if="d.status === 'completed'" class="mt-2 text-sm text-brand">
              Completed {{ d.completed_at ? formatDate(d.completed_at) : ''
              }}<template v-if="d.completed_by"> by {{ d.completed_by }}</template
              >.
              <span v-if="d.completion_note" class="block text-ink/75"
                >Note: {{ d.completion_note }}</span
              >
            </p>
            <p v-if="d.status === 'withdrawn'" class="mt-2 text-sm text-ink/75">Withdrawn.</p>
          </section>

          <section>
            <h3 :class="label">Weekly reports ({{ d.reports.length }})</h3>
            <p v-if="!d.reports.length" class="mt-1 text-sm text-ink/70">No reports yet.</p>
            <ul v-else class="mt-1 divide-y divide-ink/10 border-y border-ink/10">
              <li v-for="report in d.reports" :key="report.id">
                <button
                  type="button"
                  :aria-expanded="expanded.has(report.id)"
                  :aria-controls="`${uid}-r-${report.id}`"
                  class="flex w-full items-center justify-between gap-3 py-2 text-left text-sm hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
                  @click="toggle(report.id)"
                >
                  <span>
                    <span class="font-medium text-ink">Week {{ report.week_number }}</span>
                    <span class="text-ink/70">
                      · {{ formatPeriod(report.period_start, report.period_end) }}</span
                    >
                  </span>
                  <span class="flex items-center gap-2">
                    <span class="font-mono text-xs text-ink"
                      >{{ formatHours(report.total_hours) }} h</span
                    >
                    <ReportStatusPill :status="report.status" />
                  </span>
                </button>
                <div v-if="expanded.has(report.id)" :id="`${uid}-r-${report.id}`" class="pb-3">
                  <DailyHoursGrid :report="report" />
                  <p v-if="report.return_note" class="mt-2 text-xs text-accent-ink">
                    Return note: {{ report.return_note }}
                  </p>
                </div>
              </li>
            </ul>
          </section>
        </div>

        <!-- Actions -->
        <footer
          v-if="d && isActive"
          class="space-y-3 border-t border-ink/10 bg-canvas/60 px-5 py-4"
        >
          <div
            v-if="actionError"
            role="alert"
            class="rounded-md border border-red-700/30 bg-red-50 px-3 py-2 text-sm text-red-800"
          >
            {{ actionError }}
          </div>

          <div
            v-if="confirming"
            role="alertdialog"
            :aria-labelledby="`${uid}-confirm`"
            class="space-y-3"
          >
            <p :id="`${uid}-confirm`" class="text-sm text-ink">
              <template v-if="confirming === 'complete'"
                >Mark {{ d.student.name }}’s OJT as completed?</template
              >
              <template v-else-if="confirming === 'force'">
                Complete anyway with {{ formatHours(missing) }} hours still missing? Give a reason;
                it is kept with the record.
              </template>
              <template v-else>
                Withdraw {{ d.student.name }} from {{ d.partner.company_name }}? The slot opens
                again; reports are kept.
              </template>
            </p>
            <div v-if="confirming === 'force'">
              <label :for="`${uid}-reason`" class="sr-only">Reason</label>
              <textarea
                :id="`${uid}-reason`"
                v-model="reason"
                rows="2"
                maxlength="500"
                class="block w-full rounded-md border border-ink/25 px-3 py-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
              />
              <p class="mt-1 text-right font-mono text-xs text-ink/70">
                {{ reason.trim().length }}/500
              </p>
            </div>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                :disabled="store.working"
                class="h-10 rounded-md px-3 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-brand"
                @click="confirming = null"
              >
                Cancel
              </button>
              <button
                ref="confirmButton"
                type="button"
                :disabled="store.working"
                class="h-10 rounded-md px-4 text-sm font-medium text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-70"
                :class="
                  confirming === 'withdraw'
                    ? 'bg-red-700 hover:bg-red-800 focus-visible:outline-red-700'
                    : 'bg-brand hover:bg-brand-dark focus-visible:outline-brand'
                "
                @click="confirm"
              >
                {{
                  store.working
                    ? 'Working…'
                    : confirming === 'withdraw'
                      ? 'Withdraw placement'
                      : 'Mark completed'
                }}
              </button>
            </div>
          </div>

          <div v-else class="space-y-2">
            <button
              type="button"
              :disabled="missing > 0"
              class="h-11 w-full rounded-md bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50"
              @click="ask('complete')"
            >
              Mark completed
            </button>
            <p v-if="missing > 0" class="text-center text-xs text-ink/75">
              {{ formatHours(missing) }} countersigned hours still missing.
              <button
                v-if="isAdmin"
                type="button"
                class="font-medium text-brand underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand"
                @click="ask('force')"
              >
                Complete anyway
              </button>
            </p>
            <button
              type="button"
              class="h-10 w-full rounded-md border border-red-700/60 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
              @click="ask('withdraw')"
            >
              Withdraw placement
            </button>
          </div>
        </footer>
      </aside>
    </div>
  </Teleport>
</template>
