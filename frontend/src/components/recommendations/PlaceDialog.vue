<script setup lang="ts">
import { ref, useId } from 'vue'
import { useDialog } from '@/composables/useDialog'

/** "Place {student} at {company}" with a start date. Mount with v-if while open. */
const props = defineProps<{ student: string; company: string; busy: boolean }>()
const emit = defineEmits<{ cancel: []; confirm: [startDate: string] }>()

const uid = useId()
const dialog = ref<HTMLElement | null>(null)
const dateInput = ref<HTMLInputElement | null>(null)
const startDate = ref(new Date().toISOString().slice(0, 10))

useDialog(dialog, {
  onClose: () => {
    if (!props.busy) emit('cancel')
  },
  initialFocus: () => dateInput.value,
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4">
      <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${uid}-title`"
        class="w-full max-w-[420px] rounded-xl bg-white p-6 shadow-2xl"
      >
        <h2 :id="`${uid}-title`" class="font-serif text-xl font-semibold text-ink">
          Place {{ student }} at {{ company }}?
        </h2>
        <form class="mt-4" @submit.prevent="emit('confirm', startDate)">
          <label :for="`${uid}-date`" class="block text-sm font-medium text-ink">Start date</label>
          <input
            :id="`${uid}-date`"
            ref="dateInput"
            v-model="startDate"
            type="date"
            required
            class="mt-1.5 block h-11 w-full rounded-md border border-ink/25 px-3 text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
          />
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
              :disabled="busy || !startDate"
              class="h-11 rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-70"
            >
              {{ busy ? 'Placing…' : 'Confirm' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
