<?php

namespace ErnestDefoe\Giveaways\Notification;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * Notifies a giveaway's host that a winner has claimed their prize, so they can
 * fulfill it. fromUser is the claimer; data carries the giveaway + claimer name.
 */
class GiveawayClaimedBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(
        public Giveaway $giveaway,
        public User $claimer
    ) {
    }

    public function getFromUser(): ?User
    {
        return $this->claimer;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->giveaway;
    }

    public function getData(): mixed
    {
        return [
            'title'   => $this->giveaway->title,
            'slug'    => $this->giveaway->slug,
            'prize'   => $this->giveaway->prize,
            'claimer' => $this->claimer->username,
        ];
    }

    public static function getType(): string
    {
        return 'giveawayClaimed';
    }

    public static function getSubjectModel(): string
    {
        return Giveaway::class;
    }
}
