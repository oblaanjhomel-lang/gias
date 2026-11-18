import http from '@/lib/http'
import { csrf } from '@/api/auth'
import type { PartnerRegistrationPayload, StudentRegistrationPayload } from '@/types/approvals'

/** Returns the confirmation message (who reviews the registration). */
export async function registerStudent(payload: StudentRegistrationPayload): Promise<string> {
  await csrf()
  const { data } = await http.post<{ message: string }>('/api/register/student', payload)
  return data.message
}

/** Multipart: documents go as documents[i][file] + documents[i][type]. */
export async function registerPartner(payload: PartnerRegistrationPayload): Promise<string> {
  const form = new FormData()
  const { documents, ...fields } = payload

  for (const [key, value] of Object.entries(fields)) {
    form.append(key, value)
  }
  documents.forEach((document, i) => {
    form.append(`documents[${i}][file]`, document.file)
    form.append(`documents[${i}][type]`, document.type)
  })

  await csrf()
  const { data } = await http.post<{ message: string }>('/api/register/partner', form)
  return data.message
}
