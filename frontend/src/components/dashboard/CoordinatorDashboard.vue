<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import StatCard from '@/components/charts/StatCard.vue'
import ActionList from '@/components/dashboard/ActionList.vue'
import DashboardFrame from '@/components/dashboard/DashboardFrame.vue'
import TableCard from '@/components/dashboard/TableCard.vue'
import { plural } from '@/lib/format'
import type { CoordinatorDashboard } from '@/types/dashboard'

const props = defineProps<{ data: CoordinatorDashboard }>()

const lede = computed(() => {
  const { pending_students: pending, overdue_interns: overdue } = props.data.stats
  const approvals =
    pending === 0
      ? 'No student accounts need approval.'
      : `${plural(pending, 'student account')} ${pending === 1 ? 'needs' : 'need'} approval.`
  const reports =
    overdue === 0
      ? 'All your deployed interns have filed a weekly report in the last two weeks.'
      : `${overdue} of your deployed interns ${overdue === 1 ? "hasn't" : "haven't"} filed a weekly report in over two weeks.`
  return `${approvals} ${reports}`
})

const partners = computed(() => props.data.interns_by_partner.filter((r) => !r.unplaced))
const unplaced = computed(() => props.data.interns_by_partner.find((r) => r.unplaced)?.interns ?? 0)

const actions = computed(() => [
  {
    label: 'Student approvals',
    detail: 'Accounts waiting for you',
    count: props.data.queue.pending_students,
    to: '/coordinator/approvals',
  },
  {
    label: 'Overdue weekly reports',
    detail: 'No report in over two weeks',
    count: props.data.queue.overdue_interns,
    to: '/coordinator/monitoring',
  },
  {
    label: 'Unplaced students',
    detail: 'Find them a partner in Recommendations',
    count: props.data.queue.unplaced_students,
    to: '/coordinator/recommendations',
  },
  {
    label: 'Evaluations to review',
    detail: 'Submitted by Industry Partners',
    count: props.data.queue.evaluations_to_review,
    to: '/coordinator/evaluations',
  },
])
</script>

<template>
  <DashboardFrame :lede="lede">
    <template #stats>
      <StatCard
        label="My interns"
        :value="data.stats.active_interns"
        :note="`${plural(data.stats.unplaced_students, 'student')} not yet placed`"
      />
      <StatCard
        label="Pending students"
        :value="data.stats.pending_students"
        :note="data.stats.pending_students > 0 ? 'Waiting for your approval' : 'Nothing to approve'"
        :tone="data.stats.pending_students > 0 ? 'warn' : 'ok'"
      />
      <StatCard
        label="Partner slots open"
        :value="data.stats.open_slots"
        note="Across active partners"
      />
      <StatCard
        label="Overdue reports"
        :value="data.stats.overdue_interns"
        :note="
          data.stats.overdue_interns > 0 ? 'No report in over two weeks' : 'Everyone is on track'
        "
        :tone="data.stats.overdue_interns > 0 ? 'warn' : 'ok'"
      />
    </template>

    <template #main>
      <TableCard title="My interns by host establishment" link-to="/coordinator/map">
        <table class="w-full text-left text-sm">
          <thead>
            <tr
              class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
            >
              <th scope="col" class="py-2 font-medium">Host establishment</th>
              <th scope="col" class="py-2 text-right font-medium">Interns</th>
              <th scope="col" class="py-2 text-right font-medium">Open slots</th>
              <th scope="col" class="py-2 text-right font-medium">Overdue</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="partners.length === 0">
              <td colspan="4" class="py-4 text-ink/65">None of your students is deployed yet.</td>
            </tr>
            <tr v-for="row in partners" :key="row.company ?? ''" class="border-b border-ink/5">
              <th scope="row" class="py-2.5 font-normal">
                <span class="block text-ink">{{ row.company }}</span>
                <span class="block text-[12.5px] text-ink/60">{{ row.city }}</span>
              </th>
              <td class="py-2.5 text-right font-mono text-[13px]">{{ row.interns }}</td>
              <td class="py-2.5 text-right font-mono text-[13px] text-ink/80">{{ row.open }}</td>
              <td
                class="py-2.5 text-right font-mono text-[13px]"
                :class="(row.overdue ?? 0) > 0 ? 'font-medium text-[#8a6a1f]' : 'text-ink/60'"
              >
                {{ row.overdue }}
              </td>
            </tr>
            <tr class="bg-canvas/60">
              <th scope="row" class="py-2.5 font-normal text-ink/75 italic">Not yet placed</th>
              <td class="py-2.5 text-right font-mono text-[13px]">{{ unplaced }}</td>
              <td class="py-2.5 text-right" colspan="2">
                <RouterLink
                  to="/coordinator/recommendations"
                  class="text-[13px] font-medium text-brand hover:underline"
                >
                  Recommend <span aria-hidden="true">→</span>
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </TableCard>
    </template>

    <template #actions>
      <ActionList :items="actions" />
    </template>
  </DashboardFrame>
</template>
