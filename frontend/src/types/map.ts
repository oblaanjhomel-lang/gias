// Mirrors backend MapService / MapController / NearbyPartnerResource / UnlocatedPartnerResource.
import type { GeocodeStatus, Province, Status } from '@/types/accounts'

export type Saturation = 'open' | 'near' | 'full' | 'pending'

export const SATURATIONS: Saturation[] = ['open', 'near', 'full', 'pending']

export interface LatLng {
  lat: number
  lng: number
}

export interface PartnerProperties {
  /** User id of the partner account. */
  id: number
  company_name: string
  city: string
  province: Province
  status: Status
  intern_slots: number
  filled: number
  open: number
  saturation: Saturation
  geocode_status: GeocodeStatus
  /** Not sent to students. */
  interns_by_program?: { code: string; count: number }[]
  /** Admin, coordinator, and a partner viewing their own feature. */
  contact_person?: string
  contact_number?: string | null
  email?: string
  address?: string
}

export interface PartnerFeature {
  type: 'Feature'
  id: number
  /** null only for a partner's own, not-yet-located site. [lng, lat] per GeoJSON. */
  geometry: { type: 'Point'; coordinates: [number, number] } | null
  properties: PartnerProperties
}

export interface PartnerFeatureCollection {
  type: 'FeatureCollection'
  features: PartnerFeature[]
}

export interface MapConfig {
  campus: { name: string; lat: number; lng: number }
  region_bounds: { min_lat: number; max_lat: number; min_lng: number; max_lng: number }
  coverage_radius_km: number
}

export interface ProvinceSummary {
  province: Province
  partners: number
  interns: number
  open_slots: number
}

export interface MapSummary {
  partners: number
  interns_deployed: number
  open_slots: number
  provinces: ProvinceSummary[]
  coverage: {
    radius_km: number
    students_unplaced: number
    within_radius: number
    /** null when there are no unplaced students. */
    percent: number | null
  }
}

export interface UnlocatedPartner {
  id: number
  company_name: string
  address: string
  city: string
  province: Province
  status: Status
  geocode_status: GeocodeStatus
}

export interface NearbyPartner {
  id: number
  company_name: string
  city: string
  province: Province
  distance_km: number
  intern_slots: number
  open: number
  latitude: number
  longitude: number
}

export interface NearbyResult {
  partners: NearbyPartner[]
  homeMissing: boolean
  home: LatLng | null
}

export interface MapFilters {
  programId: number | null
  province: Province | ''
  saturation: Saturation[]
}
