<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import NotificationItem from '@/components/notifications/NotificationItem.vue'
import { errorMessage } from '@/lib/apiErrors'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification, NotificationFilter } from '@/types/notifications'

const store = useNotificationsStore()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const bell = ref<HTMLButtonElement | null>(null)
const panel = ref<HTMLElement | null>(null)

const badge = computed(() => (store.unreadCount > 9 ? '9+' : String(store.unreadCount)))

const toast = ref<string | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | null = null
function showToast(message: string): void {
  toast.value = message
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (toast.value = null), 3500)
}

async function openPanel(): Promise<void> {
  open.value = true
  void store.loadPreferences()
  void store.refreshUnreadCount()
  void store.fetchPanel()
  await nextTick()
  panel.value?.focus()
}

function close(returnFocus = true): void {
  if (!open.value) return
  open.value = false
  if (returnFocus) void nextTick(() => bell.value?.focus())
}

function toggle(): void {
  if (open.value) close()
  else void openPanel()
}

function onKeydown(event: KeyboardEvent): void {
  if (open.value && event.key === 'Escape') {
    event.preventDefault()
    close()
  }
}

function onPointerDown(event: PointerEvent): void {
  if (open.value && root.value && !root.value.contains(event.target as Node)) close(false)
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('pointerdown', onPointerDown)
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('pointerdown', onPointerDown)
  if (toastTimer) clearTimeout(toastTimer)
})

watch(
  () => route.fullPath,
  () => close(false),
)

function setFilter(filter: NotificationFilter): void {
  if (filter !== store.panelFilter) void store.setPanelFilter(filter)
}

async function openItem(item: AppNotification): Promise<void> {
  try {
    await store.markRead(item.id)
  } catch {
    // Still navigate; it stays unread.
  }
  close()
  if (item.action_path) await router.push(item.action_path)
}

async function markAll(): Promise<void> {
  try {
    await store.markAllRead()
  } catch (e) {
    showToast(errorMessage(e, 'Could not mark notifications as read.'))
  }
}

async function onEmailToggle(event: Event): Promise<void> {
  const value = (event.target as HTMLInputElement).checked
  try {
    await store.setEmailNotifications(value)
    showToast(value ? 'Email notifications turned on.' : 'Email notifications turned off.')
  } catch (e) {
    showToast(errorMessage(e, 'Could not save your email preference.'))
  }
}
</script>

<template>
  <div ref="root" class="relative">
    <button
      ref="bell"
      type="button"
      class="relative grid size-[34px] place-items-center rounded-md border border-ink/15 text-ink/80 transition-colors hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      :class="open ? 'border-brand text-brand' : ''"
      :aria-label="`Notifications, ${store.unreadCount} unread`"
      :aria-expanded="open"
      aria-haspopup="dialog"
      aria-controls="notifications-panel"
      @click="toggle"
    >
      <svg
        aria-hidden="true"
        viewBox="0 0 24 24"
        class="size-[18px]"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      <span
        v-if="store.unreadCount > 0"
        aria-hidden="true"
        class="absolute -top-1.5 -right-1.5 min-w-[18px] rounded-full bg-[#C62828] px-1 text-center font-mono text-[10px] leading-[18px] font-medium text-white ring-2 ring-white"
      >
        {{ badge }}
      </span>
    </button>

    <div
      v-if="open"
      id="notifications-panel"
      ref="panel"
      role="dialog"
      aria-label="Notifications"
      tabindex="-1"
      class="absolute top-[calc(100%+10px)] right-0 z-[1100] flex w-[392px] flex-col overflow-hidden rounded-[10px] border border-ink/10 bg-white shadow-xl focus:outline-none"
    >
      <div class="flex items-center justify-between border-b border-ink/10 px-4 pt-4 pb-3">
        <h2 class="font-serif text-lg font-semibold text-ink">Notifications</h2>
        <button
          type="button"
          :disabled="store.unreadCount === 0"
          class="text-[13px] font-medium text-brand hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
          @click="markAll"
        >
          Mark all as read
        </button>
      </div>

      <div class="flex gap-5 border-b border-ink/10 px-4" role="group" aria-label="Show">
        <button
          v-for="f in ['all', 'unread'] as const"
          :key="f"
          type="button"
          :aria-pressed="store.panelFilter === f"
          class="-mb-px border-b-2 pt-2.5 pb-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          :class="
            store.panelFilter === f
              ? 'border-brand text-brand'
              : 'border-transparent text-ink/70 hover:text-ink'
          "
          @click="setFilter(f)"
        >
          {{ f === 'all' ? 'All' : `Unread (${store.unreadCount})` }}
        </button>
      </div>

      <div class="max-h-[420px] overflow-y-auto" :aria-busy="store.panelLoading">
        <p v-if="store.panelError" class="px-4 py-6 text-sm text-[#B42318]">
          {{ store.panelError }}
        </p>
        <p
          v-else-if="store.panelLoading && store.panelItems.length === 0"
          class="px-4 py-6 text-sm text-ink/70"
        >
          Loading…
        </p>
        <p
          v-else-if="store.panelItems.length === 0"
          class="px-4 py-10 text-center text-sm text-ink/70"
        >
          You're all caught up.
        </p>
        <ul v-else class="divide-y divide-ink/10">
          <li v-for="item in store.panelItems" :key="item.id">
            <NotificationItem :notification="item" @open="openItem" />
          </li>
        </ul>
      </div>

      <div class="flex flex-col gap-2 border-t border-ink/10 bg-canvas/60 px-4 py-3">
        <RouterLink
          to="/notifications"
          class="self-start text-[13px] font-medium text-brand hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          View all
        </RouterLink>
        <label class="flex items-center gap-2 text-[13px] text-ink/80">
          <input
            type="checkbox"
            class="size-4 accent-brand"
            :checked="store.emailNotifications"
            @change="onEmailToggle"
          />
          Also email these to {{ auth.user?.email }}
        </label>
      </div>
    </div>

    <div role="status" class="pointer-events-none fixed right-6 bottom-6 z-[1200]">
      <p v-if="toast" class="rounded-md bg-ink px-4 py-2.5 text-sm text-white shadow-lg">
        {{ toast }}
      </p>
    </div>
  </div>
</template>
