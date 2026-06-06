<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\GiveawayCategory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/giveaway-categories — all categories with their giveaway counts. */
class ListCategoriesController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $cats = GiveawayCategory::query()
            ->withCount('giveaways')
            ->orderBy('position')->orderBy('name')->get();

        $data = $cats->map(fn (GiveawayCategory $c) => [
            'id'       => (int) $c->id,
            'name'     => $c->name,
            'slug'     => $c->slug,
            'color'    => $c->color,
            'icon'     => $c->icon,
            'position' => (int) $c->position,
            'count'    => (int) ($c->giveaways_count ?? 0),
        ])->all();

        return new JsonResponse(['data' => $data]);
    }
}
