<?php

namespace ErnestDefoe\Giveaways\Api\Resource;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayCategory;
use ErnestDefoe\Giveaways\Slugs;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Giveaway categories, as the `giveaway-categories` JSON:API type: everyone
 * lists them (with how many giveaways each has); managers create, edit and
 * delete them. Deleting one leaves its giveaways uncategorised.
 *
 * @extends AbstractDatabaseResource<GiveawayCategory>
 */
class CategoryResource extends AbstractDatabaseResource
{
    private const MANAGE = 'giveaways.manage';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function type(): string
    {
        return 'giveaway-categories';
    }

    public function model(): string
    {
        return GiveawayCategory::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        if ($context->endpoint instanceof Endpoint\Index && $context->collection instanceof self) {
            // Counted as the viewer would list them: no one else's drafts, and
            // none of an advent calendar's giveaways.
            $actor = $context->getActor();
            $query->withCount(['giveaways' => fn (Builder $q) => $q->whereVisibleTo($actor)->whereNull('calendar_id')])
                ->orderBy('position')->orderBy('name');
        }
    }

    public function endpoints(): array
    {
        $view = fn (Context $context) => $context->getActor()->assertCan('viewForum');

        return [
            Endpoint\Index::make()->before($view),
            Endpoint\Create::make()->authenticated()->can(self::MANAGE),
            Endpoint\Update::make()->authenticated()->can(self::MANAGE),
            Endpoint\Delete::make()->authenticated()->can(self::MANAGE),
        ];
    }

    public function fields(): array
    {
        $manage = fn ($category, Context $context) => $context->getActor()->hasPermission(self::MANAGE);

        return [
            Schema\Str::make('name')->writable($manage)->requiredOnCreate()->rule('filled')->maxLength(100)
                ->rule(fn () => function ($attribute, $value, $fail) {
                    trim((string) $value) === '' && $fail($this->translator->trans('ernestdefoe-giveaways.api.category_name_required'));
                })
                ->set(fn (GiveawayCategory $c, $value) => $c->name = trim((string) $value)),
            Schema\Str::make('slug'),
            // Anything but a hex colour falls back to the default teal.
            Schema\Str::make('color')->writable($manage)
                ->set(fn (GiveawayCategory $c, $value) => $c->color = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color = trim((string) $value)) ? $color : '#69c6b9'),
            Schema\Str::make('icon')->nullable()->writable($manage)->maxLength(60)
                ->set(fn (GiveawayCategory $c, $value) => $c->icon = trim((string) $value) !== '' ? trim((string) $value) : null),
            Schema\Integer::make('position')->writable($manage),
            Schema\Integer::make('count')->get(fn (GiveawayCategory $c) => (int) ($c->getAttribute('giveaways_count') ?? 0)),
        ];
    }

    /** A new slug when the name changes; timestamps. */
    public function saving(object $model, BaseContext $context): ?object
    {
        /** @var GiveawayCategory $model */
        if (! $model->slug || $model->isDirty('name')) {
            $model->slug = Slugs::unique(GiveawayCategory::class, $model->name, $model->id, 'category');
        }
        $model->created_at ??= Carbon::now();
        $model->updated_at = Carbon::now();

        return $model;
    }

    public function delete(object $model, BaseContext $context): void
    {
        // All or nothing: its giveaways lose the category, then it goes.
        $model->getConnection()->transaction(function () use ($model) {
            Giveaway::query()->where('category_id', $model->id)->update(['category_id' => null]);
            $model->delete();
        });
    }
}
