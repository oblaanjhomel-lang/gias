import http from '@/lib/http'
import type { Dashboard } from '@/types/dashboard'

/** The signed-in user's dashboard numbers (shape depends on the role). */
export async function get(): Promise<Dashboard> {
  const { data } = await http.get<{ data: Dashboard }>('/api/dashboard')
  return data.data
}
