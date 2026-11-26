<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\CalendarResource;
use ErnestDefoe\Giveaways\Advent\Clock;
use ErnestDefoe\Giveaways\Advent\FestoonZone;
use ErnestDefoe\Giveaways\Advent\ForumZone;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * With Festoon enabled, a calendar without its own timezone follows
 * Festoon's. Checked through the container (see AdventPairingTest for why
 * not the API): the adapter must resolve, and the calendar resource, which
 * every API request builds, must build.
 */
class AdventFestoonTest extends TestCase
{
    #[Test]
    public function a_calendar_without_a_timezone_follows_festoons()
    {
        $this->extension('ernestdefoe-festoon', 'ernestdefoe-giveaways');
        $this->setting('ernestdefoe-festoon.timezone', 'Pacific/Auckland');

        $container = $this->app()->getContainer();
        $this->assertInstanceOf(FestoonZone::class, $container->make(ForumZone::class));
        $this->assertInstanceOf(CalendarResource::class, $container->make(CalendarResource::class));

        $calendar = new Calendar();
        $calendar->timezone = '';
        $this->assertSame('Pacific/Auckland', $container->make(Clock::class)->zone($calendar));

        $calendar->timezone = 'Europe/Oslo';
        $this->assertSame('Europe/Oslo', $container->make(Clock::class)->zone($calendar));
    }

    /**
     * PHP finds a class whatever the case of its name once it is loaded, but
     * Composer's autoloader only by the exact case: written as ErnestDefoe\Festoon
     * (Festoon's namespace is Ernestdefoe\Festoon), the adapter worked wherever
     * Festoon had loaded the class first, and fatally failed everywhere else.
     */
    #[Test]
    public function the_adapters_name_the_optional_classes_exactly()
    {
        $named = fn (string $class) => (string) (new \ReflectionClass($class))->getConstructor()?->getParameters()[0]->getType();

        $this->assertSame((new \ReflectionClass(\Ernestdefoe\Festoon\Config::class))->getName(), $named(FestoonZone::class));

        $source = (string) file_get_contents(__DIR__.'/../../../src/Advent/SecretSantaExchanges.php');
        $this->assertStringContainsString('use '.(new \ReflectionClass(\ErnestDefoe\SecretSanta\Exchange::class))->getName().';', $source);
    }

    #[Test]
    public function without_festoon_it_is_utc()
    {
        $this->extension('ernestdefoe-giveaways');

        $calendar = new Calendar();
        $calendar->timezone = '';
        $this->assertSame('UTC', $this->app()->getContainer()->make(Clock::class)->zone($calendar));
    }
}
