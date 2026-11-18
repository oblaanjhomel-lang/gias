import axios from 'axios'
import http from '@/lib/http'
import type { DeploymentDetail, ReportPayload, WeeklyReport } from '@/types/monitoring'

/** The student's active (or latest) deployment; null if they've never been placed (404). */
export async function getDeployment(): Promise<DeploymentDetail | null> {
  try {
    const { data } = await http.get<{ data: DeploymentDetail }>('/api/student/deployment')
    return data.data
  } catch (e) {
    if (axios.isAxiosError(e) && e.response?.status === 404) return null
    throw e
  }
}

/** Create the week's draft (or update it while still draft / returned). Dates are computed by the server. */
export async function saveDraft(weekNumber: number, payload: ReportPayload): Promise<WeeklyReport> {
  const { data } = await http.post<{ data: WeeklyReport }>('/api/student/reports', {
    week_number: weekNumber,
    ...payload,
  })
  return data.data
}

export async function updateReport(id: number, payload: ReportPayload): Promise<WeeklyReport> {
  const { data } = await http.put<{ data: WeeklyReport }>(`/api/student/reports/${id}`, payload)
  return data.data
}

export async function submitReport(id: number): Promise<WeeklyReport> {
  const { data } = await http.post<{ data: WeeklyReport }>(`/api/student/reports/${id}/submit`)
  return data.data
}
