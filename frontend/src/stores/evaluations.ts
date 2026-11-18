import { defineStore } from 'pinia'
import * as evaluationsApi from '@/api/evaluations'
import { errorMessage } from '@/lib/apiErrors'
import type { PaginationMeta } from '@/types/accounts'
import type {
  Evaluation,
  EvaluationQueueRow,
  EvaluationSummary,
  EvaluationType,
  QueueStatus,
} from '@/types/evaluations'

/** Drops list responses that a newer filter / search has superseded. */
let latestList = 0

/** Coordinator / admin "Evaluations" (review queue and admin overview). */
export const useEvaluationsStore = defineStore('evaluations', {
  state: () => ({
    type: 'final' as EvaluationType,
    status: '' as QueueStatus | '',
    search: '',
    programId: null as number | null,
    page: 1,

    rows: [] as EvaluationQueueRow[],
    meta: null as PaginationMeta | null,
    counts: { to_review: 0, reviewed: 0, not_submitted: 0 },
    loading: false,

    selectedDeploymentId: null as number | null,
    detail: null as Evaluation | null,
    loadingDetail: false,
    working: false,

    summary: null as EvaluationSummary | null,

    notice: null as string | null,
    error: null as string | null,
  }),

  getters: {
    selectedRow: (state): EvaluationQueueRow | null =>
      state.rows.find((r) => r.deployment_id === state.selectedDeploymentId) ?? null,
  },

  actions: {
    async fetch(): Promise<void> {
      const request = ++latestList
      this.loading = true
      try {
        const result = await evaluationsApi.list({
          type: this.type,
          status: this.status,
          search: this.search.trim(),
          programId: this.programId,
          page: this.page,
        })
        if (request !== latestList) return
        this.rows = result.rows
        this.meta = result.meta
        this.counts = result.counts
      } catch (e) {
        if (request === latestList) this.error = errorMessage(e, 'Could not load evaluations.')
      } finally {
        if (request === latestList) this.loading = false
      }
    },

    async fetchSummary(): Promise<void> {
      try {
        this.summary = await evaluationsApi.summary(this.type)
      } catch (e) {
        this.error = errorMessage(e, 'Could not load the overview.')
      }
    },

    setType(type: EvaluationType): Promise<void> {
      this.type = type
      this.page = 1
      this.select(null)
      return this.fetch()
    },

    setStatus(status: QueueStatus | ''): Promise<void> {
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

    /** Select an intern; loads the evaluation if one has been submitted. */
    async select(deploymentId: number | null): Promise<void> {
      this.selectedDeploymentId = deploymentId
      this.detail = null
      const row = this.selectedRow
      if (!row?.evaluation.id || ['draft', 'not_started'].includes(row.evaluation.status)) return

      this.loadingDetail = true
      try {
        this.detail = await evaluationsApi.get(row.evaluation.id)
      } catch (e) {
        this.error = errorMessage(e)
      } finally {
        this.loadingDetail = false
      }
    },

    /** Saves the coordinator rating (if changed) and marks the evaluation reviewed. */
    async markReviewed(rating: number, note: string | null): Promise<void> {
      if (!this.detail) return
      this.working = true
      try {
        if (
          this.detail.coordinator_rating !== rating ||
          (this.detail.coordinator_note ?? null) !== note
        ) {
          await evaluationsApi.setCoordinatorRating(this.detail.id, rating, note)
        }
        this.detail = await evaluationsApi.review(this.detail.id)
        this.notice = `Reviewed ${this.detail.deployment?.student.name}’s ${this.detail.type} evaluation (final rating ${this.detail.final_rating?.toFixed(2)}).`
        await this.fetch()
      } catch (e) {
        this.error = errorMessage(e)
      } finally {
        this.working = false
      }
    },

    async reopen(): Promise<void> {
      if (!this.detail) return
      this.working = true
      try {
        this.detail = await evaluationsApi.reopen(this.detail.id)
        this.notice = 'Reopened. You can change your rating and review it again.'
        await this.fetch()
      } catch (e) {
        this.error = errorMessage(e)
      } finally {
        this.working = false
      }
    },

    /** Returns an error message for the dialog, or null on success. */
    async returnToPartner(note: string): Promise<string | null> {
      if (!this.detail) return null
      this.working = true
      try {
        this.detail = await evaluationsApi.returnToPartner(this.detail.id, note)
        this.notice = `Returned the evaluation to ${this.detail.deployment?.partner.company_name} with your note.`
        await this.fetch()
        return null
      } catch (e) {
        return errorMessage(e)
      } finally {
        this.working = false
      }
    },
  },
})
