import GiveawayCard from '../components/GiveawayCard';
import CalendarCard from '../advent/CalendarCard';
import { giveawaysBySlug } from '../../common/api';
import type { Giveaway } from '../../common/api';
import { calendarSummaries } from '../../common/advent';
import type { AdventCalendar } from '../../common/advent';
import type { Kind } from './scan';

type Found = Giveaway | AdventCalendar | null;

const cache = new Map<string, Promise<Found>>();
let pending = new Map<string, (found: Found) => void>();
let timer = 0;

/** One request per kind for every link the page shows, 20 keys at a time. */
const fetchers: Record<Kind, (keys: string[]) => Promise<Array<{ key: string; found: Found }>>> = {
  // The same list endpoint, query and visibility as the giveaways page.
  giveaways: (keys) =>
    giveawaysBySlug(keys).then((data) =>
      keys.map((key) => ({ key, found: data.find((g) => g.slug === key) || data.find((g) => String(g.id) === key) || null }))
    ),
  // Only the calendar's summary: a card never carries what is behind a door.
  advent: (keys) => calendarSummaries(keys).then((data) => keys.map((key) => ({ key, found: data.find((c) => c.slug === key) || null }))),
};

/**
 * A link whose giveaway or calendar does not come back (deleted, or not
 * visible to this viewer) stays a plain link.
 */
function flush(): void {
  const batch = pending;
  pending = new Map();
  timer = 0;

  (['giveaways', 'advent'] as Kind[]).forEach((kind) => {
    const keys = [...batch.keys()].filter((k) => k.startsWith(kind + ':')).map((k) => k.slice(kind.length + 1));
    for (let i = 0; i < keys.length; i += 20) {
      const chunk = keys.slice(i, i + 20);
      fetchers[kind](chunk).then(
        (rows) => rows.forEach(({ key, found }) => batch.get(`${kind}:${key}`)!(found)),
        () => chunk.forEach((key) => batch.get(`${kind}:${key}`)!(null))
      );
    }
  });
}

function lookup(kind: Kind, key: string): Promise<Found> {
  const id = `${kind}:${key}`;
  let found = cache.get(id);
  if (!found) {
    found = new Promise((resolve) => pending.set(id, resolve));
    cache.set(id, found);
    timer ||= window.setTimeout(flush, 0);
  }
  return found;
}

export default function embed(links: Array<[HTMLAnchorElement, string, Kind]>): void {
  links.forEach(([a, key, kind]) =>
    lookup(kind, key).then((found) => {
      if (!found || !a.isConnected) return;

      const holder = document.createElement('span');
      holder.className = 'GiveawayEmbed';
      a.replaceWith(holder);
      m.render(holder, kind === 'advent' ? <CalendarCard calendar={found as AdventCalendar} /> : <GiveawayCard giveaway={found as Giveaway} />);
    })
  );
}
