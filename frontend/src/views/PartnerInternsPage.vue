<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import * as partnerApi from '@/api/partnerReports'
import type { PartnerReportTab } from '@/api/partnerReports'
import HoursBar from '@/components/monitoring/HoursBar.vue'
import ReviewReportDialog from '@/components/monitoring/ReviewReportDialog.vue'
import { errorMessage } from '@/lib/apiErrors'
import { formatDate, formatHours, formatPeriod } from '@/lib/format'
import type { PaginationMeta } from '@/types/accounts'
import type { PartnerIntern, WeeklyReport } from '@/types/monitoring'

/** /partner/interns: "Our interns". */
const interns = ref<PartnerIntern[]>([])
const reports = ref<WeeklyReport[]>([])
const meta = ref<PaginationMeta | null>(null)
const counts = ref<Record<PartnerReportTab, number>>({ submitted: 0, signed: 0, returned: 0 })
const tab = ref<PartnerReportTab>('submitted')
const page = ref(1)

const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const reviewing = ref<WeeklyReport | null>(null)

const tabs: { key: PartnerReportTab; label: string }[] = [
  { key: 'submitted', label: 'To sign' },
  { key: 'signed', label: 'Signed' },
  { key: 'returned', label: 'Returned' },
]

const hosted = computed(() => interns.value.filter((i) => i.status === 'active').length)
const completed = computed(() => interns.value.filter((i) => i.status === 'completed').length)

async function loadReports(): Promise<void> {
  const result = await partnerApi.listReports(tab.value, page.value)
  reports.value = result.reports
  meta.value = result.meta
  counts.value = result.counts
}

async function loadAll(): Promise<void> {
  try {
    const [list] = await Promise.all([partnerApi.listInterns(), loadReports()])
    interns.value = list
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your interns.')
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

async function selectTab(key: PartnerReportTab): Promise<void> {
  tab.value = key
  page.value = 1
  await loadReports()
}

async function onTabKeydown(event: KeyboardEvent, index: number): Promise<void> {
  if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return
  event.preventDefault()
  const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]!
  await selectTab(next.key)
  await nextTick()
  document.getElementById(`reports-tab-${next.key}`)?.focus()
}

async function goTo(p: number): Promise<void> {
  page.value = p
  await loadReports()
}

async function onDone(message: string): Promise<void> {
  reviewing.value = null
  notice.value = message
  error.value = null
  await loadAll()
}

const STATUS: Record<string, { label: string; class: string }> = {
  active: { label: 'Active', class: 'bg-brand/10 text-brand' },
  overdue: { label: 'No log 14d+', class: 'bg-accent/20 text-accent-ink' },
  completed: { label: 'Completed', class: 'bg-brand-dark text-white' },
  withdrawn: { label: 'Withdrawn', class: 'bg-ink/[0.07] text-ink/75' },
}

const card = 'rounded-[9px] border border-ink/10 bg-white'
const th = 'px-4 py-2.5 font-medium'
</script>

<template>
  <div class="space-y-5">
    <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">Our interns</h1>

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

    <dl class="grid gap-3 sm:grid-cols-3">
      <div :class="[card, 'px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-ink/70 uppercase">
          Interns hosted
        </dt>
        <dd class="font-mono text-2xl font-medium text-ink">{{ hosted }}</dd>
      </div>
      <div :class="[card, 'px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-ink/70 uppercase">
          Reports to sign
        </dt>
        <dd class="font-mono text-2xl font-medium text-accent-ink">{{ counts.submitted }}</dd>
      </div>
      <div :class="[card, 'px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-ink/70 uppercase">Completed</dt>
        <dd class="font-mono text-2xl font-medium text-ink">{{ completed }}</dd>
      </div>
    </dl>

    <!-- Reports -->
    <section :class="card" aria-label="Weekly reports">
      <div
        role="tablist"
        aria-label="Weekly reports"
        class="flex gap-6 border-b border-ink/10 px-4 pt-2"
      >
        <button
          v-for="(t, index) in tabs"
          :id="`reports-tab-${t.key}`"
          :key="t.key"
          type="button"
          role="tab"
          :aria-selected="tab === t.key"
          aria-controls="reports-panel"
          :tabindex="tab === t.key ? 0 : -1"
          class="-mb-px border-b-2 px-0.5 pt-2 pb-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          :class="
            tab === t.key
              ? 'border-brand text-brand'
              : 'border-transparent text-ink/70 hover:text-ink'
          "
          @click="selectTab(t.key)"
          @keydown="onTabKeydown($event, index)"
        >
          {{ t.label }}<template v-if="t.key === 'submitted'"> ({{ counts.submitted }})</template>
        </button>
      </div>

      <div id="reports-panel" role="tabpanel" :aria-labelledby="`reports-tab-${tab}`">
        <p v-if="loading" class="px-4 py-8 text-center text-sm text-ink/70">Loading…</p>
        <p v-else-if="!reports.length" class="px-4 py-8 text-center text-sm text-ink/70">
          {{ tab === 'submitted' ? 'No reports waiting for your signature.' : 'Nothing here yet.' }}
        </p>
        <ul v-else class="divide-y divide-ink/10">
          <li
            v-for="report in reports"
            :key="report.id"
            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
          >
            <div>
              <p class="text-sm font-medium text-ink">
                {{ report.intern?.name }}
                <span class="font-normal text-ink/70">· {{ report.intern?.program }}</span>
              </p>
              <p class="text-xs text-ink/70">
                Week {{ report.week_number }} ·
                {{ formatPeriod(report.period_start, report.period_end) }} ·
                <span class="font-mono">{{ formatHours(report.total_hours) }} h</span>
                <template v-if="report.submitted_at">
                  · submitted {{ formatDate(report.submitted_at) }}</template
                >
              </p>
            </div>
            <button
              type="button"
              class="h-9 rounded-md px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              :class="
                tab === 'submitted'
                  ? 'bg-brand text-white hover:bg-brand-dark'
                  : 'border border-ink/20 text-ink hover:border-brand hover:text-brand'
              "
              :aria-label="`${tab === 'submitted' ? 'Review' : 'View'} week ${report.week_number} from ${report.intern?.name}`"
              @click="reviewing = report"
            >
              {{ tab === 'submitted' ? 'Review' : 'View' }}
            </button>
          </li>
        </ul>
        <nav
          v-if="meta && meta.last_page > 1"
          aria-label="Pagination"
          class="flex items-center justify-end gap-2 border-t border-ink/10 px-4 py-2 text-sm"
        >
          <button
            type="button"
            :disabled="meta.current_page <= 1"
            class="h-8 rounded border border-ink/20 px-3 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand"
            @click="goTo(meta.current_page - 1)"
          >
            Previous
          </button>
          <span class="font-mono text-xs text-ink/70"
            >Page {{ meta.current_page }} of {{ meta.last_page }}</span
          >
          <button
            type="button"
            :disabled="meta.current_page >= meta.last_page"
            class="h-8 rounded border border-ink/20 px-3 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand"
            @click="goTo(meta.current_page + 1)"
          >
            Next
          </button>
        </nav>
      </div>
    </section>

    <!-- Interns -->
    <section :class="card" aria-labelledby="interns-title">
      <h2 id="interns-title" class="px-4 pt-4 font-serif text-lg font-semibold text-ink">
        Interns
      </h2>
      <div class="overflow-x-auto">
        <table class="mt-2 w-full text-left text-sm">
          <caption class="sr-only">
            Interns at your site
          </caption>
          <thead
            class="bg-canvas/60 font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase"
          >
            <tr>
              <th scope="col" :class="th">Intern</th>
              <th scope="col" :class="th">Program</th>
              <th scope="col" :class="th">Hours</th>
              <th scope="col" :class="th">Last report</th>
              <th scope="col" :class="th">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink/10">
            <tr v-if="!interns.length">
              <td colspan="5" class="px-4 py-6 text-center text-ink/70">No interns yet.</td>
            </tr>
            <tr v-for="intern in interns" :key="intern.id">
              <td class="px-4 py-3 font-medium text-ink">{{ intern.intern.name }}</td>
              <td class="px-4 py-3 text-ink">{{ intern.intern.program ?? '—' }}</td>
              <td class="px-4 py-3"><HoursBar :progress="intern.progress" compact /></td>
              <td class="px-4 py-3 text-ink">
                {{
                  intern.progress.last_report_period_end
                    ? formatDate(intern.progress.last_report_period_end)
                    : '—'
                }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="STATUS[intern.progress.status]?.class"
                >
                  {{ STATUS[intern.progress.status]?.label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <ReviewReportDialog
      v-if="reviewing"
      :report="reviewing"
      @close="reviewing = null"
      @done="onDone"
    />
  </div>
</template>
