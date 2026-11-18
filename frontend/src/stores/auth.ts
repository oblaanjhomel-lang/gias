import { defineStore } from 'pinia'
import * as authApi from '@/api/auth'
import { roleHome } from '@/config/roles'
import type { LoginPayload, Role, User } from '@/types/auth'

let initPromise: Promise<void> | null = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    /** True once the boot-time session check has finished. */
    ready: false,
  }),

  getters: {
    isAuthenticated: (state): boolean => state.user !== null,
    role: (state): Role | null => state.user?.role ?? null,
    homeRoute(): string {
      return this.role ? roleHome[this.role] : '/login'
    },
  },

  actions: {
    /** Checks the existing session once per page load; later calls reuse the same promise. */
    init(): Promise<void> {
      initPromise ??= (async () => {
        try {
          this.user = await authApi.me({ silent: true })
        } catch {
          // 401 (no session) or backend unreachable: treat as signed out.
          this.user = null
        } finally {
          this.ready = true
        }
      })()
      return initPromise
    },

    async login(payload: LoginPayload): Promise<User> {
      await authApi.csrf()
      const user = await authApi.login(payload)
      this.user = user
      return user
    },

    async logout(): Promise<void> {
      try {
        await authApi.logout()
      } finally {
        this.clear()
      }
    },

    clear(): void {
      this.user = null
    },
  },
})
