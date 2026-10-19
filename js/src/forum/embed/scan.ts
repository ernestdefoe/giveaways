import app from 'flarum/forum/app';

let prefix: string | null = null;

/** "https://forum.example/giveaways/" — where this forum's giveaway links point. */
function giveawaysPrefix(): string {
  if (prefix === null) {
    const base = new URL(app.forum.attribute<string>('baseUrl'), location.href);
    prefix = base.origin + base.pathname.replace(/\/$/, '') + '/giveaways/';
  }
  return prefix;
}

/** The slug (or old id) a link to one of this forum's giveaways names, else null. */
function keyOf(a: HTMLAnchorElement): string | null {
  const href = a.href.split(/[?#]/)[0].replace(/\/$/, '');
  const p = giveawaysPrefix();
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
 * A giveaway link on a line of its own becomes the giveaway's card. Only the
 * link is replaced, inside its paragraph: the post's top-level nodes belong to
 * Mithril's m.trust and must stay as they are.
 */
export default function scan(root: Element): void {
  const found: Array<[HTMLAnchorElement, string]> = [];

  root.querySelectorAll<HTMLAnchorElement>('.Post-body a[href*="/giveaways/"]:not([data-gw])').forEach((a) => {
    a.setAttribute('data-gw', '');

    const key = keyOf(a);
    const parent = a.parentElement;
    if (!key || !parent || parent.classList.contains('Post-body')) return;
    if (!edge(a.previousSibling, 'previousSibling') || !edge(a.nextSibling, 'nextSibling')) return;

    found.push([a, key]);
  });

  // The card, its styles' data and the request are only loaded by a page that has one.
  if (found.length) import('./embed').then(({ default: embed }) => embed(found));
}
