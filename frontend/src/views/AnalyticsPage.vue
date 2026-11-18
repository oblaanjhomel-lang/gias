<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as programsApi from '@/api/programs'
import * as reportsApi from '@/api/reports'
import BarList from '@/components/charts/BarList.vue'
import ColumnTrend from '@/components/charts/ColumnTrend.vue'
import HistogramChart from '@/components/charts/HistogramChart.vue'
import StatCard from '@/components/charts/StatCard.vue'
import { errorMessage } from '@/lib/apiErrors'
import { plural } from '@/lib/format'
import { useAnalyticsStore } from '@/stores/analytics'
import { useAuthStore } from '@/stores/auth'
import { PROVINCES, type Program, type Province } from '@/types/accounts'
import type { AnalyticsOverview, ReportFormat, ReportType } from '@/types/analytics'

const store = useAnalyticsStore()
const auth = useAuthStore()
const isAdmin = computed(() => auth.role === 'admin')
const programs = ref<Program[]>([])

onMounted(async () => {
  await store.loadTerms()
  if (!store.data) void store.fetch()
  if (isAdmin.value) {
    try {
      programs.value = await programsApi.list()
    } catch {
      // The program filter just stays at "All programs".
    }
  }
})

const data = computed(() => store.data)
const skeleton = computed(() => store.loading && !store.data)

// ---- Filters ----

function onTerm(event: Event): void {
  void store.setFilters({ term: (event.target as HTMLSelectElement).value })
}
function onProgram(event: Event): void {
  void store.setFilters({ program_id: (event.target as HTMLSelectElement).value })
}
function onProvince(event: Event): void {
  void store.setFilters({ province: (event.target as HTMLSelectElement).value as Province | '' })
}
const termValue = computed(() => store.filters.term || store.currentTerm?.key || '')
const filtersActive = computed(
  () => !!(store.filters.term || store.filters.program_id || store.filters.province),
)

// ---- Formatting ----

const pct = (v: number | null | undefined): string => (v == null ? '—' : `${v}%`)
const num = (v: number | null | undefined, digits = 2): string =>
  v == null ? '—' : v.toFixed(digits)

// ---- Charts ----

const provinceRows = computed(() =>
  (data.value?.by_province ?? []).map((p) => ({
    label: p.province,
    value: p.interns,
    text: `${plural(p.interns, 'intern')} · ${plural(p.partners, 'partner')}`,
    highlight: p.underserved,
  })),
)

const trendColumns = computed(() =>
  (data.value?.placements_trend ?? []).map((t) => ({ label: t.label, value: t.placements })),
)

const trendTakeaway = computed(() => {
  const trend = data.value?.placements_trend ?? []
  const first = trend[0]
  const last = trend[trend.length - 1]
  if (!first || !last || trend.length < 2) return ''
  if (first.placements === 0) return `${plural(last.placements, 'placement')} in ${last.label}.`
  const change = Math.round(((last.placements - first.placements) / first.placements) * 100)
  if (change === 0) return `Level with ${first.label}.`
  return `${change > 0 ? 'Up' : 'Down'} ${Math.abs(change)}% from ${first.label}.`
})

const histogramBuckets = computed(() =>
  (data.value?.hours_distribution ?? []).map((b) => ({ label: b.bucket, value: b.interns })),
)

const criteriaRows = computed(() => {
  const evaluation = data.value?.evaluation
  return (evaluation?.criteria ?? [])
    .filter((c) => c.average !== null)
    .map((c) => ({
      label: c.label,
      value: c.average ?? 0,
      text: (c.average ?? 0).toFixed(2),
      highlight: c.key === evaluation?.lowest_criterion?.key,
    }))
})

// ---- Tables ----

const loadStyle: Record<string, { label: string; cls: string }> = {
  heavy: { label: 'Heavy', cls: 'bg-[#FBF6E8] text-[#8a6a1f] border-[#E8D9A8]' },
  balanced: { label: 'Balanced', cls: 'bg-[#E4EFE7] text-[#0B3D1A] border-transparent' },
  light: { label: 'Light', cls: 'bg-ink/[0.06] text-ink/65 border-transparent' },
  no_coordinator: { label: 'No coordinator', cls: 'bg-[#FDECEA] text-[#B42318] border-[#F5C2BC]' },
}

const skillsTop = computed(() => (data.value?.skills_gap ?? []).slice(0, 8))

type Algo = AnalyticsOverview['algorithm_comparison']['algorithms'][number]
type NumericKey =
  | 'placements_using'
  | 'top1_agreement'
  | 'top3_agreement'
  | 'mean_rank_of_chosen'
  | 'avg_feedback_rating'

/** Best value per column: highest, except mean rank (lowest). */
const best = computed(() => {
  const algos = data.value?.algorithm_comparison.algorithms ?? []
  const pick = (key: NumericKey, lowest = false): number | null => {
    const values = algos.map((a) => a[key]).filter((v): v is number => v !== null)
    if (values.length === 0) return null
    return lowest ? Math.min(...values) : Math.max(...values)
  }
  return {
    placements_using: pick('placements_using'),
    top1_agreement: pick('top1_agreement'),
    top3_agreement: pick('top3_agreement'),
    mean_rank_of_chosen: pick('mean_rank_of_chosen', true),
    avg_feedback_rating: pick('avg_feedback_rating'),
  }
})
function isBest(a: Algo, key: NumericKey): boolean {
  return a[key] !== null && a[key] === best.value[key] && (key !== 'placements_using' || a[key] > 0)
}

// ---- Reports ----

const reports = computed(() =>
  (
    [
      {
        type: 'deployments',
        label: 'Deployment masterlist',
        detail: 'Every placement in the term with hours and final rating',
      },
      {
        type: 'partners',
        label: 'Partner directory',
        detail: 'Active partners, slots, utilization and coordinates',
        adminOnly: true,
      },
      {
        type: 'programs',
        label: 'Program summary',
        detail: 'Placement, completion and evaluation per program',
      },
      {
        type: 'algorithms',
        label: 'Algorithm comparison',
        detail: 'Agreement and ratings per recommender, with each placement',
      },
    ] as { type: ReportType; label: string; detail: string; adminOnly?: boolean }[]
  ).filter((r) => !r.adminOnly || isAdmin.value),
)

const downloading = ref<string | null>(null)
const reportError = ref<string | null>(null)

async function download(type: ReportType, format: ReportFormat): Promise<void> {
  downloading.value = `${type}-${format}`
  reportError.value = null
  try {
    await reportsApi.download(type, format, store.filters)
  } catch (e) {
    reportError.value = errorMessage(e, 'Could not generate the report.')
  } finally {
    downloading.value = null
  }
}

const selectClass =
  'mt-1 block h-10 w-full rounded-md border border-ink/25 bg-white px-3 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand'
</script>

<template>
  <div class="mx-auto flex max-w-[1240px] flex-col gap-6">
    <header>
      <h1 class="font-serif text-[28px] leading-tight font-semibold text-ink">
        Analytics &amp; reporting
      </h1>
      <p class="mt-1.5 max-w-3xl text-[15px] text-ink/75">
        Placement trends, partner distribution, and coordination workload for accreditation and
        institutional planning.
      </p>
    </header>

    <!-- Filters -->
    <form
      class="flex flex-wrap items-end gap-4 rounded-[10px] border border-ink/10 bg-white px-5 py-4"
      aria-label="Analytics filters"
      @submit.prevent
    >
      <div class="w-64">
        <label for="filter-term" class="block text-[13px] font-medium text-ink">Term</label>
        <select id="filter-term" :class="selectClass" :value="termValue" @change="onTerm">
          <option v-for="term in store.terms" :key="term.key" :value="term.key">
            {{ term.label }}{{ term.key === store.currentTerm?.key ? ' (current)' : '' }}
          </option>
        </select>
      </div>
      <div v-if="isAdmin" class="w-56">
        <label for="filter-program" class="block text-[13px] font-medium text-ink">Program</label>
        <select
          id="filter-program"
          :class="selectClass"
          :value="store.filters.program_id"
          @change="onProgram"
        >
          <option value="">All programs</option>
          <option v-for="program in programs" :key="program.id" :value="String(program.id)">
            {{ program.code }}
          </option>
        </select>
      </div>
      <div class="w-52">
        <label for="filter-province" class="block text-[13px] font-medium text-ink">Province</label>
        <select
          id="filter-province"
          :class="selectClass"
          :value="store.filters.province"
          @change="onProvince"
        >
          <option value="">All provinces</option>
          <option v-for="province in PROVINCES" :key="province" :value="province">
            {{ province }}
          </option>
        </select>
      </div>
      <button
        type="button"
        :disabled="!filtersActive"
        class="h-10 rounded-md border border-ink/20 px-4 text-sm font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
        @click="store.reset()"
      >
        Reset
      </button>
      <span v-if="store.loading && store.data" class="ml-auto text-[13px] text-ink/60" role="status"
        >Updating…</span
      >
    </form>

    <div
      v-if="store.error"
      role="alert"
      class="flex items-center justify-between gap-4 rounded-md border border-[#B42318]/30 bg-[#B42318]/5 px-4 py-3 text-sm text-[#B42318]"
    >
      <p>{{ store.error }}</p>
      <button
        type="button"
        class="rounded-md border border-[#B42318]/40 bg-white px-3 py-1.5 font-medium hover:bg-[#B42318]/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#B42318]"
        @click="store.fetch()"
      >
        Retry
      </button>
    </div>

    <template v-if="data || skeleton">
      <!-- KPIs -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
        <StatCard
          label="Placement rate"
          :loading="skeleton"
          :value="pct(data?.kpis.placement_rate.rate)"
          :note="
            data
              ? `${data.kpis.placement_rate.numerator} of ${plural(data.kpis.placement_rate.denominator, 'student')}`
              : ''
          "
        />
        <StatCard
          label="Active partners"
          :loading="skeleton"
          :value="data?.kpis.active_partners ?? '—'"
          note="Approved Industry Partners"
        />
        <StatCard
          label="Partner utilization"
          :loading="skeleton"
          :value="pct(data?.kpis.partner_utilization.rate)"
          :note="
            data
              ? `${data.kpis.partner_utilization.filled} of ${plural(data.kpis.partner_utilization.slots, 'slot')}`
              : ''
          "
        />
        <StatCard
          label="Avg distance to site"
          :loading="skeleton"
          :value="data?.kpis.avg_distance_km != null ? `${data.kpis.avg_distance_km} km` : '—'"
          note="Home to host, straight line"
        />
        <StatCard
          label="Completion rate"
          :loading="skeleton"
          :value="pct(data?.kpis.completion_rate.rate)"
          :note="data ? `${data.kpis.completion_rate.completed} completed` : ''"
          :tone="(data?.kpis.completion_rate.active_past_required ?? 0) > 0 ? 'warn' : 'neutral'"
        />
        <StatCard
          label="Avg final rating"
          :loading="skeleton"
          :value="num(data?.kpis.avg_final_rating.value)"
          :note="data?.kpis.avg_final_rating.descriptor ?? 'No reviewed finals yet'"
          :tone="data?.kpis.avg_final_rating.value ? 'ok' : 'neutral'"
        />
      </div>

      <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.3fr_1fr]">
        <BarList
          title="Interns deployed by province"
          subtitle="Underserved provinces are the target for new linkages"
          :rows="provinceRows"
          label-header="Province"
          value-header="Interns · partners"
          :loading="skeleton"
        />
        <ColumnTrend
          title="Placements per term"
          subtitle="Deployments by the term they started in"
          :columns="trendColumns"
          label-header="Term"
          value-header="Placements"
          :loading="skeleton"
        >
          <template v-if="trendTakeaway" #footer>{{ trendTakeaway }}</template>
        </ColumnTrend>
      </div>

      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <HistogramChart
          title="Progress of active interns"
          subtitle="Signed hours as a share of the program’s required hours"
          :buckets="histogramBuckets"
          label-header="Progress"
          value-header="Interns"
          :loading="skeleton"
        />
        <BarList
          title="Average final rating by criterion"
          :subtitle="
            data?.evaluation.lowest_criterion
              ? `Lowest: ${data.evaluation.lowest_criterion.label} (${data.evaluation.evaluated} evaluated)`
              : 'Industry Partner ratings, 1–5'
          "
          :rows="criteriaRows"
          :max="5"
          label-header="Criterion"
          value-header="Average (1–5)"
          :loading="skeleton"
        />
      </div>

      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Admin: coordinator workload; coordinator: top host partners -->
        <section
          v-if="isAdmin"
          class="rounded-[10px] border border-ink/10 bg-white p-5"
          aria-labelledby="workload-title"
        >
          <h2 id="workload-title" class="font-serif text-lg font-semibold text-ink">
            Coordinator workload
          </h2>
          <p class="mt-0.5 text-[13px] text-ink/65">
            Heavy &gt; 20 interns · Balanced 8–20 · Light &lt; 8
          </p>
          <div v-if="skeleton" class="mt-4 h-48 animate-pulse rounded bg-ink/10" />
          <div v-else class="mt-3 max-h-[420px] overflow-y-auto">
            <table class="w-full text-left text-sm">
              <thead class="sticky top-0 bg-white">
                <tr
                  class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
                >
                  <th scope="col" class="py-2 font-medium">Program</th>
                  <th scope="col" class="py-2 font-medium">Coordinator</th>
                  <th scope="col" class="py-2 text-right font-medium">Interns</th>
                  <th scope="col" class="py-2 text-right font-medium">Partners</th>
                  <th scope="col" class="py-2 pl-3 font-medium">Load</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in data?.workload ?? []"
                  :key="row.program.code"
                  class="border-b border-ink/5"
                >
                  <th
                    scope="row"
                    class="py-2 font-mono text-[13px] font-medium"
                    :title="row.program.name"
                  >
                    {{ row.program.code }}
                  </th>
                  <td class="py-2 text-ink/80">{{ row.coordinator ?? '—' }}</td>
                  <td class="py-2 text-right font-mono text-[13px]">{{ row.interns }}</td>
                  <td class="py-2 text-right font-mono text-[13px]">{{ row.partners_used }}</td>
                  <td class="py-2 pl-3">
                    <span
                      class="inline-block rounded border px-2 py-0.5 text-xs font-medium"
                      :class="loadStyle[row.load]?.cls"
                    >
                      {{ loadStyle[row.load]?.label }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section
          v-else
          class="rounded-[10px] border border-ink/10 bg-white p-5"
          aria-labelledby="top-partners-title"
        >
          <h2 id="top-partners-title" class="font-serif text-lg font-semibold text-ink">
            Top host partners
          </h2>
          <p class="mt-0.5 text-[13px] text-ink/65">Most interns from your program this term</p>
          <div v-if="skeleton" class="mt-4 h-48 animate-pulse rounded bg-ink/10" />
          <p v-else-if="(data?.top_partners ?? []).length === 0" class="mt-4 text-sm text-ink/65">
            No placements in this term.
          </p>
          <table v-else class="mt-3 w-full text-left text-sm">
            <thead>
              <tr
                class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
              >
                <th scope="col" class="py-2 font-medium">Partner</th>
                <th scope="col" class="py-2 text-right font-medium">Interns</th>
                <th scope="col" class="py-2 text-right font-medium">Avg final rating</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in data?.top_partners ?? []"
                :key="row.company"
                class="border-b border-ink/5"
              >
                <th scope="row" class="py-2.5 font-normal">
                  <span class="block text-ink">{{ row.company }}</span>
                  <span class="block text-[12.5px] text-ink/60">{{ row.city }}</span>
                </th>
                <td class="py-2.5 text-right font-mono text-[13px]">{{ row.interns }}</td>
                <td class="py-2.5 text-right font-mono text-[13px]">
                  {{ num(row.avg_final_rating) }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section
          class="rounded-[10px] border border-ink/10 bg-white p-5"
          aria-labelledby="skills-gap-title"
        >
          <h2 id="skills-gap-title" class="font-serif text-lg font-semibold text-ink">
            Skills gap
          </h2>
          <p class="mt-0.5 text-[13px] text-ink/65">
            Partners needing a skill vs. active students who have it
          </p>
          <div v-if="skeleton" class="mt-4 h-48 animate-pulse rounded bg-ink/10" />
          <table v-else class="mt-3 w-full text-left text-sm">
            <thead>
              <tr
                class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
              >
                <th scope="col" class="py-2 font-medium">Skill</th>
                <th scope="col" class="py-2 text-right font-medium">Partners need</th>
                <th scope="col" class="py-2 text-right font-medium">Students have</th>
                <th scope="col" class="py-2 text-right font-medium">Gap</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in skillsTop" :key="row.skill" class="border-b border-ink/5">
                <th scope="row" class="py-2 font-normal text-ink">{{ row.skill }}</th>
                <td class="py-2 text-right font-mono text-[13px]">{{ row.partners_need }}</td>
                <td class="py-2 text-right font-mono text-[13px]">{{ row.students_have }}</td>
                <td class="py-2 text-right font-mono text-[13px]">
                  <span
                    v-if="row.gap > 0"
                    class="rounded bg-[#FBF6E8] px-1.5 py-0.5 font-medium text-[#8a6a1f]"
                    >+{{ row.gap }}</span
                  >
                  <span v-else class="text-ink/60">{{ row.gap }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <!-- Algorithm comparison -->
      <section
        class="rounded-[10px] border border-ink/10 bg-white p-5"
        aria-labelledby="algorithms-title"
      >
        <h2 id="algorithms-title" class="font-serif text-lg font-semibold text-ink">
          Recommendation algorithm comparison
        </h2>
        <div v-if="skeleton" class="mt-4 h-32 animate-pulse rounded bg-ink/10" />
        <template v-else-if="data">
          <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
              <thead>
                <tr
                  class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
                >
                  <th scope="col" class="py-2 font-medium">Algorithm</th>
                  <th scope="col" class="py-2 text-right font-medium">Placements using</th>
                  <th scope="col" class="py-2 text-right font-medium">Top-1 agreement</th>
                  <th scope="col" class="py-2 text-right font-medium">Top-3 agreement</th>
                  <th scope="col" class="py-2 text-right font-medium">Mean rank of chosen</th>
                  <th scope="col" class="py-2 text-right font-medium">
                    Avg coordinator rating (n)
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="a in data.algorithm_comparison.algorithms"
                  :key="a.algorithm"
                  class="border-b border-ink/5"
                >
                  <th scope="row" class="py-2.5 font-medium text-ink">{{ a.label }}</th>
                  <td
                    class="py-2.5 text-right font-mono text-[13px]"
                    :class="isBest(a, 'placements_using') ? 'font-semibold text-brand' : ''"
                  >
                    {{ a.placements_using }}
                  </td>
                  <td
                    class="py-2.5 text-right font-mono text-[13px]"
                    :class="isBest(a, 'top1_agreement') ? 'font-semibold text-brand' : ''"
                  >
                    {{ pct(a.top1_agreement) }}
                  </td>
                  <td
                    class="py-2.5 text-right font-mono text-[13px]"
                    :class="isBest(a, 'top3_agreement') ? 'font-semibold text-brand' : ''"
                  >
                    {{ pct(a.top3_agreement) }}
                  </td>
                  <td
                    class="py-2.5 text-right font-mono text-[13px]"
                    :class="isBest(a, 'mean_rank_of_chosen') ? 'font-semibold text-brand' : ''"
                  >
                    {{ num(a.mean_rank_of_chosen) }}
                  </td>
                  <td
                    class="py-2.5 text-right font-mono text-[13px]"
                    :class="isBest(a, 'avg_feedback_rating') ? 'font-semibold text-brand' : ''"
                  >
                    {{ num(a.avg_feedback_rating) }}
                    <span class="text-ink/55">({{ a.feedback_n }})</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mt-3 text-[13px] text-ink/65">
            Agreement = how often the partner the coordinator actually chose was ranked first (or in
            the top 3) by that algorithm. Based on
            {{ plural(data.algorithm_comparison.total_with_rank_data, 'placement') }} with
            recommendation data;
            {{ plural(data.algorithm_comparison.manual_placements, 'manual placement') }}
            excluded.
          </p>
        </template>
      </section>
    </template>

    <!-- Reports -->
    <section
      class="rounded-[10px] border border-ink/10 bg-white p-5"
      aria-labelledby="reports-title"
    >
      <h2 id="reports-title" class="font-serif text-lg font-semibold text-ink">Generate reports</h2>
      <p class="mt-0.5 text-[13px] text-ink/65">
        Uses the term, program and province selected above.
      </p>
      <p v-if="reportError" role="alert" class="mt-3 text-sm text-[#B42318]">{{ reportError }}</p>
      <ul class="mt-3 divide-y divide-ink/10">
        <li
          v-for="report in reports"
          :key="report.type"
          class="flex flex-wrap items-center justify-between gap-3 py-3"
        >
          <div>
            <p class="text-sm font-medium text-ink">{{ report.label }}</p>
            <p class="text-[12.5px] text-ink/65">{{ report.detail }}</p>
          </div>
          <div class="flex gap-2">
            <button
              v-for="format in ['pdf', 'csv'] as const"
              :key="format"
              type="button"
              :disabled="downloading !== null"
              :aria-label="`Download ${report.label} as ${format.toUpperCase()}`"
              class="h-9 min-w-16 rounded-md border px-3 font-mono text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
              :class="
                format === 'pdf'
                  ? 'border-brand bg-brand text-white hover:bg-brand-dark'
                  : 'border-ink/20 bg-white text-ink hover:border-brand hover:text-brand'
              "
              @click="download(report.type, format)"
            >
              {{ downloading === `${report.type}-${format}` ? '…' : format.toUpperCase() }}
            </button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
