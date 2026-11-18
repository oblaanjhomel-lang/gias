import http from '@/lib/http'
import type { DeploymentRecord, PlacementItem } from '@/types/recommendations'

/** The only way a student gets placed: an explicit coordinator / admin action. */
export async function place(item: PlacementItem): Promise<DeploymentRecord> {
  const { data } = await http.post<{ data: DeploymentRecord }>('/api/placements', item)
  return data.data
}

/** All-or-nothing: a failure rolls back every item and names the failing one. */
export async function placeBatch(items: PlacementItem[]): Promise<DeploymentRecord[]> {
  const { data } = await http.post<{ data: DeploymentRecord[] }>('/api/placements/batch', { items })
  return data.data
}

export async function withdraw(deploymentId: number): Promise<DeploymentRecord> {
  const { data } = await http.patch<{ data: DeploymentRecord }>(
    `/api/placements/${deploymentId}/withdraw`,
  )
  return data.data
}
