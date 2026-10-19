import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import { countdown, phase } from '../../common/format';
import type { Giveaway } from '../../common/api';

export interface GiveawayCardAttrs {
  giveaway: Giveaway;
}

export default class GiveawayCard extends Component<GiveawayCardAttrs> {
  view(): Mithril.Children {
    const g = this.attrs.giveaway;
    const p = phase(g);

    return (
      <Link className="GiveawayCard" href={app.route('giveaways.show', { slug: g.slug })}>
        <div
          className={'GiveawayCard-cover' + (g.coverUrl ? '' : ' GiveawayCard-cover--placeholder')}
          style={g.coverUrl ? { backgroundImage: `url("${g.coverUrl}")` } : undefined}
        >
          {!g.coverUrl && <Icon name="fas fa-gift" />}
          <span className={`GiveawayCard-status GiveawayCard-status--${p}`}>{app.translator.trans(`ernestdefoe-giveaways.forum.status_${p}`)}</span>
        </div>

        <div className="GiveawayCard-body">
          {g.category && (
            <span className="GiveawayCard-category" style={{ color: g.category.color }}>
              {g.category.icon && <Icon name={g.category.icon} />} {g.category.name}
            </span>
          )}
          <h3 className="GiveawayCard-title">{g.title}</h3>
          <div className="GiveawayCard-prize">
            <Icon name="fas fa-trophy" /> {g.prize}
            {winnerCount(g.winnerCount)}
          </div>

          <div className="GiveawayCard-meta">
            <span>
              <Icon name="fas fa-clock" />{' '}
              {p === 'upcoming'
                ? app.translator.trans('ernestdefoe-giveaways.forum.starts_in', { time: countdown(g.startsAt) })
                : p === 'active' || p === 'draft'
                  ? app.translator.trans('ernestdefoe-giveaways.forum.ends_in', { time: countdown(g.endsAt) })
                  : app.translator.trans('ernestdefoe-giveaways.forum.ended')}
            </span>
            <span>
              <Icon name="fas fa-users" /> {app.translator.trans('ernestdefoe-giveaways.forum.entrants', { count: g.entrantCount })}
            </span>
          </div>

          {g.myEntries > 0 && (
            <div className="GiveawayCard-entered">
              <Icon name="fas fa-check-circle" /> {app.translator.trans('ernestdefoe-giveaways.forum.your_entries', { count: g.myEntries })}
            </div>
          )}
        </div>
      </Link>
    );
  }
}

/** "× 10" after the prize, when there is more than one winner. */
export function winnerCount(count: number): Mithril.Children {
  return count > 1 ? (
    <span className="GiveawayWinnerCount">{app.translator.trans('ernestdefoe-giveaways.forum.winner_count', { count })}</span>
  ) : null;
}
