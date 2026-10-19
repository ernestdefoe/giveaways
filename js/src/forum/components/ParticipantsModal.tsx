import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import stringToColor from 'flarum/common/utils/stringToColor';

import { listEntrants } from '../../common/api';
import type { Entrant, Giveaway } from '../../common/api';
import { dateTime } from '../../common/format';

export interface ParticipantsModalAttrs extends IInternalModalAttrs {
  giveaway: Giveaway;
}

/** Who entered, oldest first, a page at a time. */
export default class ParticipantsModal extends Modal<ParticipantsModalAttrs> {
  entrants: Entrant[] = [];
  total = 0;
  loading = true;

  oninit(vnode: Mithril.Vnode<ParticipantsModalAttrs>) {
    super.oninit(vnode);
    this.more();
  }

  className() {
    return 'GiveawayParticipantsModal';
  }

  title() {
    return app.translator.trans('ernestdefoe-giveaways.forum.participants.title', { count: this.total || this.attrs.giveaway.entrantCount });
  }

  more() {
    this.loading = true;
    listEntrants(this.attrs.giveaway.id, this.entrants.length)
      .then((res) => {
        this.entrants.push(...res.data);
        this.total = res.meta.total;
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <ul className="GiveawayParticipants">
          {this.entrants.map((e) => (
            <li className="GiveawayParticipants-item">
              {e.user ? (
                <Link href={app.route('user', { username: e.user.username })} className="GiveawayParticipants-user">
                  {e.user.avatarUrl ? (
                    <img className="Avatar" src={e.user.avatarUrl} alt="" loading="lazy" decoding="async" />
                  ) : (
                    <span className="Avatar" style={{ '--avatar-bg': '#' + stringToColor(e.user.displayName) }}>
                      {[...e.user.displayName][0]?.toUpperCase()}
                    </span>
                  )}
                  <span>{e.user.displayName}</span>
                </Link>
              ) : (
                <span className="GiveawayParticipants-user">{app.translator.trans('ernestdefoe-giveaways.forum.participants.deleted_user')}</span>
              )}
              <span className="GiveawayParticipants-meta">
                {e.entries > 1 && (
                  <span className="GiveawayParticipants-entries">
                    {app.translator.trans('ernestdefoe-giveaways.forum.participants.entries', { count: e.entries })}
                  </span>
                )}
                {e.joinedAt && <time datetime={e.joinedAt}>{dateTime(e.joinedAt)}</time>}
              </span>
            </li>
          ))}
        </ul>
        {this.loading ? (
          <LoadingIndicator />
        ) : (
          this.entrants.length < this.total && (
            <div className="GiveawayParticipants-more">
              <Button className="Button" onclick={() => this.more()}>
                {app.translator.trans('ernestdefoe-giveaways.forum.participants.load_more')}
              </Button>
            </div>
          )
        )}
      </div>
    );
  }
}
