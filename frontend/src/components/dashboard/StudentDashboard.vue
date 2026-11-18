<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import StatCard from '@/components/charts/StatCard.vue'
import DashboardFrame from '@/components/dashboard/DashboardFrame.vue'
import TableCard from '@/components/dashboard/TableCard.vue'
import { formatDate, formatHours, plural } from '@/lib/format'
import type { NextStep, StudentDashboard } from '@/types/dashboard'

const props = defineProps<{ data: StudentDashboard }>()

const stats = computed(() => props.data.stats)
const remaining = computed(() =>
  stats.value ? Math.max(0, stats.value.required_hours - stats.value.signed_hours) : 0,
)

function stepText(step: NextStep): { title: string; detail: string; to: string } {
  switch (step.type) {
    case 'report_due':
      return {
        title: `File your Week ${step.week} report`,
        detail: `For the week ending ${formatDate(step.date)}`,
        to: '/student/deployment',
      }
    case 'report_returned':
      return {
        title: `Week ${step.week} report was returned`,
        detail: 'Read your supervisor’s note, fix it and resubmit',
        to: '/student/deployment',
      }
    case 'evaluation_released':
      return {
        title: 'Your final evaluation is released',
        detail: step.date
          ? `Reviewed ${formatDate(step.date)}`
          : 'Reviewed by your OJT Coordinator',
        to: '/student/evaluation',
      }
    default:
      return {
        title: 'You haven’t been placed yet',
        detail: 'Your OJT Coordinator will assign your partner',
        to: '/student/map',
      }
  }
}

const lede = computed(() => {
  if (!stats.value) return ''
  if (stats.value.deployment_status === 'completed')
    return 'You have completed your OJT. Congratulations!'
  const hours =
    remaining.value === 0
      ? 'You have reached your required OJT hours.'
      : `You are ${formatHours(remaining.value)} hours from completing your OJT requirement.`
  const due = props.data.next_steps.find((s) => s.type === 'report_due')
  return due && due.type === 'report_due'
    ? `${hours} Next: your Week ${due.week} report, for the week ending ${formatDate(due.date)}.`
    : hours
})

const reportStatus: Record<string, string> = {
  submitted: 'Awaiting signature',
  signed: 'Signed',
  returned: 'Returned',
}
</script>

<template>
  <!-- Not placed yet -->
  <DashboardFrame v-if="!stats" lede="You haven’t been placed with an Industry Partner yet.">
    <template #main>
      <section
        class="rounded-[10px] border border-dashed border-ink/20 bg-white px-6 py-10 text-center lg:col-span-2"
      >
        <h2 class="font-serif text-xl font-semibold text-ink">No placement yet</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-ink/70">
          Your OJT Coordinator assigns your Industry Partner. Meanwhile, keep your skills up to date
          so the recommendations fit you, and explore partners near you.
        </p>
        <div class="mt-5 flex justify-center gap-3">
          <RouterLink
            to="/student/skills"
            class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            Update my skills
          </RouterLink>
          <RouterLink
            to="/student/map"
            class="rounded-md border border-ink/20 px-4 py-2 text-sm font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            Find a partner
          </RouterLink>
        </div>
      </section>
    </template>
  </DashboardFrame>

  <DashboardFrame v-else :lede="lede">
    <template #stats>
      <StatCard
        label="Hours signed"
        :value="formatHours(stats.signed_hours)"
        :note="`of ${stats.required_hours} required${stats.pending_hours > 0 ? ` · ${formatHours(stats.pending_hours)} awaiting signature` : ''}`"
        :tone="remaining === 0 ? 'ok' : 'neutral'"
      />
      <StatCard
        label="Deployment"
        :value="stats.deployment_status === 'active' ? 'Active' : 'Completed'"
        :note="`${stats.partner.company}, ${stats.partner.city}`"
        :tone="stats.deployment_status === 'completed' ? 'ok' : 'neutral'"
      />
      <StatCard
        label="Weeks remaining"
        :value="data.weeks_remaining_estimate ?? '—'"
        :note="
          data.weeks_remaining_estimate === null
            ? 'Estimated after your first signed week'
            : 'Estimate at your current pace'
        "
      />
      <StatCard
        label="Last report"
        :value="stats.last_report_period_end ? formatDate(stats.last_report_period_end) : '—'"
        :note="
          stats.last_report_status
            ? (reportStatus[stats.last_report_status] ?? stats.last_report_status)
            : 'No report filed yet'
        "
        :tone="
          stats.last_report_status === 'returned'
            ? 'warn'
            : stats.last_report_status === 'signed'
              ? 'ok'
              : 'neutral'
        "
      />
    </template>

    <template #main>
      <TableCard title="My placement" link-to="/student/deployment" link-label="My deployment">
        <dl v-if="data.placement" class="divide-y divide-ink/5 text-sm">
          <div class="flex justify-between gap-4 py-2.5">
            <dt class="text-ink/65">Industry Partner</dt>
            <dd class="text-right text-ink">
              {{ data.placement.company }}, {{ data.placement.city }}
            </dd>
          </div>
          <div class="flex justify-between gap-4 py-2.5">
            <dt class="text-ink/65">Supervisor</dt>
            <dd class="text-right text-ink">{{ data.placement.supervisor }}</dd>
          </div>
          <div class="flex justify-between gap-4 py-2.5">
            <dt class="text-ink/65">OJT Coordinator</dt>
            <dd class="text-right text-ink">{{ data.placement.coordinator ?? '—' }}</dd>
          </div>
          <div class="flex justify-between gap-4 py-2.5">
            <dt class="text-ink/65">Started</dt>
            <dd class="text-right text-ink">{{ formatDate(data.placement.start_date) }}</dd>
          </div>
          <div class="flex justify-between gap-4 py-2.5">
            <dt class="text-ink/65">Next report</dt>
            <dd class="text-right text-ink">
              {{
                data.placement.next_report
                  ? `Week ${data.placement.next_report.week} · due ${formatDate(data.placement.next_report.date)}`
                  : '—'
              }}
            </dd>
          </div>
        </dl>
      </TableCard>
    </template>

    <template #actions>
      <section
        class="rounded-[10px] border border-ink/10 bg-white p-5"
        aria-labelledby="next-steps-title"
      >
        <h2 id="next-steps-title" class="font-serif text-lg font-semibold text-ink">
          Your next steps
        </h2>
        <p v-if="data.next_steps.length === 0" class="mt-3 text-sm text-ink/65">
          You’re all caught up.
          {{ remaining > 0 ? `${plural(Math.ceil(remaining), 'hour')} to go.` : '' }}
        </p>
        <ul v-else class="mt-3 space-y-2">
          <li v-for="(step, i) in data.next_steps" :key="i">
            <RouterLink
              :to="stepText(step).to"
              class="block rounded-md border px-3.5 py-2.5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              :class="
                step.type === 'evaluation_released'
                  ? 'border-ink/10 bg-white hover:border-ink/25'
                  : 'border-[#E8D9A8] bg-[#FBF6E8] hover:border-accent'
              "
            >
              <span class="block font-medium text-ink"
                >{{ stepText(step).title }} <span aria-hidden="true">→</span></span
              >
              <span class="block text-[12.5px] text-ink/65">{{ stepText(step).detail }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </template>
  </DashboardFrame>
</template>
