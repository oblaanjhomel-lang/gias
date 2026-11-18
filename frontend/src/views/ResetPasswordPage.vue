<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import * as authApi from '@/api/auth'
import AuthSplitLayout from '@/components/AuthSplitLayout.vue'
import TextField from '@/components/TextField.vue'
import { errorMessage, validationErrors } from '@/lib/apiErrors'

const route = useRoute()
const router = useRouter()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))
const linkIsComplete = computed(() => token.value !== '' && email.value !== '')

const form = reactive({ password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const banner = ref<string | null>(null)
const submitting = ref(false)

async function submit(): Promise<void> {
  errors.value = {}
  banner.value = null
  submitting.value = true

  try {
    await authApi.csrf()
    await authApi.resetPassword({ token: token.value, email: email.value, ...form })
    await router.replace({ name: 'login', query: { notice: 'password-set' } })
  } catch (e) {
    const fields = validationErrors(e)
    if (fields?.password || fields?.password_confirmation) {
      errors.value = fields
    } else if (fields?.email || fields?.token) {
      // Invalid / expired / already-used token.
      banner.value = `${fields.email ?? fields.token} Ask the System Development Office to send a new link.`
    } else {
      banner.value = errorMessage(e)
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthSplitLayout>
    <h1 class="font-serif text-3xl font-semibold text-ink">Set your password</h1>
    <p v-if="email" class="mt-2 text-sm text-ink/70">
      For <span class="font-medium text-ink">{{ email }}</span>
    </p>

    <div
      v-if="!linkIsComplete"
      role="alert"
      class="mt-6 rounded-md border border-accent bg-accent/10 px-4 py-3 text-sm text-ink"
    >
      This link is incomplete. Open the link from your email again, or ask the System Development
      Office for a new one.
    </div>

    <template v-else>
      <div
        v-if="banner"
        role="alert"
        class="mt-6 rounded-md border border-accent bg-accent/10 px-4 py-3 text-sm text-ink"
      >
        {{ banner }}
      </div>

      <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
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
          {{ submitting ? 'Saving…' : 'Set password' }}
        </button>
      </form>
    </template>

    <p class="mt-8 text-sm">
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      >
        Back to sign in
      </RouterLink>
    </p>
  </AuthSplitLayout>
</template>
