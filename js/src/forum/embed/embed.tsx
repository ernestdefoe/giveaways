import app from 'flarum/forum/app';
import GiveawayCard from '../components/GiveawayCard';
import type { Giveaway } from '../../common/api';

type Lookup = Promise<Giveaway | null>;

const cache = new Map<string, Lookup>();
let pending = new Map<string, (g: Giveaway | null) => void>();
let timer = 0;

/**
 * Every giveaway link the page shows is looked up in one request, through the
 * same list endpoint, query and visibility as the giveaways page. A giveaway
 * that does not come back (deleted, or not visible to this viewer) leaves its
 * link as a plain link.
 */
function flush(): void {
  const batch = pending;
  pending = new Map();
  timer = 0;

  const keys = [...batch.keys()];
  for (let i = 0; i < keys.length; i += 20) {
    const chunk = keys.slice(i, i + 20);

    app
      .request<{ data: Giveaway[] }>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/giveaways',
        params: { filter: { slug: chunk.join(',') } },
      })
      .then(
        ({ data }) => chunk.forEach((key) => batch.get(key)!(data.find((g) => g.slug === key) || data.find((g) => String(g.id) === key) || null)),
        () => chunk.forEach((key) => batch.get(key)!(null))
      );
  }
}

function lookup(key: string): Lookup {
  let found = cache.get(key);
  if (!found) {
    found = new Promise((resolve) => pending.set(key, resolve));
    cache.set(key, found);
    timer ||= window.setTimeout(flush, 0);
  }
  return found;
}

export default function embed(links: Array<[HTMLAnchorElement, string]>): void {
  links.forEach(([a, key]) =>
    lookup(key).then((g) => {
      if (!g || !a.isConnected) return;

      const holder = document.createElement('span');
      holder.className = 'GiveawayEmbed';
      a.replaceWith(holder);
      m.render(holder, <GiveawayCard giveaway={g} />);
    })
  );
}
