import http from '@/lib/http'
import type {
  Importance,
  Level,
  PartnerSkill,
  RecommendationSettings,
  Skill,
  StudentSkill,
  WsmWeights,
} from '@/types/skills'

// Skills list (any signed-in user; admin gets inactive skills and usage counts too)

export async function listSkills(): Promise<Skill[]> {
  const { data } = await http.get<{ data: Skill[] }>('/api/skills')
  return data.data
}

export async function createSkill(name: string): Promise<Skill> {
  const { data } = await http.post<{ data: Skill }>('/api/admin/skills', { name })
  return data.data
}

export async function updateSkill(
  id: number,
  changes: { name?: string; is_active?: boolean },
): Promise<Skill> {
  const { data } = await http.put<{ data: Skill }>(`/api/admin/skills/${id}`, changes)
  return data.data
}

export async function deleteSkill(id: number): Promise<void> {
  await http.delete(`/api/admin/skills/${id}`)
}

// WSM settings (admin) + read-only fuzzy config

export async function getSettings(): Promise<RecommendationSettings> {
  const { data } = await http.get<{ data: RecommendationSettings }>(
    '/api/admin/recommendation-settings',
  )
  return data.data
}

export async function updateSettings(values: WsmWeights): Promise<RecommendationSettings> {
  const { data } = await http.put<{ data: RecommendationSettings }>(
    '/api/admin/recommendation-settings',
    values,
  )
  return data.data
}

// Own skills

export async function getPartnerSkills(): Promise<PartnerSkill[]> {
  const { data } = await http.get<{ data: PartnerSkill[] }>('/api/partner/skills')
  return data.data
}

export async function savePartnerSkills(
  skills: { skill_id: number; importance: Importance }[],
): Promise<PartnerSkill[]> {
  const { data } = await http.put<{ data: PartnerSkill[] }>('/api/partner/skills', { skills })
  return data.data
}

export async function getStudentSkills(): Promise<StudentSkill[]> {
  const { data } = await http.get<{ data: StudentSkill[] }>('/api/student/skills')
  return data.data
}

export async function saveStudentSkills(
  skills: { skill_id: number; level: Level }[],
): Promise<StudentSkill[]> {
  const { data } = await http.put<{ data: StudentSkill[] }>('/api/student/skills', { skills })
  return data.data
}
