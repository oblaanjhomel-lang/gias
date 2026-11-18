import { defineStore } from 'pinia'
import * as coordinatorsApi from '@/api/coordinators'
import * as partnersApi from '@/api/partners'
import * as programsApi from '@/api/programs'
import { errorMessage } from '@/lib/apiErrors'
import type {
  AccountTab,
  Coordinator,
  CoordinatorPayload,
  IndustryPartner,
  ManagedStatus,
  PaginationMeta,
  PartnerPayload,
  Program,
  Status,
} from '@/types/accounts'

/** Drops responses from superseded requests (e.g. fast typing in the search box). */
let latestRequest = 0

export const useAccountsStore = defineStore('accounts', {
  state: () => ({
    tab: 'coordinators' as AccountTab,
    search: '',
    status: '' as Status | '',
    page: 1,

    coordinators: [] as Coordinator[],
    partners: [] as IndustryPartner[],
    meta: null as PaginationMeta | null,
    programsWithoutCoordinator: [] as Program[],
    /** Unfiltered totals for the tab labels. */
    counts: { coordinators: null, partners: null } as Record<AccountTab, number | null>,
    programs: [] as Program[],

    loading: false,
    error: null as string | null,
  }),

  getters: {
    hasFilters: (state): boolean => state.search.trim() !== '' || state.status !== '',
  },

  actions: {
    /** First load of the page. */
    async load(): Promise<void> {
      await Promise.all([this.fetch(), this.refreshCounts(), this.loadPrograms()])
    },

    async fetch(): Promise<void> {
      const request = ++latestRequest
      this.loading = true
      this.error = null
      const params = { search: this.search.trim(), status: this.status, page: this.page }

      try {
        if (this.tab === 'coordinators') {
          const result = await coordinatorsApi.list(params)
          if (request !== latestRequest) return
          this.coordinators = result.data
          this.meta = result.meta
          this.programsWithoutCoordinator = result.meta.programs_without_active_coordinator
        } else {
          const result = await partnersApi.list(params)
          if (request !== latestRequest) return
          this.partners = result.data
          this.meta = result.meta
        }

        // e.g. the last row on the last page was deleted
        if (this.meta && this.meta.last_page > 0 && this.page > this.meta.last_page) {
          this.page = this.meta.last_page
          await this.fetch()
        }
      } catch (e) {
        if (request === latestRequest) this.error = errorMessage(e, 'Could not load accounts.')
      } finally {
        if (request === latestRequest) this.loading = false
      }
    },

    async refreshCounts(): Promise<void> {
      try {
        const [coordinators, partners] = await Promise.all([
          coordinatorsApi.list(),
          partnersApi.list(),
        ])
        this.counts = { coordinators: coordinators.meta.total, partners: partners.meta.total }
        this.programsWithoutCoordinator = coordinators.meta.programs_without_active_coordinator
      } catch {
        // Counts are decorative; the table shows its own error.
      }
    },

    async loadPrograms(): Promise<void> {
      if (this.programs.length === 0) this.programs = await programsApi.list()
    },

    setTab(tab: AccountTab): Promise<void> | void {
      if (tab === this.tab) return
      this.tab = tab
      this.search = ''
      this.status = ''
      this.page = 1
      this.meta = null
      return this.fetch()
    },

    setSearch(search: string): Promise<void> {
      this.search = search
      this.page = 1
      return this.fetch()
    },

    setStatusFilter(status: Status | ''): Promise<void> {
      this.status = status
      this.page = 1
      return this.fetch()
    },

    setPage(page: number): Promise<void> {
      this.page = page
      return this.fetch()
    },

    // Coordinators

    async createCoordinator(payload: CoordinatorPayload): Promise<Coordinator> {
      const created = await coordinatorsApi.create(payload)
      await Promise.all([this.fetch(), this.refreshCounts()])
      return created
    },

    async updateCoordinator(id: number, payload: CoordinatorPayload): Promise<Coordinator> {
      const updated = await coordinatorsApi.update(id, payload)
      await this.fetch()
      return updated
    },

    async setCoordinatorStatus(id: number, status: ManagedStatus): Promise<Coordinator> {
      const updated = await coordinatorsApi.setStatus(id, status)
      await this.fetch()
      return updated
    },

    async removeCoordinator(id: number): Promise<void> {
      await coordinatorsApi.remove(id)
      await Promise.all([this.fetch(), this.refreshCounts()])
    },

    // Industry partners

    async createPartner(payload: PartnerPayload): Promise<IndustryPartner> {
      const created = await partnersApi.create(payload)
      await Promise.all([this.fetch(), this.refreshCounts()])
      return created
    },

    async updatePartner(id: number, payload: PartnerPayload): Promise<IndustryPartner> {
      const updated = await partnersApi.update(id, payload)
      await this.fetch()
      return updated
    },

    async setPartnerStatus(id: number, status: ManagedStatus): Promise<IndustryPartner> {
      const updated = await partnersApi.setStatus(id, status)
      await this.fetch()
      return updated
    },

    async removePartner(id: number): Promise<void> {
      await partnersApi.remove(id)
      await Promise.all([this.fetch(), this.refreshCounts()])
    },
  },
})
