import http from '@/lib/http'
import type { PaginationMeta } from '@/types/accounts'
import type { PartnerIntern, WeeklyReport } from '@/types/monitoring'

export type PartnerReportTab = 'submitted' | 'signed' | 'returned'

export async function listInterns(): Promise<PartnerIntern[]> {
  const { data } = await http.get<{ data: PartnerIntern[] }>('/api/partner/interns')
  return data.data
}

export async function listReports(
  status: PartnerReportTab,
  page = 1,
): Promise<{
  reports: WeeklyReport[]
  meta: PaginationMeta
  counts: Record<PartnerReportTab, number>
}> {
  const { data } = await http.get<{
    data: WeeklyReport[]
    meta: PaginationMeta & { counts: Record<PartnerReportTab, number> }
  }>('/api/partner/reports', { params: { status, page } })
  return { reports: data.data, meta: data.meta, counts: data.meta.counts }
}

export async function signReport(id: number): Promise<WeeklyReport> {
  const { data } = await http.post<{ data: WeeklyReport }>(`/api/partner/reports/${id}/sign`)
  return data.data
}

export async function returnReport(id: number, note: string): Promise<WeeklyReport> {
  const { data } = await http.post<{ data: WeeklyReport }>(`/api/partner/reports/${id}/return`, {
    note,
  })
  return data.data
}
