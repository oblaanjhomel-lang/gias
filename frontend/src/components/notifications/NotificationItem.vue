<script setup lang="ts">
import type { AppNotification, NotificationCategory } from '@/types/notifications'

defineProps<{ notification: AppNotification }>()
defineEmits<{ open: [notification: AppNotification] }>()

const TAGS: Record<NotificationCategory, { label: string; bg: string; fg: string }> = {
  approval: { label: 'APPROVAL', bg: '#E4EFE7', fg: '#0B3D1A' },
  deadline: { label: 'DEADLINE', bg: '#FBF6E8', fg: '#5e4610' },
  deployment: { label: 'DEPLOYMENT', bg: '#E6EEF6', fg: '#1F3F66' },
  evaluation: { label: 'EVALUATION', bg: '#EFE9F5', fg: '#45306a' },
  system: { label: 'SYSTEM', bg: '#ECEAE5', fg: '#3f444a' },
}

function tag(category: NotificationCategory) {
  return TAGS[category] ?? TAGS.system
}
</script>

<template>
  <button
    type="button"
    class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
    :class="notification.read ? 'bg-white' : 'bg-[#F6FAF7]'"
    @click="$emit('open', notification)"
  >
    <span class="mt-1.5 flex w-2 shrink-0 justify-center">
      <span v-if="!notification.read" class="size-2 rounded-full bg-brand" aria-hidden="true" />
    </span>
    <span class="min-w-0 flex-1">
      <span class="flex items-center gap-2">
        <span
          class="rounded px-1.5 py-px font-mono text-[10px] font-medium tracking-wider"
          :style="{
            backgroundColor: tag(notification.category).bg,
            color: tag(notification.category).fg,
          }"
        >
          {{ tag(notification.category).label }}
        </span>
        <time :datetime="notification.created_at" class="font-mono text-[11px] text-ink/60">
          {{ notification.created_human }}
        </time>
        <span v-if="!notification.read" class="sr-only">Unread.</span>
      </span>
      <span
        class="mt-1 block text-sm leading-snug text-ink"
        :class="notification.read ? 'font-normal' : 'font-semibold'"
      >
        {{ notification.message }}
      </span>
      <span v-if="notification.action_label" class="mt-1 block text-[13px] font-medium text-brand">
        {{ notification.action_label }} <span aria-hidden="true">→</span>
      </span>
    </span>
  </button>
</template>
