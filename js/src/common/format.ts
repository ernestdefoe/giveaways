/** Compact human countdown between now and an ISO datetime, e.g. "2d 4h" or "5m 12s". */
export function countdown(iso: string | null): string {
  if (!iso) return '';
  let secs = Math.floor((new Date(iso).getTime() - Date.now()) / 1000);
  if (secs <= 0) return '0s';

  const d = Math.floor(secs / 86400);
  secs -= d * 86400;
  const h = Math.floor(secs / 3600);
  secs -= h * 3600;
  const m = Math.floor(secs / 60);
  const s = secs - m * 60;

  if (d > 0) return `${d}d ${h}h`;
  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
}

export function isPast(iso: string | null): boolean {
  return !!iso && new Date(iso).getTime() <= Date.now();
}

/** A full, localized date and time, e.g. "October 19, 2026 9:00 PM" (dayjs is a Flarum global). */
export function dateTime(iso: string | null): string {
  return iso ? dayjs(iso).format('LLL') : '';
}

/** What the giveaway is doing now, as the cards and page label it. */
export type Phase = 'draft' | 'upcoming' | 'active' | 'drawn' | 'cancelled';

export function phase(g: { status: string; startsAt: string | null }): Phase {
  if (g.status === 'active' && g.startsAt && !isPast(g.startsAt)) return 'upcoming';
  return g.status as Phase;
}
