<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Api\GiveawaySerializer;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST  /api/giveaways         create  (requires giveaways.create)
 * PATCH /api/giveaways/{id}    update  (manager or author)
 */
class SaveGiveawayController implements RequestHandlerInterface
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $id = Arr::get($request->getAttributes(), 'routeParameters.id');
        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        if ($id) {
            $g = Giveaway::query()->findOrFail((int) $id);
            $this->assertCanManage($actor, $g);
        } else {
            $actor->assertCan('giveaways.create');
            $g = new Giveaway();
            $g->user_id = $actor->id;
            $g->status = 'active';
        }

        $errors = [];

        if (array_key_exists('title', $attrs) || ! $id) {
            $title = trim((string) ($attrs['title'] ?? ''));
            $title === '' ? $errors['title'] = $this->translator->trans('ernestdefoe-giveaways.api.title_required') : $g->title = mb_substr($title, 0, 255);
        }
        if (array_key_exists('prize', $attrs) || ! $id) {
            $prize = trim((string) ($attrs['prize'] ?? ''));
            $prize === '' ? $errors['prize'] = $this->translator->trans('ernestdefoe-giveaways.api.prize_required') : $g->prize = mb_substr($prize, 0, 255);
        }
        if (array_key_exists('endsAt', $attrs) || ! $id) {
            $ends = $this->date($attrs['endsAt'] ?? null);
            if (! $ends) {
                $errors['endsAt'] = $this->translator->trans('ernestdefoe-giveaways.api.ends_required');
            } elseif (! $id && $ends->isPast()) {
                $errors['endsAt'] = $this->translator->trans('ernestdefoe-giveaways.api.ends_future');
            } else {
                $g->ends_at = $ends;
            }
        }
        if (array_key_exists('startsAt', $attrs)) {
            $g->starts_at = $this->date($attrs['startsAt']);
        }
        if (array_key_exists('description', $attrs)) {
            $g->description = mb_substr((string) $attrs['description'], 0, 20000) ?: null;
        }
        if (array_key_exists('coverUrl', $attrs)) {
            $g->cover_url = $this->url($attrs['coverUrl']);
        }
        if (array_key_exists('winnerCount', $attrs)) {
            $g->winner_count = max(1, min(100, (int) $attrs['winnerCount']));
        }
        if (array_key_exists('categoryId', $attrs)) {
            $cid = $attrs['categoryId'] ? (int) $attrs['categoryId'] : null;
            $g->category_id = ($cid && \ErnestDefoe\Giveaways\GiveawayCategory::whereKey($cid)->exists()) ? $cid : null;
        }

        // Entry-method + eligibility settings.
        $s = $g->settingsArray();
        foreach (['postBonus' => 'post_bonus', 'minPosts' => 'min_posts', 'minAgeDays' => 'min_age_days'] as $in => $key) {
            if (array_key_exists($in, $attrs)) {
                $s[$key] = max(0, (int) $attrs[$in]);
            }
        }
        if (array_key_exists('claimInstructions', $attrs)) {
            $s['claim_instructions'] = mb_substr(trim((string) $attrs['claimInstructions']), 0, 2000);
        }
        $g->settings = json_encode($s);

        if ($errors) {
            throw new ValidationException($errors);
        }

        if (! $g->slug || array_key_exists('title', $attrs)) {
            $g->slug = $this->uniqueSlug($g->title, $g->id);
        }
        if (! $id) {
            $g->created_at = Carbon::now();
        }
        $g->updated_at = Carbon::now();
        $g->save();
        $g->load(['user', 'category']);

        return new JsonResponse(['data' => GiveawaySerializer::serialize($g, $actor, true)], $id ? 200 : 201);
    }

    private function assertCanManage($actor, Giveaway $g): void
    {
        $ok = $actor->hasPermission('giveaways.manage')
            || ($g->user_id && (int) $actor->id === (int) $g->user_id && $actor->hasPermission('giveaways.create'));
        if (! $ok) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }
    }

    private function date($value): ?Carbon
    {
        if ($value === null || $value === '') return null;
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function url($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        $ok = filter_var($v, FILTER_VALIDATE_URL) || (str_starts_with($v, '/') && ! str_starts_with($v, '//'));
        return $ok ? mb_substr($v, 0, 600) : null;
    }

    private function uniqueSlug(string $title, $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'giveaway';
        $slug = $base;
        $i = 2;
        while (Giveaway::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
