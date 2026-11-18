<script setup lang="ts">
import { addDays, formatHours, parseDate } from '@/lib/format'
import { DAY_LABELS, DAYS, type WeeklyReport } from '@/types/monitoring'

/** Read-only daily hours, tasks and learnings of a report. */
defineProps<{ report: WeeklyReport }>()

const dayOfMonth = (ymd: string) => parseDate(ymd).getDate()
</script>

<template>
  <div class="space-y-3">
    <table class="w-full table-fixed text-center text-sm">
      <caption class="sr-only">
        Hours per day
      </caption>
      <thead class="font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
        <tr>
          <th v-for="(day, i) in DAYS" :key="day" scope="col" class="pb-1 font-medium">
            {{ DAY_LABELS[day] }} {{ dayOfMonth(addDays(report.period_start, i)) }}
          </th>
          <th scope="col" class="pb-1 font-medium">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr class="font-mono">
          <td v-for="day in DAYS" :key="day" class="rounded bg-canvas py-1.5 text-ink">
            {{ formatHours(report.daily_hours[day] ?? 0) }}
          </td>
          <td class="py-1.5 font-medium text-brand">{{ formatHours(report.total_hours) }}</td>
        </tr>
      </tbody>
    </table>
    <div>
      <p class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase">
        Tasks
      </p>
      <p class="mt-0.5 text-sm whitespace-pre-line text-ink">{{ report.tasks }}</p>
    </div>
    <div v-if="report.learnings">
      <p class="font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase">
        Learnings
      </p>
      <p class="mt-0.5 text-sm whitespace-pre-line text-ink">{{ report.learnings }}</p>
    </div>
  </div>
</template>
