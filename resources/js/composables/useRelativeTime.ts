import { createSharedComposable, useNow } from '@vueuse/core';
import {
    formatDateTime,
    formatRelativeTime as formatRelative,
} from '@/lib/time';

/**
 * Shared clock for relative timestamps, so every component reuses one timer.
 */
export const useRelativeTime = createSharedComposable(() => {
    const now = useNow({ interval: 60_000 });

    return {
        now,
        formatRelativeTime: (value: Date | string | number): string =>
            formatRelative(value, now.value),
        formatDateTime,
    };
});
