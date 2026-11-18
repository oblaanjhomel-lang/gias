import http from '@/lib/http'
import type {
  MapConfig,
  MapFilters,
  MapSummary,
  NearbyPartner,
  NearbyResult,
  PartnerFeature,
  PartnerFeatureCollection,
  UnlocatedPartner,
} from '@/types/map'

const BASE = '/api/map'

export async function getConfig(): Promise<MapConfig> {
  const { data } = await http.get<MapConfig>(`${BASE}/config`)
  return data
}

export async function getPartners(
  filters: Partial<MapFilters> = {},
): Promise<PartnerFeatureCollection> {
  const { data } = await http.get<PartnerFeatureCollection>(`${BASE}/partners`, {
    // axios sends arrays as saturation[]=open&saturation[]=near
    params: {
      program_id: filters.programId ?? undefined,
      province: filters.province || undefined,
      saturation: filters.saturation?.length ? filters.saturation : undefined,
    },
  })
  return data
}

export async function getUnlocated(): Promise<UnlocatedPartner[]> {
  const { data } = await http.get<{ data: UnlocatedPartner[] }>(`${BASE}/unlocated`)
  return data.data
}

/** Returns the partner's updated feature (geocode_status becomes "manual"). */
export async function updateLocation(
  partnerId: number,
  lat: number,
  lng: number,
): Promise<PartnerFeature> {
  const { data } = await http.patch<PartnerFeature>(`${BASE}/partners/${partnerId}/location`, {
    latitude: lat,
    longitude: lng,
  })
  return data
}

export async function getNearby(): Promise<NearbyResult> {
  const { data } = await http.get<{
    data: NearbyPartner[]
    meta: { home_missing: boolean; home: { latitude: number; longitude: number } | null }
  }>(`${BASE}/nearby`)

  return {
    partners: data.data,
    homeMissing: data.meta.home_missing,
    home: data.meta.home ? { lat: data.meta.home.latitude, lng: data.meta.home.longitude } : null,
  }
}

export async function getSummary(radiusKm: number): Promise<MapSummary> {
  const { data } = await http.get<MapSummary>(`${BASE}/summary`, {
    params: { radius_km: radiusKm },
  })
  return data
}
