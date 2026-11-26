<?php

namespace ErnestDefoe\Giveaways\Advent;

use ErnestDefoe\Giveaways\EntryService;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\RichText;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Advent calendars, as the `giveaway-calendars` JSON:API type (also the
 * subject of the "today's door is open" alert).
 *
 * - Index (newest first) and Show, which also finds a calendar by its slug.
 *   The viewer's openings, reminder, grand-prize entry and opened doors are
 *   loaded for the whole page at once (`after`), so the query count doesn't
 *   grow with the calendars.
 * - Create, Update and Delete for managers, through Calendars, which checks
 *   everything the builder sends (attributes here only declare what may be sent).
 * - `POST /{id}/open` {day, joker} and `POST /{id}/remind` {on} for members;
 *   both answer with the calendar as the member now sees it.
 *
 * A door's content is only ever in `doors` (DoorView, opened doors only) and,
 * for managers, `editor`.
 *
 * @extends AbstractDatabaseResource<Calendar>
 */
class CalendarResource extends AbstractDatabaseResource
{
    private const MANAGE = 'giveaways.manage';

    /** @var \WeakMap<Calendar, array<string, mixed>> opened, remind, tickets, doors, exchanges, entered (see preload) */
    private \WeakMap $extras;

    public function __construct(
        private readonly Calendars $calendars,
        private readonly Opener $opener,
        private readonly Clock $clock,
        private readonly DoorView $doorView,
        private readonly RichText $rich,
        private readonly EntryService $entries,
        private readonly Exchanges $exchanges,
    ) {
        $this->extras = new \WeakMap();
    }

    public function type(): string
    {
        return 'giveaway-calendars';
    }

    public function model(): string
    {
        return Calendar::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        /** @var Builder<Calendar> $query */
        $query->visibleTo($context->getActor());

        if ($context->endpoint instanceof Endpoint\Index && $context->collection instanceof self) {
            $query->orderByDesc('starts_on')->orderByDesc('id');
        }
    }

    /** A calendar's address carries its slug; its id still works. */
    public function find(string $id, BaseContext $context): ?object
    {
        return $this->query($context)->where(fn (Builder $q) => $q->where('slug', $id)->when(ctype_digit($id), fn ($q) => $q->orWhere('id', (int) $id)))->first();
    }

    public function endpoints(): array
    {
        $member = function (string $name, string $path, \Closure $act) {
            return Endpoint\Endpoint::make($name)
                ->route('POST', $path)
                ->authenticated()
                ->action(function (Context $context) use ($act) {
                    $context->getActor()->assertCan('viewForum');
                    $act($context->model, $context->getActor(), (array) ($context->body() ?? []));
                    $this->preload([$context->model], $context->getActor());

                    return $context->model;
                });
        };

        return [
            $member('open', '/{id}/open', fn (Calendar $c, User $actor, array $body) => $this->opener->open(
                $c,
                $actor,
                (int) Arr::get($body, 'day'),
                (bool) Arr::get($body, 'joker', false),
            )),
            $member('remind', '/{id}/remind', fn (Calendar $c, User $actor, array $body) => $this->remind($c, $actor, (bool) Arr::get($body, 'on'))),
            Endpoint\Index::make()
                ->before(fn (Context $context) => $context->getActor()->assertCan('viewForum'))
                ->paginate(10, 20)
                ->extractFilter(fn () => [])
                ->after(function (Context $context, $calendars) {
                    $this->preload($calendars, $context->getActor());

                    return $calendars;
                }),
            Endpoint\Show::make()
                ->before(fn (Context $context) => $context->getActor()->assertCan('viewForum'))
                ->after(function (Context $context, Calendar $calendar) {
                    $this->preload([$calendar], $context->getActor());

                    return $calendar;
                }),
            Endpoint\Create::make()->authenticated()->can(self::MANAGE),
            Endpoint\Update::make()->authenticated()->can(self::MANAGE),
            Endpoint\Delete::make()->authenticated()->can(self::MANAGE),
        ];
    }

    public function fields(): array
    {
        $manage = fn ($calendar, Context $context) => $context->getActor()->hasPermission(self::MANAGE);
        // Accepted from managers and handed to Calendars::save as sent; nothing is set here.
        $input = fn (Schema\Attribute $field) => $field->writable($manage)->set(fn () => null);
        // Only on the calendar's own endpoints: as an alert's subject a calendar
        // needs its title and slug, not a grand prize or doors (one query each).
        $own = fn ($calendar, Context $context) => $context->collection instanceof self;
        $single = fn ($calendar, Context $context) => $manage($calendar, $context) && ! $context->endpoint instanceof Endpoint\Index;
        $extra = fn (Calendar $c, string $key, mixed $default) => $this->extras[$c][$key] ?? $default;

        return [
            $input(Schema\Str::make('title')),
            Schema\Str::make('slug'),
            Schema\Str::make('descriptionHtml')->nullable()->visible($own)->get(fn (Calendar $c) => $this->rich->html($c->description, $c)),
            $input(Schema\Str::make('startsOn'))->get(fn (Calendar $c) => $c->starts_on->format('Y-m-d')),
            $input(Schema\Integer::make('days')),
            // The zone the doors actually follow, so the page can say so.
            Schema\Str::make('zone')->get(fn (Calendar $c) => $this->clock->zone($c)),
            Schema\Str::make('status'),
            Schema\Integer::make('today')->get(fn (Calendar $c) => $this->clock->today($c)),
            Schema\Str::make('endsAt')->get(fn (Calendar $c) => $this->clock->opensAt($c, $c->days + 1)->toIso8601String()),
            Schema\Integer::make('jokers')->get(fn () => $this->opener->jokers()),
            Schema\Integer::make('jokersUsed')->get(fn (Calendar $c) => count(array_filter($extra($c, 'opened', []))))
                ->visible(fn ($c, Context $context) => ! $context->getActor()->isGuest()),
            Schema\Integer::make('tickets')->get(fn (Calendar $c) => $extra($c, 'tickets', 0)),
            Schema\Boolean::make('remind')->get(fn (Calendar $c) => $extra($c, 'remind', false)),
            // Why this member can't open doors right now (not eligible, not signed in...), or null.
            Schema\Str::make('ineligible')->nullable()->visible($own)->get(fn (Calendar $c, Context $context) => $this->ineligible($c, $context->getActor())),
            Schema\Boolean::make('canManage')->get($manage),
            Schema\Arr::make('grand')->nullable()->visible($own)->get(fn (Calendar $c) => $c->grand ? [
                'slug' => $c->grand->slug,
                'prize' => $c->grand->prize,
                'winnerCount' => (int) $c->grand->winner_count,
                'status' => $c->grand->status,
            ] : null),
            // Sent by the builder (handed to Calendars::save); read back as the member's own view.
            $input(Schema\Arr::make('doors'))->visible($own)->get(fn (Calendar $c) => $this->doorView->forMember(
                $c,
                $extra($c, 'opened', []),
                $extra($c, 'doors', []),
                $extra($c, 'exchanges', []),
                $extra($c, 'entered', []),
            )),

            // The builder's own fields, managers only.
            $input(Schema\Str::make('description')->nullable())->visible($manage)->get(fn (Calendar $c) => $this->rich->source($c->description, $c)),
            $input(Schema\Str::make('timezone'))->visible($manage),
            $input(Schema\Integer::make('minPosts'))->visible($manage)->get(fn (Calendar $c) => (int) $c->settingsArray()['min_posts']),
            $input(Schema\Integer::make('minAgeDays'))->visible($manage)->get(fn (Calendar $c) => (int) $c->settingsArray()['min_age_days']),
            $input(Schema\Str::make('grandPrize'))->visible($manage)->get(fn (Calendar $c) => (string) $c->grand?->prize),
            $input(Schema\Integer::make('grandWinners'))->visible($manage)->get(fn (Calendar $c) => (int) ($c->grand->winner_count ?? 1)),
            $input(Schema\Str::make('grandClaimInstructions'))->visible($manage)->get(fn (Calendar $c) => (string) ($c->grand?->settingsArray()['claim_instructions'] ?? '')),
            $input(Schema\Str::make('grandClaimLines'))->visible($manage)->get(fn (Calendar $c) => (string) ($c->grand?->settingsArray()['claim_lines'] ?? '')),
            // Not on a list: one calendar at a time is edited.
            Schema\Arr::make('editor')->visible($single)->get(fn (Calendar $c) => $this->doorView->forEditor($c, $c->doors()->with('giveaway')->get())),
            Schema\Arr::make('exchangeChoices')->visible($single)->get(fn () => $this->exchanges->choices()),
            $input(Schema\Boolean::make('publish'))->visible(false),
        ];
    }

    public function create(object $model, BaseContext $context): object
    {
        return $this->calendars->save(null, $this->input($context), $context->getActor());
    }

    public function update(object $model, BaseContext $context): object
    {
        return $this->calendars->save($model, $this->input($context), $context->getActor());
    }

    public function delete(object $model, BaseContext $context): void
    {
        $this->calendars->delete($model);
    }

    /** @return array<string, mixed> */
    private function input(BaseContext $context): array
    {
        return (array) ($context->body()['data']['attributes'] ?? []);
    }

    private function remind(Calendar $calendar, User $actor, bool $on): void
    {
        $actor->assertCan('giveaways.enter');
        $mine = Reminder::query()->where('calendar_id', $calendar->id)->where('user_id', $actor->id);
        if (! $on) {
            $mine->delete();
        } elseif (! $mine->exists()) {
            Reminder::query()->insertOrIgnore(['calendar_id' => $calendar->id, 'user_id' => $actor->id]);
        }
    }

    private function ineligible(Calendar $calendar, User $actor): ?string
    {
        if ($actor->isGuest() || ! $calendar->grand || ! $calendar->isPublished()) {
            return null;
        }

        return $this->entries->ineligibleReason($calendar->grand, $actor);
    }

    /**
     * The viewer's openings, reminder and ticket count, the doors they have
     * opened (with the draws behind them and any exchanges), and every
     * calendar's grand prize: a fixed number of queries for any page size.
     *
     * @param iterable<Calendar> $calendars
     */
    private function preload(iterable $calendars, User $actor): void
    {
        $calendars = [...$calendars];
        if (! $calendars) {
            return;
        }
        $ids = array_map(fn (Calendar $c) => $c->id, $calendars);

        $grands = Giveaway::query()->whereIn('calendar_id', $ids)->where('calendar_role', 'grand')->get()->keyBy('calendar_id');
        foreach ($calendars as $calendar) {
            $calendar->setRelation('grand', $grands->get($calendar->id));
        }

        $opened = $remind = $doors = [];
        if (! $actor->isGuest()) {
            foreach (Opening::query()->whereIn('calendar_id', $ids)->where('user_id', $actor->id)->get(['calendar_id', 'day', 'via_joker']) as $o) {
                $opened[$o->calendar_id][$o->day] = $o->via_joker;
            }
            $remind = Reminder::query()->whereIn('calendar_id', $ids)->where('user_id', $actor->id)->pluck('calendar_id')->flip()->all();

            $doors = $opened ? Door::query()->whereIn('calendar_id', array_keys($opened))->with('giveaway')->get()
                ->filter(fn (Door $d) => isset($opened[$d->calendar_id][$d->day]))
                ->groupBy('calendar_id')->map(fn ($g) => $g->keyBy('day')->all())->all() : [];
        }

        $drawIds = $exchangeIds = [];
        foreach ($doors as $byDay) {
            foreach ($byDay as $door) {
                $door->giveaway_id && $drawIds[] = $door->giveaway_id;
                $door->kind === Door::EXCHANGE && $door->exchange_id && $exchangeIds[] = $door->exchange_id;
            }
        }
        $entered = $drawIds ? GiveawayEntry::query()->whereIn('giveaway_id', $drawIds)->where('user_id', $actor->id)
            ->pluck('giveaway_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all() : [];
        $exchanges = $this->exchanges->visibleTo(array_values(array_unique($exchangeIds)), $actor);

        foreach ($calendars as $calendar) {
            $mine = $opened[$calendar->id] ?? [];
            $this->extras[$calendar] = [
                'opened' => $mine,
                'remind' => isset($remind[$calendar->id]),
                'tickets' => count($mine),
                'doors' => $doors[$calendar->id] ?? [],
                'exchanges' => $exchanges,
                'entered' => $entered,
            ];
        }
    }
}
