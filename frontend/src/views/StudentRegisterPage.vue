<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import * as programsApi from '@/api/programs'
import { registerStudent } from '@/api/registration'
import AuthSplitLayout from '@/components/AuthSplitLayout.vue'
import RegistrationReceivedPanel from '@/components/RegistrationReceivedPanel.vue'
import SelectField, { type SelectOption } from '@/components/SelectField.vue'
import TextField from '@/components/TextField.vue'
import { errorMessage, validationErrors } from '@/lib/apiErrors'
import { PROVINCES, type Program, type Province } from '@/types/accounts'

const programs = ref<Program[]>([])
onMounted(async () => {
  try {
    programs.value = await programsApi.list()
  } catch {
    banner.value = 'Could not load the list of programs. Refresh the page to try again.'
  }
})

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  student_number: '',
  program_id: '',
  year_level: '',
  address: '',
  city: '',
  province: '' as Province | '',
  contact_number: '',
})

const errors = ref<Record<string, string>>({})
const banner = ref<string | null>(null)
const submitting = ref(false)
const confirmation = ref<string | null>(null)

const programOptions = computed<SelectOption[]>(() =>
  programs.value.map((p) => ({ value: String(p.id), label: `${p.code} — ${p.name}` })),
)
const yearOptions: SelectOption[] = ['1st', '2nd', '3rd', '4th', '5th'].map((label, i) => ({
  value: String(i + 1),
  label: `${label} year`,
}))
const provinceOptions: SelectOption[] = PROVINCES.map((p) => ({ value: p, label: p }))

async function submit(): Promise<void> {
  errors.value = {}
  banner.value = null
  submitting.value = true

  try {
    confirmation.value = await registerStudent({
      ...form,
      name: form.name.trim(),
      email: form.email.trim(),
      student_number: form.student_number.trim(),
      program_id: form.program_id ? Number(form.program_id) : null,
      year_level: form.year_level ? Number(form.year_level) : null,
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
      <h1 class="font-serif text-3xl font-semibold text-ink">Register as a student</h1>
      <p class="mt-2 text-sm text-ink/70">
        Your program's OJT Coordinator reviews new accounts before you can sign in.
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
          <legend :class="legend">Account</legend>
          <TextField
            id="name"
            v-model="form.name"
            label="Full name"
            autocomplete="name"
            :error="errors.name"
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

        <fieldset class="space-y-4">
          <legend :class="legend">Academic</legend>
          <TextField
            id="student_number"
            v-model="form.student_number"
            label="Student number"
            autocomplete="off"
            :error="errors.student_number"
          />
          <SelectField
            id="program_id"
            v-model="form.program_id"
            label="Program"
            placeholder="Select your program"
            :options="programOptions"
            :error="errors.program_id"
          />
          <SelectField
            id="year_level"
            v-model="form.year_level"
            label="Year level"
            placeholder="Select your year level"
            :options="yearOptions"
            :error="errors.year_level"
          />
        </fieldset>

        <fieldset class="space-y-4">
          <legend :class="legend">Home address</legend>
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
            placeholder="Select your province"
            :options="provinceOptions"
            :error="errors.province"
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
          <p class="text-xs text-ink/70">Used to suggest Industry Partners near you.</p>
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
