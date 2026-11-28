<?php

namespace ErnestDefoe\Giveaways\Http;

use ErnestDefoe\Giveaways\Sponsors\Campaigns;
use ErnestDefoe\Giveaways\Sponsors\Counter;
use ErnestDefoe\Giveaways\Sponsors\OptIn;
use ErnestDefoe\Giveaways\Sponsors\Sponsor;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /giveaways/go/{slug}?c=giveaway-5: the sponsor's tracked link. Counts
 * one click for the campaign (when it names one the visitor can see and the
 * sponsor presents), then sends the visitor to the website saved on the
 * sponsor, never to an address from the request. A plain forum route: it
 * answers with a redirect, not a JSON:API document.
 */
class GoToSponsor implements RequestHandlerInterface
{
    public function __construct(private readonly Campaigns $campaigns, private readonly Counter $counter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $slug = (string) Arr::get($request->getAttribute('routeParameters', []), 'slug');
        $sponsor = Sponsor::query()->where('slug', rawurldecode($slug))->first() ?? throw new ModelNotFoundException();

        if (preg_match('/^(giveaway|calendar)-(\d+)$/', (string) Arr::get($request->getQueryParams(), 'c'), $m)) {
            $campaign = $this->campaigns->find($m[1], (int) $m[2], RequestUtil::getActor($request));
            if ($campaign && (int) $campaign->sponsor_id === $sponsor->id) {
                $this->counter->record($sponsor->id, $m[1] === 'calendar' ? OptIn::CALENDAR : OptIn::GIVEAWAY, (int) $m[2], 'clicks', $request);
            }
        }

        return new RedirectResponse($sponsor->website_url, 302, ['Referrer-Policy' => 'strict-origin-when-cross-origin']);
    }
}
