export type NotificationCategory = 'approval' | 'deadline' | 'deployment' | 'evaluation' | 'system'

export type NotificationFilter = 'all' | 'unread'

export interface AppNotification {
  id: string
  category: NotificationCategory
  message: string
  action_path: string | null
  action_label: string | null
  read: boolean
  /** ISO 8601 */
  created_at: string
  /** "just now", "5 min ago", "2 h ago", "3 d ago", then a date */
  created_human: string
}

export interface NotificationPreferences {
  email_notifications: boolean
}
