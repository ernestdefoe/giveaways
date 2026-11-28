import app from 'flarum/common/app';
import { flat } from './jsonapi';
import type { Resource } from './jsonapi';
import type { SponsorSummary } from './sponsors';

export interface GiveawayUser {
  id: number;
  username: string;
  displayName: string;
  avatarUrl: string | null;
}

export interface GiveawayWinner {
  position: number;
  user: GiveawayUser | null;
  claimedAt: string | null;
  /** Kept in the list: a forfeited winner is part of how the prize moved. */
  forfeited: boolean;
}

export interface GiveawayCategory {
  id: number;
  name: string;
  slug: string;
  color: string;
  icon: string | null;
  position?: number;
  count?: number;
}

export interface Giveaway {
  id: number;
  title: string;
  slug: string;
  prize: string;
  /** Editable source — only sent to those who can edit it, on the detail endpoint. */
  description?: string;
  rules?: string;
  /** Rendered by the forum's formatter (safe HTML), detail endpoint only. */
  descriptionHtml?: string | null;
  rulesHtml?: string | null;
  /** Public question the drawn winner must answer to claim; null when none is set. */
  skillQuestion: string | null;
  /** Expected answer — only sent to managers, for the edit form. */
  skillAnswer: string | null;
  /** Wrong answers allowed before the winner forfeits; 0 = unlimited. */
  skillAttempts: number;
  coverUrl: string | null;
  winnerCount: number;
  /** A draft is only ever sent to the member who made it. */
  status: 'draft' | 'active' | 'drawn' | 'cancelled';
  startsAt: string | null;
  endsAt: string | null;
  drawnAt: string | null;
  running: boolean;
  entrantCount: number;
  totalEntries: number;
  myEntries: number;
  mySources: Record<string, number> | null;
  postBonus: number;
  minPosts: number;
  minAgeDays: number;
  canManage: boolean;
  /** Can run the draw now (a host only once it has ended). */
  canDraw: boolean;
  /** Can delete it (a drawn giveaway only by a manager). */
  canDelete: boolean;
  /** The viewer is hosting it, so cannot enter. */
  isHost: boolean;
  iWon: boolean;
  myClaimedAt: string | null;
  myForfeited: boolean;
  /** Null when unlimited; otherwise what this winner has left. */
  myAttemptsLeft: number | null;
  /** Shared instructions: a winner gets them once they have claimed. */
  claimInstructions: string | null;
  /** This winner's own line of the per-winner list, once claimed. */
  myClaimLine: string | null;
  /** The whole per-winner list — editors only. */
  claimLines: string | null;
  canViewParticipants: boolean;
  /** The discussion it was posted in, if the viewer can see it (detail endpoint only). */
  discussionId?: number | null;
  createdBy: GiveawayUser | null;
  /** The advent calendar it belongs to (its grand prize or a door's draw): entered through the calendar. */
  calendar: { slug: string; title: string; role: 'grand' | 'door' } | null;
  /** Who presents it (the banner, and the opt-in box). */
  sponsor: SponsorSummary | null;
  /** The viewer shares their email with the sponsor for this giveaway. */
  myConsent: boolean;
  /** The opt-in box's sentence, as the server stores it (one giveaway only). */
  sponsorConsentText?: string | null;
  category: { id: number; name: string; slug: string; color: string; icon: string | null } | null;
  winners?: GiveawayWinner[];
  drawSeed?: string | null;
  entrantHash?: string | null;
}

export interface ListResult {
  data: Giveaway[];
  meta: { canCreate: boolean; canManage: boolean };
}

// Giveaways and categories are JSON:API resources; the pages use them flat, with a numeric id.

function one<T extends { id: number }>(res: { data: Resource<T> }): { data: T } {
  return { data: flat(res.data) };
}

function base(): string {
  return app.forum.attribute('apiUrl') + '/giveaways';
}

export function listGiveaways(): Promise<ListResult> {
  return app
    .request<{ data: Resource<Giveaway>[]; meta: ListResult['meta'] }>({ method: 'GET', url: base() })
    .then((res) => ({ data: res.data.map((r) => flat(r)), meta: res.meta }));
}

/** Giveaways by slug (or old id), for the cards links in posts become; at most 20 a request. */
export function giveawaysBySlug(keys: string[]): Promise<Giveaway[]> {
  return app
    .request<{ data: Resource<Giveaway>[] }>({ method: 'GET', url: base(), params: { filter: { slug: keys.join(',') } } })
    .then((res) => res.data.map((r) => flat(r)));
}

export function showGiveaway(idOrSlug: number | string): Promise<{ data: Giveaway }> {
  return app.request<{ data: Resource<Giveaway> }>({ method: 'GET', url: `${base()}/${encodeURIComponent(idOrSlug)}` }).then(one);
}

export function enterGiveaway(id: number): Promise<{ data: Giveaway }> {
  return app.request<{ data: Resource<Giveaway> }>({ method: 'POST', url: `${base()}/${id}/enter` }).then(one);
}

export function drawGiveaway(id: number): Promise<{ data: Giveaway }> {
  return app.request<{ data: Resource<Giveaway> }>({ method: 'POST', url: `${base()}/${id}/draw` }).then(one);
}

/** `answer` is the skill-testing answer, required only when the giveaway sets one. */
export function claimGiveaway(id: number, answer?: string): Promise<{ data: Giveaway }> {
  return app.request<{ data: Resource<Giveaway> }>({ method: 'POST', url: `${base()}/${id}/claim`, body: { answer: answer ?? '' } }).then(one);
}

export interface Entrant {
  user: GiveawayUser | null;
  entries: number;
  joinedAt: string | null;
}

export function listEntrants(id: number, offset = 0): Promise<{ data: Entrant[]; meta: { total: number; offset: number; limit: number } }> {
  return app.request({ method: 'GET', url: `${base()}/${id}/entrants`, params: { page: { offset } } });
}

export function deleteGiveaway(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: `${base()}/${id}` });
}

export function saveGiveaway(attributes: Record<string, unknown>, id?: number): Promise<{ data: Giveaway }> {
  return app
    .request<{ data: Resource<Giveaway> }>({
      method: id ? 'PATCH' : 'POST',
      url: id ? `${base()}/${id}` : base(),
      body: { data: { type: 'giveaways', ...(id ? { id: String(id) } : {}), attributes } },
    })
    .then(one);
}

function catBase(): string {
  return app.forum.attribute('apiUrl') + '/giveaway-categories';
}

export function listCategories(): Promise<{ data: GiveawayCategory[] }> {
  return app
    .request<{ data: Resource<GiveawayCategory>[] }>({ method: 'GET', url: catBase() })
    .then((res) => ({ data: res.data.map((r) => flat(r)) }));
}

export function saveCategory(attributes: Record<string, unknown>, id?: number): Promise<{ data: GiveawayCategory }> {
  return app
    .request<{ data: Resource<GiveawayCategory> }>({
      method: id ? 'PATCH' : 'POST',
      url: id ? `${catBase()}/${id}` : catBase(),
      body: { data: { type: 'giveaway-categories', ...(id ? { id: String(id) } : {}), attributes } },
    })
    .then(one);
}

export function deleteCategory(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: `${catBase()}/${id}` });
}
