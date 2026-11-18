<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AuthSplitLayout from '@/components/AuthSplitLayout.vue'
import { useAuthStore } from '@/stores/auth'
import type { ApiErrorBody } from '@/types/api'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

/** One-off messages from other pages, e.g. after setting a password. */
const notices: Record<string, string> = {
  'password-set': 'Password set. You can sign in now.',
}
const notice = computed(() =>
  typeof route.query.notice === 'string' ? (notices[route.query.notice] ?? null) : null,
)

const form = reactive({ email: '', password: '', remember: false })
const errors = reactive<{ email?: string; password?: string }>({})
const banner = ref<string | null>(null)
const submitting = ref(false)

function safeRedirect(): string {
  const target = route.query.redirect
  // Only same-app paths; never protocol-relative or absolute URLs.
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')
    ? target
    : auth.homeRoute
}

async function submit(): Promise<void> {
  errors.email = undefined
  errors.password = undefined
  banner.value = null
  submitting.value = true

  try {
    await auth.login({ ...form })
    await router.replace(safeRedirect())
  } catch (e) {
    if (!axios.isAxiosError<ApiErrorBody>(e) || !e.response) {
      banner.value = 'Cannot reach the server. Please try again.'
      return
    }

    const { status, data } = e.response
    if (status === 422) {
      errors.email = data.errors?.email?.[0]
      errors.password = data.errors?.password?.[0]
    } else if (status === 403) {
      banner.value = data.message
    } else if (status === 429) {
      banner.value = 'Too many sign-in attempts. Please wait a minute and try again.'
    } else {
      banner.value = 'Something went wrong. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}

const inputClass =
  'mt-1.5 block w-full rounded-md border border-ink/25 bg-white px-3 py-2.5 text-ink placeholder:text-ink/50 focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand aria-[invalid=true]:border-red-700'
</script>

<template>
  <AuthSplitLayout>
    <h1 class="font-serif text-3xl font-semibold text-ink">Sign in</h1>

    <div
      v-if="notice && !banner"
      role="status"
      class="mt-6 rounded-md border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-ink"
    >
      {{ notice }}
    </div>

    <div
      v-if="banner"
      role="alert"
      class="mt-6 rounded-md border border-accent bg-accent/10 px-4 py-3 text-sm text-ink"
    >
      {{ banner }}
    </div>

    <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
      <div>
        <label for="email" class="text-sm font-medium text-ink">Email</label>
        <input
          id="email"
          v-model.trim="form.email"
          type="email"
          name="email"
          autocomplete="username"
          required
          :aria-invalid="errors.email ? 'true' : 'false'"
          :aria-describedby="errors.email ? 'email-error' : undefined"
          :class="inputClass"
        />
        <p v-if="errors.email" id="email-error" class="mt-1.5 text-sm text-red-700">
          {{ errors.email }}
        </p>
      </div>

      <div>
        <label for="password" class="text-sm font-medium text-ink">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          name="password"
          autocomplete="current-password"
          required
          :aria-invalid="errors.password ? 'true' : 'false'"
          :aria-describedby="errors.password ? 'password-error' : undefined"
          :class="inputClass"
        />
        <p v-if="errors.password" id="password-error" class="mt-1.5 text-sm text-red-700">
          {{ errors.password }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <input
          id="remember"
          v-model="form.remember"
          type="checkbox"
          name="remember"
          class="size-4 rounded accent-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        />
        <label for="remember" class="text-sm text-ink">Keep me signed in</label>
      </div>

      <button
        type="submit"
        :disabled="submitting"
        :aria-busy="submitting"
        class="flex w-full items-center justify-center gap-2 rounded-md bg-brand px-4 py-2.5 font-medium text-white transition-colors hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-80"
      >
        <span
          v-if="submitting"
          aria-hidden="true"
          class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
        />
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>

    <nav aria-label="Register" class="mt-8 border-t border-ink/10 pt-6">
      <ul class="space-y-2 text-sm">
        <li>
          <RouterLink
            :to="{ name: 'register-student' }"
            class="font-medium text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            Register as a student
          </RouterLink>
        </li>
        <li>
          <RouterLink
            :to="{ name: 'register-partner' }"
            class="font-medium text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            Register your company
          </RouterLink>
        </li>
      </ul>
    </nav>
  </AuthSplitLayout>
</template>
