<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CompareView from '@/components/recommendations/CompareView.vue'
import DraftDialog from '@/components/recommendations/DraftDialog.vue'
import PlaceDialog from '@/components/recommendations/PlaceDialog.vue'
import RankingTable from '@/components/recommendations/RankingTable.vue'
import StudentQueue from '@/components/recommendations/StudentQueue.vue'
import WhyScoreCard from '@/components/recommendations/WhyScoreCard.vue'
import { useRecommendationsStore } from '@/stores/recommendations'
import { ALGORITHM_LABELS, ALGORITHMS, type RecommendationView } from '@/types/recommendations'
import { LEVEL_LABELS } from '@/types/skills'

/** /coordinator/recommendations and /admin/recommendations. Advisory: placing is always explicit. */
const store = useRecommendationsStore()

onMounted(() => {
  store.$reset()
  void store.loadStudents()
})

const views: { key: RecommendationView; label: string }[] = [
  ...ALGORITHMS.map((a) => ({ key: a, label: ALGORITHM_LABELS[a] })),
  { key: 'compare', label: 'Compare all three' },
]

const student = computed(() => store.result?.student ?? null)

// Place dialog
const placing = ref<{ partnerId: number; company: string } | null>(null)

async function confirmPlace(startDate: string): Promise<void> {
  if (!placing.value) return
  await store.place(placing.value.partnerId, startDate) // success or error shows in a banner
  placing.value = null
}

async function openDraft(): Promise<void> {
  await store.openDraft()
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">
          Placement recommendations
        </h1>
        <p class="mt-1 text-sm leading-relaxed text-ink/70">
          Ranks Industry Partners for each student by comparing the student’s skills with the skills
          each partner needs. This is advisory: you make the final placement. Partners with no open
          slots are left out of the ranking.
        </p>
      </div>
      <div
        role="group"
        aria-label="Algorithm"
        class="inline-flex flex-wrap rounded-md border border-ink/20 bg-white p-0.5"
      >
        <button
          v-for="v in views"
          :key="v.key"
          type="button"
          :aria-pressed="store.view === v.key"
          class="rounded px-3 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand"
          :class="store.view === v.key ? 'bg-brand text-white' : 'text-ink/75 hover:text-ink'"
          @click="store.setView(v.key)"
        >
          {{ v.label }}
        </button>
      </div>
    </div>

    <!-- Banners -->
    <div role="status">
      <div
        v-if="store.notice"
        class="flex items-start justify-between gap-4 rounded-md border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-ink"
      >
        <p>{{ store.notice }}</p>
        <button
          type="button"
          aria-label="Dismiss message"
          class="rounded px-1.5 text-lg leading-none text-ink/70 hover:text-ink focus-visible:outline-2 focus-visible:outline-brand"
          @click="store.notice = null"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
    </div>
    <div role="status">
      <div
        v-if="store.error"
        class="flex items-start justify-between gap-4 rounded-md border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-800"
      >
        <p>{{ store.error }}</p>
        <button
          type="button"
          aria-label="Dismiss error"
          class="rounded px-1.5 text-lg leading-none text-red-800/80 hover:text-red-800 focus-visible:outline-2 focus-visible:outline-red-700"
          @click="store.error = null"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[290px_1fr]">
      <StudentQueue @draft="openDraft" />

      <div class="min-w-0 space-y-4" :aria-busy="store.loadingResult">
        <p
          v-if="!student && store.loadingResult"
          class="rounded-[9px] border border-ink/10 bg-white px-5 py-10 text-center text-sm text-ink/70"
        >
          Loading recommendations…
        </p>
        <p
          v-else-if="!student"
          class="rounded-[9px] border border-ink/10 bg-white px-5 py-10 text-center text-sm text-ink/70"
        >
          Select a student to see recommendations.
        </p>

        <template v-else>
          <!-- Student -->
          <section
            aria-labelledby="student-name"
            class="rounded-[9px] border border-ink/10 bg-white p-4"
            :class="store.loadingResult ? 'opacity-60' : ''"
          >
            <h2 id="student-name" class="font-serif text-xl font-semibold text-ink">
              {{ student.name }}
            </h2>
            <p class="mt-0.5 font-mono text-[11px] tracking-[0.06em] text-ink/70 uppercase">
              {{ student.program?.code ?? 'No program' }} · Home: {{ student.city }}
              <template v-if="!student.has_home_location"> (not located)</template>
            </p>
            <ul
              v-if="student.skills.length"
              class="mt-3 flex flex-wrap gap-1.5"
              aria-label="Student skills"
            >
              <li
                v-for="s in student.skills"
                :key="s.skill_id"
                class="rounded-full bg-brand/10 px-2.5 py-0.5 text-xs text-brand"
              >
                {{ s.name }} · {{ LEVEL_LABELS[s.level] }}
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-accent-ink">
              No skills entered yet. Scores will be low until the student adds them.
            </p>
            <p v-if="student.placement" class="mt-3 text-sm font-medium text-brand">
              Placed at {{ student.placement.company_name }}
            </p>
          </section>

          <CompareView v-if="store.view === 'compare'" :key="student.id" />
          <RankingTable
            v-else
            @place="(partnerId, company) => (placing = { partnerId, company })"
            @withdraw="store.withdraw"
          />

          <p v-if="store.result?.excluded.length" class="text-sm text-ink/70">
            Not ranked because they have no open slots:
            {{ store.result.excluded.map((p) => p.company_name).join(', ') }}.
          </p>

          <WhyScoreCard />
        </template>
      </div>
    </div>

    <PlaceDialog
      v-if="placing && student"
      :student="student.name"
      :company="placing.company"
      :busy="store.working"
      @cancel="placing = null"
      @confirm="confirmPlace"
    />

    <DraftDialog v-if="store.draft" :plan="store.draft" @close="store.closeDraft()" />
  </div>
</template>
