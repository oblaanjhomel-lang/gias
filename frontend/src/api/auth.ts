import http from '@/lib/http'
import type { LoginPayload, User } from '@/types/auth'

export async function csrf(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
}

export async function login(payload: LoginPayload): Promise<User> {
  const { data } = await http.post<{ data: User }>('/api/auth/login', payload)
  return data.data
}

export async function logout(): Promise<void> {
  await http.post('/api/auth/logout')
}

export async function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<void> {
  await http.post('/api/auth/reset-password', payload)
}

/** `silent` skips the global 401 redirect (boot-time session check). */
export async function me(options: { silent?: boolean } = {}): Promise<User> {
  const { data } = await http.get<{ data: User }>('/api/auth/me', {
    skipAuthRedirect: options.silent,
  })
  return data.data
}
