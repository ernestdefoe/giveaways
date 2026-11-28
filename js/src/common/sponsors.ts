import app from 'flarum/common/app';
import { flat as toFlat } from './jsonapi';
import type { Resource as JsonApiResource } from './jsonapi';

/** A sponsor as a giveaway or calendar shows it (the "Presented by" banner, the opt-in box). */
export interface SponsorSummary {
  id: number;
  name: string;
  slug: string;
  logoUrl: string | null;
  privacyUrl: string;
}

export interface SponsorCampaign {
  kind: 'giveaway' | 'calendar';
  id: number;
  title: string;
  slug: string;
  status: string;
  prize: string | null;
  entrants: number | null;
  endsAt: string;
}

export interface Sponsor {
  id: number;
  name: string;
  slug: string;
  logoUrl: string | null;
  websiteUrl: string;
  privacyUrl: string;
  descriptionHtml?: string | null;
  campaigns?: SponsorCampaign[];
  // Managers only.
  description?: string | null;
  reportLinkCreatedAt?: string | null;
  optInCount?: number;
  /** Managers: may download the opted-in members (a separate permission). */
  canExport?: boolean;
}

export interface Consent {
  sponsor: string | null;
  privacyUrl: string | null;
  campaign: 'giveaway' | 'calendar';
  campaignId: number;
  consent: string;
  optedInAt: string;
}

type Resource = JsonApiResource<Sponsor>;

const TYPE = 'giveaway-sponsors';

function base(): string {
  return `${app.forum.attribute('apiUrl')}/${TYPE}`;
}

const flat = (r: Resource): Sponsor => toFlat(r);

/** The sponsor's tracked link: counts the click for this campaign, then goes to its website. */
export function trackedLink(slug: string, campaign?: { kind: 'giveaway' | 'calendar'; id: number }): string {
  const url = `${app.forum.attribute('baseUrl')}/giveaways/go/${encodeURIComponent(slug)}`;
  return campaign ? `${url}?c=${campaign.kind}-${campaign.id}` : url;
}

export function listSponsors(): Promise<Sponsor[]> {
  return app.request<{ data: Resource[] }>({ method: 'GET', url: base(), params: { page: { limit: 100 } } }).then((r) => r.data.map(flat));
}

export function showSponsor(slugOrId: string | number): Promise<Sponsor> {
  return app.request<{ data: Resource }>({ method: 'GET', url: `${base()}/${encodeURIComponent(String(slugOrId))}` }).then((r) => flat(r.data));
}

export function saveSponsor(attributes: Record<string, unknown>, id?: number): Promise<Sponsor> {
  return app
    .request<{ data: Resource }>({
      method: id ? 'PATCH' : 'POST',
      url: id ? `${base()}/${id}` : base(),
      body: { data: { type: TYPE, ...(id ? { id: String(id) } : {}), attributes } },
    })
    .then((r) => flat(r.data));
}

export function deleteSponsor(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: `${base()}/${id}` });
}

/** Tick (true) or untick the box: share my email with this campaign's sponsor. */
export function setConsent(campaign: 'giveaway' | 'calendar', campaignId: number, on: boolean): Promise<boolean> {
  return app
    .request<{ meta: { consented: boolean } }>({ method: 'POST', url: `${base()}/consent`, body: { campaign, campaignId, on } })
    .then((r) => r.meta.consented);
}

export function myConsents(): Promise<Consent[]> {
  return app.request<{ meta: { consents: Consent[] } }>({ method: 'GET', url: `${base()}/consents` }).then((r) => r.meta.consents);
}

/** A new secret report link; the only time it is shown. */
export function createReportLink(id: number): Promise<string> {
  return app.request<{ meta: { reportUrl: string } }>({ method: 'POST', url: `${base()}/${id}/report-link` }).then((r) => r.meta.reportUrl);
}

export function revokeReportLink(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: `${base()}/${id}/report-link` });
}

export function eraseOptIns(id: number): Promise<number> {
  return app.request<{ meta: { erased: number } }>({ method: 'POST', url: `${base()}/${id}/erase` }).then((r) => r.meta.erased);
}

/** Downloads the opted-in members as a CSV file (managers). */
export function exportOptIns(sponsor: Sponsor): Promise<void> {
  return app
    .request<string>({
      method: 'POST',
      url: `${base()}/${sponsor.id}/export`,
      deserialize: (text: string) => text,
      responseType: 'text' as XMLHttpRequestResponseType,
    } as any)
    .then((csv) => {
      const link = document.createElement('a');
      link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      link.download = `${sponsor.slug}-opt-ins.csv`;
      link.click();
      setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });
}
