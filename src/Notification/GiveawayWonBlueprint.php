<?php

namespace ErnestDefoe\Giveaways\Notification;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * Notifies a user that they won a giveaway. The giveaway title/slug are stored
 * in the notification data so the frontend can render a link without needing a
 * dedicated subject serializer.
 */
class GiveawayWonBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(
        public Giveaway $giveaway,
        public int $position = 1
    ) {
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->giveaway;
    }

    public function getData(): mixed
    {
        return [
            'title'    => $this->giveaway->title,
            'slug'     => $this->giveaway->slug,
            'prize'    => $this->giveaway->prize,
            'position' => $this->position,
        ];
    }

    public static function getType(): string
    {
        return 'giveawayWon';
    }

    public static function getSubjectModel(): string
    {
        return Giveaway::class;
    }
}
