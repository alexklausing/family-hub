import { reactive, computed } from 'vue'

// Module-level shared state so the sleep guard (Dashboard.vue) and the
// features that should keep the screen awake can coordinate without
// prop drilling. Keys are registered while a feature is active.
const activeExceptions = reactive(new Set())

export function useSleepException() {
    const hasSleepException = computed(() => activeExceptions.size > 0)

    const enableException = (key) => {
        activeExceptions.add(key)
    }

    const disableException = (key) => {
        activeExceptions.delete(key)
    }

    return {
        activeExceptions,
        hasSleepException,
        enableException,
        disableException,
    }
}
