<?php

namespace ErnestDefoe\Giveaways\Notification;

use ErnestDefoe\Giveaways\Advent\Calendar;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * "Door N of <calendar> is open", for members who asked to be reminded. It
 * names the calendar and the day, never what is behind the door.
 */
class DoorOpenBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public Calendar $calendar, public int $day)
    {
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->calendar;
    }

    public function getData(): mixed
    {
        return ['title' => $this->calendar->title, 'slug' => $this->calendar->slug, 'day' => $this->day];
    }

    public static function getType(): string
    {
        return 'giveawayDoorOpen';
    }

    public static function getSubjectModel(): string
    {
        return Calendar::class;
    }
}
