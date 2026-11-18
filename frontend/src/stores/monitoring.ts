import { defineStore } from 'pinia'
import * as monitoringApi from '@/api/monitoring'
import * as placementsApi from '@/api/placements'
import { errorMessage } from '@/lib/apiErrors'
import type { PaginationMeta } from '@/types/accounts'
import type {
  DeploymentDetail,
  MonitoringFilter,
  MonitoringRow,
  MonitoringSummary,
} from '@/types/monitoring'

/** Drops list responses that a newer filter / search has superseded. */
let latestList = 0

/** Coordinator / admin "Intern monitoring". */
export const useMonitoringStore = defineStore('monitoring', {
  state: () => ({
    filter: 'all' as MonitoringFilter,
    search: '',
    programId: null as number | null,
    province: '',
    page: 1,

    rows: [] as MonitoringRow[],
    meta: null as PaginationMeta | null,
    scopeTotal: 0,
    summary: null as MonitoringSummary | null,
    loading: false,

    /** The slide-over. */
    selectedId: null as number | null,
    detail: null as DeploymentDetail | null,
    loadingDetail: false,
    working: false,

    notice: null as string | null,
    error: null as string | null,
  }),

  actions: {
    async load(): Promise<void> {
      await Promise.all([this.fetch(), this.fetchSummary()])
    },

    async fetch(): Promise<void> {
      const request = ++latestList
      this.loading = true
      try {
        const result = await monitoringApi.listDeployments({
          filter: this.filter,
          search: this.search.trim(),
          programId: this.programId,
          province: this.province,
          page: this.page,
        })
        if (request !== latestList) return
        this.rows = result.rows
        this.meta = result.meta
        this.scopeTotal = result.scopeTotal
      } catch (e) {
        if (request === latestList) this.error = errorMessage(e, 'Could not load deployments.')
      } finally {
        if (request === latestList) this.loading = false
      }
    },

    async fetchSummary(): Promise<void> {
      try {
        this.summary = await monitoringApi.getSummary()
      } catch {
        // Cards are secondary; the table reports its own errors.
      }
    },

    setFilter(filter: MonitoringFilter): Promise<void> {
      this.filter = filter
      this.page = 1
      return this.fetch()
    },

    setSearch(search: string): Promise<void> {
      this.search = search
      this.page = 1
      return this.fetch()
    },

    setProgram(programId: number | null): Promise<void> {
      this.programId = programId
      this.page = 1
      return this.fetch()
    },

    setProvince(province: string): Promise<void> {
      this.province = province
      this.page = 1
      return this.fetch()
    },

    setPage(page: number): Promise<void> {
      this.page = page
      return this.fetch()
    },

    // Slide-over

    async open(id: number): Promise<void> {
      this.selectedId = id
      this.detail = null
      this.loadingDetail = true
      try {
        this.detail = await monitoringApi.getDeployment(id)
      } catch (e) {
        this.error = errorMessage(e)
        this.selectedId = null
      } finally {
        this.loadingDetail = false
      }
    },

    close(): void {
      this.selectedId = null
      this.detail = null
    },

    /** Returns an error message, or null on success. */
    async complete(options: { force?: boolean; note?: string } = {}): Promise<string | null> {
      if (!this.detail) return null
      this.working = true
      try {
        this.detail = await monitoringApi.completeDeployment(this.detail.id, options)
        this.notice = `Marked ${this.detail.student.name}’s deployment as completed.`
        await this.load()
        return null
      } catch (e) {
        return errorMessage(e)
      } finally {
        this.working = false
      }
    },

    async withdraw(): Promise<string | null> {
      if (!this.detail) return null
      this.working = true
      try {
        const result = await placementsApi.withdraw(this.detail.id)
        this.notice = `Withdrew ${result.student.name} from ${result.partner.company_name}.`
        this.detail = await monitoringApi.getDeployment(this.detail.id)
        await this.load()
        return null
      } catch (e) {
        return errorMessage(e)
      } finally {
        this.working = false
      }
    },
  },
})
