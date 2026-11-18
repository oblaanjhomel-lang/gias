import { onBeforeUnmount, onMounted, type Ref } from 'vue'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

/**
 * Modal behaviour for a dialog component that is mounted while open (v-if):
 * traps Tab focus inside `container`, closes on Escape, locks page scroll,
 * and returns focus to whatever was focused before (normally the opening button).
 */
export function useDialog(
  container: Ref<HTMLElement | null>,
  options: { onClose: () => void; initialFocus?: () => HTMLElement | null | undefined },
): void {
  let previouslyFocused: HTMLElement | null = null

  function focusable(): HTMLElement[] {
    return container.value
      ? Array.from(container.value.querySelectorAll<HTMLElement>(FOCUSABLE))
      : []
  }

  function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault()
      options.onClose()
      return
    }
    if (event.key !== 'Tab') return

    const items = focusable()
    const first = items[0]
    const last = items[items.length - 1]
    if (!first || !last) {
      event.preventDefault()
      return
    }

    const active = document.activeElement
    const outside = !container.value?.contains(active)
    if (event.shiftKey && (active === first || outside)) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && (active === last || outside)) {
      event.preventDefault()
      first.focus()
    }
  }

  onMounted(() => {
    previouslyFocused =
      document.activeElement instanceof HTMLElement ? document.activeElement : null
    document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
    ;(options.initialFocus?.() ?? focusable()[0])?.focus()
  })

  onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
    if (previouslyFocused?.isConnected) previouslyFocused.focus()
  })
}
