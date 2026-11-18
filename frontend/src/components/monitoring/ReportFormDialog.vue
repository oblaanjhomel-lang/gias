<script setup lang="ts">
import { computed, nextTick, reactive, ref, useId } from 'vue'
import * as studentApi from '@/api/studentDeployment'
import { useDialog } from '@/composables/useDialog'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { addDays, formatHours, formatPeriod, parseDate } from '@/lib/format'
import DailyHoursGrid from '@/components/monitoring/DailyHoursGrid.vue'
import ReportStatusPill from '@/components/monitoring/ReportStatusPill.vue'
import {
  DAY_LABELS,
  DAYS,
  MAX_WEEK_HOURS,
  type DailyHours,
  type ReportWeek,
  type WeeklyReport,
} from '@/types/monitoring'

/**
 * New / edit / view a weekly report (640px dialog). The server computes the period dates
 * and the total; the week select only offers weeks that have started.
 */
const props = defineProps<{
  weeks: ReportWeek[]
  report: WeeklyReport | null
  defaultWeek: number | null
  /** The deployment is no longer active: view only. */
  locked?: boolean
}>()
const emit = defineEmits<{ close: []; saved: [message: string] }>()

const uid = useId()
const dialog = ref<HTMLElement | null>(null)

const editable = computed(
  () =>
    !props.locked && (props.report === null || ['draft', 'returned'].includes(props.report.status)),
)

const week = ref<number>(
  props.report?.week_number ?? props.defaultWeek ?? props.weeks[0]?.week_number ?? 1,
)
const hours = reactive<DailyHours>({
  mon: 0,
  tue: 0,
  wed: 0,
  thu: 0,
  fri: 0,
  sat: 0,
  sun: 0,
  ...props.report?.daily_hours,
})
const tasks = ref(props.report?.tasks ?? '')
const learnings = ref(props.report?.learnings ?? '')

const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const saving = ref(false)
const confirming = ref(false)
const confirmButton = ref<HTMLButtonElement | null>(null)

/** Weeks offered in the select: started weeks with no report yet, or this report's week. */
const selectableWeeks = computed(() =>
  props.weeks.filter((w) => w.report_id === null || w.week_number === props.report?.week_number),
)

const period = computed(() => {
  if (props.report) return { start: props.report.period_start, end: props.report.period_end }
  const w = props.weeks.find((x) => x.week_number === week.value)
  return w ? { start: w.period_start, end: w.period_end } : null
})

const total = computed(() => DAYS.reduce((sum, d) => sum + (Number(hours[d]) || 0), 0))
const overLimit = computed(() => total.value > MAX_WEEK_HOURS)

const dayDate = (i: number) =>
  period.value ? parseDate(addDays(period.value.start, i)).getDate() : ''

function payload() {
  return {
    daily_hours: Object.fromEntries(DAYS.map((d) => [d, Number(hours[d]) || 0])) as DailyHours,
    tasks: tasks.value.trim(),
    learnings: learnings.value.trim() || null,
  }
}

async function save(): Promise<WeeklyReport | null> {
  errors.value = {}
  formError.value = null
  try {
    return props.report
      ? await studentApi.updateReport(props.report.id, payload())
      : await studentApi.saveDraft(week.value, payload())
  } catch (e) {
    const fields = validationErrors(e)
    if (fields) {
      errors.value = fields
      formError.value = fields.status ?? fields.deployment ?? null
      confirming.value = false
    } else {
      formError.value = errorMessage(e)
    }
    return null
  }
}

async function saveDraft(): Promise<void> {
  saving.value = true
  const saved = await save()
  saving.value = false
  if (saved)
    emit(
      'saved',
      `Saved week ${saved.week_number} as a ${saved.status === 'returned' ? 'returned report' : 'draft'}.`,
    )
}

async function askSubmit(): Promise<void> {
  confirming.value = true
  await nextTick()
  confirmButton.value?.focus()
}

async function submit(): Promise<void> {
  saving.value = true
  const saved = await save()
  if (saved) {
    try {
      await studentApi.submitReport(saved.id)
      emit('saved', `Week ${saved.week_number} was submitted for your supervisor’s signature.`)
    } catch (e) {
      formError.value = errorMessage(e)
      confirming.value = false
    }
  }
  saving.value = false
}

useDialog(dialog, {
  onClose: () => {
    if (!saving.value) emit('close')
  },
})

const fieldError = (key: string) => errors.value[key]
const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4">
      <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${uid}-title`"
        class="flex max-h-[calc(100vh-2rem)] w-full max-w-[640px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
      >
        <header class="flex items-start justify-between gap-3 border-b border-ink/10 px-6 py-4">
          <div>
            <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
              {{ report ? `Week ${report.week_number} report` : 'New weekly report' }}
            </h2>
            <p v-if="period" class="mt-0.5 font-mono text-xs text-ink/70">
              Week {{ week }} · {{ formatPeriod(period.start, period.end) }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <ReportStatusPill v-if="report" :status="report.status" />
            <button
              type="button"
              aria-label="Close"
              class="grid size-9 place-items-center rounded-md text-2xl leading-none text-ink/70 hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-brand"
              @click="emit('close')"
            >
              <span aria-hidden="true">×</span>
            </button>
          </div>
        </header>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <div
            v-if="formError"
            role="alert"
            class="rounded-md border border-red-700/30 bg-red-50 px-3 py-2.5 text-sm text-red-800"
          >
            {{ formError }}
          </div>
          <div
            v-if="report?.return_note"
            class="rounded-md border-l-[3px] border-accent bg-accent/10 px-3 py-2 text-sm text-ink"
          >
            <p :class="label">Returned by your supervisor</p>
            <p class="mt-0.5">{{ report.return_note }}</p>
          </div>

          <!-- Read-only (submitted / signed) -->
          <DailyHoursGrid v-if="report && !editable" :report="report" />

          <template v-else>
            <div v-if="!report">
              <label :for="`${uid}-week`" class="block text-sm font-medium text-ink">Week</label>
              <select
                :id="`${uid}-week`"
                v-model.number="week"
                :aria-invalid="fieldError('week_number') ? 'true' : undefined"
                class="mt-1.5 block h-11 w-full rounded-md border border-ink/25 bg-white px-3 text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
              >
                <option v-for="w in selectableWeeks" :key="w.week_number" :value="w.week_number">
                  Week {{ w.week_number }} · {{ formatPeriod(w.period_start, w.period_end) }}
                </option>
              </select>
              <p v-if="fieldError('week_number')" class="mt-1.5 text-sm text-red-700">
                {{ fieldError('week_number') }}
              </p>
            </div>

            <fieldset>
              <legend :class="label">Hours per day</legend>
              <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
                <div v-for="(day, i) in DAYS" :key="day">
                  <label :for="`${uid}-${day}`" class="block text-xs text-ink/75">
                    {{ DAY_LABELS[day] }} {{ dayDate(i) }}
                  </label>
                  <input
                    :id="`${uid}-${day}`"
                    v-model.number="hours[day]"
                    type="number"
                    min="0"
                    max="12"
                    step="0.5"
                    inputmode="decimal"
                    :aria-invalid="fieldError(`daily_hours.${day}`) ? 'true' : undefined"
                    class="mt-1 block h-11 w-full rounded-md border px-2 text-center font-mono text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
                    :class="
                      fieldError(`daily_hours.${day}`)
                        ? 'border-red-700 focus-visible:outline-red-700'
                        : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
                    "
                  />
                  <p v-if="fieldError(`daily_hours.${day}`)" class="mt-1 text-xs text-red-700">
                    {{ fieldError(`daily_hours.${day}`) }}
                  </p>
                </div>
              </div>
              <p class="mt-2 text-sm" aria-live="polite">
                Total:
                <span
                  class="font-mono font-medium"
                  :class="overLimit ? 'text-red-700' : 'text-ink'"
                >
                  {{ formatHours(total) }} h
                </span>
                <span v-if="overLimit" class="text-red-700">
                  — a week can have at most {{ MAX_WEEK_HOURS }} hours.</span
                >
              </p>
              <p v-if="fieldError('daily_hours')" class="mt-1 text-sm text-red-700">
                {{ fieldError('daily_hours') }}
              </p>
            </fieldset>

            <div>
              <label :for="`${uid}-tasks`" class="block text-sm font-medium text-ink">Tasks</label>
              <textarea
                :id="`${uid}-tasks`"
                v-model="tasks"
                rows="5"
                maxlength="2000"
                required
                :aria-invalid="fieldError('tasks') ? 'true' : undefined"
                :aria-describedby="`${uid}-tasks-count`"
                class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
                :class="
                  fieldError('tasks')
                    ? 'border-red-700 focus-visible:outline-red-700'
                    : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
                "
              />
              <div class="mt-1 flex justify-between gap-3 text-xs">
                <p class="text-red-700">{{ fieldError('tasks') }}</p>
                <p :id="`${uid}-tasks-count`" class="shrink-0 font-mono text-ink/70">
                  {{ tasks.trim().length }}/2000<span class="sr-only"> characters, minimum 20</span>
                </p>
              </div>
            </div>

            <div>
              <label :for="`${uid}-learnings`" class="block text-sm font-medium text-ink"
                >Learnings (optional)</label
              >
              <textarea
                :id="`${uid}-learnings`"
                v-model="learnings"
                rows="3"
                maxlength="2000"
                class="mt-1.5 block w-full rounded-md border border-ink/25 px-3 py-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
              />
              <p v-if="fieldError('learnings')" class="mt-1 text-xs text-red-700">
                {{ fieldError('learnings') }}
              </p>
            </div>
          </template>
        </div>

        <footer class="border-t border-ink/10 bg-canvas/60 px-6 py-4">
          <div
            v-if="confirming"
            role="alertdialog"
            :aria-labelledby="`${uid}-confirm`"
            class="flex flex-wrap items-center justify-end gap-3"
          >
            <p :id="`${uid}-confirm`" class="mr-auto text-sm text-ink">
              Your supervisor will review it. You can’t edit it unless it’s returned.
            </p>
            <button
              type="button"
              :disabled="saving"
              class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              @click="confirming = false"
            >
              Cancel
            </button>
            <button
              ref="confirmButton"
              type="button"
              :disabled="saving"
              class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-70"
              @click="submit"
            >
              {{ saving ? 'Submitting…' : 'Yes, submit' }}
            </button>
          </div>
          <div v-else class="flex flex-wrap justify-end gap-3">
            <button
              type="button"
              class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              @click="emit('close')"
            >
              {{ editable ? 'Cancel' : 'Close' }}
            </button>
            <template v-if="editable">
              <button
                type="button"
                :disabled="saving || overLimit"
                class="h-11 rounded-md border border-brand px-4 text-sm font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
                @click="saveDraft"
              >
                {{ saving ? 'Saving…' : 'Save draft' }}
              </button>
              <button
                type="button"
                :disabled="saving || overLimit"
                class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
                @click="askSubmit"
              >
                Submit for signature
              </button>
            </template>
          </div>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
