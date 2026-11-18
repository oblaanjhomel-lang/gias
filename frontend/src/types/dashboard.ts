// Mirrors backend DashboardService (GET /api/dashboard). Numbers only; the UI writes sentences.

export interface AdminDashboard {
  role: 'admin'
  stats: {
    active_partners: number
    pending_partners: number
    active_deployments: number
    provinces_covered: number
    unlocated_partners: number
    /** Program codes */
    programs_without_coordinator: string[]
    evaluations_awaiting_review: number
  }
  saturation_top: { company: string; city: string; filled: number; slots: number; open: number }[]
  queue: {
    pending_partner_approvals: number
    unlocated_partners: number
    programs_without_coordinator: number
    evaluations_awaiting_review: number
  }
}

export interface CoordinatorDashboard {
  role: 'coordinator'
  stats: {
    active_interns: number
    pending_students: number
    open_slots: number
    overdue_interns: number
    unplaced_students: number
  }
  /** The last row has unplaced: true (company null, interns = unplaced students). */
  interns_by_partner: {
    company: string | null
    city: string | null
    interns: number
    open: number | null
    overdue: number | null
    unplaced: boolean
  }[]
  queue: {
    pending_students: number
    overdue_interns: number
    unplaced_students: number
    evaluations_to_review: number
  }
}

export interface PartnerDashboard {
  role: 'partner'
  stats: {
    interns_hosted: number
    slots: number
    reports_to_sign: number
    evaluations_open: number
    programs_served: string[]
  }
  interns: {
    name: string
    program: string | null
    signed_hours: number
    required_hours: number
    last_report: string | null
  }[]
  queue: { reports_to_sign: number; evaluations_open: number }
}

export type NextStep =
  | { type: 'report_due'; week: number; date: string }
  | { type: 'report_returned'; week: number; date: string }
  | { type: 'evaluation_released'; date: string | null }
  | { type: 'not_placed' }

export interface StudentDashboard {
  role: 'student'
  stats: {
    signed_hours: number
    required_hours: number
    pending_hours: number
    deployment_status: 'active' | 'completed'
    partner: { company: string; city: string }
    last_report_period_end: string | null
    last_report_status: string | null
  } | null
  weeks_remaining_estimate: number | null
  placement: {
    company: string
    city: string
    supervisor: string
    coordinator: string | null
    start_date: string
    next_report: { week: number; date: string } | null
  } | null
  next_steps: NextStep[]
}

export type Dashboard = AdminDashboard | CoordinatorDashboard | PartnerDashboard | StudentDashboard
