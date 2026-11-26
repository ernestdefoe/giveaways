import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { AdventCalendar } from '../../common/advent';
import { fof, hasFofWidgets, placed } from '../../common/fofWidgets';

const EXT = 'ernestdefoe-giveaways';
const KEY = 'giveawaysTodayDoor';

export interface TodayStore {
  calendar: AdventCalendar | null;
  state: 'idle' | 'loading' | 'done';
}

/**
 * Module-level, not per component: Bespoke remounts its widgets, and the
 * calendar is fetched once a page load whichever host draws it.
 */
const store: TodayStore = { calendar: null, state: 'idle' };

/** The widget's body (its request and view): a lazy chunk, fetched only where the widget is placed. */
let body: typeof import('./todayBody') | null = null;

function start(): void {
  if (body) {
    body.load(store);
    return;
  }
  import('./todayBody').then((mod) => {
    body = mod;
    mod.load(store);
  });
}

/** Opening a door on the calendar page: the widget asks again next time. */
export function forgetToday(): void {
  store.state = 'idle';
}

/**
 * "Today's door": the current calendar's door number and whether the viewer
 * has opened it, with a link to the calendar. Never what is behind a door:
 * it only ever asks the server for the calendar's summary fields.
 */
export class TodayDoor extends Component {
  oncreate(vnode: Mithril.VnodeDOM) {
    super.oncreate(vnode);
    start();
  }

  view(): Mithril.Children {
    return body && store.calendar ? m(body.TodayDoor, { store }) : null;
  }
}

/**
 * Register with every widget host present, Bespoke and fof/forum-widgets-core,
 * resolved at runtime so the bundle is the same with or without them. Only
 * where an admin places it: there is no stock-sidebar fallback, since that
 * would cost every index page a request on forums with no calendar.
 */
export function registerTodayWidget(): void {
  const g = globalThis as any;

  if (g.flarum?.extensions && 'ernestdefoe-bespoke' in g.flarum.extensions) registerWithBespoke(g);
  if (hasFofWidgets() && (app as any).widgets) registerWithFof();
}

/** Bespoke's widget queue. */
function registerWithBespoke(g: any): void {
  g.BespokeWidgetQueue = g.BespokeWidgetQueue || [];
  g.BespokeWidgetQueue.push({
    type: 'giveaways-today-door',
    // 🚨 A translation KEY: Bespoke resolves it.
    label: 'ernestdefoe-giveaways.forum.advent.widget.title',
    icon: 'fas fa-door-open',
    zones: ['sidebar', 'above-list', 'below-list', 'footer'],
    schema: [],
    component: { view: () => m(TodayDoor) },
  });
}

/** fof/forum-widgets-core: opt-in, only where its layout editor placed it. */
function registerWithFof(): void {
  const Widgets = fof('common/extend/Widgets');
  const WidgetBase = fof('common/components/Widget') as { new (...args: any[]): any } | undefined;
  if (!Widgets || !WidgetBase) return;

  class TodayDoorFofWidget extends WidgetBase {
    oncreate(vnode: Mithril.VnodeDOM) {
      super.oncreate(vnode);
      start();
    }
    // No frame without a calendar to show: FoF would draw an empty box.
    view() {
      return store.calendar ? super.view() : null;
    }
    className() {
      return 'AdventTodayFofWidget';
    }
    icon() {
      return 'fas fa-door-open';
    }
    title() {
      return app.translator.trans('ernestdefoe-giveaways.forum.advent.widget.title');
    }
    content() {
      return m(TodayDoor);
    }
  }

  new Widgets()
    .add({
      key: KEY,
      component: TodayDoorFofWidget,
      // 🚨 Opt-in: only where the admin placed it in FoF's layout editor.
      isDisabled: () => !placed(app, EXT, KEY) || (store.state === 'done' && !store.calendar),
      isUnique: true,
      placement: 'end',
      position: 0,
    })
    .extend(app, EXT);

  // Registered after FoF drew its sections; a placed widget appears now.
  m.redraw();
}
