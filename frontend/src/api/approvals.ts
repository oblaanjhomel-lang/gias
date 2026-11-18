import http from '@/lib/http'
import type { Paginated } from '@/types/accounts'
import type {
  ApprovalCounts,
  ApprovalListParams,
  PartnerApproval,
  PartnerDocument,
  StudentApproval,
} from '@/types/approvals'

const BASE = '/api/approvals'

function query(params: ApprovalListParams) {
  return { status: params.status, search: params.search || undefined, page: params.page }
}

export async function counts(): Promise<ApprovalCounts> {
  const { data } = await http.get<ApprovalCounts>(`${BASE}/counts`)
  return data
}

// Students

export async function listStudents(
  params: ApprovalListParams = {},
): Promise<Paginated<StudentApproval>> {
  const { data } = await http.get<Paginated<StudentApproval>>(`${BASE}/students`, {
    params: query(params),
  })
  return data
}

export async function approveStudent(id: number): Promise<StudentApproval> {
  const { data } = await http.post<{ data: StudentApproval }>(`${BASE}/students/${id}/approve`)
  return data.data
}

export async function returnStudent(id: number, reason: string): Promise<StudentApproval> {
  const { data } = await http.post<{ data: StudentApproval }>(`${BASE}/students/${id}/return`, {
    reason,
  })
  return data.data
}

// Industry partners

export async function listPartners(
  params: ApprovalListParams = {},
): Promise<Paginated<PartnerApproval>> {
  const { data } = await http.get<Paginated<PartnerApproval>>(`${BASE}/partners`, {
    params: query(params),
  })
  return data
}

export async function approvePartner(id: number): Promise<PartnerApproval> {
  const { data } = await http.post<{ data: PartnerApproval }>(`${BASE}/partners/${id}/approve`)
  return data.data
}

export async function returnPartner(id: number, reason: string): Promise<PartnerApproval> {
  const { data } = await http.post<{ data: PartnerApproval }>(`${BASE}/partners/${id}/return`, {
    reason,
  })
  return data.data
}

/**
 * Fetch a partner's document through the session-authenticated API and hand it to the
 * browser as a download (a plain link wouldn't carry the API's JSON/CSRF conventions).
 */
export async function downloadDocument(
  partnerId: number,
  document: PartnerDocument,
): Promise<void> {
  const { data } = await http.get<Blob>(`${BASE}/partners/${partnerId}/documents/${document.id}`, {
    responseType: 'blob',
  })

  const url = URL.createObjectURL(data)
  const link = window.document.createElement('a')
  link.href = url
  link.download = document.original_name
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
