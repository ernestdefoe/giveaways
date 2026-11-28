<?php

/*
 * This file is part of ernestdefoe/giveaways.
 *
 * Licensed under the MIT license.
 */

use ErnestDefoe\Giveaways\Advent;
use ErnestDefoe\Giveaways\Api;
use ErnestDefoe\Giveaways\Api\Resource;
use ErnestDefoe\Giveaways\Console\DrawDueCommand;
use ErnestDefoe\Giveaways\Gdpr\SponsorConsents;
use ErnestDefoe\Giveaways\Http;
use ErnestDefoe\Giveaways\Sponsors;
use ErnestDefoe\Giveaways\Console\RemindCommand;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\Listener\AwardPostBonus;
use ErnestDefoe\Giveaways\Listener\PublishFromPost;
use ErnestDefoe\Giveaways\Notification\DoorOpenBlueprint;
use ErnestDefoe\Giveaways\Notification\GiveawayClaimedBlueprint;
use ErnestDefoe\Giveaways\Notification\GiveawayWonBlueprint;
use ErnestDefoe\Giveaways\ScopeGiveawayVisibility;
use Flarum\Extend;
use Flarum\Gdpr\Extend\UserData;
use Flarum\Api\Resource\ForumResource;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The giveaway pages and modals are their own chunks; this publishes them.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/giveaways', 'giveaways.index')
        ->route('/giveaways/{slug}', 'giveaways.show')
        ->route('/advent/{slug}', 'giveaways.advent')
        ->route('/giveaways/sponsors/{slug}', 'giveaways.sponsor'),

    // A sponsor's tracked link (counts a click, redirects to its website) and
    // its campaign report behind a secret link: plain pages, not JSON:API.
    (new Extend\Routes('forum'))
        ->get('/giveaways/go/{slug}', 'giveaways.go', Http\GoToSponsor::class)
        ->get('/giveaways/sponsors/report/{token}', 'giveaways.sponsors.report', Http\SponsorReport::class),

    (new Extend\View())
        ->namespace('ernestdefoe-giveaways', __DIR__.'/views'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        // On until switched off. A fourth serializeToForum() argument is
        // ignored on Flarum 2; the default has to be registered.
        ->default('ernestdefoe-giveaways.show_nav', true)
        // Jokers per member per advent calendar (0–3), for opening a missed door.
        ->default('ernestdefoe-giveaways.advent_jokers', 1)
        // Days a sponsor's opt-ins are kept after its campaign ends (0 to 365; 0 = erased at the end).
        ->default('ernestdefoe-giveaways.sponsor_retention_days', 90)
        ->serializeToForum('giveawaysNavLabel', 'ernestdefoe-giveaways.nav_label')
        ->serializeToForum('giveawaysShowNav', 'ernestdefoe-giveaways.show_nav', 'boolval'),

    (new Extend\Event())
        ->listen(Posted::class, AwardPostBonus::class)
        // A draft made in the composer goes public with its post.
        ->listen(Posted::class, PublishFromPost::class)
        ->listen(Revised::class, PublishFromPost::class),

    // Shows the composer's "Add giveaway" button.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    new Extend\ApiResource(Resource\GiveawayResource::class),
    new Extend\ApiResource(Resource\CategoryResource::class),
    (new Extend\ModelVisibility(Giveaway::class))
        ->scope(ScopeGiveawayVisibility::class),
    new Extend\ApiResource(Advent\CalendarResource::class),
    new Extend\ApiResource(Sponsors\SponsorResource::class),
    (new Extend\ModelVisibility(Advent\Calendar::class))
        ->scope(Advent\ScopeCalendarVisibility::class),

    // Both optional: a calendar follows Festoon's timezone, and a door can
    // feature a Secret Santa exchange.
    (new Extend\Conditional())
        // A member's sponsor consents in their data export, and erased with them.
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())->addType(SponsorConsents::class),
        ])
        // A giveaway added in the composer goes public when its post is approved.
        ->whenExtensionEnabled('flarum-approval', fn () => [
            (new Extend\Event())->listen('Flarum\\Approval\\Event\\PostWasApproved', PublishFromPost::class),
        ])
        ->whenExtensionEnabled('ernestdefoe-festoon', fn () => [
            (new Extend\ServiceProvider())->register(Advent\FestoonProvider::class),
        ])
        ->whenExtensionEnabled('ernestdefoe-secret-santa', fn () => [
            (new Extend\ServiceProvider())->register(Advent\SecretSantaProvider::class),
        ]),

    (new Extend\Notification())
        ->type(GiveawayWonBlueprint::class, ['alert'])
        ->type(GiveawayClaimedBlueprint::class, ['alert'])
        ->type(DoorOpenBlueprint::class, ['alert']),

    (new Extend\Console())
        ->command(DrawDueCommand::class)
        ->schedule(DrawDueCommand::class, function (ScheduledEvent $event) {
            $event->everyMinute()->withoutOverlapping();
        })
        ->command(RemindCommand::class)
        ->schedule(RemindCommand::class, function (ScheduledEvent $event) {
            $event->everyFiveMinutes()->withoutOverlapping();
        })
        ->command(Sponsors\RetentionCommand::class)
        ->schedule(Sponsors\RetentionCommand::class, function (ScheduledEvent $event) {
            $event->daily()->withoutOverlapping();
        }),
];
