// Mirrors backend AnalyticsService (GET /api/analytics/overview) and TermService (GET /api/terms).
import type { Province } from '@/types/accounts'

export interface AcademicTerm {
  /** "2026-2027-1" */
  key: string
  /** "AY 2026–2027 · 1st Semester" */
  label: string
  /** "1st Sem 2026–27" */
  short_label: string
  start: string
  end: string
}

export interface TermsOverview {
  current: AcademicTerm
  /** Most recent first */
  terms: AcademicTerm[]
}

export interface AnalyticsFilters {
  term: string
  /** "" = all programs (admin only) */
  program_id: string
  /** "" = all provinces */
  province: Province | ''
}

export type ReportType = 'deployments' | 'partners' | 'programs' | 'algorithms'
export type ReportFormat = 'csv' | 'pdf'

/** Rates are percentages with one decimal, or null when the denominator is 0. */
export interface AnalyticsOverview {
  scope: { term: AcademicTerm; program_id: number | null; province: string | null }
  kpis: {
    placement_rate: { rate: number | null; numerator: number; denominator: number }
    active_partners: number
    partner_utilization: { rate: number | null; filled: number; slots: number }
    avg_distance_km: number | null
    completion_rate: { rate: number | null; completed: number; active_past_required: number }
    avg_final_rating: { value: number | null; descriptor: string | null }
    placements: number
  }
  by_province: {
    province: Province
    interns: number
    partners: number
    open_slots: number
    underserved: boolean
  }[]
  placements_trend: { key: string; label: string; placements: number }[]
  hours_distribution: { bucket: string; interns: number }[]
  /** Admin only (null for coordinators). */
  workload:
    | {
        program: { code: string; name: string }
        coordinator: string | null
        interns: number
        partners_used: number
        load: 'heavy' | 'balanced' | 'light' | 'no_coordinator'
      }[]
    | null
  top_partners: {
    company: string
    city: string
    interns: number
    avg_final_rating: number | null
  }[]
  skills_gap: { skill: string; partners_need: number; students_have: number; gap: number }[]
  evaluation: {
    evaluated: number
    average_partner_mean: number | null
    average_descriptor: string | null
    criteria: { key: string; label: string; average: number | null }[]
    lowest_criterion: { key: string; label: string; average: number } | null
  }
  algorithm_comparison: {
    algorithms: {
      algorithm: 'cos' | 'wsm' | 'fuzzy'
      label: string
      placements_using: number
      top1_agreement: number | null
      top3_agreement: number | null
      mean_rank_of_chosen: number | null
      avg_feedback_rating: number | null
      feedback_n: number
    }[]
    total_with_rank_data: number
    manual_placements: number
  }
}
