<?php

/*
 * This file is part of ernestdefoe/giveaways.
 *
 * Licensed under the MIT license.
 */

use ErnestDefoe\Giveaways\Api\Controller;
use ErnestDefoe\Giveaways\Console\DrawDueCommand;
use ErnestDefoe\Giveaways\Listener\AwardPostBonus;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/giveaways', 'giveaways.index')
        ->route('/giveaways/{slug}', 'giveaways.show'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Settings())
        ->serializeToForum('giveawaysNavLabel', 'ernestdefoe-giveaways.nav_label')
        ->serializeToForum('giveawaysShowNav', 'ernestdefoe-giveaways.show_nav', 'boolval', true),

    (new Extend\Routes('api'))
        ->get('/giveaways', 'giveaways.index', Controller\ListGiveawaysController::class)
        ->get('/giveaways/{id}', 'giveaways.show', Controller\ShowGiveawayController::class)
        ->post('/giveaways', 'giveaways.create', Controller\SaveGiveawayController::class)
        ->patch('/giveaways/{id}', 'giveaways.update', Controller\SaveGiveawayController::class)
        ->delete('/giveaways/{id}', 'giveaways.delete', Controller\DeleteGiveawayController::class)
        ->post('/giveaways/{id}/enter', 'giveaways.enter', Controller\EnterGiveawayController::class)
        ->post('/giveaways/{id}/draw', 'giveaways.draw', Controller\DrawGiveawayController::class),

    (new Extend\Event())
        ->listen(Posted::class, AwardPostBonus::class),

    (new Extend\Console())
        ->command(DrawDueCommand::class)
        ->schedule('giveaways:draw-due', function (ScheduledEvent $event) {
            $event->everyMinute()->withoutOverlapping();
        }),
];
