import http from '@/lib/http'

export interface Health {
  status: string
  laravel: string
  php: string
  postgis: string
}

export async function getHealth(): Promise<Health> {
  const { data } = await http.get<{ data: Health }>('/api/health')
  return data.data
}
