<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useRecommendationsStore } from '@/stores/recommendations'
import { ALGORITHM_LABELS, ALGORITHMS, type Algorithm } from '@/types/recommendations'

/** All three algorithms side by side, their agreement, and a 1–5 rating for each. */
const store = useRecommendationsStore()

const nameOf = (id: number | null) =>
  id === null ? '—' : (store.partnerById.get(id)?.company_name ?? '—')

const agreement = computed(() => store.result?.agreement ?? null)

const ratings = reactive<
  Record<Algorithm, { rating: number | null; comment: string; saved: boolean; saving: boolean }>
>({
  cos: { rating: null, comment: '', saved: false, saving: false },
  wsm: { rating: null, comment: '', saved: false, saving: false },
  fuzzy: { rating: null, comment: '', saved: false, saving: false },
})

async function save(algorithm: Algorithm): Promise<void> {
  const entry = ratings[algorithm]
  if (entry.rating === null) return
  entry.saving = true
  entry.saved = await store.rate(algorithm, entry.rating, entry.comment)
  entry.saving = false
}

function choose(algorithm: Algorithm, rating: number): void {
  ratings[algorithm].rating = rating
  ratings[algorithm].saved = false
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="agreement"
      role="status"
      class="rounded-md border px-4 py-3 text-sm"
      :class="
        agreement.all_agree
          ? 'border-brand/30 bg-brand/5 text-ink'
          : 'border-accent bg-accent/10 text-ink'
      "
    >
      <p class="font-medium" :class="agreement.all_agree ? 'text-brand' : 'text-accent-ink'">
        {{
          agreement.all_agree
            ? 'All three algorithms agree on the top pick.'
            : 'The algorithms disagree on the top pick.'
        }}
      </p>
      <p class="mt-1">
        <template v-for="(a, i) in ALGORITHMS" :key="a">
          {{ ALGORITHM_LABELS[a] }}: <strong>{{ nameOf(agreement.top[a]) }}</strong
          ><template v-if="i < ALGORITHMS.length - 1"> · </template>
        </template>
      </p>
    </div>

    <div class="overflow-x-auto rounded-[9px] border border-ink/10 bg-white">
      <table class="w-full text-left text-sm">
        <caption class="sr-only">
          Scores and ranks under each algorithm
        </caption>
        <thead class="bg-canvas/60 font-mono text-[10.5px] tracking-[0.06em] text-ink/70 uppercase">
          <tr>
            <th scope="col" class="px-3 py-2.5 font-medium">Partner</th>
            <th scope="col" class="px-3 py-2.5 font-medium">Cosine</th>
            <th scope="col" class="px-3 py-2.5 font-medium">WSM</th>
            <th scope="col" class="px-3 py-2.5 font-medium">Fuzzy</th>
            <th scope="col" class="px-3 py-2.5 font-medium">Avg rank</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink/10">
          <tr v-if="!store.result?.compare.length">
            <td colspan="5" class="px-3 py-8 text-center text-ink/70">
              No partners with open slots to compare.
            </td>
          </tr>
          <tr v-for="row in store.result?.compare ?? []" :key="row.partner_id">
            <td class="px-3 py-3">
              <button
                type="button"
                class="text-left font-medium text-ink underline-offset-2 hover:text-brand hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                @click="store.explain(row.partner_id)"
              >
                {{ row.company_name }}
              </button>
            </td>
            <td
              v-for="a in ALGORITHMS"
              :key="a"
              class="px-3 py-3 font-mono text-xs whitespace-nowrap"
              :class="row[a].rank === 1 ? 'font-semibold text-brand' : 'text-ink'"
            >
              {{ row[a].score.toFixed(2) }} · #{{ row[a].rank }}
            </td>
            <td class="px-3 py-3 font-mono text-xs text-ink">{{ row.average_rank.toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <section aria-labelledby="rate-title" class="rounded-[9px] border border-ink/10 bg-white p-4">
      <h3 id="rate-title" class="font-serif text-base font-semibold text-ink">
        Rate these recommendations
      </h3>
      <p class="mt-0.5 text-xs text-ink/70">
        How useful was each algorithm’s list for this student? Ratings are exported for evaluation.
      </p>
      <div class="mt-3 grid gap-4 md:grid-cols-3">
        <fieldset v-for="a in ALGORITHMS" :key="a" class="space-y-2">
          <legend class="text-sm font-medium text-ink">{{ ALGORITHM_LABELS[a] }}</legend>
          <div role="group" :aria-label="`Rating for ${ALGORITHM_LABELS[a]}`" class="flex gap-1">
            <button
              v-for="n in 5"
              :key="n"
              type="button"
              :aria-pressed="ratings[a].rating === n"
              :aria-label="`${n} out of 5`"
              class="size-8 rounded-md border font-mono text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
              :class="
                ratings[a].rating === n
                  ? 'border-brand bg-brand text-white'
                  : 'border-ink/20 text-ink hover:border-brand'
              "
              @click="choose(a, n)"
            >
              {{ n }}
            </button>
          </div>
          <label :for="`comment-${a}`" class="sr-only"
            >Comment for {{ ALGORITHM_LABELS[a] }} (optional)</label
          >
          <textarea
            :id="`comment-${a}`"
            v-model="ratings[a].comment"
            rows="2"
            maxlength="1000"
            placeholder="Comment (optional)"
            class="block w-full rounded-md border border-ink/25 px-2.5 py-1.5 text-sm text-ink focus-visible:border-brand focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand"
            @input="ratings[a].saved = false"
          />
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="ratings[a].rating === null || ratings[a].saving"
              class="h-8 rounded-md border border-brand px-3 text-xs font-medium text-brand hover:bg-brand/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50"
              @click="save(a)"
            >
              {{ ratings[a].saving ? 'Saving…' : 'Save rating' }}
            </button>
            <span v-if="ratings[a].saved" role="status" class="text-xs text-brand">Saved</span>
          </div>
        </fieldset>
      </div>
    </section>
  </div>
</template>
