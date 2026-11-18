<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as studentApi from '@/api/studentDeployment'
import ReportFormDialog from '@/components/monitoring/ReportFormDialog.vue'
import ReportStatusPill from '@/components/monitoring/ReportStatusPill.vue'
import { errorMessage } from '@/lib/apiErrors'
import { formatDate, formatHours, formatPeriod } from '@/lib/format'
import type { DeploymentDetail, WeeklyReport } from '@/types/monitoring'

/** /student/deployment: "My deployment". */
const deployment = ref<DeploymentDetail | null>(null)
const neverPlaced = ref(false)
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)

async function load(): Promise<void> {
  try {
    deployment.value = await studentApi.getDeployment()
    neverPlaced.value = deployment.value === null
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your deployment.')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const progress = computed(() => deployment.value?.progress ?? null)
const weeks = computed(() => deployment.value?.weeks ?? [])
const isActive = computed(() => deployment.value?.status === 'active')

/** The first started week with no report yet. */
const nextWeek = computed(() => weeks.value.find((w) => w.report_id === null)?.week_number ?? null)

const STATUS_PILL: Record<string, string> = {
  active: 'bg-brand/10 text-brand',
  overdue: 'bg-accent/20 text-accent-ink',
  completed: 'bg-brand-dark text-white',
  withdrawn: 'bg-ink/[0.07] text-ink/75',
}

// Dialog
const dialogOpen = ref(false)
const editing = ref<WeeklyReport | null>(null)

function openNew(): void {
  editing.value = null
  dialogOpen.value = true
}

function openReport(report: WeeklyReport): void {
  editing.value = report
  dialogOpen.value = true
}

async function onSaved(message: string): Promise<void> {
  dialogOpen.value = false
  notice.value = message
  await load()
}

const card = 'rounded-[9px] border border-ink/10 bg-white p-5'
const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <div class="space-y-5">
    <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">My deployment</h1>

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

    <section v-else-if="neverPlaced" :class="[card, 'py-10 text-center']">
      <p class="font-serif text-lg font-semibold text-ink">You haven’t been placed yet.</p>
      <p class="mt-1 text-sm text-ink/70">
        Your OJT Coordinator will assign your Industry Partner.
      </p>
    </section>

    <template v-else-if="deployment && progress">
      <div class="grid gap-5 lg:grid-cols-2">
        <!-- Placement -->
        <section aria-labelledby="placement-title" :class="card">
          <div class="flex items-start justify-between gap-3">
            <h2 id="placement-title" class="font-serif text-lg font-semibold text-ink">
              {{ deployment.partner.company_name }}
            </h2>
            <span
              class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
              :class="STATUS_PILL[progress.status]"
            >
              {{ progress.status === 'overdue' ? 'Active · overdue' : progress.status }}
            </span>
          </div>
          <p class="mt-0.5 text-sm text-ink/75">
            {{ deployment.partner.address }}, {{ deployment.partner.city }},
            {{ deployment.partner.province }}
          </p>
          <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt :class="label">On-site supervisor</dt>
              <dd class="mt-0.5 text-ink">
                {{ deployment.partner.contact_person }}<br />
                <span class="text-ink/75">{{ deployment.partner.contact_number ?? '—' }}</span>
              </dd>
            </div>
            <div>
              <dt :class="label">OJT Coordinator</dt>
              <dd class="mt-0.5 text-ink">
                <template v-if="deployment.coordinator">
                  {{ deployment.coordinator.name }}<br />
                  <a
                    :href="`mailto:${deployment.coordinator.email}`"
                    class="break-all text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-brand"
                  >
                    {{ deployment.coordinator.email }}
                  </a>
                </template>
                <span v-else class="text-ink/70">Not assigned yet</span>
              </dd>
            </div>
            <div>
              <dt :class="label">Start date</dt>
              <dd class="mt-0.5 text-ink">{{ formatDate(deployment.start_date) }}</dd>
            </div>
          </dl>
        </section>

        <!-- Progress -->
        <section aria-labelledby="progress-title" :class="card">
          <h2 id="progress-title" :class="label">Progress</h2>
          <p class="mt-1 font-serif text-3xl font-semibold text-ink">
            {{ formatHours(progress.signed_hours) }} of {{ progress.required_hours }} hours
          </p>
          <div
            class="mt-3 h-2.5 overflow-hidden rounded-full bg-ink/10"
            role="progressbar"
            :aria-valuenow="progress.percent"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="Countersigned hours"
          >
            <div
              class="h-full rounded-full"
              :class="
                progress.percent >= 100
                  ? 'bg-brand-dark'
                  : progress.is_overdue
                    ? 'bg-accent'
                    : 'bg-brand'
              "
              :style="{ width: `${progress.percent}%` }"
            />
          </div>
          <p class="mt-2 text-sm text-ink/75">
            {{ formatHours(progress.pending_hours) }} hours awaiting signature ·
            {{ progress.weeks_elapsed }}
            {{ progress.weeks_elapsed === 1 ? 'week' : 'weeks' }} elapsed
          </p>
          <p
            v-if="progress.is_overdue"
            class="mt-3 rounded-md border border-accent bg-accent/10 px-3 py-2 text-sm text-accent-ink"
          >
            Your last report was over 14 days ago. Submit your missing weeks.
          </p>
        </section>
      </div>

      <!-- Reports -->
      <section aria-labelledby="reports-title" :class="card">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 id="reports-title" class="font-serif text-lg font-semibold text-ink">
            Weekly reports
          </h2>
          <button
            v-if="isActive"
            type="button"
            :disabled="nextWeek === null"
            :title="
              nextWeek === null ? 'Every week that has started already has a report.' : undefined
            "
            class="h-10 rounded-md bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
            @click="openNew"
          >
            New weekly report
          </button>
        </div>

        <div class="mt-3 overflow-x-auto">
          <table class="w-full text-left text-sm">
            <caption class="sr-only">
              Weekly reports
            </caption>
            <thead class="font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
              <tr class="border-b border-ink/10">
                <th scope="col" class="py-2 pr-3 font-medium">Week</th>
                <th scope="col" class="py-2 pr-3 font-medium">Period</th>
                <th scope="col" class="py-2 pr-3 font-medium">Hours</th>
                <th scope="col" class="py-2 pr-3 font-medium">Status</th>
                <th scope="col" class="py-2 text-right font-medium">
                  <span class="sr-only">Action</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!deployment.reports.length">
                <td colspan="5" class="py-6 text-center text-ink/70">No reports yet.</td>
              </tr>
              <template v-for="report in deployment.reports" :key="report.id">
                <tr class="border-b border-ink/10">
                  <td class="py-2.5 pr-3 font-mono text-ink">{{ report.week_number }}</td>
                  <td class="py-2.5 pr-3 text-ink">
                    {{ formatPeriod(report.period_start, report.period_end) }}
                  </td>
                  <td class="py-2.5 pr-3 font-mono text-ink">
                    {{ formatHours(report.total_hours) }}
                  </td>
                  <td class="py-2.5 pr-3"><ReportStatusPill :status="report.status" /></td>
                  <td class="py-2.5 text-right">
                    <button
                      type="button"
                      class="rounded px-2 py-1 text-sm font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-brand"
                      :aria-label="`${isActive && ['draft', 'returned'].includes(report.status) ? 'Edit' : 'View'} week ${report.week_number}`"
                      @click="openReport(report)"
                    >
                      {{
                        isActive && ['draft', 'returned'].includes(report.status) ? 'Edit' : 'View'
                      }}
                    </button>
                  </td>
                </tr>
                <tr v-if="report.status === 'returned' && report.return_note">
                  <td colspan="5" class="pb-3">
                    <p
                      class="rounded-md border-l-[3px] border-accent bg-accent/10 px-3 py-2 text-sm text-ink"
                    >
                      <span class="font-medium text-accent-ink">Returned:</span>
                      {{ report.return_note }}
                    </p>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <ReportFormDialog
      v-if="dialogOpen && deployment"
      :weeks="weeks"
      :report="editing"
      :locked="!isActive"
      :default-week="nextWeek"
      @close="dialogOpen = false"
      @saved="onSaved"
    />
  </div>
</template>
