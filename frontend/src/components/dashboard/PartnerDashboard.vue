<script setup lang="ts">
import { computed } from 'vue'
import StatCard from '@/components/charts/StatCard.vue'
import ActionList from '@/components/dashboard/ActionList.vue'
import DashboardFrame from '@/components/dashboard/DashboardFrame.vue'
import TableCard from '@/components/dashboard/TableCard.vue'
import { formatDate, formatHours, plural } from '@/lib/format'
import type { PartnerDashboard } from '@/types/dashboard'

const props = defineProps<{ data: PartnerDashboard }>()

const openSlots = computed(() =>
  Math.max(0, props.data.stats.slots - props.data.stats.interns_hosted),
)

const lede = computed(() => {
  const toSign = props.data.stats.reports_to_sign
  const sign =
    toSign === 0
      ? 'No weekly reports are waiting for your signature'
      : `${plural(toSign, 'weekly report')} ${toSign === 1 ? 'is' : 'are'} waiting for your signature`
  const slots =
    openSlots.value === 0
      ? 'all your intern slots are filled.'
      : `you have ${plural(openSlots.value, 'open intern slot')}.`
  return `${sign}, and ${slots}`
})

const actions = computed(() => [
  {
    label: 'Weekly reports to sign',
    detail: 'Countersign or return with a note',
    count: props.data.queue.reports_to_sign,
    to: '/partner/interns',
  },
  {
    label: 'Evaluations to complete',
    detail: 'Midterm or final windows that are open',
    count: props.data.queue.evaluations_open,
    to: '/partner/evaluations',
  },
])
</script>

<template>
  <DashboardFrame :lede="lede">
    <template #stats>
      <StatCard
        label="Interns hosted"
        :value="data.stats.interns_hosted"
        :note="`of ${plural(data.stats.slots, 'slot')}`"
      />
      <StatCard
        label="Reports to sign"
        :value="data.stats.reports_to_sign"
        :note="data.stats.reports_to_sign > 0 ? 'Waiting for your signature' : 'All signed'"
        :tone="data.stats.reports_to_sign > 0 ? 'warn' : 'ok'"
      />
      <StatCard
        label="Evaluations open"
        :value="data.stats.evaluations_open"
        :note="data.stats.evaluations_open > 0 ? 'Ready for your rating' : 'Nothing due'"
        :tone="data.stats.evaluations_open > 0 ? 'warn' : 'neutral'"
      />
      <StatCard
        label="Programs served"
        :value="data.stats.programs_served.length"
        :note="data.stats.programs_served.join(', ') || 'No interns yet'"
      />
    </template>

    <template #main>
      <TableCard title="Interns at our site" link-to="/partner/interns" link-label="All interns">
        <p v-if="data.interns.length === 0" class="py-4 text-sm text-ink/65">
          No interns are deployed with you yet.
        </p>
        <table v-else class="w-full text-left text-sm">
          <thead>
            <tr
              class="border-b border-ink/15 font-mono text-[11px] tracking-wider text-ink/65 uppercase"
            >
              <th scope="col" class="py-2 font-medium">Intern</th>
              <th scope="col" class="py-2 text-right font-medium">Signed hours</th>
              <th scope="col" class="py-2 text-right font-medium">Last report</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.interns" :key="row.name" class="border-b border-ink/5">
              <th scope="row" class="py-2.5 font-normal">
                <span class="block text-ink">{{ row.name }}</span>
                <span class="block font-mono text-[11.5px] text-ink/60">{{ row.program }}</span>
              </th>
              <td class="py-2.5 text-right font-mono text-[13px]">
                {{ formatHours(row.signed_hours) }} / {{ row.required_hours }}
              </td>
              <td class="py-2.5 text-right text-[13px] text-ink/75">
                {{ row.last_report ? formatDate(row.last_report) : '—' }}
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
