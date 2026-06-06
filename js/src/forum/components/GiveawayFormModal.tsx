import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

import { saveGiveaway } from '../../common/api';
import type { Giveaway } from '../../common/api';

export interface GiveawayFormAttrs extends IInternalModalAttrs {
  giveaway?: Giveaway;
  onsave?: () => void;
}

function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function toIso(local: string): string | null {
  if (!local) return null;
  const d = new Date(local);
  return isNaN(d.getTime()) ? null : d.toISOString();
}

export default class GiveawayFormModal extends Modal<GiveawayFormAttrs> {
  titleInput!: Stream<string>;
  prize!: Stream<string>;
  description!: Stream<string>;
  coverUrl!: Stream<string>;
  endsAt!: Stream<string>;
  startsAt!: Stream<string>;
  winnerCount!: Stream<number>;
  postBonus!: Stream<number>;
  minPosts!: Stream<number>;
  minAgeDays!: Stream<number>;

  oninit(vnode: Mithril.Vnode<GiveawayFormAttrs>) {
    super.oninit(vnode);
    const g = this.attrs.giveaway;
    this.titleInput = Stream(g?.title || '');
    this.prize = Stream(g?.prize || '');
    this.description = Stream(g?.description || '');
    this.coverUrl = Stream(g?.coverUrl || '');
    this.endsAt = Stream(toLocalInput(g?.endsAt));
    this.startsAt = Stream(toLocalInput(g?.startsAt));
    this.winnerCount = Stream(g?.winnerCount || 1);
    this.postBonus = Stream(g?.postBonus || 0);
    this.minPosts = Stream(g?.minPosts || 0);
    this.minAgeDays = Stream(g?.minAgeDays || 0);
  }

  className() {
    return 'GiveawayFormModal Modal--large';
  }

  title() {
    return this.attrs.giveaway
      ? app.translator.trans('ernestdefoe-giveaways.forum.edit')
      : app.translator.trans('ernestdefoe-giveaways.forum.create');
  }

  content(): Mithril.Children {
    const t = (k: string) => app.translator.trans('ernestdefoe-giveaways.forum.form.' + k);
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.field(t('title_label'), (
            <input className="FormControl" value={this.titleInput()} placeholder={t('title_placeholder') as string}
              oninput={(e: Event) => this.titleInput((e.target as HTMLInputElement).value)} />
          ))}
          {this.field(t('prize_label'), (
            <input className="FormControl" value={this.prize()} placeholder={t('prize_placeholder') as string}
              oninput={(e: Event) => this.prize((e.target as HTMLInputElement).value)} />
          ))}
          {this.field(t('description_label'), (
            <textarea className="FormControl" rows={4} value={this.description()} placeholder={t('description_placeholder') as string}
              oninput={(e: Event) => this.description((e.target as HTMLTextAreaElement).value)} />
          ))}
          {this.field(t('cover_label'), (
            <input className="FormControl" value={this.coverUrl()} placeholder={t('cover_placeholder') as string}
              oninput={(e: Event) => this.coverUrl((e.target as HTMLInputElement).value)} />
          ))}

          <div className="GiveawayFormModal-row">
            {this.field(t('starts_label'), (
              <input type="datetime-local" className="FormControl" value={this.startsAt()}
                oninput={(e: Event) => this.startsAt((e.target as HTMLInputElement).value)} />
            ))}
            {this.field(t('ends_label'), (
              <input type="datetime-local" className="FormControl" value={this.endsAt()}
                oninput={(e: Event) => this.endsAt((e.target as HTMLInputElement).value)} />
            ))}
          </div>

          <div className="GiveawayFormModal-row">
            {this.numberField(t('winner_count_label'), this.winnerCount, 1)}
            {this.numberField(t('post_bonus_label'), this.postBonus, 0, t('post_bonus_help'))}
          </div>
          <div className="GiveawayFormModal-row">
            {this.numberField(t('min_posts_label'), this.minPosts, 0)}
            {this.numberField(t('min_age_label'), this.minAgeDays, 0)}
          </div>

          <div className="Form-group">
            <Button type="submit" className="Button Button--primary" loading={this.loading}>
              {t('submit')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  field(label: Mithril.Children, control: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        {control}
      </div>
    );
  }

  numberField(label: Mithril.Children, stream: Stream<number>, min: number, help?: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        <input type="number" className="FormControl" min={min} value={stream()}
          oninput={(e: Event) => stream(parseInt((e.target as HTMLInputElement).value, 10) || 0)} />
        {help && <p className="helpText">{help}</p>}
      </div>
    );
  }

  onsubmit(e: Event) {
    e.preventDefault();
    this.loading = true;

    const attrs = {
      title: this.titleInput(),
      prize: this.prize(),
      description: this.description(),
      coverUrl: this.coverUrl(),
      endsAt: toIso(this.endsAt()),
      startsAt: toIso(this.startsAt()),
      winnerCount: this.winnerCount(),
      postBonus: this.postBonus(),
      minPosts: this.minPosts(),
      minAgeDays: this.minAgeDays(),
    };

    saveGiveaway(attrs, this.attrs.giveaway?.id)
      .then(() => {
        this.loading = false;
        app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-giveaways.forum.saved'));
        this.hide();
        this.attrs.onsave?.();
      })
      .catch((err) => {
        this.loading = false;
        this.onerror(err);
      });
  }
}
