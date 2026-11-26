<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * The calendar beside Festoon and Secret Santa, with both really installed
 * and enabled: the optional adapters are only bound then, so only this
 * proves they load (a wrong class name in one took a forum's API down).
 *
 * Festoon is checked through the container, not the API: enabling it inside
 * the test forum publishes its stylesheets mid-boot, which builds the API
 * router before Giveaways has added its routes (a test-harness effect; on a
 * real forum extensions are enabled long before a request).
 */
class AdventPairingTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-secret-santa', 'ernestdefoe-giveaways');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 10, 'username' => 'elf', 'email' => 'elf@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear()],
            ],
            'group_user' => [['user_id' => 10, 'group_id' => 3]],
            'group_permission' => [
                ['permission' => 'giveaways.enter', 'group_id' => 3],
                ['permission' => 'secret-santa.view', 'group_id' => 3],
            ],
            'secret_santa_exchanges' => [
                ['id' => 7, 'title' => 'Forum gift swap', 'status' => 'open', 'kind' => 'physical', 'signup_ends_at' => Carbon::now()->addWeek()],
            ],
        ]);
    }

    private function call(string $method, string $path, int $actor, ?array $json = null): array
    {
        $response = $this->send($this->request($method, $path, ['authenticatedAs' => $actor] + ($json !== null ? ['json' => $json] : [])));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function a_door_can_feature_an_exchange()
    {
        [$status, $body] = $this->call('POST', '/api/giveaway-calendars', 1, ['data' => ['type' => 'giveaway-calendars', 'attributes' => [
            'title' => 'Paired',
            'startsOn' => Carbon::now('UTC')->format('Y-m-d'),
            'days' => 3,
            'timezone' => 'UTC',
            'grandPrize' => 'A console',
            'publish' => true,
            'doors' => [['day' => 1, 'kind' => 'exchange', 'title' => 'Swap', 'exchangeId' => 7]],
        ]]]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame([['id' => 7, 'title' => 'Forum gift swap']], $body['data']['attributes']['exchangeChoices']);

        [$status, $body] = $this->call('POST', '/api/giveaway-calendars/'.$body['data']['id'].'/open', 10, ['day' => 1]);
        $this->assertSame(200, $status, json_encode($body));
        $exchange = $body['data']['attributes']['doors'][0]['exchange'];
        $this->assertSame('Forum gift swap', $exchange['title']);
        $this->assertTrue($exchange['signupsOpen']);
    }

    #[Test]
    public function a_member_who_cannot_see_secret_santa_sees_the_door_but_not_the_exchange()
    {
        // Revoked before the member's first request: permissions are cached per user for the run.
        $this->database()->table('group_permission')->where('permission', 'secret-santa.view')->delete();
        $id = $this->exchangeCalendar();

        [$status, $body] = $this->call('POST', "/api/giveaway-calendars/$id/open", 10, ['day' => 1]);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('opened', $body['data']['attributes']['doors'][0]['state']);
        $this->assertNull($body['data']['attributes']['doors'][0]['exchange']);
    }

    private function exchangeCalendar(): string
    {
        [, $body] = $this->call('POST', '/api/giveaway-calendars', 1, ['data' => ['type' => 'giveaway-calendars', 'attributes' => [
            'title' => 'Paired',
            'startsOn' => Carbon::now('UTC')->format('Y-m-d'),
            'days' => 3,
            'timezone' => 'UTC',
            'grandPrize' => 'A console',
            'publish' => true,
            'doors' => [['day' => 1, 'kind' => 'exchange', 'title' => 'Swap', 'exchangeId' => 7]],
        ]]]);

        return $body['data']['id'];
    }

    #[Test]
    public function an_exchange_door_needs_an_exchange_that_exists()
    {
        [$status, $body] = $this->call('POST', '/api/giveaway-calendars', 1, ['data' => ['type' => 'giveaway-calendars', 'attributes' => [
            'title' => 'Broken',
            'startsOn' => Carbon::now()->addMonth()->format('Y-m-d'),
            'grandPrize' => 'A console',
            'doors' => [['day' => 2, 'kind' => 'exchange', 'exchangeId' => 999]],
        ]]]);
        $this->assertSame(422, $status, json_encode($body));
    }
}
