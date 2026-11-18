<script setup lang="ts">
import { computed, reactive, ref, useId } from 'vue'
import SelectField, { type SelectOption } from '@/components/SelectField.vue'
import TextField from '@/components/TextField.vue'
import { useDialog } from '@/composables/useDialog'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { useAccountsStore } from '@/stores/accounts'
import {
  PROVINCES,
  type AccountTab,
  type Coordinator,
  type IndustryPartner,
  type ManagedStatus,
  type Province,
} from '@/types/accounts'

/** Add/Edit an OJT Coordinator or Industry Partner. Mount with v-if while open. */
const props = defineProps<{
  kind: AccountTab
  /** The record to edit (matching `kind`), or null to create. */
  coordinator?: Coordinator | null
  partner?: IndustryPartner | null
}>()

const emit = defineEmits<{
  close: []
  /** Success message for the page banner. */
  saved: [message: string]
}>()

const store = useAccountsStore()
const uid = useId()

const isCoordinator = computed(() => props.kind === 'coordinators')
const record = computed(() => (isCoordinator.value ? props.coordinator : props.partner) ?? null)
const isEdit = computed(() => record.value !== null)

const title = computed(() => {
  const what = isCoordinator.value ? 'OJT Coordinator' : 'Industry Partner'
  return `${isEdit.value ? 'Edit' : 'Add'} ${what}`
})

const subtitle = computed(() => {
  if (isCoordinator.value) {
    return isEdit.value
      ? 'Update the account details and program assignment.'
      : 'Creates a DNSC staff account and emails a set-password link.'
  }
  return isEdit.value
    ? 'Update the company profile, contact and location.'
    : 'Creates a partner account and emails a set-password link.'
})

// Form state (strings, as bound to inputs; converted on submit)

const originalStatus = record.value?.status ?? 'active'

const form = reactive({
  // coordinator
  name: props.coordinator?.name ?? '',
  program_id: props.coordinator?.program ? String(props.coordinator.program.id) : '',
  // partner
  company_name: props.partner?.company_name ?? '',
  contact_person: props.partner?.contact_person ?? '',
  contact_number: props.partner?.contact_number ?? '',
  address: props.partner?.address ?? '',
  city: props.partner?.city ?? '',
  province: (props.partner?.province ?? '') as Province | '',
  intern_slots: props.partner ? String(props.partner.intern_slots) : '',
  // shared
  email: record.value?.email ?? '',
  status: originalStatus as string,
})

const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const submitting = ref(false)

const programOptions = computed<SelectOption[]>(() =>
  store.programs.map((p) => ({ value: String(p.id), label: `${p.code} — ${p.name}` })),
)
const provinceOptions: SelectOption[] = PROVINCES.map((p) => ({ value: p, label: p }))
const statusOptions = computed<SelectOption[]>(() => [
  ...(originalStatus === 'pending'
    ? [{ value: 'pending', label: 'Pending approval', disabled: true }]
    : []),
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
])

/** Only send status when the admin actually changed it. */
function changedStatus(): { status?: ManagedStatus } {
  return isEdit.value && form.status !== originalStatus && form.status !== 'pending'
    ? { status: form.status as ManagedStatus }
    : {}
}

async function submit(): Promise<void> {
  if (submitting.value) return
  submitting.value = true
  errors.value = {}
  formError.value = null

  try {
    emit('saved', isCoordinator.value ? await saveCoordinator() : await savePartner())
  } catch (e) {
    const fields = validationErrors(e)
    if (fields) {
      errors.value = fields
      focusFirstError()
    } else {
      formError.value = errorMessage(e)
    }
  } finally {
    submitting.value = false
  }
}

async function saveCoordinator(): Promise<string> {
  const payload = {
    name: form.name.trim(),
    email: form.email.trim(),
    program_id: form.program_id ? Number(form.program_id) : null,
    ...changedStatus(),
  }

  if (props.coordinator) {
    const saved = await store.updateCoordinator(props.coordinator.id, payload)
    return `Saved changes to ${saved.name}.`
  }
  const created = await store.createCoordinator(payload)
  return `Created ${created.name}. A set-password link was sent to ${created.email}.`
}

async function savePartner(): Promise<string> {
  const payload = {
    company_name: form.company_name.trim(),
    contact_person: form.contact_person.trim(),
    email: form.email.trim(),
    contact_number: form.contact_number.trim() || null,
    address: form.address.trim(),
    city: form.city.trim(),
    province: form.province,
    intern_slots: form.intern_slots.trim() === '' ? null : Number(form.intern_slots),
    ...changedStatus(),
  }

  const saved = props.partner
    ? await store.updatePartner(props.partner.id, payload)
    : await store.createPartner(payload)

  const notFound =
    saved.geocode_status === 'failed'
      ? ' Its address couldn’t be located — fix it on the map later.'
      : ''

  return props.partner
    ? `Saved changes to ${saved.company_name}.${notFound}`
    : `Created ${saved.company_name}. A set-password link was sent to ${saved.email}.${notFound}`
}

// Dialog behaviour

const dialog = ref<HTMLElement | null>(null)

function close(): void {
  if (!submitting.value) emit('close')
}

function focusFirstError(): void {
  requestAnimationFrame(() =>
    dialog.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
  )
}

useDialog(dialog, {
  onClose: close,
  initialFocus: () => dialog.value?.querySelector<HTMLElement>('form input, form select'),
})

const titleId = `${uid}-title`
const subtitleId = `${uid}-subtitle`
const field = (name: string) => `${uid}-${name}`
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4">
      <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-describedby="subtitleId"
        class="flex max-h-[calc(100vh-2rem)] w-full max-w-[520px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
      >
        <header
          class="flex items-start gap-3 bg-linear-135 from-brand to-brand-dark px-6 py-5 text-white"
        >
          <span
            aria-hidden="true"
            class="grid size-10 shrink-0 place-items-center rounded-md border border-white/30 bg-white/15 font-mono text-sm font-medium"
          >
            {{ isCoordinator ? 'OC' : 'IP' }}
          </span>
          <div class="min-w-0 flex-1">
            <h2 :id="titleId" class="font-serif text-xl leading-tight font-semibold">
              {{ title }}
            </h2>
            <p :id="subtitleId" class="mt-1 text-sm text-white/85">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            aria-label="Close"
            class="-mt-1 -mr-2 grid size-9 place-items-center rounded-md text-2xl leading-none text-white/85 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
            @click="close"
          >
            <span aria-hidden="true">×</span>
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="submit">
          <div class="space-y-6 overflow-y-auto px-6 py-5">
            <div
              v-if="formError"
              role="alert"
              class="rounded-md border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
              {{ formError }}
            </div>

            <!-- OJT Coordinator -->
            <template v-if="isCoordinator">
              <fieldset class="space-y-4">
                <legend
                  class="mb-3 font-mono text-[11px] font-medium tracking-[0.08em] text-ink/70 uppercase"
                >
                  Account information
                </legend>
                <TextField
                  :id="field('name')"
                  v-model="form.name"
                  label="Full name"
                  autocomplete="off"
                  :error="errors.name"
                />
                <TextField
                  :id="field('email')"
                  v-model="form.email"
                  label="DNSC email"
                  type="email"
                  inputmode="email"
                  autocomplete="off"
                  placeholder="name@dnsc.edu.ph"
                  :error="errors.email"
                />
              </fieldset>

              <fieldset class="space-y-4">
                <legend
                  class="mb-3 font-mono text-[11px] font-medium tracking-[0.08em] text-ink/70 uppercase"
                >
                  Assignment
                </legend>
                <div class="grid gap-4" :class="isEdit ? 'sm:grid-cols-[1fr_10rem]' : ''">
                  <SelectField
                    :id="field('program')"
                    v-model="form.program_id"
                    label="Program"
                    placeholder="Select a program"
                    :options="programOptions"
                    :error="errors.program_id"
                  />
                  <SelectField
                    v-if="isEdit"
                    :id="field('status')"
                    v-model="form.status"
                    label="Status"
                    :options="statusOptions"
                    :error="errors.status"
                  />
                </div>
              </fieldset>
            </template>

            <!-- Industry Partner -->
            <template v-else>
              <fieldset class="space-y-4">
                <legend
                  class="mb-3 font-mono text-[11px] font-medium tracking-[0.08em] text-ink/70 uppercase"
                >
                  Account information
                </legend>
                <TextField
                  :id="field('company')"
                  v-model="form.company_name"
                  label="Company name"
                  autocomplete="organization"
                  :error="errors.company_name"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                  <TextField
                    :id="field('contact-person')"
                    v-model="form.contact_person"
                    label="Contact person"
                    autocomplete="off"
                    :error="errors.contact_person"
                  />
                  <TextField
                    :id="field('contact-number')"
                    v-model="form.contact_number"
                    label="Contact number"
                    type="tel"
                    inputmode="tel"
                    autocomplete="off"
                    placeholder="09XXXXXXXXX"
                    :error="errors.contact_number"
                  />
                </div>
                <TextField
                  :id="field('email')"
                  v-model="form.email"
                  label="Email"
                  type="email"
                  inputmode="email"
                  autocomplete="off"
                  :error="errors.email"
                />
              </fieldset>

              <fieldset class="space-y-4">
                <legend
                  class="mb-3 font-mono text-[11px] font-medium tracking-[0.08em] text-ink/70 uppercase"
                >
                  Location &amp; capacity
                </legend>
                <TextField
                  :id="field('address')"
                  v-model="form.address"
                  label="Street / barangay"
                  autocomplete="off"
                  :error="errors.address"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                  <TextField
                    :id="field('city')"
                    v-model="form.city"
                    label="City or municipality"
                    autocomplete="off"
                    :error="errors.city"
                  />
                  <SelectField
                    :id="field('province')"
                    v-model="form.province"
                    label="Province"
                    placeholder="Select a province"
                    :options="provinceOptions"
                    :error="errors.province"
                  />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                  <TextField
                    :id="field('slots')"
                    v-model="form.intern_slots"
                    label="Intern slots"
                    type="number"
                    inputmode="numeric"
                    :min="0"
                    :max="100"
                    :error="errors.intern_slots"
                  />
                  <SelectField
                    v-if="isEdit"
                    :id="field('status')"
                    v-model="form.status"
                    label="Status"
                    :options="statusOptions"
                    :error="errors.status"
                  />
                </div>
                <p class="text-xs text-ink/70">
                  The address is located on the map automatically when you save.
                </p>
              </fieldset>
            </template>
          </div>

          <footer class="flex justify-end gap-3 border-t border-ink/10 bg-canvas/60 px-6 py-4">
            <button
              type="button"
              class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              @click="close"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              :aria-busy="submitting"
              class="flex h-11 items-center gap-2 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-80"
            >
              <span
                v-if="submitting"
                aria-hidden="true"
                class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
              />
              <template v-if="submitting">Saving…</template>
              <template v-else>{{ isEdit ? 'Save changes' : 'Create account' }}</template>
            </button>
          </footer>
        </form>
      </div>
    </div>
  </Teleport>
</template>
