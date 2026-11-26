<?php

namespace ErnestDefoe\Giveaways\Api\Resource;

use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\ClaimService;
use ErnestDefoe\Giveaways\DrawService;
use ErnestDefoe\Giveaways\EntryService;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\GiveawayWinner;
use ErnestDefoe\Giveaways\GiveawayWriter;
use ErnestDefoe\Giveaways\RichText;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Giveaways, as the `giveaways` JSON:API type (also the subject of the won
 * and claimed alerts).
 *
 * - Index: active first, then drafts, then drawn. `filter[slug]=a,b` (at most
 *   20 slugs or old ids) is how giveaway links in posts become cards; without
 *   it, an advent calendar's giveaways are left out. Meta: canCreate, canManage.
 * - Show finds a giveaway by its slug first, then its id.
 * - Create, Update and Delete go through GiveawayWriter, which checks everything
 *   the form sends and who may send it.
 * - Custom: `GET /{id}/entrants` (25 a page), `POST /{id}/enter`, `/{id}/draw`
 *   and `/{id}/claim` {answer}.
 *
 * What each viewer sees is decided in one place, GiveawayPresenter; the fields
 * read it. A list is presented in one go (its counts and the viewer's own
 * entries and wins batch-loaded); as an alert's subject a giveaway sends only
 * the basic fields.
 *
 * @extends AbstractDatabaseResource<Giveaway>
 */
class GiveawayResource extends AbstractDatabaseResource
{
    private const ENTRANTS_PER_PAGE = 25;

    /** @var \WeakMap<Giveaway, array<array-key, mixed>> */
    private \WeakMap $views;

    public function __construct(
        private readonly GiveawayWriter $writer,
        private readonly EntryService $entries,
        private readonly DrawService $draws,
        private readonly ClaimService $claims,
        private readonly TranslatorInterface $translator,
        private readonly RichText $rich,
    ) {
        $this->views = new \WeakMap();
    }

    public function type(): string
    {
        return 'giveaways';
    }

    public function model(): string
    {
        return Giveaway::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        /** @var Builder<Giveaway> $query */
        $query->whereVisibleTo($context->getActor());

        if (! $context->endpoint instanceof Endpoint\Index || ! $context->collection instanceof self) {
            return;
        }

        $keys = $this->keys($context);
        $query
            // A calendar's giveaways are listed on the calendar; a link still finds one.
            ->when($keys === null, fn (Builder $q) => $q->whereNull('calendar_id'))
            ->when($keys !== null, function (Builder $q) use ($keys) {
                // A link carries a slug, or an id from before slugs were safe.
                $ids = array_values(array_filter($keys, 'ctype_digit'));
                $q->where(fn (Builder $q) => $q->whereIn('slug', $keys)->when($ids, fn ($q) => $q->orWhereIn('id', $ids)));
            })
            // Portable ordering: FIELD() is MySQL-only.
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'drawn' THEN 2 WHEN 'cancelled' THEN 3 ELSE 4 END")
            ->orderBy('ends_at', 'desc');
    }

    /** Show by slug first (all-digit slugs from before they were safe), then id; everything else by id first. */
    public function find(string $id, BaseContext $context): ?object
    {
        // A slug in a non-Latin script arrives percent-encoded.
        $id = rawurldecode($id);
        $query = fn () => $this->query($context);
        $bySlug = fn () => $query()->where('slug', $id)->first();
        $byId = fn () => ctype_digit($id) ? $query()->whereKey((int) $id)->first() : null;

        return $context->endpoint instanceof Endpoint\Show ? ($bySlug() ?? $byId()) : ($byId() ?? $bySlug());
    }

    public function endpoints(): array
    {
        // Custom endpoints first: Show's /{id} would otherwise take their paths.
        return [...$this->actionEndpoints(), ...$this->crudEndpoints()];
    }

    /** @return list<Endpoint\Endpoint> */
    private function actionEndpoints(): array
    {
        $view = fn (Context $context) => $context->getActor()->assertCan('viewForum');

        return [
            Endpoint\Endpoint::make('entrants')
                ->route('GET', '/{id}/entrants')
                ->action(function (Context $context) use ($view) {
                    $view($context);

                    return $this->entrants($context->model, $context->getActor(), max(0, (int) Arr::get($context->queryParam('page') ?? [], 'offset', 0)));
                }),
            Endpoint\Endpoint::make('enter')
                ->route('POST', '/{id}/enter')
                ->authenticated()
                ->action(function (Context $context) use ($view) {
                    $view($context);
                    $this->enter($context->model, $context->getActor());

                    return $context->model;
                }),
            Endpoint\Endpoint::make('draw')
                ->route('POST', '/{id}/draw')
                ->authenticated()
                ->action(function (Context $context) use ($view) {
                    $view($context);
                    $this->draw($context->model, $context->getActor());

                    return $context->model->refresh();
                }),
            Endpoint\Endpoint::make('claim')
                ->route('POST', '/{id}/claim')
                ->authenticated()
                ->action(function (Context $context) use ($view) {
                    $view($context);
                    $body = (array) ($context->body() ?? []);
                    $answer = Arr::get($body, 'answer', Arr::get($body, 'data.attributes.answer', ''));
                    $this->claims->claim($context->model, $context->getActor(), is_string($answer) ? $answer : '');

                    return $context->model;
                }),
        ];
    }

    /** @return list<\Tobyz\JsonApiServer\Endpoint\Endpoint> */
    private function crudEndpoints(): array
    {
        $view = fn (Context $context) => $context->getActor()->assertCan('viewForum');

        return [
            Endpoint\Index::make()
                ->before($view)
                ->paginate(100, 100)
                // filter[slug] is read in scope(); there is no searcher.
                ->extractFilter(fn () => [])
                ->meta([
                    Schema\Boolean::make('canCreate')->get(fn (Context $c) => $c->getActor()->hasPermission('giveaways.create') || $c->getActor()->hasPermission('giveaways.manage')),
                    Schema\Boolean::make('canManage')->get(fn (Context $c) => $c->getActor()->hasPermission('giveaways.manage')),
                ])
                ->after(function (Context $context, $giveaways) {
                    $this->presentList($giveaways, $context->getActor());

                    return $giveaways;
                }),
            Endpoint\Show::make()->before($view),
            Endpoint\Create::make()->before($view)->authenticated(),
            Endpoint\Update::make()->before($view)->authenticated(),
            Endpoint\Delete::make()->before($view)->authenticated(),
        ];
    }

    public function fields(): array
    {
        // Only on the giveaways' own endpoints: as an alert's subject a giveaway
        // needs its name and address, not the viewer's entries and the winners.
        $own = fn ($g, Context $context) => $context->collection instanceof self;
        // Accepted from signed-in members and handed to GiveawayWriter as sent;
        // it decides who may change what. Nothing is set here.
        $write = fn ($g, Context $context) => ! $context->getActor()->isGuest();
        $input = fn (Schema\Attribute $field) => $field->writable($write)->set(fn () => null);
        $presented = fn (string $name) => fn (Giveaway $g, Context $context) => $this->view($g, $context)[$name] ?? null;

        $fields = [
            // Always: what an alert needs.
            $input(Schema\Str::make('title')),
            Schema\Str::make('slug'),
            $input(Schema\Str::make('prize')),
            Schema\Str::make('status'),
            $input(Schema\Str::make('endsAt')->nullable())->get(fn (Giveaway $g) => $g->ends_at?->toIso8601String()),
            Schema\Str::make('drawnAt')->nullable()->get(fn (Giveaway $g) => $g->drawn_at?->toIso8601String()),
            // Write-only.
            $input(Schema\Integer::make('categoryId')->nullable())->visible(false),
            $input(Schema\Boolean::make('draft'))->visible(false),
            $input(Schema\Boolean::make('publish'))->visible(false),
        ];

        // A key the presenter leaves out for this viewer (the editable source,
        // the full winners list on a list page...) is left out here too.
        $shown = fn (string $name) => fn (Giveaway $g, Context $context) => $own($g, $context) && array_key_exists($name, $this->view($g, $context));

        foreach (self::PRESENTED as $name => [$type, $writable]) {
            $field = $type::make($name)->nullable()->visible($shown($name))->get($presented($name));
            $fields[] = $writable ? $input($field) : $field;
        }

        return $fields;
    }

    /**
     * The presenter's keys, by schema type, and whether the form may send them.
     *
     * @var array<string, array{0: class-string, 1: bool}>
     */
    private const PRESENTED = [
        'skillQuestion' => [Schema\Str::class, true],
        'skillAnswer' => [Schema\Str::class, true],
        'skillAttempts' => [Schema\Integer::class, true],
        'coverUrl' => [Schema\Str::class, true],
        'winnerCount' => [Schema\Integer::class, true],
        'startsAt' => [Schema\Str::class, true],
        'running' => [Schema\Boolean::class, false],
        'entrantCount' => [Schema\Integer::class, false],
        'totalEntries' => [Schema\Integer::class, false],
        'myEntries' => [Schema\Integer::class, false],
        'mySources' => [Schema\Arr::class, false],
        'postBonus' => [Schema\Integer::class, true],
        'minPosts' => [Schema\Integer::class, true],
        'minAgeDays' => [Schema\Integer::class, true],
        'canManage' => [Schema\Boolean::class, false],
        'canDraw' => [Schema\Boolean::class, false],
        'canDelete' => [Schema\Boolean::class, false],
        'canViewParticipants' => [Schema\Boolean::class, false],
        'isHost' => [Schema\Boolean::class, false],
        'iWon' => [Schema\Boolean::class, false],
        'myClaimedAt' => [Schema\Str::class, false],
        'myForfeited' => [Schema\Boolean::class, false],
        'myAttemptsLeft' => [Schema\Integer::class, false],
        'claimInstructions' => [Schema\Str::class, true],
        'myClaimLine' => [Schema\Str::class, false],
        'claimLines' => [Schema\Str::class, true],
        'createdBy' => [Schema\Arr::class, false],
        'category' => [Schema\Arr::class, false],
        'calendar' => [Schema\Arr::class, false],
        // One giveaway only (not on a list).
        'descriptionHtml' => [Schema\Str::class, false],
        'rulesHtml' => [Schema\Str::class, false],
        'description' => [Schema\Str::class, true],
        'rules' => [Schema\Str::class, true],
        'winners' => [Schema\Arr::class, false],
        'discussionId' => [Schema\Integer::class, false],
        'drawSeed' => [Schema\Str::class, false],
        'entrantHash' => [Schema\Str::class, false],
    ];

    public function create(object $model, BaseContext $context): object
    {
        return $this->writer->save(null, $this->input($context), $context->getActor());
    }

    public function update(object $model, BaseContext $context): object
    {
        return $this->writer->save($model, $this->input($context), $context->getActor());
    }

    public function delete(object $model, BaseContext $context): void
    {
        /** @var Giveaway $model */
        if (! $model->canBeDeletedBy($context->getActor())) {
            throw new PermissionDeniedException();
        }

        // Children also cascade in the database; deleted here too for drivers without foreign keys.
        GiveawayEntry::query()->where('giveaway_id', $model->id)->delete();
        GiveawayWinner::query()->where('giveaway_id', $model->id)->delete();
        $model->delete();
    }

    /** @return array<string, mixed> */
    private function input(BaseContext $context): array
    {
        return (array) ($context->body()['data']['attributes'] ?? []);
    }

    /**
     * What the presenter shows this viewer: a whole list at once (presentList),
     * else this one giveaway in full.
     *
     * @return array<array-key, mixed>
     */
    private function view(Giveaway $g, Context $context): array
    {
        return $this->views[$g] ??= GiveawayPresenter::forActor($context->getActor(), $this->rich)
            ->present($g->loadMissing(['user', 'category', 'calendar']), ! $context->endpoint instanceof Endpoint\Index);
    }

    /** @param iterable<Giveaway> $giveaways */
    private function presentList(iterable $giveaways, User $actor): void
    {
        $list = (new Collection([...$giveaways]))->load(['user', 'category', 'calendar']);
        $presenter = GiveawayPresenter::forList($actor, $list, $this->rich);
        foreach ($list as $g) {
            $this->views[$g] = $presenter->present($g, false);
        }
    }

    /** @return array<string, mixed> one page of who entered and when */
    private function entrants(Giveaway $g, User $actor, int $offset): array
    {
        if (! $g->participantsVisibleTo($actor)) {
            throw new PermissionDeniedException();
        }

        $query = GiveawayEntry::query()->where('giveaway_id', $g->id);
        $total = (clone $query)->count();
        $rows = $query->with('user')->orderBy('created_at')->orderBy('id')->skip($offset)->take(self::ENTRANTS_PER_PAGE)->get();

        return [
            'data' => $rows->map(fn (GiveawayEntry $e) => [
                'user' => $e->user ? [
                    'id' => (int) $e->user->id,
                    'username' => $e->user->username,
                    'displayName' => $e->user->display_name,
                    'avatarUrl' => $e->user->avatar_url,
                ] : null,
                'entries' => (int) $e->entries,
                'joinedAt' => $e->created_at?->toIso8601String(),
            ])->all(),
            'meta' => ['total' => $total, 'offset' => $offset, 'limit' => self::ENTRANTS_PER_PAGE],
        ];
    }

    private function enter(Giveaway $g, User $actor): void
    {
        $actor->assertCan('giveaways.enter');

        // A calendar's draws are entered by opening its doors.
        if ($g->calendar_id) {
            throw new ValidationException(['enter' => $this->translator->trans('ernestdefoe-giveaways.api.advent.enter_on_calendar')]);
        }
        if ($reason = $this->entries->ineligibleReason($g, $actor)) {
            throw new ValidationException(['enter' => $reason]);
        }

        $this->entries->enter($g, $actor);
    }

    private function draw(Giveaway $g, User $actor): void
    {
        if (! $g->canBeManagedBy($actor)) {
            throw new PermissionDeniedException();
        }
        if ($g->status === 'active' && ! $g->canBeDrawnBy($actor)) {
            throw new ValidationException(['draw' => $this->translator->trans('ernestdefoe-giveaways.api.draw_not_ended')]);
        }

        $this->draws->draw($g);
    }

    /** @return string[]|null the requested slugs, or null for the whole list */
    private function keys(BaseContext $context): ?array
    {
        $raw = ($context->queryParam('filter') ?? [])['slug'] ?? null;
        if (! is_string($raw)) {
            return null;
        }

        $keys = array_filter(array_map(fn ($k) => mb_substr(trim($k), 0, 255), explode(',', $raw)), 'strlen');

        return array_slice(array_values(array_unique($keys)), 0, 20);
    }
}
