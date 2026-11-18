// Mirrors backend StudentApprovalResource / PartnerApprovalResource / ApprovalCountsResource.
import type { GeocodeStatus, IndustryPartner, Program, Province, Status } from '@/types/accounts'

export type ApprovalTab = 'partners' | 'students'

/** `rejected` is shown as "Returned". */
export type ApprovalStatus = 'pending' | 'rejected'

/** In-page result of an action, shown on the card until the next refresh. */
export type ApprovalOutcome = 'approved' | 'returned'

export type PartnerDocumentType = 'sec_dti' | 'business_permit' | 'moa_draft' | 'other'

export const DOCUMENT_TYPES: { value: PartnerDocumentType; label: string }[] = [
  { value: 'sec_dti', label: 'SEC / DTI registration' },
  { value: 'business_permit', label: 'Business permit' },
  { value: 'moa_draft', label: 'MOA draft' },
  { value: 'other', label: 'Other' },
]

export interface StudentApproval {
  /** User id (what /api/approvals/students/{id} takes). */
  id: number
  name: string
  email: string
  status: Status
  student_number: string
  year_level: number
  program: Program | null
  contact_number: string
  address: string
  city: string
  province: Province
  latitude: number | null
  longitude: number | null
  geocode_status: GeocodeStatus
  created_at: string
  reviewed_at: string | null
  review_note: string | null
}

export interface PartnerDocument {
  id: number
  type: PartnerDocumentType
  original_name: string
  /** Bytes. */
  size: number
}

export interface PartnerApproval extends IndustryPartner {
  documents: PartnerDocument[]
  reviewed_at: string | null
  review_note: string | null
}

export interface ApprovalCounts {
  students_pending: number
  partners_pending: number
}

export interface ApprovalListParams {
  status?: ApprovalStatus
  search?: string
  page?: number
}

export interface StudentRegistrationPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  student_number: string
  program_id: number | null
  year_level: number | null
  contact_number: string
  address: string
  city: string
  province: Province | ''
}

export interface PartnerRegistrationPayload {
  company_name: string
  contact_person: string
  email: string
  contact_number: string
  address: string
  city: string
  province: Province | ''
  intern_slots: string
  password: string
  password_confirmation: string
  documents: { file: File; type: PartnerDocumentType | '' }[]
}
