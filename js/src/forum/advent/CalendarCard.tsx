import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import type { AdventCalendar } from '../../common/advent';

/** A calendar on the giveaways page: where it stands today, and the grand prize. */
export default class CalendarCard extends Component<{ calendar: AdventCalendar }> {
  view(): Mithril.Children {
    const c = this.attrs.calendar;
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.${key}`, params);
    const state =
      c.status === 'draft'
        ? t('draft')
        : c.today < 1
          ? t('card_starts', { date: dayjs(c.nextOpensAt ?? c.doors?.[0]?.opensAt).format('LL') })
          : c.today > c.days
            ? t('over')
            : t('today', { day: c.today });

    return (
      <Link className="GiveawayCard CalendarCard" href={app.route('giveaways.advent', { slug: c.slug })}>
        <div className="GiveawayCard-cover GiveawayCard-cover--placeholder CalendarCard-cover">
          <Icon name="fas fa-door-closed" />
          <span className="GiveawayCard-status">{state}</span>
        </div>
        <div className="GiveawayCard-body">
          <h3 className="GiveawayCard-title">{c.title}</h3>
          {c.grand && (
            <div className="GiveawayCard-prize">
              <Icon name="fas fa-trophy" /> {c.grand.prize}
              {c.grand.winnerCount > 1 && ` × ${c.grand.winnerCount}`}
            </div>
          )}
          <div className="GiveawayCard-meta">
            {t('card_doors', { count: c.days })}
            {app.session.user && c.tickets > 0 && [' · ', t('tickets', { count: c.tickets })]}
          </div>
        </div>
      </Link>
    );
  }
}
