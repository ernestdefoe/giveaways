import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import { showSponsor, trackedLink } from '../../common/sponsors';
import type { Sponsor, SponsorCampaign } from '../../common/sponsors';

/** /giveaways/sponsors/:slug — who the sponsor is, and what it presents. */
export default class SponsorPage extends Page {
  sponsor: Sponsor | null = null;
  missing = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    showSponsor(m.route.param('slug'))
      .then((sponsor) => {
        this.sponsor = sponsor;
        app.setTitle(sponsor.name);
      })
      .catch(() => {
        this.missing = true;
      })
      .finally(() => m.redraw());
  }

  view(): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.sponsors.${key}`, params);
    const s = this.sponsor;
    if (this.missing) return <div className="SponsorPage container">{t('not_found')}</div>;
    if (!s) return <LoadingIndicator display="block" />;

    return (
      <div className="SponsorPage container">
        <header className="SponsorPage-header">
          {s.logoUrl && <img className="SponsorPage-logo" src={s.logoUrl} alt="" decoding="async" />}
          <div>
            <h2 className="SponsorPage-name">{s.name}</h2>
            <a className="Button" href={trackedLink(s.slug)} target="_blank" rel="sponsored noopener">
              {t('visit')}
            </a>
          </div>
        </header>
        {s.descriptionHtml && <div className="SponsorPage-description Post-body">{m.trust(s.descriptionHtml)}</div>}
        <h3 className="SponsorPage-heading">{t('presents')}</h3>
        {(s.campaigns || []).length ? (
          <ul className="SponsorPage-campaigns">{(s.campaigns || []).map((c) => this.campaign(c))}</ul>
        ) : (
          <p className="helpText">{t('nothing_yet')}</p>
        )}
      </div>
    );
  }

  campaign(c: SponsorCampaign): Mithril.Children {
    const href = c.kind === 'calendar' ? app.route('giveaways.advent', { slug: c.slug }) : app.route('giveaways.show', { slug: c.slug });

    return (
      <li className="SponsorPage-campaign">
        <Link href={href}>{c.title}</Link>
        <span className="SponsorPage-meta">
          {c.prize ? `${c.prize} · ` : ''}
          {app.translator.trans(
            `ernestdefoe-giveaways.forum.sponsors.${c.kind === 'calendar' ? 'calendar' : c.status === 'drawn' ? 'ended' : 'giveaway'}`,
            {
              date: dayjs(c.endsAt).format('LL'),
            }
          )}
        </span>
      </li>
    );
  }
}
