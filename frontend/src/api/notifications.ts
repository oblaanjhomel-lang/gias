import http from '@/lib/http'
import type { Paginated } from '@/types/accounts'
import type {
  AppNotification,
  NotificationFilter,
  NotificationPreferences,
} from '@/types/notifications'

const BASE = '/api/notifications'

export async function list(
  filter: NotificationFilter = 'all',
  page = 1,
): Promise<Paginated<AppNotification>> {
  const { data } = await http.get<Paginated<AppNotification>>(BASE, { params: { filter, page } })
  return data
}

export async function unreadCount(): Promise<number> {
  const { data } = await http.get<{ data: { count: number } }>(`${BASE}/unread-count`)
  return data.data.count
}

export async function markRead(id: string): Promise<AppNotification> {
  const { data } = await http.post<{ data: AppNotification }>(`${BASE}/${id}/read`)
  return data.data
}

export async function markAllRead(): Promise<number> {
  const { data } = await http.post<{ data: { marked: number } }>(`${BASE}/read-all`)
  return data.data.marked
}

export async function preferences(): Promise<NotificationPreferences> {
  const { data } = await http.get<{ data: NotificationPreferences }>(
    '/api/me/notification-preferences',
  )
  return data.data
}

export async function updatePreferences(
  prefs: NotificationPreferences,
): Promise<NotificationPreferences> {
  const { data } = await http.put<{ data: NotificationPreferences }>(
    '/api/me/notification-preferences',
    prefs,
  )
  return data.data
}
