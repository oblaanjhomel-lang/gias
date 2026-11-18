<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue'
import * as partnerApi from '@/api/partnerReports'
import DailyHoursGrid from '@/components/monitoring/DailyHoursGrid.vue'
import ReportStatusPill from '@/components/monitoring/ReportStatusPill.vue'
import { useDialog } from '@/composables/useDialog'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { formatPeriod } from '@/lib/format'
import type { WeeklyReport } from '@/types/monitoring'

/** Partner: review a weekly report; countersign or return it (submitted reports only). */
const props = defineProps<{ report: WeeklyReport }>()
const emit = defineEmits<{ close: []; done: [message: string] }>()

const uid = useId()
const dialog = ref<HTMLElement | null>(null)
const noteField = ref<HTMLTextAreaElement | null>(null)

const canAct = computed(() => props.report.status === 'submitted')
const returning = ref(false)
const note = ref('')
const noteError = ref<string | undefined>()
const error = ref<string | null>(null)
const busy = ref(false)

const who = computed(() => props.report.intern?.name ?? 'the intern')

async function sign(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await partnerApi.signReport(props.report.id)
    emit('done', `Countersigned week ${props.report.week_number} for ${who.value}.`)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

async function startReturn(): Promise<void> {
  returning.value = true
  await nextTick()
  noteField.value?.focus()
}

async function sendReturn(): Promise<void> {
  noteError.value = undefined
  if (note.value.trim().length < 10) {
    noteError.value = 'Write at least 10 characters so the intern knows what to fix.'
    noteField.value?.focus()
    return
  }
  busy.value = true
  error.value = null
  try {
    await partnerApi.returnReport(props.report.id, note.value.trim())
    emit('done', `Returned week ${props.report.week_number} to ${who.value} with your note.`)
  } catch (e) {
    const fields = validationErrors(e)
    if (fields?.note) noteError.value = fields.note
    else error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

useDialog(dialog, {
  onClose: () => {
    if (!busy.value) emit('close')
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
        class="flex max-h-[calc(100vh-2rem)] w-full max-w-[640px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
      >
        <header class="flex items-start justify-between gap-3 border-b border-ink/10 px-6 py-4">
          <div>
            <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
              {{ report.intern?.name ?? 'Intern' }} · week {{ report.week_number }}
            </h2>
            <p class="mt-0.5 font-mono text-xs text-ink/70">
              {{ formatPeriod(report.period_start, report.period_end) }}
              <template v-if="report.intern?.program"> · {{ report.intern.program }}</template>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <ReportStatusPill :status="report.status" />
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

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
          <div
            v-if="error"
            role="alert"
            class="rounded-md border border-red-700/30 bg-red-50 px-3 py-2.5 text-sm text-red-800"
          >
            {{ error }}
          </div>
          <DailyHoursGrid :report="report" />
          <p
            v-if="report.return_note && report.status === 'returned'"
            class="rounded-md border-l-[3px] border-accent bg-accent/10 px-3 py-2 text-sm text-ink"
          >
            <span class="font-medium text-accent-ink">Your note:</span> {{ report.return_note }}
          </p>

          <div v-if="returning">
            <label :for="`${uid}-note`" class="block text-sm font-medium text-ink"
              >What should the intern fix?</label
            >
            <textarea
              :id="`${uid}-note`"
              ref="noteField"
              v-model="note"
              rows="3"
              maxlength="500"
              required
              :aria-invalid="noteError ? 'true' : undefined"
              :aria-describedby="`${uid}-note-count`"
              class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
              :class="
                noteError
                  ? 'border-red-700 focus-visible:outline-red-700'
                  : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
              "
            />
            <div class="mt-1 flex justify-between gap-3 text-xs">
              <p class="text-red-700">{{ noteError }}</p>
              <p :id="`${uid}-note-count`" class="shrink-0 font-mono text-ink/70">
                {{ note.trim().length }}/500<span class="sr-only"> characters, minimum 10</span>
              </p>
            </div>
          </div>
        </div>

        <footer
          class="flex flex-wrap justify-end gap-3 border-t border-ink/10 bg-canvas/60 px-6 py-4"
        >
          <button
            type="button"
            :disabled="busy"
            class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            @click="returning ? (returning = false) : emit('close')"
          >
            {{ returning ? 'Cancel' : 'Close' }}
          </button>
          <template v-if="canAct">
            <button
              v-if="returning"
              type="button"
              :disabled="busy"
              class="h-11 rounded-md border border-accent-ink px-4 text-sm font-medium text-accent-ink hover:bg-accent/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60"
              @click="sendReturn"
            >
              {{ busy ? 'Returning…' : 'Return to intern' }}
            </button>
            <template v-else>
              <button
                type="button"
                :disabled="busy"
                class="h-11 rounded-md border border-ink/25 px-4 text-sm font-medium text-ink hover:border-accent-ink hover:text-accent-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                @click="startReturn"
              >
                Return
              </button>
              <button
                type="button"
                :disabled="busy"
                class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-70"
                @click="sign"
              >
                {{ busy ? 'Signing…' : 'Countersign' }}
              </button>
            </template>
          </template>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
