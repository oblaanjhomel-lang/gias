import http from '@/lib/http'
import type {
  Algorithm,
  DraftPlan,
  MatchingStudent,
  PartnerSuggestion,
  PlacementStudent,
  RecommendationResult,
} from '@/types/recommendations'

export async function listStudents(): Promise<{
  students: PlacementStudent[]
  placed: number
  total: number
}> {
  const { data } = await http.get<{
    data: PlacementStudent[]
    meta: { placed: number; total: number }
  }>('/api/recommendations/students')
  return { students: data.data, ...data.meta }
}

/** studentId = the student's user id. */
export async function getRecommendation(studentId: number): Promise<RecommendationResult> {
  const { data } = await http.get<{ data: RecommendationResult }>(
    `/api/recommendations/students/${studentId}`,
  )
  return data.data
}

/** A greedy plan for every unplaced student. Nothing is saved. */
export async function draft(algorithm: Algorithm): Promise<DraftPlan> {
  const { data } = await http.post<{ data: DraftPlan }>('/api/recommendations/draft', { algorithm })
  return data.data
}

export async function sendFeedback(
  studentId: number,
  algorithm: Algorithm,
  rating: number,
  comment: string | null,
): Promise<void> {
  await http.post(`/api/recommendations/students/${studentId}/feedback`, {
    algorithm,
    rating,
    comment,
  })
}

/** Student: top 3 partners by WSM. */
export async function studentSuggestions(): Promise<PartnerSuggestion[]> {
  const { data } = await http.get<{ data: PartnerSuggestion[] }>('/api/student/recommendations')
  return data.data
}

/** Partner: top 3 unplaced students by WSM, plus their own open slots. */
export async function matchingStudents(): Promise<{
  students: MatchingStudent[]
  open: number
  internSlots: number
}> {
  const { data } = await http.get<{
    data: MatchingStudent[]
    meta: { open: number; intern_slots: number }
  }>('/api/partner/matching-students')
  return { students: data.data, open: data.meta.open, internSlots: data.meta.intern_slots }
}

/** Admin: download the evaluation CSV through the session-authenticated API. */
export async function downloadExport(type: 'placements' | 'feedback'): Promise<void> {
  const response = await http.get<Blob>('/api/admin/recommendations/export', {
    params: { type },
    responseType: 'blob',
  })

  const disposition = String(response.headers['content-disposition'] ?? '')
  const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `gias-${type}.csv`

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
