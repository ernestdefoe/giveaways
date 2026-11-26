import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import { countdown } from '../../common/format';
import { currentCalendar } from '../../common/advent';
import type { TodayStore } from './todayWidget';

/** Fetches the calendar into the shared store, once a page load. */
export function load(store: TodayStore): void {
  if (store.state !== 'idle') return;
  store.state = 'loading';
  currentCalendar()
    .then((calendar) => (store.calendar = calendar))
    .catch(() => {
      /* app.request already showed the alert */
    })
    .finally(() => {
      store.state = 'done';
      m.redraw();
    });
}

/**
 * "Today's door": the current calendar's door number and whether the viewer
 * has opened it, with a link to the calendar. Never what is behind a door:
 * it only ever asks the server for the calendar's summary fields.
 */
export class TodayDoor extends Component<{ store: TodayStore }> {
  view(): Mithril.Children {
    const c = this.attrs.store.calendar;
    if (!c) return null;

    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.widget.${key}`, params);
    const running = c.today >= 1;
    const href = app.route('giveaways.advent', { slug: c.slug });

    return (
      <div className={'AdventToday' + (c.todayOpened ? ' is-opened' : '')}>
        <Link className="AdventToday-door" href={href} aria-hidden="true" tabindex="-1">
          {running ? c.today : <Icon name="fas fa-door-closed" />}
        </Link>
        <div className="AdventToday-body">
          <Link className="AdventToday-title" href={href}>
            {c.title}
          </Link>
          <div className="AdventToday-state">
            {!running
              ? t('starts_in', { time: countdown(c.nextOpensAt ?? null) })
              : c.todayOpened
                ? [<Icon name="fas fa-check" />, ' ', t('opened', { day: c.today })]
                : t('waiting', { day: c.today })}
          </div>
          {running && c.nextOpensAt && <div className="AdventToday-next">{t('next_in', { time: countdown(c.nextOpensAt) })}</div>}
        </div>
      </div>
    );
  }
}
