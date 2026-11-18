/** Laravel's JSON error body (422 validation, 403, 429, …). */
export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}
