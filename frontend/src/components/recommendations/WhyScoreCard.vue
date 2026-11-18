<script setup lang="ts">
import { computed } from 'vue'
import { useRecommendationsStore } from '@/stores/recommendations'
import type { WsmCriterion } from '@/types/recommendations'
import { LEVEL_LABELS } from '@/types/skills'

/** "Why this score": the three algorithms' working for the selected partner. */
const store = useRecommendationsStore()

const e = computed(() => store.explanation)
const partner = computed(() => (e.value ? store.partnerById.get(e.value.partner_id) : undefined))

const CRITERION: Record<WsmCriterion, string> = {
  coverage: 'Skill coverage',
  proficiency: 'Proficiency',
  proximity: 'Proximity',
  slots: 'Open slots',
}

const pct = (v: number) => `${Math.round(v * 100)}%`
const deg = (v: number) => v.toFixed(2)

const label = 'font-mono text-[10.5px] font-medium tracking-[0.08em] text-ink/70 uppercase'
</script>

<template>
  <section
    v-if="e && partner"
    aria-labelledby="why-title"
    class="rounded-[9px] border border-ink/10 bg-white p-4"
  >
    <h3 id="why-title" class="font-serif text-base font-semibold text-ink">
      Why this score: {{ partner.company_name }}
    </h3>

    <div class="mt-3 grid gap-5 lg:grid-cols-3">
      <!-- Cosine -->
      <div>
        <h4 :class="label">
          Cosine similarity <span class="text-ink">{{ e.cos.score.toFixed(2) }}</span>
        </h4>
        <p class="mt-1.5 text-sm leading-relaxed text-ink">
          The student’s skill levels and the partner’s needs point in a similar direction: dot
          product {{ e.cos.dot }} ÷ (‖student‖ {{ e.cos.norm_s.toFixed(2) }} × ‖partner‖
          {{ e.cos.norm_p.toFixed(2) }}) = {{ e.cos.score.toFixed(2) }}.
        </p>
        <ul class="mt-2 flex flex-wrap gap-1.5" aria-label="Skills the partner needs">
          <li
            v-for="skill in e.cos.needed"
            :key="skill.skill_id"
            class="rounded-full px-2 py-0.5 text-xs"
            :class="skill.level ? 'bg-brand/10 text-brand' : 'bg-accent/15 text-accent-ink'"
          >
            {{ skill.name }} · {{ skill.level ? LEVEL_LABELS[skill.level] : 'missing'
            }}<template v-if="skill.importance === 2"> (required)</template>
          </li>
          <li v-if="!e.cos.needed.length" class="text-xs text-ink/70">
            This partner hasn’t listed needed skills.
          </li>
        </ul>
      </div>

      <!-- WSM -->
      <div>
        <h4 :class="label">
          Weighted sum (WSM) <span class="text-ink">{{ e.wsm.score.toFixed(2) }}</span>
        </h4>
        <ul class="mt-1.5 space-y-2">
          <li v-for="c in e.wsm.criteria" :key="c.key" class="text-sm text-ink">
            <span class="block">
              {{ CRITERION[c.key] }} — {{ pct(c.weight) }} × {{ c.value.toFixed(2) }} =
              <span class="font-mono font-medium">{{ c.contribution.toFixed(3) }}</span>
            </span>
            <span
              class="mt-1 block h-1.5 overflow-hidden rounded-full bg-ink/10"
              aria-hidden="true"
            >
              <span class="block h-full rounded-full bg-brand" :style="{ width: pct(c.value) }" />
            </span>
          </li>
        </ul>
        <p class="mt-2 text-xs text-ink/70">Admin sets the weights under Skills &amp; matching.</p>
      </div>

      <!-- Fuzzy -->
      <div>
        <h4 :class="label">
          Fuzzy logic <span class="text-ink">{{ e.fuzzy.score.toFixed(2) }}</span>
        </h4>
        <dl class="mt-1.5 space-y-1 text-sm text-ink">
          <div>
            <dt class="inline font-medium">Fit {{ e.fuzzy.fit.toFixed(1) }}:</dt>
            <dd class="inline font-mono text-xs">
              low {{ deg(e.fuzzy.fit_membership.low) }} · medium
              {{ deg(e.fuzzy.fit_membership.medium) }} · high {{ deg(e.fuzzy.fit_membership.high) }}
            </dd>
          </div>
          <div>
            <dt class="inline font-medium">Distance {{ e.fuzzy.distance_km }} km:</dt>
            <dd class="inline font-mono text-xs">
              near {{ deg(e.fuzzy.distance_membership.near) }} · mid
              {{ deg(e.fuzzy.distance_membership.mid) }} · far
              {{ deg(e.fuzzy.distance_membership.far) }}
            </dd>
          </div>
        </dl>
        <p v-if="e.fuzzy.distance_missing" class="mt-1.5 text-xs text-accent-ink">
          Home or partner location missing — distance treated as far.
        </p>
        <p :class="[label, 'mt-2.5']">Rules fired</p>
        <ul class="mt-1 space-y-0.5 font-mono text-xs text-ink">
          <li v-for="(rule, i) in e.fuzzy.rules_fired" :key="i">
            IF fit is {{ rule.fit
            }}<template v-if="rule.distance !== 'any'">
              AND distance is {{ rule.distance }}</template
            >
            THEN {{ rule.then }} ({{ rule.strength.toFixed(2) }})
          </li>
        </ul>
        <p class="mt-2 text-xs text-ink/70">
          Mamdani inference with centroid defuzzification on a 0–100 scale (centroid
          {{ e.fuzzy.centroid.toFixed(1) }}).
        </p>
      </div>
    </div>
  </section>
</template>
