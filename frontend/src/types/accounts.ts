// Mirrors backend CoordinatorResource / IndustryPartnerResource / ProgramResource.
import type { Program, Status } from '@/types/auth'

export type { Program, Status }

/** Mirrors backend App\Enums\Province. */
export const PROVINCES = [
  'Davao del Norte',
  'Davao City',
  'Davao de Oro',
  'Davao del Sur',
  'Davao Oriental',
  'Davao Occidental',
] as const

export type Province = (typeof PROVINCES)[number]

export type GeocodeStatus = 'pending' | 'located' | 'failed' | 'manual'

/** Statuses an admin can set directly (pending is only for self-registered accounts). */
export type ManagedStatus = 'active' | 'inactive'

export type AccountTab = 'coordinators' | 'partners'

export interface Coordinator {
  id: number
  name: string
  email: string
  status: Status
  program: Program | null
  last_login_at: string | null
  created_at: string
}

export interface IndustryPartner {
  /** The partner's USER id (what /api/admin/partners/{id} takes). */
  id: number
  email: string
  status: Status
  company_name: string
  contact_person: string
  contact_number: string | null
  address: string
  city: string
  province: Province
  intern_slots: number
  latitude: number | null
  longitude: number | null
  geocode_status: GeocodeStatus
  created_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T, M extends object = object> {
  data: T[]
  meta: PaginationMeta & M
}

export interface CoordinatorListMeta {
  programs_without_active_coordinator: Program[]
}

export interface ListParams {
  search?: string
  status?: Status | ''
  page?: number
}

export interface CoordinatorPayload {
  name: string
  email: string
  program_id: number | null
  status?: ManagedStatus
}

export interface PartnerPayload {
  company_name: string
  contact_person: string
  email: string
  contact_number: string | null
  address: string
  city: string
  province: Province | ''
  intern_slots: number | null
  status?: ManagedStatus
  /** Manual pin; send both or neither. */
  latitude?: number
  longitude?: number
}
