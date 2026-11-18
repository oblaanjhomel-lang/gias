<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { useDialog } from '@/composables/useDialog'

/** Asks for the reason a registration is returned. Mount with v-if while open. */
const props = defineProps<{
  name: string
  busy: boolean
  /** Server-side validation message for `reason`, if any. */
  error?: string
}>()
const emit = defineEmits<{ cancel: []; confirm: [reason: string] }>()

const MIN = 10
const MAX = 500

const uid = useId()
const dialog = ref<HTMLElement | null>(null)
const textarea = ref<HTMLTextAreaElement | null>(null)
const reason = ref('')
const touched = ref(false)

const length = computed(() => reason.value.trim().length)
const localError = computed(() => {
  if (!touched.value) return undefined
  if (length.value < MIN)
    return `Write at least ${MIN} characters so the applicant knows what to fix.`
  return undefined
})
const shownError = computed(() => props.error ?? localError.value)

function submit(): void {
  touched.value = true
  if (length.value < MIN || length.value > MAX) {
    textarea.value?.focus()
    return
  }
  emit('confirm', reason.value.trim())
}

useDialog(dialog, {
  onClose: () => {
    if (!props.busy) emit('cancel')
  },
  initialFocus: () => textarea.value,
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4">
      <div
        ref="dialog"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="`${uid}-title`"
        :aria-describedby="`${uid}-desc`"
        class="w-full max-w-[480px] rounded-xl bg-white p-6 shadow-2xl"
      >
        <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
          Return {{ name }}’s registration?
        </h2>
        <p :id="`${uid}-desc`" class="mt-2 text-sm leading-relaxed text-ink/75">
          The applicant can’t sign in and is emailed your reason. You can still approve it later
          from the Returned list.
        </p>

        <form class="mt-5" novalidate @submit.prevent="submit">
          <label :for="`${uid}-reason`" class="block text-sm font-medium text-ink">
            Reason (sent to the applicant)
          </label>
          <textarea
            :id="`${uid}-reason`"
            ref="textarea"
            v-model="reason"
            rows="4"
            :maxlength="MAX"
            required
            :aria-invalid="shownError ? 'true' : undefined"
            :aria-describedby="`${uid}-count${shownError ? ` ${uid}-error` : ''}`"
            class="mt-1.5 block w-full resize-y rounded-md border bg-white px-3 py-2.5 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
            :class="
              shownError
                ? 'border-red-700 focus-visible:outline-red-700'
                : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
            "
            @blur="touched = true"
          />
          <div class="mt-1.5 flex items-start justify-between gap-3 text-sm">
            <p :id="`${uid}-error`" class="text-red-700">{{ shownError }}</p>
            <p :id="`${uid}-count`" class="shrink-0 font-mono text-xs text-ink/70">
              {{ length }}/{{ MAX }}<span class="sr-only"> characters, minimum {{ MIN }}</span>
            </p>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <button
              type="button"
              :disabled="busy"
              class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              @click="emit('cancel')"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="busy"
              :aria-busy="busy"
              class="h-11 rounded-md border border-red-700 px-5 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:cursor-wait disabled:opacity-70"
            >
              {{ busy ? 'Returning…' : 'Return registration' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
