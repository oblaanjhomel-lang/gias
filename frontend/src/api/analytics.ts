import http from '@/lib/http'
import type { AnalyticsFilters, AnalyticsOverview, TermsOverview } from '@/types/analytics'

export async function terms(): Promise<TermsOverview> {
  const { data } = await http.get<{ data: TermsOverview }>('/api/terms')
  return data.data
}

/** Empty filters are left out (the API defaults to the current term, all programs/provinces). */
export function filterParams(filters: Partial<AnalyticsFilters>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== '' && v != null),
  ) as Record<string, string>
}

export async function overview(filters: Partial<AnalyticsFilters>): Promise<AnalyticsOverview> {
  const { data } = await http.get<{ data: AnalyticsOverview }>('/api/analytics/overview', {
    params: filterParams(filters),
  })
  return data.data
}
