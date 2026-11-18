import { defineStore } from 'pinia'
import * as approvalsApi from '@/api/approvals'
import { errorMessage } from '@/lib/apiErrors'
import type { PaginationMeta } from '@/types/accounts'
import type {
  ApprovalCounts,
  ApprovalOutcome,
  ApprovalStatus,
  ApprovalTab,
  PartnerApproval,
  StudentApproval,
} from '@/types/approvals'

/** Drops responses from superseded requests (e.g. fast typing in the search box). */
let latestRequest = 0

export const useApprovalsStore = defineStore('approvals', {
  state: () => ({
    tab: 'students' as ApprovalTab,
    status: 'pending' as ApprovalStatus,
    search: '',
    page: 1,

    students: [] as StudentApproval[],
    partners: [] as PartnerApproval[],
    meta: null as PaginationMeta | null,
    counts: { students_pending: 0, partners_pending: 0 } as ApprovalCounts,
    /** Cards acted on since the last fetch: updated in place, dropped on the next refresh. */
    outcomes: {} as Record<number, ApprovalOutcome>,

    loading: false,
    error: null as string | null,
  }),

  getters: {
    totalPending: (state): number => state.counts.students_pending + state.counts.partners_pending,
  },

  actions: {
    async fetch(): Promise<void> {
      const request = ++latestRequest
      this.loading = true
      this.error = null
      const params = { status: this.status, search: this.search.trim(), page: this.page }

      try {
        if (this.tab === 'students') {
          const result = await approvalsApi.listStudents(params)
          if (request !== latestRequest) return
          this.students = result.data
          this.meta = result.meta
        } else {
          const result = await approvalsApi.listPartners(params)
          if (request !== latestRequest) return
          this.partners = result.data
          this.meta = result.meta
        }
        this.outcomes = {}

        if (this.meta && this.meta.last_page > 0 && this.page > this.meta.last_page) {
          this.page = this.meta.last_page
          await this.fetch()
        }
      } catch (e) {
        if (request === latestRequest) this.error = errorMessage(e, 'Could not load registrations.')
      } finally {
        if (request === latestRequest) this.loading = false
      }
    },

    async refreshCounts(): Promise<void> {
      try {
        this.counts = await approvalsApi.counts()
      } catch {
        // Badges are decorative; keep the last known counts.
      }
    },

    setTab(tab: ApprovalTab): Promise<void> | void {
      if (tab === this.tab) return
      this.tab = tab
      this.search = ''
      this.page = 1
      this.meta = null
      return this.fetch()
    },

    setStatus(status: ApprovalStatus): Promise<void> | void {
      if (status === this.status) return
      this.status = status
      this.page = 1
      return this.fetch()
    },

    setSearch(search: string): Promise<void> {
      this.search = search
      this.page = 1
      return this.fetch()
    },

    setPage(page: number): Promise<void> {
      this.page = page
      return this.fetch()
    },

    /** Approve the registration with this user id on the current tab. */
    async approve(id: number): Promise<void> {
      if (this.tab === 'students') {
        this.replace(await approvalsApi.approveStudent(id))
      } else {
        this.replace(await approvalsApi.approvePartner(id))
      }
      this.outcomes[id] = 'approved'
      await this.refreshCounts()
    },

    /** Return (reject) the registration with this user id, with a reason sent to the applicant. */
    async returnRegistration(id: number, reason: string): Promise<void> {
      if (this.tab === 'students') {
        this.replace(await approvalsApi.returnStudent(id, reason))
      } else {
        this.replace(await approvalsApi.returnPartner(id, reason))
      }
      this.outcomes[id] = 'returned'
      await this.refreshCounts()
    },

    /** Swap the updated row into the current list without refetching. */
    replace(row: StudentApproval | PartnerApproval): void {
      if (this.tab === 'students') {
        this.students = this.students.map((s) => (s.id === row.id ? (row as StudentApproval) : s))
      } else {
        this.partners = this.partners.map((p) => (p.id === row.id ? (row as PartnerApproval) : p))
      }
    },
  },
})
