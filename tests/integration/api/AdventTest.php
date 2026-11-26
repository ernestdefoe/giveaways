<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\Clock;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\Job\SendDoorReminders;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Advent calendars: building one, opening doors (today free, the past with a
 * joker, the future never), tickets for the grand prize, the reminder, and the
 * rule that a door's content reaches nobody before they open it, by any route.
 *
 * The calendar runs in UTC from two days ago, so door 3 is today's.
 */
class AdventTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** Text that must never reach a member before its door opens for them. */
    private const SECRETS = ['FUTURE-REVEAL-4', 'FUTURE-PRIZE-5', 'Door five title', 'TODAY-REVEAL-BODY', 'Mug of today'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-giveaways');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 10, 'username' => 'elf', 'email' => 'elf@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear()],
                ['id' => 11, 'username' => 'reindeer', 'email' => 'reindeer@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear()],
            ],
            'group_user' => [['user_id' => 10, 'group_id' => 3], ['user_id' => 11, 'group_id' => 3]],
            'group_permission' => [['permission' => 'giveaways.enter', 'group_id' => 3]],
        ]);
    }

    private function call(string $method, string $path, ?int $actor = null, ?array $json = null, array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($json !== null) {
            $options['json'] = $json;
        }
        $request = $this->request($method, $path, $options);
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        if (! $actor && $method !== 'GET') {
            $request = $this->requestWithCsrfToken($request);
        }
        $response = $this->send($request);
        $body = (string) $response->getBody();

        return [$response->getStatusCode(), json_decode($body, true), $body];
    }

    /** Builds and publishes the calendar as the admin; returns its JSON:API document. */
    private function build(array $overrides = []): array
    {
        $attributes = array_merge([
            'title' => 'Winter Doors',
            'startsOn' => Carbon::now('UTC')->subDays(2)->format('Y-m-d'),
            'days' => 5,
            'timezone' => 'UTC',
            'grandPrize' => 'A console',
            'grandWinners' => 1,
            'publish' => true,
            'doors' => [
                ['day' => 1, 'kind' => 'reveal', 'title' => 'Day one', 'body' => 'Code: ONE'],
                ['day' => 2, 'kind' => 'prize', 'title' => 'Stickers', 'prize' => 'Sticker pack', 'winners' => 2],
                ['day' => 3, 'kind' => 'prize', 'title' => 'Mug of today', 'prize' => 'A mug', 'body' => 'TODAY-REVEAL-BODY'],
                ['day' => 4, 'kind' => 'reveal', 'title' => 'Four', 'body' => 'FUTURE-REVEAL-4'],
                ['day' => 5, 'kind' => 'prize', 'title' => 'Door five title', 'prize' => 'FUTURE-PRIZE-5'],
            ],
        ], $overrides);

        [$status, $body] = $this->call('POST', '/api/giveaway-calendars', 1, ['data' => ['type' => 'giveaway-calendars', 'attributes' => $attributes]]);
        $this->assertSame(201, $status, json_encode($body));

        return $body['data'];
    }

    private function open(int $actor, string $id, int $day, bool $joker = false): array
    {
        return $this->call('POST', "/api/giveaway-calendars/$id/open", $actor, ['day' => $day, 'joker' => $joker]);
    }

    private function door(array $calendar, int $day): array
    {
        return $calendar['attributes']['doors'][$day - 1];
    }

    #[Test]
    public function a_door_that_has_not_opened_reaches_nobody_by_any_route()
    {
        $calendar = $this->build();
        $id = $calendar['id'];
        $slug = $calendar['attributes']['slug'];
        $future = Giveaway::query()->where('calendar_id', $id)->where('prize', 'FUTURE-PRIZE-5')->firstOrFail();
        $today = Giveaway::query()->where('calendar_id', $id)->where('prize', 'A mug')->firstOrFail();

        // The admin's reminder for today's door goes out, so the alert route is covered too.
        $this->call('POST', "/api/giveaway-calendars/$id/remind", 10, ['on' => true]);
        $this->app()->getContainer()->call([new SendDoorReminders((int) $id, 3), 'handle']);

        foreach ([null, 10] as $actor) {
            $routes = [
                ['GET', "/api/giveaway-calendars/$slug", []],
                ['GET', "/api/giveaway-calendars/$id", []],
                ['GET', '/api/giveaway-calendars', []],
                ['GET', '/api/giveaways', []],
                // The post-card embed's lookup, by slug and by id.
                ['GET', '/api/giveaways', ['filter' => ['slug' => $future->slug.','.$future->id]]],
                ['GET', '/api/giveaways/'.$future->slug, []],
                ['GET', '/api/giveaways/'.$future->id, []],
                ['GET', '/api/giveaways/'.$future->id.'/entrants', []],
                // The forum payload (every page) and the alerts list.
                ['GET', '/api', []],
                ['GET', '/', []],
                ['GET', "/advent/$slug", []],
                ['GET', '/api/notifications', []],
            ];
            foreach ($routes as [$method, $path, $query]) {
                [$status, , $raw] = $this->call($method, $path, $actor, null, $query);
                foreach (self::SECRETS as $secret) {
                    $this->assertStringNotContainsString($secret, $raw, "$path leaked \"$secret\" to actor ".($actor ?? 'guest')." (HTTP $status)");
                }
            }
            // A future door's draw can't even be entered or found directly.
            [$status] = $this->call('POST', '/api/giveaways/'.$future->id.'/enter', $actor);
            $this->assertContains($status, [401, 404]);
        }

        // Today's door draw exists for everyone (its day has begun) but its door
        // content is still not in the calendar until opened.
        [$status] = $this->call('GET', '/api/giveaways/'.$today->slug, 10);
        $this->assertSame(200, $status);

        // The reminder names the calendar and the day, nothing behind the door.
        [, $alerts, $raw] = $this->call('GET', '/api/notifications', 10);
        $this->assertStringContainsString('giveawayDoorOpen', $raw);
        $this->assertStringContainsString('Winter Doors', $raw);

        // The builder still sees everything.
        [, $managed, $raw] = $this->call('GET', "/api/giveaway-calendars/$id", 1);
        $this->assertStringContainsString('FUTURE-PRIZE-5', $raw);
        $this->assertCount(5, $managed['data']['attributes']['editor']);
    }

    #[Test]
    public function today_is_free_the_past_needs_a_joker_and_the_future_is_shut()
    {
        $id = $this->build()['id'];

        [$status, $body] = $this->open(10, $id, 4);
        $this->assertSame(422, $status, 'a future door opened');

        [$status, $body] = $this->open(10, $id, 3);
        $this->assertSame(200, $status, json_encode($body));
        $door = $this->door($body['data'], 3);
        $this->assertSame('opened', $door['state']);
        $this->assertSame('Mug of today', $door['title']);
        $this->assertStringContainsString('TODAY-REVEAL-BODY', $door['html']);
        $this->assertTrue($door['draw']['entered'], "today's prize door enters its draw");
        $this->assertSame(1, $body['data']['attributes']['tickets']);
        $this->assertSame('locked', $this->door($body['data'], 4)['state']);
        $this->assertArrayNotHasKey('html', $this->door($body['data'], 4));

        // Opening it again changes nothing.
        [, $body] = $this->open(10, $id, 3);
        $this->assertSame(1, $body['data']['attributes']['tickets']);

        [$status] = $this->open(10, $id, 2);
        $this->assertSame(422, $status, 'a missed door opened without a joker');

        [$status, $body] = $this->open(10, $id, 2, true);
        $this->assertSame(200, $status);
        $this->assertSame(2, $body['data']['attributes']['tickets']);
        $this->assertSame(1, $body['data']['attributes']['jokersUsed']);
        $this->assertTrue($this->door($body['data'], 2)['joker']);
        $this->assertFalse($this->door($body['data'], 2)['draw']['entered'], 'a joker never enters a past draw');

        // One joker by default.
        [$status] = $this->open(10, $id, 1, true);
        $this->assertSame(422, $status, 'a second joker was spent');

        // A joker on today's door is not spent.
        [, $body] = $this->open(11, $id, 3, true);
        $this->assertSame(0, $body['data']['attributes']['jokersUsed']);

        $grand = Giveaway::query()->where('calendar_id', $id)->where('calendar_role', 'grand')->firstOrFail();
        $this->assertSame(2, (int) GiveawayEntry::query()->where('giveaway_id', $grand->id)->where('user_id', 10)->value('entries'));
        $this->assertSame(1, (int) GiveawayEntry::query()->where('giveaway_id', $grand->id)->where('user_id', 11)->value('entries'));
    }

    #[Test]
    public function a_calendars_draws_are_entered_only_through_its_doors()
    {
        $id = $this->build()['id'];
        $grand = Giveaway::query()->where('calendar_id', $id)->where('calendar_role', 'grand')->firstOrFail();
        $today = Giveaway::query()->where('calendar_id', $id)->where('prize', 'A mug')->firstOrFail();

        foreach ([$grand, $today] as $giveaway) {
            [$status] = $this->call('POST', '/api/giveaways/'.$giveaway->id.'/enter', 10);
            $this->assertSame(422, $status);
            // Nor edited, drawn early or deleted one by one, even by the admin.
            [$status] = $this->call('PATCH', '/api/giveaways/'.$giveaway->id, 1, ['data' => ['attributes' => ['prize' => 'Swapped']]]);
            $this->assertSame(403, $status);
            [$status] = $this->call('POST', '/api/giveaways/'.$giveaway->id.'/draw', 1);
            $this->assertSame(403, $status);
            [$status] = $this->call('DELETE', '/api/giveaways/'.$giveaway->id, 1);
            $this->assertSame(403, $status);
        }

        // Unlisted on the giveaways page.
        [, $list] = $this->call('GET', '/api/giveaways', 10);
        $this->assertNotContains((int) $grand->id, array_column($list['data'], 'id'));
    }

    #[Test]
    public function guests_and_members_without_the_permission_cannot_open_or_build()
    {
        $id = $this->build()['id'];

        [$status] = $this->open(0, $id, 3);
        $this->assertSame(401, $status);

        $this->database()->table('group_permission')->where('permission', 'giveaways.enter')->delete();
        [$status] = $this->open(10, $id, 3);
        $this->assertSame(403, $status);

        [$status] = $this->call('POST', '/api/giveaway-calendars', 10, ['data' => ['type' => 'giveaway-calendars', 'attributes' => ['title' => 'Mine']]]);
        $this->assertSame(403, $status);
        [$status] = $this->call('DELETE', "/api/giveaway-calendars/$id", 10);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function an_opened_doors_terms_and_the_dates_are_fixed_once_it_opens()
    {
        $id = $this->build()['id'];
        $patch = fn (array $attributes) => $this->call('PATCH', "/api/giveaway-calendars/$id", 1, ['data' => ['type' => 'giveaway-calendars', 'id' => $id, 'attributes' => $attributes]]);

        [$status] = $patch(['startsOn' => Carbon::now('UTC')->format('Y-m-d')]);
        $this->assertSame(422, $status, 'the dates moved after the first door');
        [$status] = $patch(['doors' => [['day' => 3, 'kind' => 'prize', 'title' => 'Mug of today', 'prize' => 'A cheaper mug']]]);
        $this->assertSame(422, $status, "an opened door's prize changed");
        [$status] = $patch(['publish' => false]);
        $this->assertSame(422, $status);

        // A door that hasn't opened can still change.
        [$status, $body] = $patch(['doors' => [['day' => 5, 'kind' => 'reveal', 'title' => 'Five', 'body' => 'Changed']]]);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(0, Giveaway::query()->where('calendar_id', $id)->where('prize', 'FUTURE-PRIZE-5')->count(), 'its draw went with it');
    }

    #[Test]
    public function the_reminder_is_opt_in_and_skips_members_who_already_opened_the_door()
    {
        $id = (int) $this->build()['id'];
        $this->call('POST', "/api/giveaway-calendars/$id/remind", 10, ['on' => true]);
        $this->call('POST', "/api/giveaway-calendars/$id/remind", 11, ['on' => true]);
        $this->open(10, (string) $id, 3);

        $this->app()->getContainer()->call([new SendDoorReminders($id, 3), 'handle']);
        // A job running for a day that has passed sends nothing.
        $this->app()->getContainer()->call([new SendDoorReminders($id, 2), 'handle']);

        $sent = $this->database()->table('notifications')->where('type', 'giveawayDoorOpen')->pluck('user_id')->map(fn ($u) => (int) $u)->all();
        $this->assertSame([11], $sent);

        // A second calendar's alert too: the alerts list must not pay per calendar.
        $other = (int) $this->build(['title' => 'Other doors'])['id'];
        $this->call('POST', "/api/giveaway-calendars/$other/remind", 11, ['on' => true]);
        $this->app()->getContainer()->call([new SendDoorReminders($other, 3), 'handle']);
        [$status, $alerts] = $this->call('GET', '/api/notifications', 11);
        $this->assertSame(200, $status);
        $this->assertCount(2, array_filter($alerts['data'], fn ($n) => $n['attributes']['contentType'] === 'giveawayDoorOpen'));
        $this->assertSame(3, $this->app()->getContainer()->make(Clock::class)->today(Calendar::query()->findOrFail($id)));
    }

    #[Test]
    public function deleting_a_calendar_removes_its_giveaways()
    {
        $id = $this->build()['id'];
        $this->open(10, $id, 3);

        [$status] = $this->call('DELETE', "/api/giveaway-calendars/$id", 1);
        $this->assertSame(204, $status);
        $this->assertSame(0, Giveaway::query()->where('calendar_id', $id)->count());
        $this->assertSame(0, $this->database()->table('giveaway_calendar_opens')->count());
    }

    #[Test]
    public function the_calendar_list_costs_the_same_for_one_calendar_as_for_ten()
    {
        $count = function () {
            $db = $this->database();
            $db->flushQueryLog();
            $db->enableQueryLog();
            [$status] = $this->call('GET', '/api/giveaway-calendars', 10);
            $this->assertSame(200, $status);
            $n = count($db->getQueryLog());
            $db->disableQueryLog();

            return $n;
        };

        $first = $this->build()['id'];
        $this->open(10, $first, 3);
        $this->call('POST', "/api/giveaway-calendars/$first/remind", 10, ['on' => true]);
        $one = $count();

        for ($i = 2; $i <= 10; $i++) {
            $id = $this->build(['title' => "Calendar $i"])['id'];
            $this->open(10, $id, 3);
        }
        $ten = $count();

        $this->assertSame($one, $ten, "listing 10 calendars ran $ten queries, 1 ran $one");
    }
}
