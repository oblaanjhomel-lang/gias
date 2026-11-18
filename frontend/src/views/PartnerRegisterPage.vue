<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { registerPartner } from '@/api/registration'
import AuthSplitLayout from '@/components/AuthSplitLayout.vue'
import RegistrationReceivedPanel from '@/components/RegistrationReceivedPanel.vue'
import SelectField, { type SelectOption } from '@/components/SelectField.vue'
import TextField from '@/components/TextField.vue'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { formatBytes } from '@/lib/format'
import { PROVINCES, type Province } from '@/types/accounts'
import { DOCUMENT_TYPES, type PartnerDocumentType } from '@/types/approvals'

const MAX_DOCUMENTS = 3
const MAX_BYTES = 5 * 1024 * 1024

const form = reactive({
  company_name: '',
  contact_person: '',
  email: '',
  contact_number: '',
  address: '',
  city: '',
  province: '' as Province | '',
  intern_slots: '',
  password: '',
  password_confirmation: '',
})

/** `key` keeps v-for stable when a row is removed. */
const documents = ref<{ key: number; file: File; type: PartnerDocumentType | '' }[]>([])
let nextKey = 0

const errors = ref<Record<string, string>>({})
const fileError = ref<string | null>(null)
const banner = ref<string | null>(null)
const submitting = ref(false)
const confirmation = ref<string | null>(null)

const provinceOptions: SelectOption[] = PROVINCES.map((p) => ({ value: p, label: p }))
const typeOptions: SelectOption[] = DOCUMENT_TYPES.map((t) => ({ value: t.value, label: t.label }))

// Documents

const fileInput = ref<HTMLInputElement | null>(null)

function onFileChosen(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = '' // allow choosing the same file again after removing it
  fileError.value = null
  if (!file) return

  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
  if (!isPdf) {
    fileError.value = `${file.name} is not a PDF.`
  } else if (file.size > MAX_BYTES) {
    fileError.value = `${file.name} is larger than 5 MB.`
  } else if (documents.value.length < MAX_DOCUMENTS) {
    documents.value.push({ key: nextKey++, file, type: '' })
  }
}

function removeDocument(index: number): void {
  documents.value.splice(index, 1)
  // Server errors are keyed by index, so they no longer line up.
  errors.value = Object.fromEntries(
    Object.entries(errors.value).filter(([k]) => !k.startsWith('documents')),
  )
}

function documentError(index: number): string | undefined {
  return errors.value[`documents.${index}.file`]
}

// Submit

async function submit(): Promise<void> {
  errors.value = {}
  banner.value = null
  submitting.value = true

  try {
    confirmation.value = await registerPartner({
      ...form,
      company_name: form.company_name.trim(),
      contact_person: form.contact_person.trim(),
      email: form.email.trim(),
      documents: documents.value.map(({ file, type }) => ({ file, type })),
    })
  } catch (e) {
    const fields = validationErrors(e)
    if (fields) {
      errors.value = fields
      requestAnimationFrame(() =>
        document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
      )
    } else {
      banner.value = errorMessage(e)
    }
  } finally {
    submitting.value = false
  }
}

const legend = 'mb-3 font-mono text-[11px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <AuthSplitLayout>
    <RegistrationReceivedPanel v-if="confirmation" :message="confirmation" />

    <template v-else>
      <h1 class="font-serif text-3xl font-semibold text-ink">Register your company</h1>
      <p class="mt-2 text-sm text-ink/70">
        The GIAS Admin reviews new Industry Partner accounts before you can sign in.
      </p>

      <div
        v-if="banner"
        role="alert"
        class="mt-6 rounded-md border border-accent bg-accent/10 px-4 py-3 text-sm text-ink"
      >
        {{ banner }}
      </div>

      <form class="mt-6 space-y-7" novalidate @submit.prevent="submit">
        <fieldset class="space-y-4">
          <legend :class="legend">Company</legend>
          <TextField
            id="company_name"
            v-model="form.company_name"
            label="Company name"
            autocomplete="organization"
            :error="errors.company_name"
          />
          <TextField
            id="contact_person"
            v-model="form.contact_person"
            label="Contact person"
            autocomplete="name"
            :error="errors.contact_person"
          />
          <TextField
            id="email"
            v-model="form.email"
            label="Email"
            type="email"
            inputmode="email"
            autocomplete="email"
            :error="errors.email"
          />
          <TextField
            id="contact_number"
            v-model="form.contact_number"
            label="Contact number"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="09XXXXXXXXX"
            :error="errors.contact_number"
          />
        </fieldset>

        <fieldset class="space-y-4">
          <legend :class="legend">Location &amp; capacity</legend>
          <TextField
            id="address"
            v-model="form.address"
            label="Street / barangay"
            autocomplete="address-line1"
            :error="errors.address"
          />
          <TextField
            id="city"
            v-model="form.city"
            label="City or municipality"
            autocomplete="address-level2"
            :error="errors.city"
          />
          <SelectField
            id="province"
            v-model="form.province"
            label="Province"
            placeholder="Select a province"
            :options="provinceOptions"
            :error="errors.province"
          />
          <TextField
            id="intern_slots"
            v-model="form.intern_slots"
            label="Intern slots"
            type="number"
            inputmode="numeric"
            :min="0"
            :max="100"
            hint="How many OJT interns you can take."
            :error="errors.intern_slots"
          />
        </fieldset>

        <fieldset class="space-y-3">
          <legend :class="legend">Documents (optional)</legend>
          <p id="documents-hint" class="text-xs text-ink/70">
            Up to 3 PDF files, 5 MB each: SEC/DTI registration, business permit, or an MOA draft.
          </p>

          <ul v-if="documents.length" class="space-y-3">
            <li
              v-for="(document, index) in documents"
              :key="document.key"
              class="rounded-md border border-ink/15 bg-white p-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-ink">{{ document.file.name }}</p>
                  <p class="font-mono text-xs text-ink/70">{{ formatBytes(document.file.size) }}</p>
                </div>
                <button
                  type="button"
                  :aria-label="`Remove ${document.file.name}`"
                  class="shrink-0 rounded px-2 py-1 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-red-700"
                  @click="removeDocument(index)"
                >
                  Remove
                </button>
              </div>
              <SelectField
                :id="`document-type-${document.key}`"
                v-model="document.type"
                class="mt-2"
                :label="`Document type for ${document.file.name}`"
                placeholder="Select a type"
                :options="typeOptions"
                :error="errors[`documents.${index}.type`]"
              />
              <p v-if="documentError(index)" class="mt-1.5 text-sm text-red-700">
                {{ documentError(index) }}
              </p>
            </li>
          </ul>

          <p v-if="errors.documents" class="text-sm text-red-700">{{ errors.documents }}</p>
          <p v-if="fileError" role="alert" class="text-sm text-red-700">{{ fileError }}</p>

          <input
            ref="fileInput"
            type="file"
            accept="application/pdf,.pdf"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
            @change="onFileChosen"
          />
          <button
            type="button"
            :disabled="documents.length >= MAX_DOCUMENTS"
            aria-describedby="documents-hint"
            class="h-11 rounded-md border border-ink/25 px-4 text-sm font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-50"
            @click="fileInput?.click()"
          >
            {{ documents.length >= MAX_DOCUMENTS ? 'Maximum of 3 documents' : 'Add a PDF' }}
          </button>
        </fieldset>

        <fieldset class="space-y-4">
          <legend :class="legend">Account</legend>
          <TextField
            id="password"
            v-model="form.password"
            label="Password"
            type="password"
            autocomplete="new-password"
            hint="At least 8 characters, with letters and numbers."
            :error="errors.password"
          />
          <TextField
            id="password_confirmation"
            v-model="form.password_confirmation"
            label="Confirm password"
            type="password"
            autocomplete="new-password"
            :error="errors.password_confirmation"
          />
        </fieldset>

        <button
          type="submit"
          :disabled="submitting"
          :aria-busy="submitting"
          class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-brand px-4 font-medium text-white transition-colors hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-80"
        >
          <span
            v-if="submitting"
            aria-hidden="true"
            class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
          />
          {{ submitting ? 'Submitting…' : 'Submit registration' }}
        </button>
      </form>

      <p class="mt-8 text-sm text-ink/70">
        Already have an account?
        <RouterLink
          :to="{ name: 'login' }"
          class="font-medium text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          Sign in
        </RouterLink>
      </p>
    </template>
  </AuthSplitLayout>
</template>
