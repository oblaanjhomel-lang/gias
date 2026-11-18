import http from '@/lib/http'
import type {
  EvaluationType,
  PartnerEvaluationForm,
  PartnerEvaluationRow,
  Ratings,
} from '@/types/evaluations'

export async function list(): Promise<{
  rows: PartnerEvaluationRow[]
  finalsSubmitted: number
  total: number
}> {
  const { data } = await http.get<{
    data: PartnerEvaluationRow[]
    meta: { finals_submitted: number; total: number }
  }>('/api/partner/evaluations')
  return { rows: data.data, finalsSubmitted: data.meta.finals_submitted, total: data.meta.total }
}

export async function get(
  deploymentId: number,
  type: EvaluationType,
): Promise<PartnerEvaluationForm> {
  const { data } = await http.get<{ data: PartnerEvaluationForm }>(
    `/api/partner/evaluations/${deploymentId}/${type}`,
  )
  return data.data
}

/** Upserts the draft; ratings may be partial. */
export async function saveDraft(
  deploymentId: number,
  type: EvaluationType,
  ratings: Ratings,
  comment: string | null,
): Promise<PartnerEvaluationForm> {
  const clean = Object.fromEntries(Object.entries(ratings).filter(([, v]) => v !== null))
  const { data } = await http.put<{ data: PartnerEvaluationForm }>(
    `/api/partner/evaluations/${deploymentId}/${type}`,
    { ratings: clean, comment },
  )
  return data.data
}

export async function submit(
  deploymentId: number,
  type: EvaluationType,
): Promise<PartnerEvaluationForm> {
  const { data } = await http.post<{ data: PartnerEvaluationForm }>(
    `/api/partner/evaluations/${deploymentId}/${type}/submit`,
  )
  return data.data
}
