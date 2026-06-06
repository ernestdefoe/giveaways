<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\GiveawayCategory;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST  /api/giveaway-categories         create  (giveaways.manage)
 * PATCH /api/giveaway-categories/{id}     update  (giveaways.manage)
 */
class SaveCategoryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('giveaways.manage');

        $id = Arr::get($request->getAttributes(), 'routeParameters.id');
        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        $cat = $id ? GiveawayCategory::query()->findOrFail((int) $id) : new GiveawayCategory();

        if (array_key_exists('name', $attrs) || ! $id) {
            $name = trim((string) ($attrs['name'] ?? ''));
            if ($name === '') {
                throw new ValidationException(['name' => 'A category name is required.']);
            }
            $cat->name = mb_substr($name, 0, 100);
        }
        if (array_key_exists('color', $attrs)) {
            $color = trim((string) $attrs['color']);
            $cat->color = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '#69c6b9';
        }
        if (array_key_exists('icon', $attrs)) {
            $icon = trim((string) $attrs['icon']);
            $cat->icon = $icon !== '' ? mb_substr($icon, 0, 60) : null;
        }
        if (array_key_exists('position', $attrs)) {
            $cat->position = (int) $attrs['position'];
        }

        if (! $cat->slug || array_key_exists('name', $attrs)) {
            $cat->slug = $this->uniqueSlug($cat->name, $cat->id);
        }
        if (! $id) {
            $cat->created_at = Carbon::now();
        }
        $cat->updated_at = Carbon::now();
        $cat->save();

        return new JsonResponse([
            'data' => [
                'id'       => (int) $cat->id,
                'name'     => $cat->name,
                'slug'     => $cat->slug,
                'color'    => $cat->color,
                'icon'     => $cat->icon,
                'position' => (int) $cat->position,
                'count'    => 0,
            ],
        ], $id ? 200 : 201);
    }

    private function uniqueSlug(string $name, $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (GiveawayCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
