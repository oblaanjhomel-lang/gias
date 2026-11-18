<script setup lang="ts">
withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'md' })
</script>

<template>
  <span
    aria-hidden="true"
    class="grid shrink-0 place-items-center rounded-md border border-white/30 bg-white/10 font-mono font-medium text-white"
    :class="size === 'sm' ? 'size-9 text-sm' : 'size-11 text-base'"
  >
    GI
  </span>
</template>
