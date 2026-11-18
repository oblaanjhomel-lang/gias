// Mirrors backend SkillResource / SkillSelectionResource / RecommendationSettingsResource.

/** Partner need: 1 = preferred, 2 = required. */
export type Importance = 1 | 2

/** Student skill: 1 = beginner, 2 = intermediate, 3 = advanced. */
export type Level = 1 | 2 | 3

export const IMPORTANCE_LABELS: Record<Importance, string> = { 1: 'Preferred', 2: 'Required' }

export const LEVEL_LABELS: Record<Level, string> = {
  1: 'Beginner',
  2: 'Intermediate',
  3: 'Advanced',
}

export interface Skill {
  id: number
  name: string
  is_active: boolean
  /** Admin list only. */
  partners_count?: number
  students_count?: number
}

export interface PartnerSkill {
  skill_id: number
  name: string
  importance: Importance
}

export interface StudentSkill {
  skill_id: number
  name: string
  level: Level
}

export type Triangle = [number, number, number]

export interface FuzzyConfig {
  inputs: {
    fit: Record<'low' | 'medium' | 'high', Triangle>
    distance_km: Record<'near' | 'mid' | 'far', Triangle>
  }
  missing_distance_km: number
  output: Record<'poor' | 'fair' | 'good' | 'excellent', Triangle>
  rules: { fit: string; distance: string; then: string }[]
}

export interface WsmWeights {
  weight_coverage: number
  weight_proficiency: number
  weight_proximity: number
  weight_slots: number
  proximity_zero_km: number
  slot_saturation: number
}

export interface RecommendationSettings extends WsmWeights {
  normalised_weights: { coverage: number; proficiency: number; proximity: number; slots: number }
  defaults: WsmWeights
  updated_at: string | null
  updated_by: string | null
  fuzzy: FuzzyConfig
}
