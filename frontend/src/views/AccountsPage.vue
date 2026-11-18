<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue'
import StatusPill from '@/components/StatusPill.vue'
import UserFormModal from '@/components/UserFormModal.vue'
import { errorMessage } from '@/lib/apiErrors'
import { useAccountsStore } from '@/stores/accounts'
import type {
  AccountTab,
  Coordinator,
  GeocodeStatus,
  IndustryPartner,
  ManagedStatus,
  Status,
} from '@/types/accounts'

const store = useAccountsStore()

onMounted(() => {
  void store.load()
})

// Tabs (ARIA tabs pattern: arrow keys / Home / End move between tabs)

const tabs: { key: AccountTab; label: string }[] = [
  { key: 'coordinators', label: 'OJT Coordinators' },
  { key: 'partners', label: 'Industry Partners' },
]

function selectTab(key: AccountTab): void {
  clearTimeout(searchTimer)
  searchInput.value = ''
  void store.setTab(key)
}

function onTabKeydown(event: KeyboardEvent, index: number): void {
  const last = tabs.length - 1
  const next =
    event.key === 'ArrowRight'
      ? index === last
        ? 0
        : index + 1
      : event.key === 'ArrowLeft'
        ? index === 0
          ? last
          : index - 1
        : event.key === 'Home'
          ? 0
          : event.key === 'End'
            ? last
            : null
  const tab = next === null ? undefined : tabs[next]
  if (!tab) return

  event.preventDefault()
  selectTab(tab.key)
  void nextTick(() => document.getElementById(`tab-${tab.key}`)?.focus())
}

const isCoordinators = computed(() => store.tab === 'coordinators')

// Search (debounced, server-side) and status filter

const searchInput = ref(store.search)
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(searchInput, (value) => {
  clearTimeout(searchTimer)
  if (value === store.search) return
  searchTimer = setTimeout(() => void store.setSearch(value), 300)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

const statusFilter = computed({
  get: () => store.status,
  set: (value: Status | '') => void store.setStatusFilter(value),
})

const statusOptions = computed<{ value: Status | ''; label: string }[]>(() => [
  { value: '', label: 'All statuses' },
  { value: 'active', label: 'Active' },
  ...(isCoordinators.value
    ? []
    : [
        { value: 'pending' as const, label: 'Pending' },
        { value: 'rejected' as const, label: 'Returned' },
      ]),
  { value: 'inactive', label: 'Inactive' },
])

// Banners

const notice = ref<string | null>(null)
const actionError = ref<string | null>(null)

function announce(message: string): void {
  actionError.value = null
  notice.value = message
}

function fail(error: unknown): void {
  notice.value = null
  actionError.value = errorMessage(error)
}

// Add / edit modal

const formOpen = ref(false)
const editingCoordinator = ref<Coordinator | null>(null)
const editingPartner = ref<IndustryPartner | null>(null)

function openCreate(): void {
  editingCoordinator.value = null
  editingPartner.value = null
  formOpen.value = true
}

function openEditCoordinator(row: Coordinator): void {
  editingCoordinator.value = row
  formOpen.value = true
}

function openEditPartner(row: IndustryPartner): void {
  editingPartner.value = row
  formOpen.value = true
}

function onSaved(message: string): void {
  formOpen.value = false
  announce(message)
}

// Activate / deactivate

const busyId = ref<number | null>(null)

async function toggleStatus(id: number, name: string, current: Status): Promise<void> {
  const next: ManagedStatus = current === 'active' ? 'inactive' : 'active'
  busyId.value = id
  try {
    if (isCoordinators.value) await store.setCoordinatorStatus(id, next)
    else await store.setPartnerStatus(id, next)
    announce(
      next === 'inactive'
        ? `Deactivated ${name}. They have been signed out.`
        : `Activated ${name}.`,
    )
  } catch (e) {
    fail(e)
  } finally {
    busyId.value = null
  }
}

// Delete

const deleting = ref<{ id: number; name: string } | null>(null)
const deleteBusy = ref(false)

async function confirmDelete(): Promise<void> {
  if (!deleting.value) return
  const { id, name } = deleting.value
  deleteBusy.value = true
  try {
    if (isCoordinators.value) await store.removeCoordinator(id)
    else await store.removePartner(id)
    announce(`Deleted ${name}.`)
  } catch (e) {
    fail(e)
  } finally {
    deleteBusy.value = false
    deleting.value = null
  }
}

// Display helpers

const dateFormat = new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' })
const formatDate = (iso: string) => dateFormat.format(new Date(iso))
const listFormat = new Intl.ListFormat('en', { style: 'long', type: 'conjunction' })

const missingCoordinatorPrograms = computed(() =>
  listFormat.format(store.programsWithoutCoordinator.map((p) => p.code)),
)

const geocodeNotes: Record<GeocodeStatus, { text: string; class: string }> = {
  located: { text: 'Located', class: 'text-brand' },
  failed: { text: 'Address not found — fix on the map later', class: 'text-accent-ink' },
  manual: { text: 'Pinned manually', class: 'text-ink/70' },
  pending: { text: 'Not located yet', class: 'text-ink/70' },
}

const rows = computed(() => (isCoordinators.value ? store.coordinators : store.partners))
const columnCount = 5

const emptyMessage = computed(() => {
  if (store.hasFilters) return 'No accounts match your search or filter.'
  return isCoordinators.value
    ? 'No coordinator accounts yet. Use “Add coordinator” to create the first one.'
    : 'No industry partner accounts yet. Use “Add industry partner” to create one.'
})

const actionButton =
  'rounded px-2 py-1 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-1 disabled:opacity-50'
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">User accounts</h1>
        <p class="mt-1 max-w-2xl text-sm text-ink/70">
          Create and manage OJT Coordinator and Industry Partner accounts. Coordinator accounts can
          only be created here.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex h-11 items-center gap-1.5 rounded-md bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        @click="openCreate"
      >
        <span aria-hidden="true" class="text-base leading-none">+</span>
        {{ isCoordinators ? 'Add coordinator' : 'Add industry partner' }}
      </button>
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

    <!-- Card -->
    <section class="overflow-hidden rounded-lg border border-ink/10 bg-white shadow-sm">
      <div class="flex flex-wrap items-end justify-between gap-4 border-b border-ink/10 px-5 pt-3">
        <div role="tablist" aria-label="Account type" class="flex gap-6">
          <button
            v-for="(tab, index) in tabs"
            :id="`tab-${tab.key}`"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="store.tab === tab.key"
            aria-controls="accounts-panel"
            :tabindex="store.tab === tab.key ? 0 : -1"
            class="-mb-px border-b-2 px-0.5 pt-2 pb-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            :class="
              store.tab === tab.key
                ? 'border-brand text-brand'
                : 'border-transparent text-ink/70 hover:text-ink'
            "
            @click="selectTab(tab.key)"
            @keydown="onTabKeydown($event, index)"
          >
            {{ tab.label }}
            <span class="font-mono text-xs">({{ store.counts[tab.key] ?? '–' }})</span>
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-3 pb-3">
          <label for="accounts-search" class="sr-only">
            Search {{ isCoordinators ? 'coordinators' : 'industry partners' }}
          </label>
          <input
            id="accounts-search"
            v-model="searchInput"
            type="search"
            autocomplete="off"
            :placeholder="
              isCoordinators ? 'Search name, email, program' : 'Search company, contact, city'
            "
            class="h-10 w-64 rounded-md border border-ink/25 bg-white px-3 text-sm text-ink placeholder:text-ink/50 focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
          />
          <label for="accounts-status" class="sr-only">Filter by status</label>
          <select
            id="accounts-status"
            v-model="statusFilter"
            class="h-10 rounded-md border border-ink/25 bg-white px-3 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
          >
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div
        id="accounts-panel"
        role="tabpanel"
        :aria-labelledby="`tab-${store.tab}`"
        :aria-busy="store.loading"
      >
        <p
          v-if="isCoordinators && store.programsWithoutCoordinator.length"
          class="border-b border-accent/40 bg-accent/15 px-5 py-2.5 text-sm text-ink"
        >
          <span class="font-medium text-accent-ink"
            >No active coordinator for {{ missingCoordinatorPrograms }}.</span
          >
          Students in those programs can’t be approved until one is active.
        </p>

        <div class="overflow-x-auto" :class="store.loading && rows.length ? 'opacity-60' : ''">
          <table class="w-full text-left text-sm">
            <caption class="sr-only">
              {{
                isCoordinators ? 'OJT Coordinator accounts' : 'Industry Partner accounts'
              }}
            </caption>
            <thead
              class="bg-canvas/60 font-mono text-[11px] tracking-[0.06em] text-ink/70 uppercase"
            >
              <tr v-if="isCoordinators">
                <th scope="col" class="px-5 py-3 font-medium">Name</th>
                <th scope="col" class="px-5 py-3 font-medium">Program</th>
                <th scope="col" class="px-5 py-3 font-medium">Created</th>
                <th scope="col" class="px-5 py-3 font-medium">Status</th>
                <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
              </tr>
              <tr v-else>
                <th scope="col" class="px-5 py-3 font-medium">Company</th>
                <th scope="col" class="px-5 py-3 font-medium">Location</th>
                <th scope="col" class="px-5 py-3 font-medium">Slots</th>
                <th scope="col" class="px-5 py-3 font-medium">Status</th>
                <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-ink/10">
              <tr v-if="store.loading && !rows.length">
                <td :colspan="columnCount" class="px-5 py-10 text-center text-ink/70">
                  Loading accounts…
                </td>
              </tr>
              <tr v-else-if="!rows.length">
                <td :colspan="columnCount" class="px-5 py-10 text-center text-ink/70">
                  {{ emptyMessage }}
                </td>
              </tr>

              <!-- Coordinators -->
              <template v-if="isCoordinators">
                <tr v-for="row in store.coordinators" :key="row.id" class="align-top">
                  <td class="px-5 py-3.5">
                    <p class="font-medium text-ink">{{ row.name }}</p>
                    <p class="text-ink/70">{{ row.email }}</p>
                  </td>
                  <td class="px-5 py-3.5">
                    <template v-if="row.program">
                      <p class="font-mono text-xs font-medium text-ink">{{ row.program.code }}</p>
                      <p class="text-ink/70">{{ row.program.name }}</p>
                    </template>
                    <span v-else class="text-ink/70">—</span>
                  </td>
                  <td class="px-5 py-3.5 font-mono text-xs whitespace-nowrap text-ink/75">
                    {{ formatDate(row.created_at) }}
                  </td>
                  <td class="px-5 py-3.5"><StatusPill :status="row.status" /></td>
                  <td class="px-5 py-3.5">
                    <div class="flex justify-end gap-1 whitespace-nowrap">
                      <button
                        type="button"
                        :aria-label="`Edit ${row.name}`"
                        :class="[
                          actionButton,
                          'text-brand hover:bg-brand/5 focus-visible:outline-brand',
                        ]"
                        @click="openEditCoordinator(row)"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        :disabled="busyId === row.id"
                        :aria-label="`${row.status === 'active' ? 'Deactivate' : 'Activate'} ${row.name}`"
                        :class="[
                          actionButton,
                          'text-ink hover:bg-ink/5 focus-visible:outline-brand',
                        ]"
                        @click="toggleStatus(row.id, row.name, row.status)"
                      >
                        {{ row.status === 'active' ? 'Deactivate' : 'Activate' }}
                      </button>
                      <button
                        type="button"
                        :aria-label="`Delete ${row.name}`"
                        :class="[
                          actionButton,
                          'text-red-700 hover:bg-red-50 focus-visible:outline-red-700',
                        ]"
                        @click="deleting = { id: row.id, name: row.name }"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- Industry partners -->
              <template v-else>
                <tr v-for="row in store.partners" :key="row.id" class="align-top">
                  <td class="px-5 py-3.5">
                    <p class="font-medium text-ink">{{ row.company_name }}</p>
                    <p class="text-ink/70">{{ row.contact_person }} · {{ row.email }}</p>
                  </td>
                  <td class="px-5 py-3.5">
                    <p class="text-ink">{{ row.city }}, {{ row.province }}</p>
                    <p class="text-xs" :class="geocodeNotes[row.geocode_status].class">
                      {{ geocodeNotes[row.geocode_status].text }}
                    </p>
                  </td>
                  <td class="px-5 py-3.5 font-mono text-ink">{{ row.intern_slots }}</td>
                  <td class="px-5 py-3.5"><StatusPill :status="row.status" /></td>
                  <td class="px-5 py-3.5">
                    <div class="flex justify-end gap-1 whitespace-nowrap">
                      <button
                        type="button"
                        :aria-label="`Edit ${row.company_name}`"
                        :class="[
                          actionButton,
                          'text-brand hover:bg-brand/5 focus-visible:outline-brand',
                        ]"
                        @click="openEditPartner(row)"
                      >
                        Edit
                      </button>
                      <!-- Self-registered and awaiting approval: review it in the approvals queue. -->
                      <RouterLink
                        v-if="row.status === 'pending'"
                        :to="{ name: 'admin-approvals' }"
                        :aria-label="`Review ${row.company_name}'s registration`"
                        :class="[
                          actionButton,
                          'text-accent-ink hover:bg-accent/10 focus-visible:outline-brand',
                        ]"
                      >
                        Review
                      </RouterLink>
                      <button
                        v-else
                        type="button"
                        :disabled="busyId === row.id"
                        :aria-label="`${row.status === 'active' ? 'Deactivate' : 'Activate'} ${row.company_name}`"
                        :class="[
                          actionButton,
                          'text-ink hover:bg-ink/5 focus-visible:outline-brand',
                        ]"
                        @click="toggleStatus(row.id, row.company_name, row.status)"
                      >
                        {{ row.status === 'active' ? 'Deactivate' : 'Activate' }}
                      </button>
                      <button
                        type="button"
                        :aria-label="`Delete ${row.company_name}`"
                        :class="[
                          actionButton,
                          'text-red-700 hover:bg-red-50 focus-visible:outline-red-700',
                        ]"
                        @click="deleting = { id: row.id, name: row.company_name }"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <nav
          v-if="store.meta && store.meta.total > 0"
          aria-label="Pagination"
          class="flex flex-wrap items-center justify-between gap-3 border-t border-ink/10 px-5 py-3 text-sm"
        >
          <p class="text-ink/70">
            Showing {{ store.meta.from }}–{{ store.meta.to }} of {{ store.meta.total }}
          </p>
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="store.meta.current_page <= 1 || store.loading"
              class="h-9 rounded-md border border-ink/20 px-3 font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
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
              class="h-9 rounded-md border border-ink/20 px-3 font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
              @click="store.setPage(store.meta.current_page + 1)"
            >
              Next
            </button>
          </div>
        </nav>
      </div>
    </section>

    <UserFormModal
      v-if="formOpen"
      :kind="store.tab"
      :coordinator="editingCoordinator"
      :partner="editingPartner"
      @close="formOpen = false"
      @saved="onSaved"
    />

    <ConfirmDeleteDialog
      v-if="deleting"
      :name="deleting.name"
      :busy="deleteBusy"
      @cancel="deleting = null"
      @confirm="confirmDelete"
    />
  </div>
</template>
