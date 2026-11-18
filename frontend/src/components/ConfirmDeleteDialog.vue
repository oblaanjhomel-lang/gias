<script setup lang="ts">
import { ref, useId } from 'vue'
import { useDialog } from '@/composables/useDialog'

/** "Delete {name}?" confirmation. Mount with v-if while open. */
const props = defineProps<{ name: string; busy: boolean }>()
const emit = defineEmits<{ cancel: []; confirm: [] }>()

const uid = useId()
const dialog = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)

useDialog(dialog, {
  onClose: () => {
    if (!props.busy) emit('cancel')
  },
  // Destructive action: start on the safe choice.
  initialFocus: () => cancelButton.value,
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
        class="w-full max-w-[440px] rounded-xl bg-white p-6 shadow-2xl"
      >
        <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
          Delete {{ name }}?
        </h2>
        <p :id="`${uid}-desc`" class="mt-2 text-sm leading-relaxed text-ink/75">
          This permanently deletes the account and its records, and can’t be undone. To keep the
          records, deactivate the account instead.
        </p>

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
            class="h-11 rounded-md bg-red-700 px-5 text-sm font-medium text-white hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:cursor-wait disabled:opacity-80"
            @click="emit('confirm')"
          >
            {{ busy ? 'Deleting…' : 'Delete account' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
