import axios from 'axios'
import type { ApiErrorBody } from '@/types/api'

/** First message per field from a Laravel 422, or null if this isn't a validation error. */
export function validationErrors(error: unknown): Record<string, string> | null {
  if (axios.isAxiosError<ApiErrorBody>(error) && error.response?.status === 422) {
    const errors = error.response.data.errors ?? {}
    return Object.fromEntries(
      Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? '']),
    )
  }
  return null
}

/** One human-readable sentence for a failed request. */
export function errorMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.',
): string {
  if (!axios.isAxiosError<ApiErrorBody>(error)) return fallback
  if (!error.response) return 'Cannot reach the server. Please try again.'

  const body = error.response.data
  const firstFieldError = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined
  return firstFieldError ?? body?.message ?? fallback
}
