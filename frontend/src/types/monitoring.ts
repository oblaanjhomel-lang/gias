// Mirrors backend WeeklyReportResource, DeploymentDetailResource, MonitoringRowResource,
// DeploymentProgressResource and DeploymentMonitoringService::summary().

export type ReportStatus = 'draft' | 'submitted' | 'signed' | 'returned'

export type Day = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun'

export const DAYS: Day[] = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

export const DAY_LABELS: Record<Day, string> = {
  mon: 'Mon',
  tue: 'Tue',
  wed: 'Wed',
  thu: 'Thu',
  fri: 'Fri',
  sat: 'Sat',
  sun: 'Sun',
}

export type DailyHours = Record<Day, number>

export const MAX_WEEK_HOURS = 60

export interface WeeklyReport {
  id: number
  deployment_id: number
  week_number: number
  /** Y-m-d, computed by the server. */
  period_start: string
  period_end: string
  daily_hours: DailyHours
  total_hours: number
  tasks: string
  learnings: string | null
  status: ReportStatus
  submitted_at: string | null
  signed_at: string | null
  signed_by?: string | null
  return_note: string | null
  /** Partner lists only. */
  intern?: { name: string; program: string | null } | null
}

export type MonitoringStatus = 'active' | 'overdue' | 'completed' | 'withdrawn'

export interface Progress {
  signed_hours: number
  pending_hours: number
  required_hours: number
  missing_hours: number
  percent: number
  weeks_elapsed: number
  last_report_period_end: string | null
  is_overdue: boolean
  status: MonitoringStatus
}

/** A week that has started on the student's active deployment. */
export interface ReportWeek {
  week_number: number
  period_start: string
  period_end: string
  report_id: number | null
  status: ReportStatus | null
}

export interface DeploymentDetail {
  id: number
  status: 'active' | 'completed' | 'withdrawn'
  start_date: string
  end_date: string | null
  completed_at: string | null
  completed_by: string | null
  completion_note: string | null
  student: {
    id: number
    name: string
    student_number: string
    program: { code: string; name: string } | null
  }
  partner: {
    id: number
    company_name: string
    address: string
    city: string
    province: string
    contact_person: string
    contact_number: string | null
  }
  coordinator: { name: string; email: string } | null
  progress: Progress
  reports: WeeklyReport[]
  /** Student view only. */
  weeks: ReportWeek[] | null
}

export interface MonitoringRow {
  id: number
  student: { id: number; name: string; student_number: string; program: string | null }
  partner: { id: number; company_name: string; city: string }
  start_date: string
  status: 'active' | 'completed' | 'withdrawn'
  progress: Progress
}

export interface MonitoringSummary {
  active: number
  overdue: number
  completed: number
  withdrawn: number
  reports_awaiting_signature: number
  average_percent: number | null
}

export type MonitoringFilter = 'all' | 'active' | 'overdue' | 'completed' | 'withdrawn'

export interface PartnerIntern {
  id: number
  intern: { name: string; program: string | null }
  start_date: string
  status: 'active' | 'completed' | 'withdrawn'
  progress: Progress
}

export interface ReportPayload {
  daily_hours: DailyHours
  tasks: string
  learnings: string | null
}
