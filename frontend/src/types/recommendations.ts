// Mirrors backend RecommendationService output (via RecommendationResource), placements and suggestions.
import type { Level } from '@/types/skills'

export type Algorithm = 'cos' | 'wsm' | 'fuzzy'

export const ALGORITHMS: Algorithm[] = ['cos', 'wsm', 'fuzzy']

export const ALGORITHM_LABELS: Record<Algorithm, string> = {
  cos: 'Cosine similarity',
  wsm: 'Weighted sum (WSM)',
  fuzzy: 'Fuzzy logic',
}

/** The page's algorithm tabs. */
export type RecommendationView = Algorithm | 'compare'

export interface PlacementInfo {
  deployment_id: number
  partner_id: number
  company_name: string
  start_date?: string | null
}

/** A student in the "awaiting placement" queue. `id` is the user id. */
export interface PlacementStudent {
  id: number
  name: string
  program: string | null
  city: string
  top_skills: { name: string; level: Level }[]
  skills_count: number
  placement: PlacementInfo | null
}

export interface RankedPartner {
  /** Partner user id. */
  id: number
  company_name: string
  city: string
  province: string
  distance_km: number | null
  intern_slots: number
  open: number
  skills_needed: number
  skills_matched: number
  is_current_placement: boolean
}

export interface RankEntry {
  partner_id: number
  score: number
  rank: number
}

export interface CompareRow {
  partner_id: number
  company_name: string
  cos: { score: number; rank: number }
  wsm: { score: number; rank: number }
  fuzzy: { score: number; rank: number }
  average_rank: number
}

export interface NeededSkill {
  skill_id: number
  name: string
  importance: 1 | 2
  /** null when the student doesn't have it. */
  level: Level | null
}

export interface CosineExplanation {
  score: number
  dot: number
  norm_s: number
  norm_p: number
  needed: NeededSkill[]
  matched: NeededSkill[]
  missing_required: NeededSkill[]
}

export type WsmCriterion = 'coverage' | 'proficiency' | 'proximity' | 'slots'

export interface WsmExplanation {
  score: number
  criteria: { key: WsmCriterion; value: number; weight: number; contribution: number }[]
  distance_km: number | null
  open: number
}

export interface FuzzyExplanation {
  score: number
  fit: number
  fit_membership: { low: number; medium: number; high: number }
  distance_km: number
  distance_missing: boolean
  distance_membership: { near: number; mid: number; far: number }
  rules_fired: { fit: string; distance: string; then: string; strength: number }[]
  centroid: number
}

export interface Explanation {
  partner_id: number
  cos: CosineExplanation
  wsm: WsmExplanation
  fuzzy: FuzzyExplanation
}

export interface RecommendationResult {
  student: {
    id: number
    name: string
    program: { code: string; name: string } | null
    city: string
    province: string
    has_home_location: boolean
    skills: { skill_id: number; name: string; level: Level }[]
    placement: PlacementInfo | null
  }
  partners: RankedPartner[]
  rankings: Record<Algorithm, RankEntry[]>
  compare: CompareRow[]
  agreement: { all_agree: boolean; top: Record<Algorithm, number | null> }
  excluded: { id: number; company_name: string; city: string; intern_slots: number }[]
  explanations: Explanation[]
}

export interface DraftProposal {
  student: { id: number; name: string; program: string | null }
  partner: { id: number; company_name: string; city: string }
  score: number
  distance_km: number | null
}

export interface DraftPlan {
  algorithm: Algorithm
  proposals: DraftProposal[]
  unassigned: { id: number; name: string; program: string | null }[]
}

export interface DeploymentRecord {
  id: number
  status: 'active' | 'completed' | 'withdrawn'
  student: { id: number; name: string }
  partner: { id: number; company_name: string }
  algorithm_used: Algorithm | 'manual' | null
  rank_cos: number | null
  rank_wsm: number | null
  rank_fuzzy: number | null
  start_date: string | null
  end_date: string | null
}

export interface PlacementItem {
  student_id: number
  partner_id: number
  algorithm: Algorithm | 'manual' | null
  start_date: string
}

/** Student view (top 3 by WSM). */
export interface PartnerSuggestion {
  id: number
  company_name: string
  city: string
  province: string
  distance_km: number | null
  open: number
  score: number
}

/** Partner view (top 3 by WSM): name and program only. */
export interface MatchingStudent {
  name: string
  program: string | null
  score: number
}
