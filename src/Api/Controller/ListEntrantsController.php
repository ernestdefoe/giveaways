<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/giveaways/{id}/entrants?page[offset]=0 — who entered, and when.
 *
 * Seen by the giveaway's host, by managers, and by anyone granted
 * `giveaways.viewParticipants`. One page is three queries whatever its size:
 * the giveaway, the count, and the entries with their users eager-loaded.
 */
class ListEntrantsController implements RequestHandlerInterface
{
    private const LIMIT = 25;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        $g = Giveaway::query()->visibleTo($actor)->findOrFail($id);

        if (! $g->participantsVisibleTo($actor)) {
            throw new PermissionDeniedException();
        }

        $offset = max(0, (int) Arr::get($request->getQueryParams(), 'page.offset', 0));

        $query = GiveawayEntry::query()->where('giveaway_id', $g->id);
        $total = (clone $query)->count();

        $rows = $query->with('user')
            ->orderBy('created_at')->orderBy('id')
            ->skip($offset)->take(self::LIMIT)
            ->get();

        return new JsonResponse([
            'data' => $rows->map(fn (GiveawayEntry $e) => [
                'user' => $e->user ? [
                    'id' => (int) $e->user->id,
                    'username' => $e->user->username,
                    'displayName' => $e->user->display_name,
                    'avatarUrl' => $e->user->avatar_url,
                ] : null,
                'entries' => (int) $e->entries,
                'joinedAt' => optional($e->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'total' => $total,
                'offset' => $offset,
                'limit' => self::LIMIT,
            ],
        ]);
    }
}
