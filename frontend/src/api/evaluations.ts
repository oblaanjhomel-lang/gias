import http from '@/lib/http'
import type { PaginationMeta } from '@/types/accounts'
import type {
  Evaluation,
  EvaluationQueueRow,
  EvaluationSummary,
  EvaluationType,
  QueueStatus,
} from '@/types/evaluations'

export interface EvaluationQuery {
  type: EvaluationType
  status?: QueueStatus | ''
  search?: string
  /** Admin only. */
  programId?: number | null
  page?: number
}

export async function summary(type: EvaluationType | null): Promise<EvaluationSummary> {
  const { data } = await http.get<{ data: EvaluationSummary }>('/api/evaluations/summary', {
    params: { type: type ?? undefined },
  })
  return data.data
}

export async function list(query: EvaluationQuery): Promise<{
  rows: EvaluationQueueRow[]
  meta: PaginationMeta
  counts: { to_review: number; reviewed: number; not_submitted: number }
}> {
  const { data } = await http.get<{
    data: EvaluationQueueRow[]
    meta: PaginationMeta & {
      counts: { to_review: number; reviewed: number; not_submitted: number }
    }
  }>('/api/evaluations', {
    params: {
      type: query.type,
      status: query.status || undefined,
      search: query.search || undefined,
      program_id: query.programId ?? undefined,
      page: query.page,
    },
  })
  return { rows: data.data, meta: data.meta, counts: data.meta.counts }
}

export async function get(id: number): Promise<Evaluation> {
  const { data } = await http.get<{ data: Evaluation }>(`/api/evaluations/${id}`)
  return data.data
}

export async function setCoordinatorRating(
  id: number,
  rating: number,
  note: string | null,
): Promise<Evaluation> {
  const { data } = await http.put<{ data: Evaluation }>(
    `/api/evaluations/${id}/coordinator-rating`,
    {
      rating,
      note,
    },
  )
  return data.data
}

export async function review(id: number): Promise<Evaluation> {
  const { data } = await http.post<{ data: Evaluation }>(`/api/evaluations/${id}/review`)
  return data.data
}

export async function reopen(id: number): Promise<Evaluation> {
  const { data } = await http.post<{ data: Evaluation }>(`/api/evaluations/${id}/reopen`)
  return data.data
}

export async function returnToPartner(id: number, note: string): Promise<Evaluation> {
  const { data } = await http.post<{ data: Evaluation }>(`/api/evaluations/${id}/return`, { note })
  return data.data
}

/** Admin: download the CSV for one type through the session-authenticated API. */
export async function exportCsv(type: EvaluationType): Promise<void> {
  const response = await http.get<Blob>('/api/admin/evaluations/export', {
    params: { type },
    responseType: 'blob',
  })
  const disposition = String(response.headers['content-disposition'] ?? '')
  const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `gias-evaluations-${type}.csv`
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
