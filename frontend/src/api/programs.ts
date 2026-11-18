import http from '@/lib/http'
import type { Program } from '@/types/accounts'

export async function list(): Promise<Program[]> {
  const { data } = await http.get<{ data: Program[] }>('/api/programs')
  return data.data
}
