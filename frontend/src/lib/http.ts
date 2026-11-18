import axios from 'axios'

declare module 'axios' {
  interface AxiosRequestConfig {
    /** Skip the global 401 handler (used by the boot-time /me check). */
    skipAuthRedirect?: boolean
    /** Internal: set once a request has been retried after a CSRF refresh. */
    _csrfRetried?: boolean
  }
}

const http = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

let unauthorizedHandler: (() => void) | null = null

/**
 * Registered once in main.ts (clears the auth store and routes to /login).
 * Kept as a callback so this module doesn't import the store or router.
 */
export function onUnauthorized(handler: () => void): void {
  unauthorizedHandler = handler
}

http.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    if (!axios.isAxiosError(error) || !error.response || !error.config) {
      return Promise.reject(error)
    }

    const { status } = error.response
    const config = error.config

    // 419: CSRF token expired. Get a fresh XSRF-TOKEN cookie and retry once.
    if (status === 419 && !config._csrfRetried) {
      config._csrfRetried = true
      await http.get('/sanctum/csrf-cookie')
      return http.request(config)
    }

    if (status === 401 && !config.skipAuthRedirect) {
      unauthorizedHandler?.()
    }

    return Promise.reject(error)
  },
)

export default http
