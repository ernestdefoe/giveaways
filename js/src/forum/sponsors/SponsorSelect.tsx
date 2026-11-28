import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { listSponsors } from '../../common/sponsors';
import type { Sponsor } from '../../common/sponsors';

export interface SponsorSelectAttrs {
  value: number | null;
  onchange: (id: number | null) => void;
}

/**
 * Managers pick the sponsor presenting a giveaway or calendar. Option values
 * are strings ("s12") so the browser keeps "No sponsor" first.
 */
export default class SponsorSelect extends Component<SponsorSelectAttrs> {
  sponsors: Sponsor[] | null = null;

  oninit(vnode: Mithril.Vnode<SponsorSelectAttrs, this>) {
    super.oninit(vnode);
    listSponsors()
      .then((list) => (this.sponsors = list))
      .catch(() => (this.sponsors = []))
      .finally(() => m.redraw());
  }

  view(): Mithril.Children {
    const t = (key: string) => app.translator.trans(`ernestdefoe-giveaways.forum.sponsors.${key}`);
    const list = this.sponsors;
    if (!list || (!list.length && !this.attrs.value)) return null;

    return (
      <div className="Form-group SponsorSelect">
        <label>{t('select_label')}</label>
        <select
          className="FormControl"
          value={this.attrs.value ? `s${this.attrs.value}` : ''}
          onchange={(e: Event) => {
            const v = (e.target as HTMLSelectElement).value;
            this.attrs.onchange(v ? parseInt(v.slice(1), 10) : null);
          }}
        >
          <option value="">{t('select_none')}</option>
          {list.map((s) => (
            <option value={`s${s.id}`}>{s.name}</option>
          ))}
        </select>
        <p className="helpText">{t('select_help')}</p>
      </div>
    );
  }
}
