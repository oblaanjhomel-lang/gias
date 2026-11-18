<script setup lang="ts">
import { onMounted, ref } from 'vue'
import * as dashboardApi from '@/api/dashboard'
import StatCard from '@/components/charts/StatCard.vue'
import AdminDashboard from '@/components/dashboard/AdminDashboard.vue'
import CoordinatorDashboard from '@/components/dashboard/CoordinatorDashboard.vue'
import PartnerDashboard from '@/components/dashboard/PartnerDashboard.vue'
import StudentDashboard from '@/components/dashboard/StudentDashboard.vue'
import { errorMessage } from '@/lib/apiErrors'
import type { Dashboard } from '@/types/dashboard'

// Shared by /admin, /coordinator, /partner and /student: the API returns the role's numbers.
const data = ref<Dashboard | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    data.value = await dashboardApi.get()
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your dashboard.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <!-- Loading skeleton -->
  <div v-if="loading && !data" class="mx-auto flex max-w-[1200px] flex-col gap-6" aria-busy="true">
    <span class="sr-only" role="status">Loading your dashboard…</span>
    <div>
      <span class="block h-8 w-72 animate-pulse rounded bg-ink/10" />
      <span class="mt-3 block h-4 w-[28rem] max-w-full animate-pulse rounded bg-ink/10" />
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard v-for="n in 4" :key="n" label="…" value="" loading />
    </div>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.45fr_1fr]">
      <div class="h-64 animate-pulse rounded-[10px] bg-white" />
      <div class="h-64 animate-pulse rounded-[10px] bg-white" />
    </div>
  </div>

  <div
    v-else-if="error && !data"
    role="alert"
    class="mx-auto flex max-w-xl flex-col items-start gap-3 rounded-[10px] border border-[#B42318]/30 bg-white p-6"
  >
    <p class="text-sm text-[#B42318]">{{ error }}</p>
    <button
      type="button"
      class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      @click="load"
    >
      Retry
    </button>
  </div>

  <template v-else-if="data">
    <AdminDashboard v-if="data.role === 'admin'" :data="data" />
    <CoordinatorDashboard v-else-if="data.role === 'coordinator'" :data="data" />
    <PartnerDashboard v-else-if="data.role === 'partner'" :data="data" />
    <StudentDashboard v-else :data="data" />
  </template>
</template>
