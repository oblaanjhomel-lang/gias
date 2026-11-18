<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { useDialog } from '@/composables/useDialog'

/**
 * Confirmation (role="alertdialog"), optionally asking for a required note with a counter.
 * Mount with v-if while open.
 */
const props = withDefaults(
  defineProps<{
    title: string
    message: string
    confirmLabel: string
    busy?: boolean
    /** Show a note textarea with this label (required, noteMin–noteMax characters). */
    noteLabel?: string
    noteMin?: number
    noteMax?: number
    error?: string | null
  }>(),
  { busy: false, noteLabel: undefined, noteMin: 10, noteMax: 500, error: null },
)
const emit = defineEmits<{ cancel: []; confirm: [note: string] }>()

const uid = useId()
const dialog = ref<HTMLElement | null>(null)
const field = ref<HTMLTextAreaElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)
const note = ref('')
const touched = ref(false)

const length = computed(() => note.value.trim().length)
const noteError = computed(() =>
  props.noteLabel && touched.value && length.value < props.noteMin
    ? `Write at least ${props.noteMin} characters.`
    : null,
)

function confirm(): void {
  touched.value = true
  if (props.noteLabel && (length.value < props.noteMin || length.value > props.noteMax)) {
    field.value?.focus()
    return
  }
  emit('confirm', note.value.trim())
}

useDialog(dialog, {
  onClose: () => {
    if (!props.busy) emit('cancel')
  },
  initialFocus: () => field.value ?? cancelButton.value,
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
        class="w-full max-w-[460px] rounded-xl bg-white p-6 shadow-2xl"
      >
        <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">{{ title }}</h2>
        <p :id="`${uid}-desc`" class="mt-2 text-sm leading-relaxed text-ink/75">{{ message }}</p>

        <div v-if="noteLabel" class="mt-4">
          <label :for="`${uid}-note`" class="block text-sm font-medium text-ink">{{
            noteLabel
          }}</label>
          <textarea
            :id="`${uid}-note`"
            ref="field"
            v-model="note"
            rows="3"
            :maxlength="noteMax"
            required
            :aria-invalid="noteError ? 'true' : undefined"
            :aria-describedby="`${uid}-count`"
            class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-0"
            :class="
              noteError
                ? 'border-red-700 focus-visible:outline-red-700'
                : 'border-ink/25 focus-visible:border-brand focus-visible:outline-brand'
            "
            @blur="touched = true"
          />
          <div class="mt-1 flex justify-between gap-3 text-xs">
            <p class="text-red-700">{{ noteError }}</p>
            <p :id="`${uid}-count`" class="shrink-0 font-mono text-ink/70">
              {{ length }}/{{ noteMax
              }}<span class="sr-only"> characters, minimum {{ noteMin }}</span>
            </p>
          </div>
        </div>

        <p v-if="error" role="alert" class="mt-3 text-sm text-red-700">{{ error }}</p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            ref="cancelButton"
            type="button"
            :disabled="busy"
            class="h-11 rounded-md px-4 text-sm font-medium text-ink hover:bg-ink/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            @click="emit('cancel')"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="busy"
            :aria-busy="busy"
            class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-70"
            @click="confirm"
          >
            {{ busy ? 'Working…' : confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
