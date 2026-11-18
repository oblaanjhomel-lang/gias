<script setup lang="ts">
import { computed } from 'vue'
import { formatBytes, formatDate, yearLevel } from '@/lib/format'
import type {
  ApprovalOutcome,
  PartnerApproval,
  PartnerDocument,
  StudentApproval,
} from '@/types/approvals'
import { DOCUMENT_TYPES } from '@/types/approvals'

/** One registration in the approvals queue: a student OR a partner. */
const props = defineProps<{
  student?: StudentApproval
  partner?: PartnerApproval
  /** Set right after an action; the card stays until the list refreshes. */
  outcome?: ApprovalOutcome
  busy: boolean
}>()

const emit = defineEmits<{
  approve: []
  return: []
  download: [document: PartnerDocument]
}>()

const record = computed(() => (props.student ?? props.partner)!)
const isStudent = computed(() => props.student !== undefined)
const isReturned = computed(() => record.value.status === 'rejected' && !props.outcome)
const title = computed(() => props.student?.name ?? props.partner?.company_name ?? '')

const detail = computed(() => {
  if (props.student) {
    const s = props.student
    return [s.program?.code ?? 'No program', yearLevel(s.year_level), s.student_number].join(' · ')
  }
  const p = props.partner!
  return [
    `${p.intern_slots} intern ${p.intern_slots === 1 ? 'slot' : 'slots'}`,
    `${p.city}, ${p.province}`,
  ].join(' · ')
})

const geocoded = computed(() => {
  const r = record.value
  if (r.latitude !== null && r.longitude !== null) {
    return { text: `${r.latitude.toFixed(4)}, ${r.longitude.toFixed(4)}`, failed: false }
  }
  return {
    text: r.geocode_status === 'failed' ? 'failed — address too vague' : 'not located yet',
    failed: r.geocode_status === 'failed',
  }
})

const typeLabel = (type: string) => DOCUMENT_TYPES.find((t) => t.value === type)?.label ?? type

const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <article
    class="flex flex-col gap-5 rounded-[9px] border border-ink/10 bg-white p-5 shadow-sm sm:flex-row"
    :aria-labelledby="`approval-${record.id}`"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-3">
        <span
          class="rounded bg-brand/10 px-2 py-0.5 font-mono text-[10.5px] font-medium tracking-[0.08em] text-brand uppercase"
        >
          {{ isStudent ? 'Student' : 'Industry partner' }}
        </span>
        <span class="font-mono text-[11px] tracking-[0.06em] text-ink/70 uppercase">
          Filed {{ formatDate(record.created_at) }}
        </span>
      </div>

      <!-- tabindex: the page moves focus here after an action removes the buttons. -->
      <h2
        :id="`approval-${record.id}`"
        tabindex="-1"
        class="mt-2 font-serif text-xl font-semibold text-ink focus:outline-none"
      >
        {{ title }}
      </h2>
      <p class="mt-0.5 text-sm text-ink/75">{{ detail }}</p>

      <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
        <!-- Student -->
        <template v-if="student">
          <div>
            <dt :class="label">Contact</dt>
            <dd class="mt-0.5 text-ink">
              {{ student.contact_number }}<br />
              <span class="break-all text-ink/75">{{ student.email }}</span>
            </dd>
          </div>
          <div>
            <dt :class="label">Home address</dt>
            <dd class="mt-0.5 text-ink">
              {{ student.address }}, {{ student.city }}, {{ student.province }}
            </dd>
          </div>
        </template>

        <!-- Industry partner -->
        <template v-else-if="partner">
          <div>
            <dt :class="label">Contact person</dt>
            <dd class="mt-0.5 text-ink">
              {{ partner.contact_person }}
              <template v-if="partner.contact_number">· {{ partner.contact_number }}</template
              ><br />
              <span class="break-all text-ink/75">{{ partner.email }}</span>
            </dd>
          </div>
          <div>
            <dt :class="label">Documents</dt>
            <dd class="mt-0.5">
              <ul v-if="partner.documents.length" class="space-y-1">
                <li v-for="document in partner.documents" :key="document.id">
                  <button
                    type="button"
                    class="text-left font-medium text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    :aria-label="`Download ${typeLabel(document.type)}: ${document.original_name}`"
                    @click="emit('download', document)"
                  >
                    {{ typeLabel(document.type) }}
                  </button>
                  <span class="font-mono text-xs text-ink/70">
                    · {{ formatBytes(document.size) }}</span
                  >
                </li>
              </ul>
              <span v-else class="text-ink/70">None uploaded</span>
            </dd>
          </div>
        </template>

        <div>
          <dt :class="label">Geocoded</dt>
          <dd
            class="mt-0.5 font-mono text-xs"
            :class="geocoded.failed ? 'text-red-700' : 'text-ink'"
          >
            {{ geocoded.text }}
          </dd>
        </div>
      </dl>

      <div
        v-if="record.review_note && record.status === 'rejected'"
        class="mt-4 rounded-md border-l-[3px] border-red-700 bg-red-50 px-3 py-2 text-sm text-ink"
      >
        <p :class="label">
          Returned<template v-if="record.reviewed_at">
            {{ formatDate(record.reviewed_at) }}</template
          >
        </p>
        <p class="mt-0.5">{{ record.review_note }}</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex shrink-0 flex-col gap-2 sm:w-[132px]">
      <p
        v-if="outcome === 'approved'"
        class="rounded-md bg-brand/10 px-3 py-2.5 text-center text-sm font-medium text-brand"
      >
        ✓ Approved
      </p>
      <p
        v-else-if="outcome === 'returned'"
        class="rounded-md bg-red-50 px-3 py-2.5 text-center text-sm font-medium text-red-800"
      >
        Returned
      </p>
      <template v-else>
        <button
          type="button"
          :disabled="busy"
          :aria-label="`Approve ${title}`"
          class="h-10 rounded-md bg-brand px-3 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-70"
          @click="emit('approve')"
        >
          {{ busy ? 'Working…' : 'Approve' }}
        </button>
        <button
          v-if="!isReturned"
          type="button"
          :disabled="busy"
          :aria-label="`Return ${title}`"
          class="h-10 rounded-md border border-ink/25 px-3 text-sm font-medium text-ink hover:border-red-700 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-70"
          @click="emit('return')"
        >
          Return
        </button>
      </template>
    </div>
  </article>
</template>
