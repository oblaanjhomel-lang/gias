<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as studentApi from '@/api/studentEvaluations'
import CriterionBars from '@/components/CriterionBars.vue'
import { errorMessage } from '@/lib/apiErrors'
import { formatDate } from '@/lib/format'
import { EVALUATION_TYPES, type Evaluation } from '@/types/evaluations'

/** /student/evaluation: "My evaluation" (reviewed results only). */
const evaluations = ref<Evaluation[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    evaluations.value = await studentApi.list()
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your evaluations.')
  } finally {
    loading.value = false
  }
})

/** Latest deployment's evaluation of each type (the API returns the latest deployment first). */
const byType = computed(() => ({
  midterm: evaluations.value.find((e) => e.type === 'midterm') ?? null,
  final: evaluations.value.find((e) => e.type === 'final') ?? null,
}))

const card = 'rounded-[9px] border border-ink/10 bg-white p-5'
const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <div class="space-y-5">
    <h1 class="font-serif text-[26px] leading-tight font-semibold text-ink">My evaluation</h1>

    <div role="alert">
      <p
        v-if="error"
        class="rounded-md border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-800"
      >
        {{ error }}
      </p>
    </div>
    <p v-if="loading" class="text-sm text-ink/70">Loading…</p>

    <template v-else>
      <div class="grid gap-5 lg:grid-cols-2">
        <section
          v-for="t in EVALUATION_TYPES"
          :key="t.value"
          :class="card"
          :aria-labelledby="`eval-${t.value}`"
        >
          <h2 :id="`eval-${t.value}`" :class="label">{{ t.label }} evaluation</h2>
          <template v-if="byType[t.value]">
            <p class="mt-1">
              <span class="font-serif text-3xl font-semibold text-ink">{{
                byType[t.value]!.partner_mean?.toFixed(2)
              }}</span>
              <span class="ml-2 text-sm text-ink/75">{{
                byType[t.value]!.partner_descriptor
              }}</span>
            </p>
            <p class="font-mono text-[11px] tracking-[0.06em] text-ink/70 uppercase">
              Reviewed ·
              {{ byType[t.value]!.reviewed_at ? formatDate(byType[t.value]!.reviewed_at!) : '' }}
            </p>
            <div class="mt-4"><CriterionBars :rows="byType[t.value]!.ratings" /></div>
            <template v-if="byType[t.value]!.comment">
              <p :class="[label, 'mt-4']">Supervisor’s comment</p>
              <p class="mt-1 text-sm whitespace-pre-line text-ink">
                {{ byType[t.value]!.comment }}
              </p>
            </template>
          </template>
          <p v-else class="mt-2 text-sm text-ink/70">
            Not available yet. It appears here after your OJT Coordinator reviews it.
          </p>
        </section>
      </div>

      <section aria-labelledby="final-rating" class="space-y-2">
        <h2 id="final-rating" class="sr-only">Final OJT rating</h2>
        <div class="grid gap-3 sm:grid-cols-3">
          <div :class="card">
            <p :class="label">Supervisor · 70%</p>
            <p class="mt-1 font-mono text-2xl font-medium text-ink">
              {{ byType.final ? byType.final.partner_mean?.toFixed(2) : '—' }}
            </p>
          </div>
          <div :class="card">
            <p :class="label">Coordinator · 30%</p>
            <p class="mt-1 font-mono text-2xl font-medium text-ink">
              {{ byType.final ? `${byType.final.coordinator_rating} / 5` : '—' }}
            </p>
          </div>
          <div :class="[card, 'bg-brand/5']">
            <p :class="label">Final OJT rating</p>
            <p class="mt-1">
              <span class="font-mono text-2xl font-medium text-brand">{{
                byType.final?.final_rating?.toFixed(2) ?? '—'
              }}</span>
              <span v-if="byType.final" class="ml-1.5 text-sm text-ink/75">{{
                byType.final.final_descriptor
              }}</span>
            </p>
          </div>
        </div>
        <p v-if="!byType.final" class="text-sm text-ink/70">
          Released after your OJT Coordinator reviews it.
        </p>
      </section>
    </template>
  </div>
</template>
