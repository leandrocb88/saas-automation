/**
 * Format an ISO date string in the user's local browser timezone.
 * Shows date only (e.g. "Mar 25, 2026").
 */
export function formatLocalDate(isoString: string | null | undefined): string | null {
    if (!isoString) return null;
    try {
        const d = new Date(isoString);
        if (isNaN(d.getTime())) return null;
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return null;
    }
}

/**
 * Format an ISO timestamp in the user's local browser timezone.
 * Shows date + time (e.g. "Mar 25, 2026, 11:30 PM").
 */
export function formatLocalDateTime(isoString: string | null | undefined): string | null {
    if (!isoString) return null;
    try {
        const d = new Date(isoString);
        if (isNaN(d.getTime())) return null;
        return d.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return null;
    }
}
