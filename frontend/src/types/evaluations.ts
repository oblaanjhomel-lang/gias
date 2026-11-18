// Mirrors backend EvaluationResource / PartnerEvaluationFormResource / PartnerEvaluationListResource /
// EvaluationQueueRowResource / EvaluationSummaryResource, and config gias.evaluation.
import type { Progress } from '@/types/monitoring'

export type EvaluationType = 'midterm' | 'final'

export type EvaluationStatus = 'draft' | 'submitted' | 'returned' | 'reviewed'

/** Queue status: an evaluation's status, or "not_started" when there is none. */
export type QueueStatus = EvaluationStatus | 'not_started'

export const EVALUATION_TYPES: { value: EvaluationType; label: string }[] = [
  { value: 'midterm', label: 'Midterm' },
  { value: 'final', label: 'Final' },
]

/** Mirrors config gias.evaluation.criteria (order matters). */
export const CRITERIA: { key: string; label: string }[] = [
  { key: 'quality', label: 'Quality of work' },
  { key: 'technical', label: 'Technical competence' },
  { key: 'attendance', label: 'Attendance & punctuality' },
  { key: 'initiative', label: 'Initiative' },
  { key: 'communication', label: 'Communication' },
  { key: 'teamwork', label: 'Teamwork & conduct' },
]

/** Mirrors config gias.evaluation.scale. */
export const SCALE: Record<number, string> = {
  1: 'Poor',
  2: 'Fair',
  3: 'Satisfactory',
  4: 'Very satisfactory',
  5: 'Outstanding',
}

/** Mirrors config gias.evaluation.coordinator_criterion and weights. */
export const COORDINATOR_CRITERION = 'OJT requirements, weekly reports and documentation'
export const WEIGHTS = { partner: 0.7, coordinator: 0.3 }

/** Mirrors config gias.evaluation.descriptor_bands. */
export function descriptor(value: number | null | undefined): string | null {
  if (value === null || value === undefined) return null
  if (value >= 4.5) return 'Outstanding'
  if (value >= 3.5) return 'Very satisfactory'
  if (value >= 2.5) return 'Satisfactory'
  if (value >= 1.5) return 'Fair'
  return 'Poor'
}

export function finalRating(partnerMean: number, coordinatorRating: number): number {
  return (
    Math.round((WEIGHTS.partner * partnerMean + WEIGHTS.coordinator * coordinatorRating) * 100) /
    100
  )
}

export type Ratings = Record<string, number | null>

export interface RatingRow {
  key: string
  label: string
  value: number | null
}

export interface Evaluation {
  id: number
  deployment_id: number
  type: EvaluationType
  status: EvaluationStatus
  ratings: RatingRow[]
  rated_count: number
  partner_mean: number | null
  partner_descriptor: string | null
  comment: string | null
  submitted_at: string | null
  reviewed_at: string | null
  /** Student, coordinator, admin. */
  coordinator_rating?: number | null
  final_rating?: number | null
  final_descriptor?: string | null
  /** Partner, coordinator, admin (never students). */
  return_note?: string | null
  /** Coordinator / admin only. */
  coordinator_note?: string | null
  submitted_by?: string | null
  reviewed_by?: string | null
  deployment?: {
    id: number
    status: string
    student: { name: string; student_number: string; program: string | null }
    partner: { company_name: string }
    progress: Progress | null
  } | null
}

export interface OpenState {
  open: boolean
  threshold_hours: number
  signed_hours: number
  required_hours: number
}

/** The partner's evaluation form for one intern and type. */
export interface PartnerEvaluationForm {
  deployment_id: number
  deployment_status: string
  intern: { name: string; program: string | null }
  type: EvaluationType
  open: OpenState
  evaluation: Evaluation | null
}

export interface PartnerEvaluationRow {
  deployment_id: number
  intern: { name: string; program: string | null }
  signed_hours: number
  required_hours: number
  midterm: { status: EvaluationStatus | null; open: boolean; threshold_hours: number }
  final: { status: EvaluationStatus | null; open: boolean; threshold_hours: number }
}

export interface EvaluationQueueRow {
  deployment_id: number
  deployment_status: string
  student: { name: string; student_number: string; program: string | null }
  partner: { company_name: string }
  progress: Progress
  evaluation: {
    id: number | null
    status: QueueStatus
    partner_mean: number | null
    final_rating: number | null
  }
}

export interface EvaluationSummary {
  type: EvaluationType | null
  counts: Record<EvaluationStatus, number>
  evaluations_submitted: number
  awaiting_review: number
  average_partner_mean: number | null
  average_descriptor: string | null
  criteria: { key: string; label: string; average: number | null }[]
  lowest_criterion: { key: string; label: string; average: number } | null
  programs: {
    program: { code: string; name: string }
    evaluated: number
    total_active_or_completed: number
    avg_partner_mean: number | null
    avg_final: number | null
    descriptor: string | null
  }[]
}
