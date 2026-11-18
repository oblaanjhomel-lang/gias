import http from '@/lib/http'
import type { Evaluation } from '@/types/evaluations'

/** The student's REVIEWED evaluations only (no drafts, no notes). */
export async function list(): Promise<Evaluation[]> {
  const { data } = await http.get<{ data: Evaluation[] }>('/api/student/evaluations')
  return data.data
}
