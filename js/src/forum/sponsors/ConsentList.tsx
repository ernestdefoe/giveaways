import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import FieldSet from 'flarum/common/components/FieldSet';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { myConsents, setConsent } from '../../common/sponsors';
import type { Consent } from '../../common/sponsors';

/**
 * In the member's settings: every sponsor they share their email address
 * with, the sentence they agreed to, and "Stop sharing". Nothing at all when
 * they share with none.
 */
export default class ConsentList extends Component {
  consents: Consent[] = [];

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    myConsents()
      .then((list) => (this.consents = list))
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }

  view(): Mithril.Children {
    if (!this.consents.length) return null;
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.sponsors.${key}`, params);

    return (
      <FieldSet className="ConsentList" label={extractText(t('settings_heading'))}>
        <ul className="ConsentList-items">
          {this.consents.map((c) => (
            <li className="ConsentList-item">
              <p className="ConsentList-text">{c.consent}</p>
              <p className="helpText">{t('settings_since', { date: dayjs(c.optedInAt).format('LL') })}</p>
              <Button className="Button Button--sm" onclick={() => this.stop(c)}>
                {t('stop_sharing')}
              </Button>
            </li>
          ))}
        </ul>
      </FieldSet>
    );
  }

  stop(c: Consent) {
    setConsent(c.campaign, c.campaignId, false)
      .then(() => {
        this.consents = this.consents.filter((x) => x !== c);
        app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-giveaways.forum.sponsors.withdrawn', { sponsor: c.sponsor ?? '' }));
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }
}
