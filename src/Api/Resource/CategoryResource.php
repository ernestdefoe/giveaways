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
use Flarum\Foundation\ValidationException;
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
        // Accepted from managers and checked in save(); nothing is set here.
        $input = fn (Schema\Attribute $field) => $field->writable($manage)->set(fn () => null);

        return [
            $input(Schema\Str::make('name')),
            Schema\Str::make('slug'),
            $input(Schema\Str::make('color')),
            $input(Schema\Str::make('icon')->nullable()),
            $input(Schema\Integer::make('position')),
            Schema\Integer::make('count')->get(fn (GiveawayCategory $c) => (int) ($c->getAttribute('giveaways_count') ?? 0)),
        ];
    }

    public function create(object $model, BaseContext $context): object
    {
        return $this->save(new GiveawayCategory(), $this->input($context), true);
    }

    public function update(object $model, BaseContext $context): object
    {
        return $this->save($model, $this->input($context), false);
    }

    public function delete(object $model, BaseContext $context): void
    {
        Giveaway::query()->where('category_id', $model->id)->update(['category_id' => null]);
        $model->delete();
    }

    /** @param array<string, mixed> $in */
    private function save(GiveawayCategory $cat, array $in, bool $new): GiveawayCategory
    {
        if ($new || array_key_exists('name', $in)) {
            $name = trim((string) ($in['name'] ?? ''));
            if ($name === '') {
                throw new ValidationException(['name' => $this->translator->trans('ernestdefoe-giveaways.api.category_name_required')]);
            }
            $cat->name = mb_substr($name, 0, 100);
            $cat->slug = Slugs::unique(GiveawayCategory::class, $cat->name, $cat->id, 'category');
        }
        if (array_key_exists('color', $in)) {
            $color = trim((string) $in['color']);
            $cat->color = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '#69c6b9';
        }
        if (array_key_exists('icon', $in)) {
            $icon = trim((string) $in['icon']);
            $cat->icon = $icon !== '' ? mb_substr($icon, 0, 60) : null;
        }
        if (array_key_exists('position', $in)) {
            $cat->position = (int) $in['position'];
        }
        $cat->created_at ??= Carbon::now();
        $cat->updated_at = Carbon::now();
        $cat->save();

        return $cat;
    }

    /** @return array<string, mixed> */
    private function input(BaseContext $context): array
    {
        return (array) ($context->body()['data']['attributes'] ?? []);
    }
}
