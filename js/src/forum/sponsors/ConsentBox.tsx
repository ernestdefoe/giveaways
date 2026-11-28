import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { setConsent } from '../../common/sponsors';
import type { SponsorSummary } from '../../common/sponsors';

export interface ConsentBoxAttrs {
  sponsor: SponsorSummary;
  campaign: 'giveaway' | 'calendar';
  campaignId: number;
  /** What the server has: unticked unless the member ticked it. */
  checked: boolean;
  /** The sentence the server stores when it's ticked. */
  text: string;
  onchange?: (checked: boolean) => void;
}

/**
 * "Share my email with the sponsor": never ticked unless the member ticked
 * it, separate from entering, and unticking withdraws at once. The sentence
 * is the server's own (the one it stores), with the sponsor's privacy policy
 * beside it.
 */
export default class ConsentBox extends Component<ConsentBoxAttrs> {
  busy = false;

  view(): Mithril.Children {
    const { sponsor, checked, text } = this.attrs;
    const id = `ConsentBox-${this.attrs.campaign}-${this.attrs.campaignId}`;

    return (
      <div className="ConsentBox">
        <label className="ConsentBox-label" for={id}>
          <input
            id={id}
            type="checkbox"
            checked={checked}
            disabled={this.busy}
            onchange={(e: Event) => this.toggle((e.target as HTMLInputElement).checked)}
          />
          <span>{this.sentence(text, sponsor.privacyUrl)}</span>
        </label>
      </div>
    );
  }

  /** The stored sentence word for word, its privacy-policy address a link. */
  sentence(text: string, url: string): Mithril.Children {
    const at = url ? text.lastIndexOf(url) : -1;
    if (at < 0) return text;

    return [
      text.slice(0, at),
      <a className="ConsentBox-privacy" href={url} target="_blank" rel="noopener noreferrer">
        {url}
      </a>,
      text.slice(at + url.length),
    ];
  }

  toggle(on: boolean) {
    this.busy = true;
    setConsent(this.attrs.campaign, this.attrs.campaignId, on)
      .then((consented) => {
        this.attrs.onchange?.(consented);
        app.alerts.show(
          { type: 'success' },
          app.translator.trans(`ernestdefoe-giveaways.forum.sponsors.${consented ? 'shared' : 'withdrawn'}`, { sponsor: this.attrs.sponsor.name })
        );
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }
}
