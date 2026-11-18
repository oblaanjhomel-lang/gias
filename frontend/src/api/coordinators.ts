import http from '@/lib/http'
import type {
  Coordinator,
  CoordinatorListMeta,
  CoordinatorPayload,
  ListParams,
  ManagedStatus,
  Paginated,
} from '@/types/accounts'

const BASE = '/api/admin/coordinators'

export async function list(
  params: ListParams = {},
): Promise<Paginated<Coordinator, CoordinatorListMeta>> {
  const { data } = await http.get<Paginated<Coordinator, CoordinatorListMeta>>(BASE, {
    params: {
      search: params.search || undefined,
      status: params.status || undefined,
      page: params.page,
    },
  })
  return data
}

export async function create(payload: CoordinatorPayload): Promise<Coordinator> {
  const { data } = await http.post<{ data: Coordinator }>(BASE, payload)
  return data.data
}

export async function update(id: number, payload: CoordinatorPayload): Promise<Coordinator> {
  const { data } = await http.put<{ data: Coordinator }>(`${BASE}/${id}`, payload)
  return data.data
}

export async function setStatus(id: number, status: ManagedStatus): Promise<Coordinator> {
  const { data } = await http.patch<{ data: Coordinator }>(`${BASE}/${id}/status`, { status })
  return data.data
}

export async function remove(id: number): Promise<void> {
  await http.delete(`${BASE}/${id}`)
}
