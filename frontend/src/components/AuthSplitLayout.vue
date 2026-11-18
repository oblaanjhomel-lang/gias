<script setup lang="ts">
import GiasBadge from '@/components/GiasBadge.vue'
</script>

<!-- Two-column guest layout: brand panel (470px) + off-white form column (392px). -->
<template>
  <div class="flex min-h-screen">
    <aside class="hidden w-[470px] shrink-0 flex-col bg-brand p-12 text-white lg:flex">
      <div class="flex items-center gap-3">
        <GiasBadge />
        <div>
          <p class="font-serif text-xl leading-tight font-semibold">GIAS</p>
          <p class="font-mono text-xs text-white/80">GIS-Enabled Integrated Analytics System</p>
        </div>
      </div>

      <div class="my-auto">
        <p class="font-serif text-[33px] leading-[1.2] font-semibold">
          See every industry partner and intern on the map, not in a spreadsheet.
        </p>
        <p class="mt-5 max-w-sm leading-relaxed text-white/80">
          Internship coordination, industry-partnership records, and a PostGIS-backed map of the
          Davao Region.
        </p>
      </div>
    </aside>

    <main class="flex flex-1 items-center justify-center bg-[#fdfcfa] px-6 py-12">
      <div class="w-full max-w-[392px]">
        <slot />
      </div>
    </main>
  </div>
</template>
