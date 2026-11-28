<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\RichText;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Http\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\TextResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Sponsors, as the `giveaway-sponsors` JSON:API type. Anyone can see a
 * sponsor (its page lists what it presents); managers create, edit and
 * delete them.
 *
 * Custom endpoints:
 * - `POST /consent` {campaign, campaignId, on}: a member ticks or unticks the
 *   box for one campaign (Consent). `GET /consents`: what they share.
 * - Managers: `POST /{id}/report-link` makes a new secret report link (shown
 *   once; only its hash is kept) and `DELETE /{id}/report-link` revokes it;
 *   `POST /{id}/export` downloads the opted-in members (Exporter);
 *   `POST /{id}/erase` erases every opt-in for the sponsor.
 *
 * @extends AbstractDatabaseResource<Sponsor>
 */
class SponsorResource extends AbstractDatabaseResource
{
    private const MANAGE = 'giveaways.manage';

    public function __construct(
        private readonly SponsorWriter $writer,
        private readonly Consent $consent,
        private readonly Exporter $exporter,
        private readonly Campaigns $campaigns,
        private readonly RichText $rich,
        private readonly UrlGenerator $url,
    ) {
    }

    public function type(): string
    {
        return 'giveaway-sponsors';
    }

    public function model(): string
    {
        return Sponsor::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        if ($context->endpoint instanceof Endpoint\Index && $context->collection instanceof self) {
            $query->orderBy('name');
        }
    }

    /** A sponsor's address carries its slug; its id still works. */
    public function find(string $id, BaseContext $context): ?object
    {
        $id = rawurldecode($id);

        return $this->query($context)->where(fn (Builder $q) => $q->where('slug', $id)->when(ctype_digit($id), fn ($q) => $q->orWhere('id', (int) $id)))->first();
    }

    public function endpoints(): array
    {
        // Static paths first: Show's /{id} would otherwise take them.
        return [...$this->memberEndpoints(), ...$this->managerEndpoints(), ...$this->crudEndpoints()];
    }

    /** @return list<Endpoint\Endpoint> */
    private function memberEndpoints(): array
    {
        return [
            Endpoint\Endpoint::make('consent')
                ->route('POST', '/consent')
                ->authenticated()
                ->action(function (Context $context) {
                    $body = (array) ($context->body() ?? []);
                    $campaign = (string) Arr::get($body, 'campaign');
                    $id = (int) Arr::get($body, 'campaignId');
                    $this->consent->set($context->getActor(), $campaign, $id, Arr::get($body, 'on') === true);

                    return ['meta' => ['consented' => isset($this->consent->mine($context->getActor(), $campaign, [$id])[$id])]];
                }),
            Endpoint\Endpoint::make('consents')
                ->route('GET', '/consents')
                ->authenticated()
                ->action(fn (Context $context) => ['meta' => ['consents' => $this->consent->listFor($context->getActor())]]),
        ];
    }

    /** @return list<Endpoint\Endpoint> */
    private function managerEndpoints(): array
    {
        $manager = fn (string $name, string $method, string $path) => Endpoint\Endpoint::make($name)->route($method, $path)->authenticated()->can(self::MANAGE);

        return [
            $manager('reportLink', 'POST', '/{id}/report-link')->action(function (Context $context) {
                $token = bin2hex(random_bytes(32));
                $context->model->report_token_hash = hash('sha256', $token);
                $context->model->report_token_created_at = Carbon::now();
                $context->model->save();

                // The only time the link exists in full.
                return ['meta' => ['reportUrl' => $this->url->to('forum')->route('giveaways.sponsors.report', ['token' => $token])]];
            }),
            $manager('revokeReportLink', 'DELETE', '/{id}/report-link')->action(function (Context $context) {
                $context->model->report_token_hash = null;
                $context->model->report_token_created_at = null;
                $context->model->save();

                return ['meta' => ['revoked' => true]];
            }),
            $manager('export', 'POST', '/{id}/export')
                ->action(fn (Context $context) => $this->exporter->csv($context->model, $context->getActor()))
                ->response(fn (Context $context, string $csv) => new TextResponse($csv, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="'.$context->model->slug.'-opt-ins.csv"',
                    'Cache-Control' => 'no-store',
                ])),
            $manager('erase', 'POST', '/{id}/erase')->action(fn (Context $context) => [
                'meta' => ['erased' => OptIn::query()->where('sponsor_id', $context->model->id)->delete()],
            ]),
        ];
    }

    /** @return list<\Tobyz\JsonApiServer\Endpoint\Endpoint> */
    private function crudEndpoints(): array
    {
        $view = fn (Context $context) => $context->getActor()->assertCan('viewForum');

        return [
            Endpoint\Index::make()->before($view)->paginate(50, 100),
            Endpoint\Show::make()->before($view),
            Endpoint\Create::make()->authenticated()->can(self::MANAGE),
            Endpoint\Update::make()->authenticated()->can(self::MANAGE),
            Endpoint\Delete::make()->authenticated()->can(self::MANAGE),
        ];
    }

    public function fields(): array
    {
        $manage = fn ($sponsor, Context $context) => $context->getActor()->hasPermission(self::MANAGE);
        $single = fn ($sponsor, Context $context) => $context->collection instanceof self && ! $context->endpoint instanceof Endpoint\Index;
        // Accepted from managers and checked in SponsorWriter; nothing is set here.
        $input = fn (Schema\Attribute $field) => $field->writable($manage)->set(fn () => null);

        return [
            $input(Schema\Str::make('name')),
            Schema\Str::make('slug'),
            $input(Schema\Str::make('logoUrl')->nullable())->property('logo_url'),
            $input(Schema\Str::make('websiteUrl'))->property('website_url'),
            $input(Schema\Str::make('privacyUrl'))->property('privacy_url'),
            Schema\Str::make('descriptionHtml')->nullable()->visible($single)->get(fn (Sponsor $s) => $this->rich->html($s->description, $s)),
            $input(Schema\Str::make('description')->nullable())->visible(fn ($s, Context $c) => $single($s, $c) && $manage($s, $c))->get(fn (Sponsor $s) => $this->rich->source($s->description, $s)),
            Schema\Arr::make('campaigns')->visible($single)->get(fn (Sponsor $s, Context $c) => $this->campaignList($s, $c)),
            // Managers: whether a report link exists (never the link), and how many share their email.
            Schema\Str::make('reportLinkCreatedAt')->nullable()->visible($manage)->get(fn (Sponsor $s) => $s->report_token_created_at?->toIso8601String()),
            Schema\Integer::make('optInCount')->visible(fn ($s, Context $c) => $single($s, $c) && $manage($s, $c))->get(fn (Sponsor $s) => OptIn::query()->where('sponsor_id', $s->id)->count()),
        ];
    }

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
        // Its opt-ins, stats and export log cascade in the database; cleared here too for drivers without foreign keys.
        OptIn::query()->where('sponsor_id', $model->id)->delete();
        Stat::query()->where('sponsor_id', $model->id)->delete();
        Export::query()->where('sponsor_id', $model->id)->delete();
        Giveaway::query()->where('sponsor_id', $model->id)->update(['sponsor_id' => null]);
        Calendar::query()->where('sponsor_id', $model->id)->update(['sponsor_id' => null]);
        $model->delete();
    }

    /** @return array<string, mixed> */
    private function input(BaseContext $context): array
    {
        return (array) ($context->body()['data']['attributes'] ?? []);
    }

    /**
     * What the sponsor presents that this viewer can see.
     *
     * @return list<array<string, mixed>>
     */
    private function campaignList(Sponsor $sponsor, Context $context): array
    {
        $list = $this->campaigns->forSponsor($sponsor, $context->getActor());
        $giveawayIds = array_map(fn ($c) => $c->id, array_filter($list, fn ($c) => $c instanceof Giveaway));
        // One grouped count for all of them (no user input in the raw part).
        $entrants = $giveawayIds ? GiveawayEntry::query()->whereIn('giveaway_id', $giveawayIds)
            ->selectRaw('giveaway_id, COUNT(*) as n')->groupBy('giveaway_id')->pluck('n', 'giveaway_id')->all() : [];

        return array_map(fn ($c) => [
            'kind' => Campaigns::kind($c),
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'status' => $c->status,
            'prize' => $c instanceof Giveaway ? $c->prize : null,
            'entrants' => $c instanceof Giveaway ? (int) ($entrants[$c->id] ?? 0) : null,
            'endsAt' => $this->campaigns->endsAt($c)->toIso8601String(),
        ], $list);
    }
}
