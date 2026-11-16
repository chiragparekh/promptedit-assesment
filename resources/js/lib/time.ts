type DateInput = Date | string | number;

const relativeTime = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
    style: 'short',
});

const dateTime = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/**
 * Format a timestamp as relative time, such as "9 min. ago" or "yesterday".
 */
export function formatRelativeTime(
    value: DateInput,
    now: Date = new Date(),
): string {
    const seconds = (new Date(value).getTime() - now.getTime()) / 1000;
    const absolute = Math.abs(seconds);

    if (absolute < 5) {
        return relativeTime.format(0, 'second');
    }

    if (absolute < 60) {
        return relativeTime.format(Math.round(seconds), 'second');
    }

    if (absolute < 3_600) {
        return relativeTime.format(Math.round(seconds / 60), 'minute');
    }

    if (absolute < 86_400) {
        return relativeTime.format(Math.round(seconds / 3_600), 'hour');
    }

    if (absolute < 604_800) {
        return relativeTime.format(Math.round(seconds / 86_400), 'day');
    }

    if (absolute < 2_629_800) {
        return relativeTime.format(Math.round(seconds / 604_800), 'week');
    }

    if (absolute < 31_557_600) {
        return relativeTime.format(Math.round(seconds / 2_629_800), 'month');
    }

    return relativeTime.format(Math.round(seconds / 31_557_600), 'year');
}

/**
 * Format a timestamp as a localized date and time, such as "17 Nov 2026, 04:02".
 */
export function formatDateTime(value: DateInput): string {
    return dateTime.format(new Date(value));
}
