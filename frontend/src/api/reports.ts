import http from '@/lib/http'
import { filterParams } from '@/api/analytics'
import type { AnalyticsFilters, ReportFormat, ReportType } from '@/types/analytics'

/**
 * Downloads a report through the axios instance (so the session cookie is sent) and saves it
 * with the server's file name.
 */
export async function download(
  type: ReportType,
  format: ReportFormat,
  filters: Partial<AnalyticsFilters>,
): Promise<void> {
  const response = await http.get<Blob>(`/api/reports/${type}`, {
    params: { ...filterParams(filters), format },
    responseType: 'blob',
  })

  const disposition = String(response.headers['content-disposition'] ?? '')
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
  const filename = match?.[1] ? decodeURIComponent(match[1]) : `gias-${type}.${format}`

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
