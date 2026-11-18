const dateFormat = new Intl.DateTimeFormat('en-PH', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

/** "17 Nov 2026" */
export function formatDate(iso: string): string {
  // Plain dates (Y-m-d) are calendar days, not UTC instants.
  return dateFormat.format(iso.length === 10 ? parseDate(iso) : new Date(iso))
}

/** "1.2 MB", "340 KB" */
export function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${Math.max(1, Math.round(bytes / 1024))} KB`
}

/** 1 → "1st year" */
export function yearLevel(year: number): string {
  const suffix = year === 1 ? 'st' : year === 2 ? 'nd' : year === 3 ? 'rd' : 'th'
  return `${year}${suffix} year`
}

/** Parse a Y-m-d date as a LOCAL calendar day (new Date('2026-11-17') would be UTC midnight). */
export function parseDate(ymd: string): Date {
  const [y, m, d] = ymd.slice(0, 10).split('-').map(Number)
  return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1)
}

const dayMonth = new Intl.DateTimeFormat('en-PH', { day: 'numeric', month: 'short' })

/** "17–23 Nov 2026", "28 Oct – 3 Nov 2026", "29 Dec 2026 – 4 Jan 2027" */
export function formatPeriod(start: string, end: string): string {
  const a = parseDate(start)
  const b = parseDate(end)
  if (a.getFullYear() !== b.getFullYear()) {
    return `${formatDate(start)} – ${formatDate(end)}`
  }
  if (a.getMonth() === b.getMonth()) {
    return `${a.getDate()}–${formatDate(end)}`
  }
  return `${dayMonth.format(a)} – ${formatDate(end)}`
}

/** 40 → "40", 39.5 → "39.5" */
export function formatHours(hours: number): string {
  return Number.isInteger(hours) ? String(hours) : hours.toFixed(1)
}

/** The date `days` after a Y-m-d date, as Y-m-d. */
export function addDays(ymd: string, days: number): string {
  const date = parseDate(ymd)
  date.setDate(date.getDate() + days)
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${mm}-${dd}`
}

/** plural(1, 'address', 'addresses') → "1 address"; plural(3, 'intern') → "3 interns" */
export function plural(n: number, word: string, pluralWord = `${word}s`): string {
  return `${n} ${n === 1 ? word : pluralWord}`
}

/** "Good morning/afternoon/evening" by the browser's local time. */
export function greeting(date = new Date()): string {
  const hour = date.getHours()
  return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening'
}
