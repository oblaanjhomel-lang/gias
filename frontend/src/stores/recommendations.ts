import { defineStore } from 'pinia'
import * as placementsApi from '@/api/placements'
import * as recommendationsApi from '@/api/recommendations'
import { errorMessage } from '@/lib/apiErrors'
import type {
  Algorithm,
  DraftPlan,
  Explanation,
  PlacementItem,
  PlacementStudent,
  RankedPartner,
  RecommendationResult,
  RecommendationView,
} from '@/types/recommendations'

/** Drops results for a student the user has already clicked away from. */
let latestResult = 0

export const useRecommendationsStore = defineStore('recommendations', {
  state: () => ({
    students: [] as PlacementStudent[],
    placed: 0,
    total: 0,
    loadingStudents: false,

    selectedStudentId: null as number | null,
    view: 'wsm' as RecommendationView,
    result: null as RecommendationResult | null,
    loadingResult: false,
    /** Partner whose "Why this score" card is shown. */
    explainPartnerId: null as number | null,

    draft: null as DraftPlan | null,
    loadingDraft: false,

    working: false,
    notice: null as string | null,
    error: null as string | null,
  }),

  getters: {
    partnerById: (state): Map<number, RankedPartner> =>
      new Map((state.result?.partners ?? []).map((p) => [p.id, p])),

    explanation: (state): Explanation | null =>
      state.result?.explanations.find((e) => e.partner_id === state.explainPartnerId) ?? null,

    /** The algorithm whose ranking is shown (WSM in the compare view). */
    algorithm: (state): Algorithm => (state.view === 'compare' ? 'wsm' : state.view),
  },

  actions: {
    async loadStudents(): Promise<void> {
      this.loadingStudents = true
      try {
        const result = await recommendationsApi.listStudents()
        this.students = result.students
        this.placed = result.placed
        this.total = result.total
        if (this.selectedStudentId === null && result.students[0]) {
          await this.select(result.students[0].id)
        }
      } catch (e) {
        this.error = errorMessage(e, 'Could not load students.')
      } finally {
        this.loadingStudents = false
      }
    },

    async select(studentId: number): Promise<void> {
      this.selectedStudentId = studentId
      await this.refreshResult()
    },

    async refreshResult(): Promise<void> {
      if (this.selectedStudentId === null) return
      const request = ++latestResult
      this.loadingResult = true
      try {
        const result = await recommendationsApi.getRecommendation(this.selectedStudentId)
        if (request !== latestResult) return
        this.result = result
        // Explain the current #1 by default (or keep the chosen partner if still listed).
        const stillListed = result.partners.some((p) => p.id === this.explainPartnerId)
        if (!stillListed)
          this.explainPartnerId = result.rankings[this.algorithm][0]?.partner_id ?? null
      } catch (e) {
        if (request === latestResult)
          this.error = errorMessage(e, 'Could not load recommendations.')
      } finally {
        if (request === latestResult) this.loadingResult = false
      }
    },

    setView(view: RecommendationView): void {
      this.view = view
    },

    explain(partnerId: number): void {
      this.explainPartnerId = partnerId
    },

    announce(message: string): void {
      this.error = null
      this.notice = message
    },

    fail(e: unknown): void {
      this.notice = null
      this.error = errorMessage(e)
    },

    // Placement (explicit coordinator / admin action)

    async place(partnerId: number, startDate: string): Promise<boolean> {
      if (this.selectedStudentId === null) return false
      this.working = true
      try {
        const deployment = await placementsApi.place({
          student_id: this.selectedStudentId,
          partner_id: partnerId,
          algorithm: this.view === 'compare' ? 'manual' : this.view,
          start_date: startDate,
        })
        this.announce(`Placed ${deployment.student.name} at ${deployment.partner.company_name}.`)
        await Promise.all([this.loadStudents(), this.refreshResult()])
        return true
      } catch (e) {
        this.fail(e)
        return false
      } finally {
        this.working = false
      }
    },

    async withdraw(deploymentId: number): Promise<void> {
      this.working = true
      try {
        const deployment = await placementsApi.withdraw(deploymentId)
        this.announce(
          `Withdrew ${deployment.student.name} from ${deployment.partner.company_name}.`,
        )
        await Promise.all([this.loadStudents(), this.refreshResult()])
      } catch (e) {
        this.fail(e)
      } finally {
        this.working = false
      }
    },

    // Draft for everyone (nothing saved until "Place selected")

    async openDraft(): Promise<void> {
      this.loadingDraft = true
      try {
        this.draft = await recommendationsApi.draft(this.algorithm)
      } catch (e) {
        this.fail(e)
      } finally {
        this.loadingDraft = false
      }
    },

    closeDraft(): void {
      this.draft = null
    },

    async placeBatch(items: PlacementItem[]): Promise<string | null> {
      this.working = true
      try {
        const created = await placementsApi.placeBatch(items)
        this.draft = null
        this.announce(`Placed ${created.length} ${created.length === 1 ? 'student' : 'students'}.`)
        await Promise.all([this.loadStudents(), this.refreshResult()])
        return null
      } catch (e) {
        return errorMessage(e) // shown in the draft dialog; nothing was placed
      } finally {
        this.working = false
      }
    },

    // Feedback

    async rate(algorithm: Algorithm, rating: number, comment: string): Promise<boolean> {
      if (this.selectedStudentId === null) return false
      try {
        await recommendationsApi.sendFeedback(
          this.selectedStudentId,
          algorithm,
          rating,
          comment.trim() || null,
        )
        return true
      } catch (e) {
        this.fail(e)
        return false
      }
    },
  },
})
