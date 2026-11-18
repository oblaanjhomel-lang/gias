<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import GiasBadge from '@/components/GiasBadge.vue'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import { navByRole } from '@/config/nav'
import { roleLabels } from '@/config/roles'
import { useAnalyticsStore } from '@/stores/analytics'
import { useApprovalsStore } from '@/stores/approvals'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const navItems = computed(() => (auth.role ? navByRole[auth.role] : []))

// Pending-approval badge (admin: partners + students; coordinator: their program's students).
const approvals = useApprovalsStore()
const badgeCount = computed(() => approvals.totalPending)

onMounted(() => {
  if (auth.role === 'admin' || auth.role === 'coordinator') void approvals.refreshCounts()
})

// Unread notifications: polled while the signed-in shell is mounted (stops on sign-out / 401).
const notifications = useNotificationsStore()
onMounted(() => notifications.startPolling())
onBeforeUnmount(() => notifications.stopPolling())

// Current academic term for the breadcrumb ("AY 2026–2027 · 1st Semester · Dashboard").
const analytics = useAnalyticsStore()
onMounted(() => void analytics.loadTerms())
onBeforeUnmount(() => analytics.clear())
const roleLabel = computed(() => (auth.role ? roleLabels[auth.role] : ''))

const initials = computed(() => {
  const parts = (auth.user?.name ?? '').trim().split(/\s+/).filter(Boolean)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (first + last).toUpperCase()
})

const signingOut = ref(false)

async function signOut(): Promise<void> {
  signingOut.value = true
  notifications.stopPolling()
  try {
    await auth.logout()
  } finally {
    signingOut.value = false
    await router.replace({ name: 'login' })
  }
}
</script>

<template>
  <!-- Full-bleed pages (the map) get a fixed-height viewport; others scroll normally. -->
  <div class="flex" :class="route.meta.fullBleed ? 'h-screen' : 'min-h-screen'">
    <aside class="flex w-[232px] shrink-0 flex-col overflow-y-auto bg-brand text-white">
      <div class="flex items-center gap-3 border-b border-white/15 px-5 py-5">
        <GiasBadge size="sm" />
        <div>
          <p class="font-serif text-lg leading-tight font-semibold">GIAS</p>
          <p class="font-mono text-[10.5px] tracking-wider text-white/80">
            DNSC · OJT &amp; LINKAGES
          </p>
        </div>
      </div>

      <nav aria-label="Main" class="py-4">
        <ul>
          <li v-for="item in navItems" :key="item.to">
            <RouterLink
              :to="item.to"
              class="block border-l-[3px] border-transparent px-5 py-2.5 text-sm text-white/80 transition-colors hover:bg-white/5 hover:text-white focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white aria-[current=page]:border-accent aria-[current=page]:bg-white/10 aria-[current=page]:font-medium aria-[current=page]:text-white"
            >
              <span class="flex items-center justify-between gap-2">
                {{ item.label }}
                <span
                  v-if="item.badge && badgeCount > 0"
                  class="min-w-6 rounded-full bg-accent px-1.5 py-px text-center font-mono text-[11px] font-medium text-ink"
                >
                  <span class="sr-only">(</span>{{ badgeCount
                  }}<span class="sr-only"> pending)</span>
                </span>
              </span>
            </RouterLink>
          </li>
        </ul>
      </nav>
    </aside>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col">
      <header
        class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-ink/10 bg-white px-[30px]"
      >
        <nav aria-label="Breadcrumb">
          <ol class="flex items-center gap-2 font-mono text-xs text-ink/70">
            <li v-if="analytics.currentTerm">{{ analytics.currentTerm.label }}</li>
            <li v-else>{{ roleLabel }}</li>
            <li aria-hidden="true">·</li>
            <li aria-current="page" class="text-ink">{{ route.meta.title }}</li>
          </ol>
        </nav>

        <div class="flex items-center gap-4">
          <NotificationBell />

          <div class="flex items-center gap-3">
            <span
              aria-hidden="true"
              class="grid size-9 place-items-center rounded-full bg-brand font-mono text-xs font-medium text-white"
            >
              {{ initials }}
            </span>
            <div class="leading-tight">
              <p class="text-sm font-medium text-ink">{{ auth.user?.name }}</p>
              <p class="font-mono text-[11px] text-ink/70">{{ roleLabel }}</p>
            </div>
          </div>

          <button
            type="button"
            :disabled="signingOut"
            class="rounded-md border border-ink/20 px-3 py-1.5 text-sm font-medium text-ink transition-colors hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-70"
            @click="signOut"
          >
            {{ signingOut ? 'Signing out…' : 'Sign out' }}
          </button>
        </div>
      </header>

      <main
        :class="
          route.meta.fullBleed
            ? 'min-h-0 flex-1 overflow-hidden'
            : 'flex-1 bg-canvas px-[30px] py-[28px]'
        "
      >
        <RouterView />
      </main>
    </div>
  </div>
</template>
