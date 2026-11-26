import app from 'flarum/common/app';

/**
 * Advent calendars, from the `giveaway-calendars` JSON:API type. A door's
 * content only comes back for doors the viewer has opened; every other door
 * is its number, its state and when it opens.
 */
export interface AdventDoor {
  day: number;
  opensAt: string;
  state: 'locked' | 'ready' | 'missed' | 'opened';
  /** Opened doors only from here on. */
  joker?: boolean;
  kind?: 'prize' | 'reveal' | 'exchange';
  title?: string | null;
  html?: string | null;
  draw?: { slug: string; prize: string; winnerCount: number; status: string; entered: boolean } | null;
  exchange?: { id: number; title: string; signupsOpen: boolean; signupEndsAt: string | null } | null;
}

/** A door as the builder edits it (managers only). */
export interface EditorDoor {
  day: number;
  kind: 'prize' | 'reveal' | 'exchange';
  title: string | null;
  body: string;
  prize: string | null;
  winners: number;
  claimInstructions: string;
  claimLines: string;
  exchangeId: number | null;
}

export interface AdventCalendar {
  id: string;
  title: string;
  slug: string;
  descriptionHtml: string | null;
  startsOn: string;
  days: number;
  /** The timezone the doors follow. */
  zone: string;
  status: 'draft' | 'published';
  /** Today's door: 0 or less before the first, above `days` after the last. */
  today: number;
  endsAt: string;
  jokers: number;
  jokersUsed?: number;
  tickets: number;
  remind: boolean;
  ineligible: string | null;
  canManage: boolean;
  grand: { slug: string; prize: string; winnerCount: number; status: string } | null;
  doors: AdventDoor[];
  /** Whether the viewer has opened today's door (widget and cards). */
  todayOpened?: boolean;
  /** When the next door opens: the first before it starts, null after the last. */
  nextOpensAt?: string | null;

  // Managers only.
  description?: string;
  timezone?: string;
  minPosts?: number;
  minAgeDays?: number;
  grandPrize?: string;
  grandWinners?: number;
  grandClaimInstructions?: string;
  grandClaimLines?: string;
  editor?: EditorDoor[];
  exchangeChoices?: { id: number; title: string }[];
}

interface Resource {
  id: string;
  attributes: Omit<AdventCalendar, 'id'>;
}

const TYPE = 'giveaway-calendars';

function base(): string {
  return `${app.forum.attribute('apiUrl')}/${TYPE}`;
}

/**
 * Just what the "Today's door" widget, post cards and the giveaways page show. Asking for these
 * fields only means the server never even builds the doors for them.
 */
const SUMMARY = { 'giveaway-calendars': 'title,slug,status,today,days,todayOpened,nextOpensAt,grand,tickets' };

function flat(resource: Resource): AdventCalendar {
  return { id: resource.id, ...resource.attributes };
}

export function showCalendar(slug: string): Promise<AdventCalendar> {
  return app.request<{ data: Resource }>({ method: 'GET', url: `${base()}/${encodeURIComponent(slug)}` }).then((r) => flat(r.data));
}

export function listCalendars(): Promise<AdventCalendar[]> {
  // A forum runs a calendar or two a year: one page of the newest 20 covers them.
  return app
    .request<{ data: Resource[] }>({ method: 'GET', url: base(), params: { fields: SUMMARY, page: { limit: 20 } } })
    .then((r) => r.data.map(flat));
}

/** Calendars by slug, for the cards links in posts become (at most 20 a request). */
export function calendarSummaries(slugs: string[]): Promise<AdventCalendar[]> {
  return app
    .request<{ data: Resource[] }>({ method: 'GET', url: base(), params: { fields: SUMMARY, filter: { slug: slugs.join(',') } } })
    .then((r) => r.data.map(flat));
}

/** The calendar the widget shows: the newest one running, else the next to start. */
export function currentCalendar(): Promise<AdventCalendar | null> {
  return app.request<{ data: Resource[] }>({ method: 'GET', url: base(), params: { fields: SUMMARY, page: { limit: 20 } } }).then((r) => {
    const live = r.data.map(flat).filter((c) => c.status === 'published' && c.today <= c.days);
    return live.find((c) => c.today >= 1) ?? live.reverse().find((c) => c.today < 1) ?? null;
  });
}

export function openDoor(id: string, day: number, joker = false): Promise<AdventCalendar> {
  return app.request<{ data: Resource }>({ method: 'POST', url: `${base()}/${id}/open`, body: { day, joker } }).then((r) => flat(r.data));
}

export function setReminder(id: string, on: boolean): Promise<AdventCalendar> {
  return app.request<{ data: Resource }>({ method: 'POST', url: `${base()}/${id}/remind`, body: { on } }).then((r) => flat(r.data));
}

export function saveCalendar(attributes: Record<string, unknown>, id?: string): Promise<AdventCalendar> {
  return app
    .request<{ data: Resource }>({
      method: id ? 'PATCH' : 'POST',
      url: id ? `${base()}/${id}` : base(),
      body: { data: { type: TYPE, ...(id ? { id } : {}), attributes } },
    })
    .then((r) => flat(r.data));
}

export function deleteCalendar(id: string): Promise<unknown> {
  return app.request({ method: 'DELETE', url: `${base()}/${id}` });
}

/**
 * The doors in a fixed shuffled order, the same for everyone (seeded by the
 * calendar's id), so the grid reads like a real advent calendar rather than
 * a list.
 */
export function shuffled<T>(items: T[], seed: number): T[] {
  const out = items.slice();
  let s = seed >>> 0 || 1;
  const next = () => {
    // mulberry32
    s = (s + 0x6d2b79f5) >>> 0;
    let t = s;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(next() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}
