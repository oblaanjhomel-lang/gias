<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import * as programsApi from '@/api/programs'
import DeploymentSlideOver from '@/components/monitoring/DeploymentSlideOver.vue'
import HoursBar from '@/components/monitoring/HoursBar.vue'
import RequiredHoursCard from '@/components/monitoring/RequiredHoursCard.vue'
import { formatDate } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useMonitoringStore } from '@/stores/monitoring'
import { PROVINCES, type Program } from '@/types/accounts'
import type { MonitoringFilter } from '@/types/monitoring'

/** /coordinator/monitoring ("Intern monitoring") and /admin/monitoring ("All deployments"). */
const auth = useAuthStore()
const store = useMonitoringStore()
const isAdmin = computed(() => auth.role === 'admin')

const programs = ref<Program[]>([])

onMounted(async () => {
  store.$reset()
  void store.load()
  programs.value = await programsApi.list()
})

const requiredHours = computed(() => {
  if (isAdmin.value) return null
  return programs.value.find((p) => p.id === auth.user?.program?.id)?.required_ojt_hours ?? null
})

const filters: { key: MonitoringFilter; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'active', label: 'Active' },
  { key: 'overdue', label: 'Overdue' },
  { key: 'completed', label: 'Completed' },
  { key: 'withdrawn', label: 'Withdrawn' },
]

// Debounced search
const searchInput = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(searchInput, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => void store.setSearch(value), 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const programFilter = computed({
  get: () => (store.programId === null ? '' : String(store.programId)),
  set: (value: string) => void store.setProgram(value ? Number(value) : null),
})
const provinceFilter = computed({
  get: () => store.province,
  set: (value: string) => void store.setProvince(value),
})

const STATUS: Record<string, { label: string; class: string }> = {
  active: { label: 'active', class: 'bg-brand/10 text-brand' },
  overdue: { label: 'no log 14d+', class: 'bg-accent/20 text-accent-ink' },
  completed: { label: 'completed', class: 'bg-brand-dark text-white' },
  withdrawn: { label: 'withdrawn', class: 'bg-ink/[0.07] text-ink/75' },
}

const card = 'rounded-[9px] border border-ink/10 bg-white'
const th = 'px-4 py-2.5 font-medium'
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">
        {{ isAdmin ? 'All deployments' : 'Intern monitoring' }}
      </h1>
      <p class="mt-1 font-mono text-xs tracking-[0.06em] text-ink/70 uppercase">
        Showing {{ store.meta?.total ?? 0 }} of {{ store.scopeTotal }} in your scope
      </p>
      <p class="mt-1 text-sm text-ink/70">
        Required hours: {{ requiredHours ?? 'per program' }} (set per program by Admin). Progress
        counts hours countersigned by the on-site supervisor.
      </p>
    </div>

    <div role="status">
      <p
        v-if="store.notice"
        class="flex items-start justify-between gap-3 rounded-md border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-ink"
      >
        <span>{{ store.notice }}</span>
        <button
          type="button"
          aria-label="Dismiss message"
          class="rounded px-1.5 text-lg leading-none text-ink/70 focus-visible:outline-2 focus-visible:outline-brand"
          @click="store.notice = null"
        >
          <span aria-hidden="true">×</span>
        </button>
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

    <!-- Summary -->
    <dl class="grid gap-3 sm:grid-cols-4">
      <div :class="[card, 'px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-ink/70 uppercase">Active</dt>
        <dd class="font-mono text-2xl font-medium text-ink">{{ store.summary?.active ?? '–' }}</dd>
      </div>
      <div :class="[card, 'border-accent/60 px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-accent-ink uppercase">Overdue</dt>
        <dd class="font-mono text-2xl font-medium text-accent-ink">
          {{ store.summary?.overdue ?? '–' }}
        </dd>
      </div>
      <div :class="[card, 'px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-ink/70 uppercase">Completed</dt>
        <dd class="font-mono text-2xl font-medium text-ink">
          {{ store.summary?.completed ?? '–' }}
        </dd>
      </div>
      <div :class="[card, 'px-4 py-3']">
        <dt class="font-mono text-[10.5px] tracking-[0.08em] text-ink/70 uppercase">
          Awaiting signature
        </dt>
        <dd class="font-mono text-2xl font-medium text-ink">
          {{ store.summary?.reports_awaiting_signature ?? '–' }}
        </dd>
      </div>
    </dl>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div
        role="group"
        aria-label="Show"
        class="inline-flex flex-wrap rounded-md border border-ink/20 bg-white p-0.5"
      >
        <button
          v-for="f in filters"
          :key="f.key"
          type="button"
          :aria-pressed="store.filter === f.key"
          class="rounded px-3 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand"
          :class="store.filter === f.key ? 'bg-brand text-white' : 'text-ink/75 hover:text-ink'"
          @click="store.setFilter(f.key)"
        >
          {{ f.label }}
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <template v-if="isAdmin">
          <label for="mon-program" class="sr-only">Program</label>
          <select
            id="mon-program"
            v-model="programFilter"
            class="h-10 rounded-md border border-ink/25 bg-white px-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
          >
            <option value="">All programs</option>
            <option v-for="p in programs" :key="p.id" :value="String(p.id)">{{ p.code }}</option>
          </select>
          <label for="mon-province" class="sr-only">Province</label>
          <select
            id="mon-province"
            v-model="provinceFilter"
            class="h-10 rounded-md border border-ink/25 bg-white px-2 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
          >
            <option value="">All provinces</option>
            <option v-for="p in PROVINCES" :key="p" :value="p">{{ p }}</option>
          </select>
        </template>
        <label for="mon-search" class="sr-only">Search interns</label>
        <input
          id="mon-search"
          v-model="searchInput"
          type="search"
          placeholder="Search name, student no., company"
          class="h-10 w-64 rounded-md border border-ink/25 bg-white px-3 text-sm text-ink placeholder:text-ink/50 focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-brand"
        />
      </div>
    </div>

    <!-- Table -->
    <section :class="card" :aria-busy="store.loading">
      <div class="overflow-x-auto" :class="store.loading && store.rows.length ? 'opacity-60' : ''">
        <table class="w-full text-left text-sm">
          <caption class="sr-only">
            Deployments
          </caption>
          <thead
            class="bg-canvas/60 font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase"
          >
            <tr>
              <th scope="col" :class="th">Intern</th>
              <th scope="col" :class="th">Host training establishment</th>
              <th scope="col" :class="th">Hours logged</th>
              <th scope="col" :class="th">Last report</th>
              <th scope="col" :class="th">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink/10">
            <tr v-if="store.loading && !store.rows.length">
              <td colspan="5" class="px-4 py-8 text-center text-ink/70">Loading deployments…</td>
            </tr>
            <tr v-else-if="!store.rows.length">
              <td colspan="5" class="px-4 py-8 text-center text-ink/70">
                {{
                  store.search || store.filter !== 'all'
                    ? 'No deployments match these filters.'
                    : 'No deployments in your scope yet.'
                }}
              </td>
            </tr>
            <tr
              v-for="row in store.rows"
              :key="row.id"
              :class="store.selectedId === row.id ? 'bg-brand/5' : ''"
            >
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="text-left font-medium text-ink underline-offset-2 hover:text-brand hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                  :aria-label="`Open ${row.student.name}’s deployment`"
                  @click="store.open(row.id)"
                >
                  {{ row.student.name }}
                </button>
                <span class="block text-xs text-ink/70">{{ row.student.program ?? '—' }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="block text-ink">{{ row.partner.company_name }}</span>
                <span class="block text-xs text-ink/70">{{ row.partner.city }}</span>
              </td>
              <td class="px-4 py-3"><HoursBar :progress="row.progress" compact /></td>
              <td class="px-4 py-3 text-ink">
                {{
                  row.progress.last_report_period_end
                    ? formatDate(row.progress.last_report_period_end)
                    : '—'
                }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="STATUS[row.progress.status]?.class"
                >
                  {{ STATUS[row.progress.status]?.label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <nav
        v-if="store.meta && store.meta.last_page > 1"
        aria-label="Pagination"
        class="flex items-center justify-between gap-3 border-t border-ink/10 px-4 py-3 text-sm"
      >
        <p class="text-ink/70">
          Showing {{ store.meta.from }}–{{ store.meta.to }} of {{ store.meta.total }}
        </p>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="store.meta.current_page <= 1"
            class="h-9 rounded-md border border-ink/20 px-3 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand"
            @click="store.setPage(store.meta.current_page - 1)"
          >
            Previous
          </button>
          <span class="font-mono text-xs text-ink/70"
            >Page {{ store.meta.current_page }} of {{ store.meta.last_page }}</span
          >
          <button
            type="button"
            :disabled="store.meta.current_page >= store.meta.last_page"
            class="h-9 rounded-md border border-ink/20 px-3 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand"
            @click="store.setPage(store.meta.current_page + 1)"
          >
            Next
          </button>
        </div>
      </nav>
    </section>

    <RequiredHoursCard
      v-if="isAdmin"
      @saved="
        (m) => {
          store.notice = m
          void store.load()
        }
      "
    />

    <DeploymentSlideOver
      v-if="store.selectedId !== null"
      :is-admin="isAdmin"
      @close="store.close()"
    />
  </div>
</template>
