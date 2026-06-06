import app from 'flarum/forum/app';

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
}

export interface Giveaway {
  id: number;
  title: string;
  slug: string;
  prize: string;
  description: string | null;
  coverUrl: string | null;
  winnerCount: number;
  status: 'active' | 'drawn' | 'cancelled';
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
  createdBy: GiveawayUser | null;
  winners?: GiveawayWinner[];
  drawSeed?: string | null;
  entrantHash?: string | null;
}

export interface ListResult {
  data: Giveaway[];
  meta: { canCreate: boolean; canManage: boolean };
}

function base(): string {
  return app.forum.attribute('apiUrl') + '/giveaways';
}

export function listGiveaways(): Promise<ListResult> {
  return app.request<ListResult>({ method: 'GET', url: base() });
}

export function showGiveaway(idOrSlug: number | string): Promise<{ data: Giveaway }> {
  return app.request<{ data: Giveaway }>({ method: 'GET', url: `${base()}/${idOrSlug}` });
}

export function enterGiveaway(id: number): Promise<{ data: Giveaway }> {
  return app.request<{ data: Giveaway }>({ method: 'POST', url: `${base()}/${id}/enter` });
}

export function drawGiveaway(id: number): Promise<{ data: Giveaway }> {
  return app.request<{ data: Giveaway }>({ method: 'POST', url: `${base()}/${id}/draw` });
}

export function deleteGiveaway(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: `${base()}/${id}` });
}

export function saveGiveaway(attributes: Record<string, unknown>, id?: number): Promise<{ data: Giveaway }> {
  return app.request<{ data: Giveaway }>({
    method: id ? 'PATCH' : 'POST',
    url: id ? `${base()}/${id}` : base(),
    body: { data: { attributes } },
  });
}
