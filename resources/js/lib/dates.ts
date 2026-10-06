// Date-only and timezone-less Web values preserve their calendar date/time.
// ISO timestamps with an offset represent instants, displayed in the business timezone.
export const SYSTEM_TIMEZONE = 'America/Belem';
type DateValue = string | null | undefined;

function parts(value: DateValue): Record<string, string> | null {
    if (!value) return null;
    if (!/(Z|[+-]\d{2}:?\d{2})$/i.test(value)) {
        const match = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/.exec(value);
        if (!match) return null;
        return { year: match[1], month: match[2], day: match[3], hour: match[4] ?? '00', minute: match[5] ?? '00' };
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
        timeZone: SYSTEM_TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map(part => [part.type, part.value]));
}

export function formatDate(value: DateValue): string {
    const p = parts(value);
    return p ? `${p.day}/${p.month}/${p.year}` : '—';
}

export function formatDateTime(value: DateValue): string {
    const p = parts(value);
    return p ? `${p.day}/${p.month}/${p.year} - ${p.hour}:${p.minute}h` : '—';
}

export function currentDateTimeInput(): string {
    const p = parts(new Date().toISOString())!;
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}
