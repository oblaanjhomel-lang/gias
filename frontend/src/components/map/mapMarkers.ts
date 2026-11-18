import L from 'leaflet'
import type { PartnerProperties, Saturation } from '@/types/map'

/** Marker fill per saturation (graphics), plus an accessible text colour for panel labels. */
export const SATURATION_STYLE: Record<Saturation, { label: string; fill: string; text: string }> = {
  open: { label: 'Open', fill: '#006B2B', text: '#006B2B' },
  near: { label: 'Nearly full', fill: '#C9A54E', text: '#7A5B14' },
  full: { label: 'Full', fill: '#A33A2E', text: '#A33A2E' },
  pending: { label: 'Pending', fill: '#8A93A0', text: '#5E6673' },
}

/** Diameter grows with interns deployed: 12 + 2.6·√filled px. */
export function markerDiameter(filled: number): number {
  return Math.round(12 + 2.6 * Math.sqrt(Math.max(0, filled)))
}

/** Escape user-supplied text (company names, addresses) before putting it in popup HTML. */
export function escapeHtml(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

export function partnerIcon(p: PartnerProperties): L.DivIcon {
  const d = markerDiameter(p.filled)
  return L.divIcon({
    className: 'gias-marker',
    html: `<span class="gias-marker__dot" style="width:${d}px;height:${d}px;background:${SATURATION_STYLE[p.saturation].fill}"></span>`,
    iconSize: [d, d],
    iconAnchor: [d / 2, d / 2],
    popupAnchor: [0, -d / 2],
  })
}

export function campusIcon(): L.DivIcon {
  return L.divIcon({
    className: 'gias-marker',
    html: '<span class="gias-campus">DNSC</span>',
    iconSize: [48, 24],
    iconAnchor: [24, 12],
    popupAnchor: [0, -12],
  })
}

export function homeIcon(): L.DivIcon {
  return L.divIcon({
    className: 'gias-marker',
    html:
      '<span class="gias-home"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">' +
      '<path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" fill="currentColor"/></svg></span>',
    iconSize: [30, 30],
    iconAnchor: [15, 15],
    popupAnchor: [0, -15],
  })
}

/** Marker being placed: a ring so it reads differently from saved pins. */
export function draftIcon(): L.DivIcon {
  return L.divIcon({
    className: 'gias-marker',
    html: '<span class="gias-draft"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  })
}

/** Popup HTML. Every value is escaped: names and addresses are user input. */
export function partnerPopup(p: PartnerProperties, lat: number, lng: number): string {
  const percent =
    p.intern_slots > 0 ? Math.min(100, Math.round((p.filled / p.intern_slots) * 100)) : 100
  const style = SATURATION_STYLE[p.saturation]
  const programs = p.interns_by_program ?? []

  const contact =
    p.contact_person || p.email
      ? `<div class="gias-popup__section">
          <div class="gias-popup__label">Contact</div>
          ${p.contact_person ? `<div>${escapeHtml(p.contact_person)}</div>` : ''}
          ${p.contact_number ? `<div>${escapeHtml(p.contact_number)}</div>` : ''}
          ${p.email ? `<div>${escapeHtml(p.email)}</div>` : ''}
        </div>`
      : ''

  const byProgram = programs.length
    ? `<div class="gias-popup__section">
        <div class="gias-popup__label">Interns by program</div>
        <div>${programs.map((r) => `${escapeHtml(r.code)} ${escapeHtml(r.count)}`).join(' · ')}</div>
      </div>`
    : ''

  return `
    <div class="gias-popup">
      <div class="gias-popup__title">${escapeHtml(p.company_name)}</div>
      <div class="gias-popup__muted">${escapeHtml(p.city)}, ${escapeHtml(p.province)}</div>
      <div class="gias-popup__section">
        <div class="gias-popup__label">Interns</div>
        <div><strong>${escapeHtml(p.filled)}</strong> of ${escapeHtml(p.intern_slots)} slots
          <span style="color:${style.text}">· ${escapeHtml(p.status === 'pending' ? 'Pending approval' : style.label)}</span></div>
        <div class="gias-popup__bar" role="presentation"><span style="width:${percent}%;background:${style.fill}"></span></div>
      </div>
      ${byProgram}
      ${contact}
      <div class="gias-popup__coords">${lat.toFixed(5)}, ${lng.toFixed(5)}</div>
    </div>`
}
