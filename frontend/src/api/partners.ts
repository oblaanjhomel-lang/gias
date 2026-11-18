import http from '@/lib/http'
import type {
  IndustryPartner,
  ListParams,
  ManagedStatus,
  Paginated,
  PartnerPayload,
} from '@/types/accounts'

const BASE = '/api/admin/partners'

export async function list(params: ListParams = {}): Promise<Paginated<IndustryPartner>> {
  const { data } = await http.get<Paginated<IndustryPartner>>(BASE, {
    params: {
      search: params.search || undefined,
      status: params.status || undefined,
      page: params.page,
    },
  })
  return data
}

export async function create(payload: PartnerPayload): Promise<IndustryPartner> {
  const { data } = await http.post<{ data: IndustryPartner }>(BASE, payload)
  return data.data
}

export async function update(id: number, payload: PartnerPayload): Promise<IndustryPartner> {
  const { data } = await http.put<{ data: IndustryPartner }>(`${BASE}/${id}`, payload)
  return data.data
}

export async function setStatus(id: number, status: ManagedStatus): Promise<IndustryPartner> {
  const { data } = await http.patch<{ data: IndustryPartner }>(`${BASE}/${id}/status`, { status })
  return data.data
}

export async function remove(id: number): Promise<void> {
  await http.delete(`${BASE}/${id}`)
}
