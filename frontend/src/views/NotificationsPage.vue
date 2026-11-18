<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import NotificationItem from '@/components/notifications/NotificationItem.vue'
import { errorMessage } from '@/lib/apiErrors'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification, NotificationFilter } from '@/types/notifications'

const store = useNotificationsStore()
const router = useRouter()
const actionError = ref<string | null>(null)

onMounted(() => {
  store.filter = 'all'
  store.page = 1
  void store.fetch()
  void store.refreshUnreadCount()
})

function setFilter(filter: NotificationFilter): void {
  if (filter !== store.filter) void store.setFilter(filter)
}

async function openItem(item: AppNotification): Promise<void> {
  try {
    await store.markRead(item.id)
  } catch {
    // Still navigate; it stays unread.
  }
  if (item.action_path) await router.push(item.action_path)
}

async function markAll(): Promise<void> {
  actionError.value = null
  try {
    await store.markAllRead()
  } catch (e) {
    actionError.value = errorMessage(e, 'Could not mark notifications as read.')
  }
}
</script>

<template>
  <div class="mx-auto flex max-w-3xl flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">Notifications</h1>
        <p class="mt-1 text-sm text-ink/70">Updates about your account, reports and evaluations.</p>
      </div>
      <button
        type="button"
        :disabled="store.unreadCount === 0"
        class="h-9 rounded-md border border-ink/20 bg-white px-3 text-sm font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
        @click="markAll"
      >
        Mark all as read
      </button>
    </div>

    <div class="flex gap-6 border-b border-ink/10" role="group" aria-label="Show">
      <button
        v-for="f in ['all', 'unread'] as const"
        :key="f"
        type="button"
        :aria-pressed="store.filter === f"
        class="-mb-px border-b-2 px-0.5 pt-1 pb-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="
          store.filter === f
            ? 'border-brand text-brand'
            : 'border-transparent text-ink/70 hover:text-ink'
        "
        @click="setFilter(f)"
      >
        {{ f === 'all' ? 'All' : `Unread (${store.unreadCount})` }}
      </button>
    </div>

    <div role="status">
      <p
        v-if="actionError || store.error"
        class="rounded-md border border-[#B42318]/30 bg-[#B42318]/5 px-4 py-3 text-sm text-[#B42318]"
      >
        {{ actionError ?? store.error }}
      </p>
    </div>

    <div
      class="overflow-hidden rounded-[10px] border border-ink/10 bg-white"
      :aria-busy="store.loading"
    >
      <p v-if="store.loading && store.items.length === 0" class="px-4 py-8 text-sm text-ink/70">
        Loading…
      </p>
      <p
        v-else-if="!store.error && store.items.length === 0"
        class="px-4 py-12 text-center text-sm text-ink/70"
      >
        You're all caught up.
      </p>
      <ul v-else class="divide-y divide-ink/10">
        <li v-for="item in store.items" :key="item.id">
          <NotificationItem :notification="item" @open="openItem" />
        </li>
      </ul>
    </div>

    <nav
      v-if="store.meta && store.meta.last_page > 1"
      aria-label="Pagination"
      class="flex flex-wrap items-center justify-between gap-3 text-sm"
    >
      <p class="text-ink/70">
        Showing {{ store.meta.from }}–{{ store.meta.to }} of {{ store.meta.total }}
      </p>
      <div class="flex items-center gap-2">
        <button
          type="button"
          :disabled="store.meta.current_page <= 1 || store.loading"
          class="h-9 rounded-md border border-ink/20 bg-white px-3 font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
          @click="store.setPage(store.meta.current_page - 1)"
        >
          Previous
        </button>
        <span class="font-mono text-xs text-ink/70">
          Page {{ store.meta.current_page }} of {{ store.meta.last_page }}
        </span>
        <button
          type="button"
          :disabled="store.meta.current_page >= store.meta.last_page || store.loading"
          class="h-9 rounded-md border border-ink/20 bg-white px-3 font-medium text-ink hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:pointer-events-none disabled:opacity-40"
          @click="store.setPage(store.meta.current_page + 1)"
        >
          Next
        </button>
      </div>
    </nav>
  </div>
</template>
