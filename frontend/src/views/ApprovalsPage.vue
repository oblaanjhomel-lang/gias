<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { downloadDocument } from '@/api/approvals'
import ApprovalCard from '@/components/ApprovalCard.vue'
import ReturnRegistrationDialog from '@/components/ReturnRegistrationDialog.vue'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { useApprovalsStore } from '@/stores/approvals'
import { useAuthStore } from '@/stores/auth'
import type { ApprovalStatus, ApprovalTab, PartnerDocument } from '@/types/approvals'

/** /admin/approvals (partners + students) and /coordinator/approvals (own program's students). */
const auth = useAuthStore()
const store = useApprovalsStore()
const isAdmin = computed(() => auth.role === 'admin')

onMounted(() => {
  store.$reset()
  store.tab = isAdmin.value ? 'partners' : 'students'
  void store.fetch()
  void store.refreshCounts()
})

const pendingCount = computed(() =>
  isAdmin.value ? store.totalPending : store.counts.students_pending,
)

// Tabs (admin only)

const tabs = computed<{ key: ApprovalTab; label: string; count: number }[]>(() => [
  { key: 'partners', label: 'Industry Partners', count: store.counts.partners_pending },
  { key: 'students', label: 'Students', count: store.counts.students_pending },
])

function selectTab(key: ApprovalTab): void {
  clearTimeout(searchTimer)
  searchInput.value = ''
  void store.setTab(key)
}

function onTabKeydown(event: KeyboardEvent, index: number): void {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  const last = tabs.value.length - 1
  const next =
    event.key === 'Home'
      ? 0
      : event.key === 'End'
        ? last
        : event.key === 'ArrowRight'
          ? (index + 1) % tabs.value.length
          : (index - 1 + tabs.value.length) % tabs.value.length
  const tab = tabs.value[next]
  if (!tab) return
  selectTab(tab.key)
  void nextTick(() => document.getElementById(`approvals-tab-${tab.key}`)?.focus())
}

// Pending / Returned filter and search

const filters: { value: ApprovalStatus; label: string }[] = [
  { value: 'pending', label: 'Pending' },
  { value: 'rejected', label: 'Returned' },
]

const searchInput = ref('')
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(searchInput, (value) => {
  clearTimeout(searchTimer)
  if (value === store.search) return
  searchTimer = setTimeout(() => void store.setSearch(value), 300)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

// Banners

const notice = ref<string | null>(null)
const actionError = ref<string | null>(null)

function announce(message: string, id: number): void {
  actionError.value = null
  notice.value = message
  // The card's buttons are gone; keep keyboard focus on that card.
  void nextTick(() => document.getElementById(`approval-${id}`)?.focus())
}

function fail(error: unknown): void {
  notice.value = null
  actionError.value = errorMessage(error)
}

// Actions

const busyId = ref<number | null>(null)

async function approve(id: number, name: string): Promise<void> {
  busyId.value = id
  try {
    await store.approve(id)
    announce(`Approved ${name}. They have been emailed a sign-in link.`, id)
  } catch (e) {
    fail(e)
  } finally {
    busyId.value = null
  }
}

const returning = ref<{ id: number; name: string } | null>(null)
const returnBusy = ref(false)
const returnError = ref<string | undefined>()

function openReturn(id: number, name: string): void {
  returnError.value = undefined
  returning.value = { id, name }
}

async function confirmReturn(reason: string): Promise<void> {
  if (!returning.value) return
  const { id, name } = returning.value
  returnBusy.value = true
  returnError.value = undefined
  try {
    await store.returnRegistration(id, reason)
    returning.value = null
    announce(`Returned ${name}’s registration. They have been emailed your reason.`, id)
  } catch (e) {
    const fields = validationErrors(e)
    if (fields?.reason) {
      returnError.value = fields.reason
    } else {
      returning.value = null
      fail(e)
    }
  } finally {
    returnBusy.value = false
  }
}

async function download(partnerId: number, document: PartnerDocument): Promise<void> {
  try {
    await downloadDocument(partnerId, document)
  } catch (e) {
    fail(e)
  }
}

// Display

const rows = computed(() => (store.tab === 'students' ? store.students : store.partners))

const emptyMessage = computed(() => {
  if (store.search.trim()) return 'No registrations match your search.'
  return store.status === 'pending' ? 'No pending registrations.' : 'No returned registrations.'
})
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">
          {{ isAdmin ? 'Approvals' : 'Student approvals' }}
        </h1>
        <p class="mt-1 max-w-2xl text-sm text-ink/70">
          <template v-if="isAdmin">
            Review self-registered Industry Partners and Students. Approved accounts can sign in
            right away.
          </template>
          <template v-else>
            Review students who registered for your program. Approved students can sign in right
            away.
          </template>
        </p>
      </div>
      <p class="font-mono text-sm font-medium tracking-[0.08em] text-accent-ink uppercase">
        {{ pendingCount }} pending
      </p>
    </div>

    <!-- Banners (live regions stay mounted so changes are announced) -->
    <div role="status">
      <div
        v-if="notice"
        class="flex items-start justify-between gap-4 rounded-md border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-ink"
      >
        <p>{{ notice }}</p>
        <button
          type="button"
          aria-label="Dismiss message"
          class="-my-1 rounded px-1.5 text-lg leading-none text-ink/70 hover:text-ink focus-visible:outline-2 focus-visible:outline-brand"
          @click="notice = null"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
    </div>
    <div role="alert">
      <div
        v-if="actionError || store.error"
        class="flex items-start justify-between gap-4 rounded-md border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-800"
      >
        <p>{{ actionError ?? store.error }}</p>
        <button
          v-if="actionError"
          type="button"
          aria-label="Dismiss error"
          class="-my-1 rounded px-1.5 text-lg leading-none text-red-800/80 hover:text-red-800 focus-visible:outline-2 focus-visible:outline-red-700"
          @click="actionError = null"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
    </div>

    <!-- Kind tabs (admin) -->
    <div
      v-if="isAdmin"
      role="tablist"
      aria-label="Registration type"
      class="flex gap-6 border-b border-ink/10"
    >
      <button
        v-for="(tab, index) in tabs"
        :id="`approvals-tab-${tab.key}`"
        :key="tab.key"
        type="button"
        role="tab"
        :aria-selected="store.tab === tab.key"
        aria-controls="approvals-panel"
        :tabindex="store.tab === tab.key ? 0 : -1"
        class="-mb-px border-b-2 px-0.5 pt-1 pb-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="
          store.tab === tab.key
            ? 'border-brand text-brand'
            : 'border-transparent text-ink/70 hover:text-ink'
        "
        @click="selectTab(tab.key)"
        @keydown="onTabKeydown($event, index)"
      >
        {{ tab.label }} <span class="font-mono text-xs">({{ tab.count }})</span>
      </button>
    </div>

    <div
      id="approvals-panel"
      :role="isAdmin ? 'tabpanel' : undefined"
      :aria-labelledby="isAdmin ? `approvals-tab-${store.tab}` : undefined"
      class="space-y-4"
    >
      <!-- Filter + search -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div
          role="group"
          aria-label="Show"
          class="inline-flex rounded-md border border-ink/20 bg-white p-0.5"
        >
          <button
            v-for="filter in filters"
            :key="filter.value"
            type="button"
            :aria-pressed="store.status === filter.value"
            class="rounded px-3.5 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand"
            :class="
              store.status === filter.value ? 'bg-brand text-white' : 'text-ink/75 hover:text-ink'
            "
            @click="store.setStatus(filter.value)"
          >
            {{ filter.label }}
          </button>
        </div>

        <div>
          <label for="approvals-search" class="sr-only">Search registrations</label>
          <input
            id="approvals-search"
            v-model="searchInput"
            type="search"
            autocomplete="off"
            :placeholder="
              store.tab === 'students'
                ? 'Search name, email, student no.'
                : 'Search company or city'
            "
            class="h-10 w-64 rounded-md border border-ink/25 bg-white px-3 text-sm text-ink placeholder:text-ink/50 focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
          />
        </div>
      </div>

      <!-- Cards -->
      <div
        :aria-busy="store.loading"
        class="space-y-3"
        :class="store.loading && rows.length ? 'opacity-60' : ''"
      >
        <p
          v-if="store.loading && !rows.length"
          class="rounded-[9px] border border-ink/10 bg-white px-5 py-10 text-center text-sm text-ink/70"
        >
          Loading registrations…
        </p>
        <p
          v-else-if="!rows.length"
          class="rounded-[9px] border border-ink/10 bg-white px-5 py-10 text-center text-sm text-ink/70"
        >
          {{ emptyMessage }}
        </p>

        <template v-if="store.tab === 'students'">
          <ApprovalCard
            v-for="row in store.students"
            :key="row.id"
            :student="row"
            :outcome="store.outcomes[row.id]"
            :busy="busyId === row.id"
            @approve="approve(row.id, row.name)"
            @return="openReturn(row.id, row.name)"
          />
        </template>
        <template v-else>
          <ApprovalCard
            v-for="row in store.partners"
            :key="row.id"
            :partner="row"
            :outcome="store.outcomes[row.id]"
            :busy="busyId === row.id"
            @approve="approve(row.id, row.company_name)"
            @return="openReturn(row.id, row.company_name)"
            @download="download(row.id, $event)"
          />
        </template>
      </div>

      <nav
        v-if="store.meta && store.meta.last_page > 1"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-between gap-3 text-sm"
      >
        <p class="text-ink/70">
          Showing {{ store.meta.from }}–{{ store.meta.to }} of {{ store.meta.total }}
        </p>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="store.meta.current_page <= 1 || store.loading"
            class="h-9 rounded-md border border-ink/20 bg-white px-3 font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
            @click="store.setPage(store.meta.current_page - 1)"
          >
            Previous
          </button>
          <span class="font-mono text-xs text-ink/70">
            Page {{ store.meta.current_page }} of {{ store.meta.last_page }}
          </span>
          <button
            type="button"
            :disabled="store.meta.current_page >= store.meta.last_page || store.loading"
            class="h-9 rounded-md border border-ink/20 bg-white px-3 font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
            @click="store.setPage(store.meta.current_page + 1)"
          >
            Next
          </button>
        </div>
      </nav>
    </div>

    <ReturnRegistrationDialog
      v-if="returning"
      :name="returning.name"
      :busy="returnBusy"
      :error="returnError"
      @cancel="returning = null"
      @confirm="confirmReturn"
    />
  </div>
</template>
