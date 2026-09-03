import Component from 'flarum/common/Component';

declare const m: any;

/**
 * The giveaway list's shape while it loads.
 *
 * 🚨 Every number is MEASURED against the rendered page, not chosen: a card is
 * 256px in a 280px-minimum grid with a 20px gap, and a section heading is 23
 * with 14 under it. A skeleton of the wrong size still shifts the page when the
 * real list arrives; it just looks like it was handled.
 *
 * 🚨 How many giveaways are running, and whether there is a past section at
 * all, is a property of THIS forum on THIS day — so both come from what the
 * browser saw last time. A first visit shows one section of three and settles.
 * Storage access is wrapped because a browser in private mode, or one told to
 * block site data, throws on read rather than returning null.
 */
const SHAPE_KEY = 'ernestdefoe-giveaways.sections';

const DEFAULT: number[] = [3];

export function rememberSections(counts: number[]): void {
  const kept = counts.filter((n) => n > 0);

  // Nothing rendered — not worth learning from; it would train the skeleton to
  // reserve nothing on a forum whose first load was simply slow.
  if (!kept.length) return;

  try {
    localStorage.setItem(SHAPE_KEY, JSON.stringify(kept));
  } catch {
    // Storage unavailable; the skeleton falls back to the default.
  }
}

function recalledSections(): number[] {
  try {
    const raw = localStorage.getItem(SHAPE_KEY);
    if (!raw) return DEFAULT;

    const parsed = JSON.parse(raw);

    // Anything malformed — an older version of this key, a hand-edited value —
    // is discarded rather than trusted into a broken render. The caps stop a
    // stale number reserving screens of empty page.
    if (!Array.isArray(parsed) || !parsed.length) return DEFAULT;

    return parsed
      .slice(0, 2)
      .map((n: unknown) => (Number.isFinite(n) ? Math.min(12, Math.max(1, Number(n))) : 3));
  } catch {
    return DEFAULT;
  }
}

export default class GwSkeleton extends Component {
  view() {
    return m(
      '.GwSkeleton',
      { 'aria-hidden': 'true' },
      recalledSections().map((count, s) =>
        m('section.GwSkeleton-section', { key: s }, [
          m('.GwSkeleton-bar.GwSkeleton-bar--heading'),
          m(
            '.GwSkeleton-grid',
            Array.from({ length: count }, (_, i) => m('.GwSkeleton-card', { key: i }))
          ),
        ])
      )
    );
  }
}

/**
 * A single giveaway while it loads. No remembered size: every giveaway page is
 * a different length, so the last one says nothing about the next. It
 * under-fills rather than over-fills — under-filling settles the page upward,
 * while over-filling drops the reader's scroll position out from under them.
 */
export class GwDetailSkeleton extends Component {
  view() {
    return m('.GwSkeleton.GwSkeleton--detail', { 'aria-hidden': 'true' }, [
      m('.GwSkeleton-bar.GwSkeleton-bar--heading2'),
      m('.GwSkeleton-bar.GwSkeleton-bar--meta'),
      m('.GwSkeleton-card.GwSkeleton-card--panel'),
      ...[0, 1].map((p) =>
        m('.GwSkeleton-para', { key: p }, [
          m('.GwSkeleton-bar.GwSkeleton-bar--line'),
          m('.GwSkeleton-bar.GwSkeleton-bar--line'),
          m('.GwSkeleton-bar.GwSkeleton-bar--short'),
        ])
      ),
    ]);
  }
}
