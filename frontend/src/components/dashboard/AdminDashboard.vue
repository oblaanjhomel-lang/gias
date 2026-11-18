<script setup lang="ts">
import { computed } from 'vue'
import StatCard from '@/components/charts/StatCard.vue'
import ActionList from '@/components/dashboard/ActionList.vue'
import DashboardFrame from '@/components/dashboard/DashboardFrame.vue'
import TableCard from '@/components/dashboard/TableCard.vue'
import { plural } from '@/lib/format'
import type { AdminDashboard } from '@/types/dashboard'

const props = defineProps<{ data: AdminDashboard }>()

const lede = computed(() => {
  const { pending_partners: pending, unlocated_partners: unlocated } = props.data.stats
  const waiting =
    pending === 0
      ? 'No Industry Partner registrations are waiting on you'
      : `${plural(pending, 'Industry Partner registration')} ${pending === 1 ? 'is' : 'are'} waiting on you`
  const located =
    unlocated > 0
      ? `, and ${plural(unlocated, 'address', 'addresses')} couldn't be located on the map`
      : ''
  return `${waiting}${located}.`
})

const noCoordinator = computed(() => props.data.stats.programs_without_coordinator)

const actions = computed(() => [
  {
    label: 'Partner approvals',
    detail: 'Review new Industry Partner registrations',
    count: props.data.queue.pending_partner_approvals,
    to: '/admin/approvals',
  },
  {
    label: 'Addresses not located',
    detail: 'Place their pins on the map',
    count: props.data.queue.unlocated_partners,
    to: '/admin/map',
  },
  {
    label: 'Programs without a coordinator',
    detail: 'Create or reactivate an OJT Coordinator',
    count: props.data.queue.programs_without_coordinator,
    to: '/admin/accounts',
  },
  {
    label: 'Evaluations to review',
    detail: 'Submitted by Industry Partners',
    count: props.data.queue.evaluations_awaiting_review,
    to: '/admin/evaluations',
  },
])
</script>

<template>
  <DashboardFrame :lede="lede">
    <template #stats>
      <StatCard
        label="Industry partners"
        :value="data.stats.active_partners"
        :note="`${data.stats.pending_partners} awaiting approval`"
        :tone="data.stats.pending_partners > 0 ? 'warn' : 'neutral'"
      />
      <StatCard
        label="Interns deployed"
        :value="data.stats.active_deployments"
        :note="`across ${plural(data.stats.provinces_covered, 'province')}`"
      />
      <StatCard
        label="Programs without a coordinator"
        :value="noCoordinator.length"
        :note="noCoordinator.length > 0 ? noCoordinator.join(', ') : 'Every program is covered'"
        :tone="noCoordinator.length > 0 ? 'warn' : 'ok'"
      />
      <StatCard
        label="Addresses not located"
        :value="data.stats.unlocated_partners"
        :note="
          data.stats.unlocated_partners > 0 ? 'Pin them on the map' : 'All partners are on the map'
        "
        :tone="data.stats.unlocated_partners > 0 ? 'warn' : 'ok'"
      />
    </template>

    <template #main>
      <TableCard
        title="Partner saturation"
        subtitle="Active partners closest to full"
        link-to="/admin/map"
      >
        <p v-if="data.saturation_top.length === 0" class="py-4 text-sm text-ink/65">
          No active partners with slots yet.
        </p>
        <table v-else class="w-full text-left text-sm">
          <thead>
            <tr
              class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
            >
              <th scope="col" class="py-2 font-medium">Partner</th>
              <th scope="col" class="py-2 text-right font-medium">Filled</th>
              <th scope="col" class="py-2 text-right font-medium">Open</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.saturation_top" :key="row.company" class="border-b border-ink/5">
              <th scope="row" class="py-2.5 font-normal">
                <span class="block text-ink">{{ row.company }}</span>
                <span class="block text-[12.5px] text-ink/60">{{ row.city }}</span>
              </th>
              <td class="py-2.5 text-right font-mono text-[13px]">
                {{ row.filled }} / {{ row.slots }}
              </td>
              <td
                class="py-2.5 text-right font-mono text-[13px]"
                :class="row.open === 0 ? 'font-medium text-[#8a6a1f]' : 'text-ink/80'"
              >
                {{ row.open === 0 ? 'Full' : row.open }}
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
