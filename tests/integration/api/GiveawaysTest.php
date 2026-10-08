<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class GiveawaysTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-giveaways');

        $users = [$this->normalUser()];
        $giveaways = [];
        $entries = [];

        // A host who may create giveaways, and members to enter them.
        $users[] = ['id' => 3, 'username' => 'host', 'email' => 'host@machine.local', 'is_email_confirmed' => 1];
        for ($u = 10; $u < 16; $u++) {
            $users[] = ['id' => $u, 'username' => "member$u", 'email' => "member$u@machine.local", 'is_email_confirmed' => 1];
        }

        // 1: running, hosted by 3, with a skill question. 2: ended, not drawn.
        $giveaways[] = ['id' => 1, 'user_id' => 3, 'title' => 'Running', 'slug' => 'running', 'prize' => 'A mug', 'status' => 'active', 'ends_at' => Carbon::now()->addWeek(), 'settings' => json_encode(['skill_question' => '2+2?', 'skill_answer' => 'four-secret']), 'category_id' => 1];
        $giveaways[] = ['id' => 2, 'user_id' => 3, 'title' => 'Ended', 'slug' => 'ended', 'prize' => 'A hat', 'status' => 'active', 'ends_at' => Carbon::now()->subDay(), 'winner_count' => 1];

        // Enough more on the list that a query per giveaway shows as an N+1.
        for ($g = 10; $g < 16; $g++) {
            $giveaways[] = ['id' => $g, 'user_id' => $g, 'title' => "Giveaway $g", 'slug' => "giveaway-$g", 'prize' => 'Stuff', 'status' => 'active', 'ends_at' => Carbon::now()->addDays($g), 'category_id' => 1];
            $entries[] = ['giveaway_id' => $g, 'user_id' => 2, 'entries' => 2];
        }

        foreach ([10, 11, 12] as $u) {
            $entries[] = ['giveaway_id' => 2, 'user_id' => $u, 'entries' => 1];
        }

        $this->prepareDatabase([
            User::class => $users,
            'group_user' => [['user_id' => 3, 'group_id' => 4]],
            'group_permission' => [['permission' => 'giveaways.create', 'group_id' => 4]],
            'giveaway_categories' => [['id' => 1, 'name' => 'Swag', 'slug' => 'swag', 'color' => '#123456', 'position' => 0]],
            'giveaways' => $giveaways,
            'giveaway_entries' => $entries,
        ]);
    }

    private function call(string $method, string $path, ?int $actor = null, array $attributes = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];

        if ($attributes) {
            $options['json'] = ['data' => ['attributes' => $attributes]];
        }

        $request = $this->request($method, $path, $options);

        // A guest's write is refused for its missing CSRF token before the
        // controller runs, which would hide whether the controller checks.
        if (! $actor && $method !== 'GET') {
            $request = $this->requestWithCsrfToken($request);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function anyone_can_list_and_read_giveaways()
    {
        [$status, $body] = $this->call('GET', '/api/giveaways');
        $this->assertSame(200, $status, json_encode($body));
        $this->assertCount(8, $body['data']);
        $this->assertFalse($body['meta']['canCreate']);

        $listed = array_column($body['data'], null, 'id');
        $this->assertSame(1, $listed[10]['entrantCount']);
        $this->assertSame(3, $listed[2]['entrantCount']);
        $this->assertSame('Swag', $listed[10]['category']['name']);

        [$status, $body] = $this->call('GET', '/api/giveaways/running');
        $this->assertSame(200, $status);
        $this->assertSame(1, $body['data']['id']);

        [$status] = $this->call('GET', '/api/giveaways/999');
        $this->assertSame(404, $status);
    }

    #[Test]
    public function the_skill_answer_goes_only_to_those_who_manage_the_giveaway()
    {
        [, $body] = $this->call('GET', '/api/giveaways/1', 2);
        $this->assertSame('2+2?', $body['data']['skillQuestion']);
        $this->assertNull($body['data']['skillAnswer']);
        $this->assertStringNotContainsString('four-secret', json_encode($body));

        [, $body] = $this->call('GET', '/api/giveaways/1', 3);
        $this->assertSame('four-secret', $body['data']['skillAnswer'], 'The host edits it');
    }

    #[Test]
    public function a_member_enters_once_and_a_guest_cannot()
    {
        [$status] = $this->call('POST', '/api/giveaways/1/enter');
        $this->assertSame(401, $status);

        [$status, $body] = $this->call('POST', '/api/giveaways/1/enter', 2);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(1, $body['data']['myEntries']);

        [$status, $body] = $this->call('POST', '/api/giveaways/1/enter', 2);
        $this->assertSame(200, $status, 'Entering again is not an error');
        $this->assertSame(1, $body['data']['myEntries']);
        $this->assertSame(1, $this->database()->table('giveaway_entries')->where('giveaway_id', 1)->count(), 'Entering twice is one entry');
    }

    #[Test]
    public function the_host_cannot_enter_their_own_giveaway_nor_anyone_an_ended_one()
    {
        // The host's group lacks giveaways.enter only if the default grant is
        // missing; grant it here so the refusal tested is the host rule.
        $this->prepareDatabase(['group_permission' => [['permission' => 'giveaways.enter', 'group_id' => 4]]]);

        [$status] = $this->call('POST', '/api/giveaways/1/enter', 3);
        $this->assertSame(422, $status);

        [$status] = $this->call('POST', '/api/giveaways/2/enter', 2);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function only_a_host_or_manager_creates_and_edits()
    {
        $new = ['title' => 'New', 'prize' => 'Prize', 'endsAt' => Carbon::now()->addWeek()->toIso8601String()];

        [$status] = $this->call('POST', '/api/giveaways', 2, $new);
        $this->assertSame(403, $status, 'A member without giveaways.create');

        [$status, $body] = $this->call('POST', '/api/giveaways', 3, $new);
        $this->assertSame(201, $status, json_encode($body));

        [$status] = $this->call('PATCH', '/api/giveaways/1', 2, ['title' => 'Mine now']);
        $this->assertSame(403, $status);
        $this->assertSame('Running', $this->database()->table('giveaways')->where('id', 1)->value('title'));

        [$status] = $this->call('PATCH', '/api/giveaways/1', 1, ['title' => 'Renamed']);
        $this->assertSame(200, $status);
        $this->assertSame('Renamed', $this->database()->table('giveaways')->where('id', 1)->value('title'));
    }

    #[Test]
    public function an_ended_giveaway_is_drawn_and_its_winner_claims()
    {
        [$status] = $this->call('POST', '/api/giveaways/2/draw', 2);
        $this->assertSame(403, $status);

        [$status, $body] = $this->call('POST', '/api/giveaways/2/draw', 3);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('drawn', $body['data']['status']);

        $winner = (int) $this->database()->table('giveaway_winners')->where('giveaway_id', 2)->value('user_id');
        $this->assertContains($winner, [10, 11, 12], 'The winner is one of the entrants');

        [$status] = $this->call('POST', '/api/giveaways/2/claim', 2);
        $this->assertSame(422, $status, 'Only the winner claims');

        [$status, $body] = $this->call('POST', '/api/giveaways/2/claim', $winner);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertNotNull($this->database()->table('giveaway_winners')->where('giveaway_id', 2)->value('claimed_at'));
    }

    #[Test]
    public function the_host_cannot_draw_a_running_giveaway_early()
    {
        [$status] = $this->call('POST', '/api/giveaways/1/draw', 3);

        $this->assertSame(422, $status);
        $this->assertSame('active', $this->database()->table('giveaways')->where('id', 1)->value('status'));
    }

    #[Test]
    public function only_a_manager_deletes_and_manages_categories()
    {
        [$status] = $this->call('DELETE', '/api/giveaways/1', 2);
        $this->assertSame(403, $status);

        [$status] = $this->call('POST', '/api/giveaway-categories', 3, ['name' => 'Games']);
        $this->assertSame(403, $status, 'Creating giveaways is not managing them');

        [$status, $body] = $this->call('POST', '/api/giveaway-categories', 1, ['name' => 'Games']);
        $this->assertSame(201, $status, json_encode($body));

        [$status, $body] = $this->call('GET', '/api/giveaway-categories');
        $this->assertSame(200, $status);
        $this->assertSame(['Games', 'Swag'], array_column($body['data'], 'name'));

        [$status] = $this->call('DELETE', '/api/giveaways/1', 1);
        $this->assertContains($status, [200, 204]);
        $this->assertNull($this->database()->table('giveaways')->where('id', 1)->first());
    }

    #[Test]
    public function the_forum_shows_the_nav_link_until_it_is_switched_off()
    {
        $forum = json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'];

        $this->assertTrue($forum['giveawaysShowNav']);
    }

    #[Test]
    public function the_nav_link_can_be_switched_off()
    {
        $this->setting('ernestdefoe-giveaways.show_nav', '0');

        $forum = json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'];

        $this->assertFalse($forum['giveawaysShowNav']);
    }
}
