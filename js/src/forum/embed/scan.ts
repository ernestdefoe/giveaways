import app from 'flarum/forum/app';

/** What a card is for: a giveaway, or an advent calendar. */
export type Kind = 'giveaways' | 'advent';

let root: string | null = null;

/** "https://forum.example/giveaways/" or ".../advent/": where this forum's links point. */
function prefix(kind: Kind): string {
  if (root === null) {
    const base = new URL(app.forum.attribute<string>('baseUrl'), location.href);
    root = base.origin + base.pathname.replace(/\/$/, '');
  }
  return `${root}/${kind}/`;
}

/** The slug (or old id) a link to one of this forum's giveaways or calendars names, else null. */
function keyOf(a: HTMLAnchorElement, kind: Kind): string | null {
  const href = a.href.split(/[?#]/)[0].replace(/\/$/, '');
  const p = prefix(kind);
  if (!href.startsWith(p)) return null;

  const rest = href.slice(p.length);
  if (!rest || rest.includes('/')) return null;

  try {
    return decodeURIComponent(rest);
  } catch {
    return null;
  }
}

/** Nothing but whitespace between the node and the edge of its line in that direction. */
function edge(node: Node | null, step: 'previousSibling' | 'nextSibling'): boolean {
  while (node && node.nodeType === Node.TEXT_NODE && !node.textContent!.trim()) node = node[step];
  return !node || node.nodeName === 'BR';
}

/**
 * A giveaway or advent calendar link on a line of its own becomes its card.
 * Only the link is replaced, inside its paragraph: the post's top-level nodes
 * belong to Mithril's m.trust and must stay as they are.
 */
export default function scan(post: Element): void {
  const found: Array<[HTMLAnchorElement, string, Kind]> = [];

  post
    .querySelectorAll<HTMLAnchorElement>('.Post-body a[href*="/giveaways/"]:not([data-gw]), .Post-body a[href*="/advent/"]:not([data-gw])')
    .forEach((a) => {
      a.setAttribute('data-gw', '');

      const kind: Kind = a.href.includes('/advent/') ? 'advent' : 'giveaways';
      const key = keyOf(a, kind);
      const parent = a.parentElement;
      if (!key || !parent || parent.classList.contains('Post-body')) return;
      if (!edge(a.previousSibling, 'previousSibling') || !edge(a.nextSibling, 'nextSibling')) return;

      found.push([a, key, kind]);
    });

  // The card, its styles' data and the request are only loaded by a page that has one.
  if (found.length) import('./embed').then(({ default: embed }) => embed(found));
}
