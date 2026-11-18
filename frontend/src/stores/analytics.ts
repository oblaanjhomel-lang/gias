import { defineStore } from 'pinia'
import * as analyticsApi from '@/api/analytics'
import { errorMessage } from '@/lib/apiErrors'
import type { AcademicTerm, AnalyticsFilters, AnalyticsOverview } from '@/types/analytics'

/** Drops responses from superseded requests (fast filter changes). */
let latestRequest = 0
let termsPromise: Promise<void> | null = null

function emptyFilters(): AnalyticsFilters {
  return { term: '', program_id: '', province: '' }
}

export const useAnalyticsStore = defineStore('analytics', {
  state: () => ({
    /** Academic terms (most recent first) and the current one; also used by the top bar. */
    terms: [] as AcademicTerm[],
    currentTerm: null as AcademicTerm | null,

    filters: emptyFilters(),
    data: null as AnalyticsOverview | null,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    /** Loads the term list once per page load; later calls reuse the same promise. */
    loadTerms(): Promise<void> {
      termsPromise ??= (async () => {
        try {
          const result = await analyticsApi.terms()
          this.terms = result.terms
          this.currentTerm = result.current
        } catch {
          termsPromise = null // retry next time; the breadcrumb simply omits the term
        }
      })()
      return termsPromise
    },

    async fetch(): Promise<void> {
      const request = ++latestRequest
      this.loading = true
      this.error = null
      try {
        const data = await analyticsApi.overview(this.filters)
        if (request !== latestRequest) return
        this.data = data
      } catch (e) {
        if (request === latestRequest) this.error = errorMessage(e, 'Could not load analytics.')
      } finally {
        if (request === latestRequest) this.loading = false
      }
    },

    setFilters(filters: Partial<AnalyticsFilters>): Promise<void> {
      this.filters = { ...this.filters, ...filters }
      return this.fetch()
    },

    reset(): Promise<void> {
      this.filters = emptyFilters()
      return this.fetch()
    },

    /** Called on sign-out. */
    clear(): void {
      termsPromise = null
      this.$reset()
    },
  },
})
