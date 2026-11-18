// Mirrors backend App\Http\Resources\UserResource.

export type Role = 'admin' | 'coordinator' | 'partner' | 'student'

/** `rejected` = registration returned by a reviewer (shown as "Returned"). */
export type Status = 'pending' | 'active' | 'inactive' | 'rejected'

export interface Program {
  id: number
  code: string
  name: string
  /** Hours that must be countersigned to complete OJT (Module 6). */
  required_ojt_hours?: number
}

export interface User {
  id: number
  name: string
  email: string
  role: Role
  status: Status
  program: Program | null
  last_login_at: string | null
}

export interface LoginPayload {
  email: string
  password: string
  remember: boolean
}
