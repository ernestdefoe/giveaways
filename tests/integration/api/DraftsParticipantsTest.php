<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * 0.7.0: drafts (and publishing them with a post), per-winner claim lines,
 * the participants list, and formatted descriptions.
 */
class DraftsParticipantsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-giveaways');

        $users = [$this->normalUser()];
        $users[] = ['id' => 3, 'username' => 'host', 'email' => 'host@machine.local', 'is_email_confirmed' => 1];
        for ($u = 10; $u < 14; $u++) {
            $users[] = ['id' => $u, 'username' => "member$u", 'email' => "member$u@machine.local", 'is_email_confirmed' => 1];
        }

        $entries = [];
        foreach ([10, 11, 12, 13] as $i => $u) {
            $entries[] = ['giveaway_id' => 5, 'user_id' => $u, 'entries' => 1, 'created_at' => Carbon::now()->subHours(10 - $i)];
        }

        $this->prepareDatabase([
            User::class => $users,
            // Host 3 is a member of group 5 (not a moderator), which may create.
            'groups' => [['id' => 5, 'name_singular' => 'Host', 'name_plural' => 'Hosts']],
            'group_user' => [['user_id' => 3, 'group_id' => 5], ['user_id' => 13, 'group_id' => 4]],
            'group_permission' => [['permission' => 'giveaways.create', 'group_id' => 5]],
            'giveaways' => [
                // Ended with three winners and two per-winner lines.
                ['id' => 5, 'user_id' => 3, 'title' => 'Codes', 'slug' => 'codes', 'prize' => '100GB', 'status' => 'active', 'winner_count' => 3,
                    'ends_at' => Carbon::now()->subMinute(), 'description' => "Line one\nLine two",
                    'settings' => json_encode(['claim_instructions' => 'Shared note', 'claim_lines' => "CODE-ALPHA\n\nCODE-BRAVO"])],
                // A draft of the host's.
                ['id' => 6, 'user_id' => 3, 'title' => 'Secret', 'slug' => 'secret', 'prize' => 'Thing', 'status' => 'draft',
                    'ends_at' => Carbon::now()->addWeek()],
            ],
            'giveaway_entries' => $entries,
        ]);
    }

    private function call(string $method, string $path, ?int $actor = null, array $attributes = [], array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($attributes) {
            $options['json'] = ['data' => ['attributes' => $attributes]];
        }

        $request = $this->request($method, $path, $options);
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        if (! $actor && $method !== 'GET') {
            $request = $this->requestWithCsrfToken($request);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function a_draft_is_seen_only_by_its_maker()
    {
        foreach ([null, 2, 1] as $actor) {
            [$status, $body] = $this->call('GET', '/api/giveaways', $actor);
            $this->assertNotContains(6, array_column($body['data'], 'id'), "Listed for actor $actor");

            [$status] = $this->call('GET', '/api/giveaways/secret', $actor);
            $this->assertSame(404, $status, "Shown to actor $actor (an admin too)");
        }

        [$status] = $this->call('POST', '/api/giveaways/6/enter', 2);
        $this->assertSame(404, $status);

        [$status, $body] = $this->call('GET', '/api/giveaways', 3);
        $this->assertContains(6, array_column($body['data'], 'id'));

        [$status, $body] = $this->call('GET', '/api/giveaways/secret', 3);
        $this->assertSame(200, $status);
        $this->assertSame('draft', $body['data']['status']);
    }

    #[Test]
    public function a_draft_is_created_and_published_by_hand()
    {
        [$status, $body] = $this->call('POST', '/api/giveaways', 3, [
            'title' => 'Later', 'prize' => 'Mug', 'endsAt' => Carbon::now()->addDay()->toIso8601String(), 'draft' => true,
        ]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame('draft', $body['data']['status']);

        [$status, $body] = $this->call('PATCH', '/api/giveaways/'.$body['data']['id'], 3, ['publish' => true]);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('active', $body['data']['status']);
    }

    #[Test]
    public function posting_the_link_publishes_the_draft_but_only_the_makers_post_does()
    {
        $link = 'https://localhost/giveaways/secret';

        // Someone else posting the link does nothing.
        [$status, $body] = $this->postDiscussion(1, "Look\n\n$link");
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame('draft', $this->database()->table('giveaways')->where('id', 6)->value('status'));

        $this->database()->table('group_permission')->insert(['permission' => 'startDiscussion', 'group_id' => 5]);
        [$status, $body] = $this->postDiscussion(3, "My giveaway\n\n$link");
        $this->assertSame(201, $status, json_encode($body));

        $row = $this->database()->table('giveaways')->where('id', 6)->first();
        $this->assertSame('active', $row->status);
        $this->assertSame((int) $body['data']['id'], (int) $row->discussion_id);

        [, $shown] = $this->call('GET', '/api/giveaways/secret');
        $this->assertSame((int) $row->discussion_id, $shown['data']['discussionId'], 'Links back to the discussion');

        // A discussion the viewer can't see is not pointed to.
        $this->database()->table('discussions')->where('id', $row->discussion_id)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);
        [, $shown] = $this->call('GET', '/api/giveaways/secret');
        $this->assertNull($shown['data']['discussionId']);
    }

    private function postDiscussion(int $actor, string $content): array
    {
        $response = $this->send($this->request('POST', '/api/discussions', [
            'authenticatedAs' => $actor,
            'json' => ['data' => ['type' => 'discussions', 'attributes' => ['title' => 'A giveaway', 'content' => $content]]],
        ]));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function each_winner_sees_only_their_own_line_after_claiming()
    {
        [$status] = $this->call('POST', '/api/giveaways/5/draw', 3);
        $this->assertSame(200, $status);

        $winners = $this->database()->table('giveaway_winners')->where('giveaway_id', 5)->orderBy('position')->pluck('user_id', 'position')->all();
        $this->assertCount(3, $winners);

        $expected = [1 => 'CODE-ALPHA', 2 => 'CODE-BRAVO', 3 => null];
        foreach ($winners as $pos => $uid) {
            [, $before] = $this->call('GET', '/api/giveaways/codes', (int) $uid);
            $this->assertNull($before['data']['myClaimLine'], 'Nothing before claiming');
            $this->assertNull($before['data']['claimInstructions']);
            $this->assertStringNotContainsString('CODE-', json_encode($before));

            [$status, $after] = $this->call('POST', '/api/giveaways/5/claim', (int) $uid);
            $this->assertSame(200, $status, json_encode($after));
            $this->assertSame($expected[$pos], $after['data']['myClaimLine']);
            $this->assertSame('Shared note', $after['data']['claimInstructions']);
            $this->assertNull($after['data']['claimLines']);

            // No other winner's line, anywhere in the response.
            foreach ($expected as $p => $line) {
                if ($line && $p !== $pos) {
                    $this->assertStringNotContainsString($line, json_encode($after));
                }
            }
        }

        [, $host] = $this->call('GET', '/api/giveaways/codes', 3);
        $this->assertSame("CODE-ALPHA\n\nCODE-BRAVO", $host['data']['claimLines'], 'The host edits the list');
    }

    #[Test]
    public function participants_are_listed_for_the_host_and_the_permission_only()
    {
        [$status] = $this->call('GET', '/api/giveaways/5/entrants');
        $this->assertSame(403, $status, 'Guest');

        [$status] = $this->call('GET', '/api/giveaways/5/entrants', 10);
        $this->assertSame(403, $status, 'Plain member');

        [, $show] = $this->call('GET', '/api/giveaways/codes', 10);
        $this->assertFalse($show['data']['canViewParticipants']);

        foreach ([3, 13, 1] as $actor) {
            [$status, $body] = $this->call('GET', '/api/giveaways/5/entrants', $actor);
            $this->assertSame(200, $status, "Actor $actor: ".json_encode($body));
            $this->assertSame(4, $body['meta']['total']);
            $this->assertSame([10, 11, 12, 13], array_column(array_column($body['data'], 'user'), 'id'), 'Oldest first');
            $this->assertNotNull($body['data'][0]['joinedAt']);
        }

        [, $body] = $this->call('GET', '/api/giveaways/5/entrants', 3, [], ['page' => ['offset' => 3]]);
        $this->assertSame([13], array_column(array_column($body['data'], 'user'), 'id'));
    }

    #[Test]
    public function the_description_is_formatted_safely_and_old_text_still_shows()
    {
        [, $old] = $this->call('GET', '/api/giveaways/codes', 2);
        $this->assertStringContainsString('Line one', $old['data']['descriptionHtml']);
        $this->assertStringContainsString('Line two', $old['data']['descriptionHtml']);
        $this->assertArrayNotHasKey('description', $old['data'], 'The source is only for editors');

        [$status, $body] = $this->call('PATCH', '/api/giveaways/5', 3, [
            'description' => "See https://example.com <script>alert(1)</script> <img src=x onerror=alert(1)>",
        ]);
        $this->assertSame(200, $status, json_encode($body));

        $html = $body['data']['descriptionHtml'];
        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringStartsWith('See https://example.com', $body['data']['description'], 'The editor gets back what was typed');
    }
}
