<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

/** Replaces a registration form after a successful submit. */
defineProps<{
  /** From the API, e.g. "Your program's OJT Coordinator will review your registration. …" */
  message: string
}>()

const heading = ref<HTMLElement | null>(null)

// Move focus here so screen-reader users land on the result, not on a vanished form.
onMounted(() => heading.value?.focus())
</script>

<template>
  <section
    aria-labelledby="registration-received"
    class="rounded-lg border border-brand/25 bg-brand/5 p-6"
  >
    <h1
      id="registration-received"
      ref="heading"
      tabindex="-1"
      class="font-serif text-3xl font-semibold text-ink focus:outline-none"
    >
      Registration received.
    </h1>
    <p class="mt-3 leading-relaxed text-ink">{{ message }}</p>
    <p class="mt-2 text-sm leading-relaxed text-ink/75">
      You can sign in once your account is approved. If something needs fixing, the email will say
      what.
    </p>
    <p class="mt-6">
      <RouterLink
        :to="{ name: 'login' }"
        class="inline-flex h-11 items-center rounded-md bg-brand px-5 text-sm font-medium text-white hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      >
        Back to sign in
      </RouterLink>
    </p>
  </section>
</template>
