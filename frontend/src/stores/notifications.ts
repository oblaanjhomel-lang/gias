import { defineStore } from 'pinia'
import * as notificationsApi from '@/api/notifications'
import { errorMessage } from '@/lib/apiErrors'
import type { PaginationMeta } from '@/types/accounts'
import type { AppNotification, NotificationFilter } from '@/types/notifications'

const POLL_MS = 60_000

let timer: ReturnType<typeof setInterval> | null = null
let visibilityListener: (() => void) | null = null
/** Drops responses from superseded list requests (fast tab switching). */
let latestRequest = 0
let latestPanelRequest = 0

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    unreadCount: 0,

    /** Bell panel: first page only, its own filter. */
    panelItems: [] as AppNotification[],
    panelFilter: 'all' as NotificationFilter,
    panelLoading: false,
    panelError: null as string | null,

    /** The full /notifications page. */
    items: [] as AppNotification[],
    filter: 'all' as NotificationFilter,
    page: 1,
    meta: null as PaginationMeta | null,
    loading: false,
    error: null as string | null,

    emailNotifications: true,
    preferencesLoaded: false,
  }),

  actions: {
    /**
     * Polls the unread count every 60 s while signed in. Pauses while the tab is hidden and
     * refreshes as soon as it is visible again. Safe to call more than once.
     */
    startPolling(): void {
      if (visibilityListener) return

      const tick = (): void => void this.refreshUnreadCount()
      const start = (): void => {
        if (timer === null) timer = setInterval(tick, POLL_MS)
      }
      const pause = (): void => {
        if (timer !== null) clearInterval(timer)
        timer = null
      }

      visibilityListener = () => {
        if (document.visibilityState === 'visible') {
          tick()
          start()
        } else {
          pause()
        }
      }
      document.addEventListener('visibilitychange', visibilityListener)

      if (document.visibilityState === 'visible') {
        tick()
        start()
      }
    },

    /** Called on sign-out: stops polling and forgets everything. */
    stopPolling(): void {
      if (timer !== null) clearInterval(timer)
      timer = null
      if (visibilityListener) document.removeEventListener('visibilitychange', visibilityListener)
      visibilityListener = null
      this.$reset()
    },

    async refreshUnreadCount(): Promise<void> {
      try {
        this.unreadCount = await notificationsApi.unreadCount()
      } catch {
        // The badge is decorative; keep the last known count.
      }
    },

    async fetch(): Promise<void> {
      const request = ++latestRequest
      this.loading = true
      this.error = null
      try {
        const result = await notificationsApi.list(this.filter, this.page)
        if (request !== latestRequest) return
        this.items = result.data
        this.meta = result.meta

        if (result.meta.last_page > 0 && this.page > result.meta.last_page) {
          this.page = result.meta.last_page
          await this.fetch()
        }
      } catch (e) {
        if (request === latestRequest) this.error = errorMessage(e, 'Could not load notifications.')
      } finally {
        if (request === latestRequest) this.loading = false
      }
    },

    async fetchPanel(): Promise<void> {
      const request = ++latestPanelRequest
      this.panelLoading = true
      this.panelError = null
      try {
        const result = await notificationsApi.list(this.panelFilter, 1)
        if (request !== latestPanelRequest) return
        this.panelItems = result.data
      } catch (e) {
        if (request === latestPanelRequest) {
          this.panelError = errorMessage(e, 'Could not load notifications.')
        }
      } finally {
        if (request === latestPanelRequest) this.panelLoading = false
      }
    },

    setPanelFilter(filter: NotificationFilter): Promise<void> {
      this.panelFilter = filter
      return this.fetchPanel()
    },

    setFilter(filter: NotificationFilter): Promise<void> {
      this.filter = filter
      this.page = 1
      return this.fetch()
    },

    setPage(page: number): Promise<void> {
      this.page = page
      return this.fetch()
    },

    async markRead(id: string): Promise<void> {
      const known = [...this.panelItems, ...this.items].find((n) => n.id === id)
      if (known?.read) return

      const updated = await notificationsApi.markRead(id)
      const swap = (n: AppNotification): AppNotification => (n.id === id ? updated : n)
      this.panelItems = this.panelItems.map(swap)
      this.items = this.items.map(swap)
      this.unreadCount = Math.max(0, this.unreadCount - 1)
    },

    async markAllRead(): Promise<void> {
      await notificationsApi.markAllRead()
      this.unreadCount = 0
      this.panelItems =
        this.panelFilter === 'unread' ? [] : this.panelItems.map((n) => ({ ...n, read: true }))
      if (this.filter === 'unread') {
        this.items = []
        this.meta = this.meta
          ? { ...this.meta, total: 0, last_page: 1, from: null, to: null }
          : null
      } else {
        this.items = this.items.map((n) => ({ ...n, read: true }))
      }
    },

    async loadPreferences(): Promise<void> {
      if (this.preferencesLoaded) return
      try {
        this.emailNotifications = (await notificationsApi.preferences()).email_notifications
        this.preferencesLoaded = true
      } catch {
        // Keep the default; the checkbox reloads it next time the panel opens.
      }
    },

    /** Saves immediately; reverts the checkbox if the request fails. */
    async setEmailNotifications(value: boolean): Promise<void> {
      const previous = this.emailNotifications
      this.emailNotifications = value
      try {
        this.emailNotifications = (
          await notificationsApi.updatePreferences({ email_notifications: value })
        ).email_notifications
      } catch (e) {
        this.emailNotifications = previous
        throw e
      }
    },
  },
})
