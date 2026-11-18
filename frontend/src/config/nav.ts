import type { Role } from '@/types/auth'

export interface NavItem {
  label: string
  to: string
  /** Show a count badge next to the label. */
  badge?: 'pendingApprovals'
}

/** Sidebar items per role. Modules add their entries here. */
export const navByRole: Record<Role, NavItem[]> = {
  admin: [
    { label: 'Dashboard', to: '/admin' },
    { label: 'User accounts', to: '/admin/accounts' },
    { label: 'Approvals', to: '/admin/approvals', badge: 'pendingApprovals' },
    { label: 'GIS map', to: '/admin/map' },
    { label: 'Recommendations', to: '/admin/recommendations' },
    { label: 'Skills & matching', to: '/admin/skills' },
    { label: 'All deployments', to: '/admin/monitoring' },
    { label: 'Evaluations', to: '/admin/evaluations' },
    { label: 'Analytics', to: '/admin/analytics' },
  ],
  coordinator: [
    { label: 'Dashboard', to: '/coordinator' },
    { label: 'Student approvals', to: '/coordinator/approvals', badge: 'pendingApprovals' },
    { label: 'GIS map', to: '/coordinator/map' },
    { label: 'Recommendations', to: '/coordinator/recommendations' },
    { label: 'Intern monitoring', to: '/coordinator/monitoring' },
    { label: 'Evaluations', to: '/coordinator/evaluations' },
    { label: 'Analytics', to: '/coordinator/analytics' },
  ],
  partner: [
    { label: 'Dashboard', to: '/partner' },
    { label: 'Our site on map', to: '/partner/map' },
    { label: 'Skills we need', to: '/partner/skills' },
    { label: 'Our interns', to: '/partner/interns' },
    { label: 'Evaluate interns', to: '/partner/evaluations' },
  ],
  student: [
    { label: 'Dashboard', to: '/student' },
    { label: 'Find a partner', to: '/student/map' },
    { label: 'My skills', to: '/student/skills' },
    { label: 'My deployment', to: '/student/deployment' },
    { label: 'My evaluation', to: '/student/evaluation' },
  ],
}
