import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import type { SponsorSummary } from '../../common/sponsors';

/**
 * "Presented by" a sponsor, linking to its page. `compact` for cards: plain
 * text there, since the whole card is already a link.
 */
export default class SponsorBanner extends Component<{ sponsor: SponsorSummary; compact?: boolean }> {
  view(): Mithril.Children {
    const { sponsor, compact } = this.attrs;

    const inner = [
      <span className="SponsorBanner-label">{app.translator.trans('ernestdefoe-giveaways.forum.sponsors.presented_by')}</span>,
      sponsor.logoUrl && <img className="SponsorBanner-logo" src={sponsor.logoUrl} alt="" loading="lazy" decoding="async" />,
      <span className="SponsorBanner-name">{sponsor.name}</span>,
    ];

    return compact ? (
      <span className="SponsorBanner SponsorBanner--compact">{inner}</span>
    ) : (
      <Link className="SponsorBanner" href={app.route('giveaways.sponsor', { slug: sponsor.slug })}>
        {inner}
      </Link>
    );
  }
}
