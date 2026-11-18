import http from '@/lib/http'
import type { PaginationMeta, Program } from '@/types/accounts'
import type {
  DeploymentDetail,
  MonitoringFilter,
  MonitoringRow,
  MonitoringSummary,
} from '@/types/monitoring'

export interface MonitoringQuery {
  filter: MonitoringFilter
  search?: string
  /** Admin only. */
  programId?: number | null
  province?: string
  page?: number
}

export async function getSummary(): Promise<MonitoringSummary> {
  const { data } = await http.get<{ data: MonitoringSummary }>('/api/monitoring/summary')
  return data.data
}

export async function listDeployments(
  query: MonitoringQuery,
): Promise<{ rows: MonitoringRow[]; meta: PaginationMeta; scopeTotal: number }> {
  const { data } = await http.get<{
    data: MonitoringRow[]
    meta: PaginationMeta & { scope_total: number }
  }>('/api/monitoring/deployments', {
    params: {
      filter: query.filter,
      search: query.search || undefined,
      program_id: query.programId ?? undefined,
      province: query.province || undefined,
      page: query.page,
    },
  })
  return { rows: data.data, meta: data.meta, scopeTotal: data.meta.scope_total }
}

export async function getDeployment(id: number): Promise<DeploymentDetail> {
  const { data } = await http.get<{ data: DeploymentDetail }>(`/api/monitoring/deployments/${id}`)
  return data.data
}

/** force (admin only) completes below the required hours and needs a note. */
export async function completeDeployment(
  id: number,
  options: { force?: boolean; note?: string } = {},
): Promise<DeploymentDetail> {
  const { data } = await http.post<{ data: DeploymentDetail }>(
    `/api/monitoring/deployments/${id}/complete`,
    options,
  )
  return data.data
}

/** Admin: required OJT hours for a program (100–2000). */
export async function updateRequiredHours(programId: number, hours: number): Promise<Program> {
  const { data } = await http.put<{ data: Program }>(
    `/api/admin/programs/${programId}/required-hours`,
    { required_ojt_hours: hours },
  )
  return data.data
}
