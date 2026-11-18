import type { Role } from '@/types/auth'

export const roleLabels: Record<Role, string> = {
  admin: 'Admin',
  coordinator: 'OJT Coordinator',
  partner: 'Industry Partner',
  student: 'Student',
}

export const roleHome: Record<Role, string> = {
  admin: '/admin',
  coordinator: '/coordinator',
  partner: '/partner',
  student: '/student',
}
