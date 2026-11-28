<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Gdpr\SponsorConsents;
use ErnestDefoe\Giveaways\Sponsors\OptIn;
use ErnestDefoe\Giveaways\Sponsors\RetentionCommand;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Sponsors and their privacy rules: the opt-in is never ticked for anyone,
 * the export holds only members who opted in (and still do), the report has
 * counts only behind an unguessable link, and every erasure path works.
 */
class SponsorsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use FlattensJsonApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-giveaways');

        $users = [$this->normalUser()];
        foreach ([10 => 'elf', 11 => 'reindeer', 12 => 'snowman', 13 => 'unconfirmed'] as $id => $name) {
            // Reindeer's address would run as a formula in a spreadsheet.
            $email = $id === 11 ? '=HYPERLINK("http://evil")@machine.local' : "$name@machine.local";
            $users[] = ['id' => $id, 'username' => $name, 'email' => $email, 'is_email_confirmed' => $id === 13 ? 0 : 1, 'joined_at' => Carbon::now()->subYear()];
        }

        $this->prepareDatabase([
            User::class => $users,
            'group_user' => array_map(fn ($id) => ['user_id' => $id, 'group_id' => 3], [10, 11, 12, 13]),
            'group_permission' => [['permission' => 'giveaways.enter', 'group_id' => 3], ['permission' => 'giveaways.create', 'group_id' => 3]],
        ]);
    }

    private function call(string $method, string $path, ?int $actor, ?array $json = null, array $query = []): array
    {
        $request = $this->request($method, $path, ($actor ? ['authenticatedAs' => $actor] : []) + ($json !== null ? ['json' => $json] : []));
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        if (! $actor && $method !== 'GET') {
            $request = $this->requestWithCsrfToken($request);
        }
        $response = $this->send($request);

        return [$response->getStatusCode(), $this->flat(json_decode((string) $response->getBody(), true)), $response];
    }

    private function sponsor(array $attributes = []): array
    {
        [$status, $body] = $this->call('POST', '/api/giveaway-sponsors', 1, ['data' => ['type' => 'giveaway-sponsors', 'attributes' => $attributes + [
            'name' => 'North Pole Games', 'websiteUrl' => 'https://northpole.example', 'privacyUrl' => 'https://northpole.example/privacy',
        ]]]);
        $this->assertSame(201, $status, json_encode($body));

        return $body['data'];
    }

    private function giveaway(int $sponsorId, array $attributes = []): int
    {
        [$status, $body] = $this->call('POST', '/api/giveaways', 1, ['data' => ['type' => 'giveaways', 'attributes' => $attributes + [
            'title' => 'Sleigh bells', 'prize' => 'A sled', 'endsAt' => Carbon::now()->addWeek()->toIso8601String(), 'sponsorId' => $sponsorId,
        ]]]);
        $this->assertSame(201, $status, json_encode($body));

        return (int) $body['data']['id'];
    }

    private function consent(int $actor, int $giveaway, mixed $on): int
    {
        return $this->call('POST', '/api/giveaway-sponsors/consent', $actor, ['campaign' => 'giveaway', 'campaignId' => $giveaway, 'on' => $on])[0];
    }

    #[Test]
    public function only_managers_make_sponsors_and_both_links_must_be_real()
    {
        [$status] = $this->call('POST', '/api/giveaway-sponsors', 10, ['data' => ['type' => 'giveaway-sponsors', 'attributes' => ['name' => 'X', 'websiteUrl' => 'https://x.example', 'privacyUrl' => 'https://x.example/p']]]);
        $this->assertSame(403, $status);

        foreach ([['privacyUrl' => ''], ['websiteUrl' => "\x01javascript:alert(1)"], ['privacyUrl' => 'ftp://x.example']] as $bad) {
            [$status] = $this->call('POST', '/api/giveaway-sponsors', 1, ['data' => ['type' => 'giveaway-sponsors', 'attributes' => $bad + ['name' => 'X', 'websiteUrl' => 'https://x.example', 'privacyUrl' => 'https://x.example/p']]]);
            $this->assertSame(422, $status, json_encode($bad));
        }

        // A host who isn't a manager can't put a sponsor's name on their giveaway.
        $sponsor = $this->sponsor();
        [, $body] = $this->call('POST', '/api/giveaways', 10, ['data' => ['type' => 'giveaways', 'attributes' => ['title' => 'Mine', 'prize' => 'A hat', 'endsAt' => Carbon::now()->addWeek()->toIso8601String(), 'sponsorId' => (int) $sponsor['id']]]]);
        $this->assertNull($body['data']['sponsor']);
    }

    #[Test]
    public function the_opt_in_is_never_ticked_for_anyone()
    {
        $id = $this->giveaway((int) $this->sponsor()['id']);

        // Entering shares nothing; nor does anything but an explicit true.
        $this->call('POST', "/api/giveaways/$id/enter", 10);
        foreach ([null, 'true', 1, 'on', 'yes', false] as $on) {
            $this->consent(10, $id, $on);
            $this->assertSame(0, OptIn::query()->count(), 'recorded for on = '.json_encode($on));
        }
        [, $body] = $this->call('GET', "/api/giveaways/$id", 10);
        $this->assertFalse($body['data']['myConsent']);
        $this->assertSame('North Pole Games', $body['data']['sponsor']['name']);

        // Ticked: the sentence they agreed to is kept, naming who, what, why and the policy.
        $this->assertSame(200, $this->consent(10, $id, true));
        // The sentence the forum showed, as its translator renders it...
        $optIn = OptIn::query()->firstOrFail();
        $giveaway = \ErnestDefoe\Giveaways\Giveaway::query()->findOrFail($id);
        $this->assertSame($this->app()->getContainer()->make(\ErnestDefoe\Giveaways\Sponsors\Consent::class)->sentence($optIn->sponsor, $giveaway), $optIn->consent_text);
        // ...names who gets what and why, and links the policy (the English wording, formatted as Flarum does).
        preg_match('/^      consent: "(.*)"$/m', (string) file_get_contents(__DIR__.'/../../../locale/en.yml'), $m);
        $sentence = (string) msgfmt_format_message('en', $m[1], ['forum' => 'Our forum', 'sponsor' => 'North Pole Games', 'campaign' => 'Sleigh bells', 'privacy' => 'https://northpole.example/privacy']);
        foreach (['shares my email address and username with North Pole Games', 'contact me about Sleigh bells', 'optional', "doesn't affect my entry", 'withdraw', 'https://northpole.example/privacy'] as $part) {
            $this->assertStringContainsString($part, $sentence);
        }
        $this->assertTrue($this->call('GET', "/api/giveaways/$id", 10)[1]['data']['myConsent']);
        $this->assertFalse($this->call('GET', "/api/giveaways/$id", 11)[1]['data']['myConsent']);

        // An unconfirmed address is never shared; a guest can't tick it.
        $this->assertSame(422, $this->consent(13, $id, true));
        $this->assertSame(401, $this->consent(0, $id, true));
    }

    #[Test]
    public function the_box_shows_word_for_word_what_is_stored_on_giveaways_and_calendars()
    {
        $sponsorId = (int) $this->sponsor()['id'];
        $id = $this->giveaway($sponsorId);

        [, $page] = $this->call('GET', "/api/giveaways/$id", 10);
        $shown = $page['data']['sponsorConsentText'];
        $this->assertNotEmpty($shown);
        $this->consent(10, $id, true);
        $this->assertSame($shown, OptIn::query()->where('campaign', OptIn::GIVEAWAY)->value('consent_text'));
        // Not in lists, and not for guests (they can't tick it).
        $this->assertArrayNotHasKey('sponsorConsentText', $this->call('GET', '/api/giveaways', 10)[1]['data'][0]);
        $this->assertArrayNotHasKey('sponsorConsentText', $this->call('GET', "/api/giveaways/$id", null)[1]['data']);

        [$status, $body] = $this->call('POST', '/api/giveaway-calendars', 1, ['data' => ['type' => 'giveaway-calendars', 'attributes' => [
            'title' => 'Winter Doors', 'startsOn' => Carbon::now('UTC')->format('Y-m-d'), 'days' => 2, 'timezone' => 'UTC',
            'grandPrize' => 'A console', 'grandWinners' => 1, 'publish' => true,
            'doors' => [['day' => 1, 'kind' => 'reveal', 'title' => 'One', 'body' => 'x'], ['day' => 2, 'kind' => 'reveal', 'title' => 'Two', 'body' => 'y']],
        ]]]);
        $this->assertSame(201, $status, json_encode($body));
        $calendar = (int) $body['data']['id'];
        // A sponsor added later, on its own (as the showcase seed does): nothing else changes.
        [$status] = $this->call('PATCH', "/api/giveaway-calendars/$calendar", 1, ['data' => ['type' => 'giveaway-calendars', 'id' => (string) $calendar, 'attributes' => ['sponsorId' => $sponsorId]]]);
        $this->assertSame(200, $status);
        $this->assertSame(2, (int) $this->database()->table('giveaway_calendars')->where('id', $calendar)->value('days'));

        [, $page] = $this->call('GET', "/api/giveaway-calendars/$calendar", 10);
        $this->assertSame('North Pole Games', $page['data']['sponsor']['name']);
        $this->assertFalse($page['data']['myConsent']);
        $shown = $page['data']['sponsorConsentText'];
        $this->assertSame(200, $this->call('POST', '/api/giveaway-sponsors/consent', 10, ['campaign' => 'calendar', 'campaignId' => $calendar, 'on' => true])[0]);
        $this->assertSame($shown, OptIn::query()->where('campaign', OptIn::CALENDAR)->value('consent_text'));
        $this->assertTrue($this->call('GET', "/api/giveaway-calendars/$calendar", 10)[1]['data']['myConsent']);
        $this->assertArrayNotHasKey('sponsorConsentText', $this->call('GET', "/api/giveaway-calendars/$calendar", null)[1]['data']);
    }

    #[Test]
    public function the_export_holds_only_members_who_opted_in_and_still_do()
    {
        $sponsor = $this->sponsor();
        $id = $this->giveaway((int) $sponsor['id']);
        foreach ([10, 11, 12] as $u) {
            $this->consent($u, $id, true);
        }
        $this->consent(12, $id, false); // withdrew

        [$status] = $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", 10);
        $this->assertSame(403, $status);
        [$status] = $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", null);
        $this->assertSame(401, $status);

        [$status, , $response] = $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", 1);
        $this->assertSame(200, $status);
        $this->assertStringStartsWith('text/csv', $response->getHeaderLine('Content-Type'));
        $csv = (string) $response->getBody();
        $this->assertStringContainsString('elf@machine.local', $csv);
        $this->assertStringNotContainsString('snowman', $csv, 'a withdrawn member');
        $this->assertStringNotContainsString('unconfirmed', $csv);
        // A cell a spreadsheet would run as a formula is defused.
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertSame(2, (int) $this->database()->table('giveaway_sponsor_exports')->value('rows'));

        // A member who later loses their confirmed email drops out.
        $this->database()->table('users')->where('id', 10)->update(['is_email_confirmed' => 0]);
        [, , $response] = $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", 1);
        $this->assertStringNotContainsString('elf@machine.local', (string) $response->getBody());
    }

    #[Test]
    public function the_report_is_behind_an_unguessable_link_and_has_counts_only()
    {
        $sponsor = $this->sponsor();
        $id = $this->giveaway((int) $sponsor['id']);
        // Snowman joined while the campaign ran: a new member.
        $this->database()->table('users')->where('id', 12)->update(['joined_at' => Carbon::now()->addMinute()]);
        $this->database()->table('giveaways')->where('id', $id)->update(['starts_at' => Carbon::now()->subHour()]);
        foreach ([10, 11, 12] as $u) {
            $this->call('POST', "/api/giveaways/$id/enter", $u);
        }
        $this->consent(10, $id, true);
        $this->call('GET', "/api/giveaways/$id", null);

        [$status] = $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/report-link", 10);
        $this->assertSame(403, $status);
        [, $body] = $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/report-link", 1);
        $url = $body['meta']['reportUrl'];
        $token = basename($url);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertNotSame($token, $this->database()->table('giveaway_sponsors')->value('report_token_hash'), 'only the hash is kept');

        $response = $this->send($this->request('GET', "/giveaways/sponsors/report/$token"));
        $html = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        // Flarum adds its own policy to the header; the page's meta tag has the last word.
        $this->assertStringContainsString('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', $html);
        $this->assertStringContainsString('noindex', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('Sleigh bells', $html);
        foreach (['elf', 'reindeer', 'snowman', 'machine.local'] as $personal) {
            $this->assertStringNotContainsString($personal, $html);
        }
        // 3 entrants, 1 new member, 1 opt-in: all under 5.
        $this->assertSame(3, substr_count($html, '<span class="few">'), 'shown as "fewer than 5"');

        // A giveaway with no start date counts from when it was made.
        $this->giveaway((int) $sponsor['id'], ['title' => 'No start date']);
        $response = $this->send($this->request('GET', "/giveaways/sponsors/report/$token"));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('No start date', (string) $response->getBody());

        $this->assertSame(404, $this->send($this->request('GET', '/giveaways/sponsors/report/'.str_repeat('a', 64)))->getStatusCode());
        $this->call('DELETE', "/api/giveaway-sponsors/{$sponsor['id']}/report-link", 1);
        $this->assertSame(404, $this->send($this->request('GET', "/giveaways/sponsors/report/$token"))->getStatusCode());
    }

    #[Test]
    public function the_tracked_link_goes_only_to_the_sponsors_own_website()
    {
        $sponsor = $this->sponsor();
        $id = $this->giveaway((int) $sponsor['id']);

        $response = $this->send($this->request('GET', "/giveaways/go/{$sponsor['slug']}")->withQueryParams(['c' => "giveaway-$id", 'to' => 'https://evil.example']));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://northpole.example', $response->getHeaderLine('Location'));
        $this->assertSame(1, (int) $this->database()->table('giveaway_sponsor_stats')->sum('clicks'));

        $this->assertSame(404, $this->send($this->request('GET', '/giveaways/go/nobody'))->getStatusCode());
    }

    #[Test]
    public function consents_are_erased_every_way_there_is()
    {
        $sponsor = $this->sponsor();
        $old = $this->giveaway((int) $sponsor['id'], ['title' => 'Old']);
        $recent = $this->giveaway((int) $sponsor['id'], ['title' => 'Recent']);
        foreach ([10, 11, 12] as $u) {
            $this->consent($u, $old, true);
            $this->consent($u, $recent, true);
        }
        $count = fn (?int $user = null) => OptIn::query()->when($user, fn ($q) => $q->where('user_id', $user))->count();
        $this->assertSame(6, $count());

        // The member's own list, and their GDPR erasure.
        $this->assertCount(2, $this->call('GET', '/api/giveaway-sponsors/consents', 12)[1]['meta']['consents']);
        $gdpr = $this->app()->getContainer()->make(SponsorConsents::class, ['user' => User::find(12), 'erasureRequest' => null]);
        $this->assertNotNull($gdpr->export());
        $gdpr->delete();
        $this->assertSame(0, $count(12));

        // Retention: 90 days by default after the campaign ends.
        $this->database()->table('giveaways')->where('id', $old)->update(['ends_at' => Carbon::now()->subDays(100)]);
        $this->database()->table('giveaways')->where('id', $recent)->update(['ends_at' => Carbon::now()->subDays(10)]);
        $retention = fn () => $this->app()->getContainer()->make(RetentionCommand::class)->run(new ArrayInput([]), new BufferedOutput());
        $retention();
        $this->assertSame(0, OptIn::query()->where('campaign_id', $old)->count());
        $this->assertSame(2, OptIn::query()->where('campaign_id', $recent)->count());

        // A manager can erase a sponsor's opt-ins; deleting the sponsor erases them too.
        $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/erase", 1);
        $this->assertSame(0, $count());
        $this->consent(10, $recent, true);
        $this->call('DELETE', "/api/giveaway-sponsors/{$sponsor['id']}", 1);
        $this->assertSame(0, $count());
    }

    #[Test]
    public function withdrawing_always_works_and_a_new_sponsor_starts_unticked()
    {
        $first = (int) $this->sponsor()['id'];
        $second = (int) $this->sponsor(['name' => 'Elf Workshop'])['id'];
        $id = $this->giveaway($first);

        // A double click is one agreement, not a 500.
        $this->assertSame(200, $this->consent(10, $id, true));
        $this->assertSame(200, $this->consent(10, $id, true));
        $this->assertSame(1, OptIn::query()->count());

        // The campaign changes sponsor: the box is unticked for the new one,
        // and unticking still erases the agreement with the old one.
        $this->call('PATCH', "/api/giveaways/$id", 1, ['data' => ['type' => 'giveaways', 'id' => (string) $id, 'attributes' => ['sponsorId' => $second]]]);
        $this->assertFalse($this->call('GET', "/api/giveaways/$id", 10)[1]['data']['myConsent']);
        // The old sponsor's list still names the campaign the member agreed for.
        $csv = (string) $this->call('POST', "/api/giveaway-sponsors/$first/export", 1)[2]->getBody();
        $this->assertStringContainsString('"elf","elf@machine.local","Sleigh bells"', $csv);
        $this->assertSame(200, $this->consent(10, $id, false));
        $this->assertSame(0, OptIn::query()->count());

        // The sponsor is removed from the campaign: withdrawing still works.
        $this->consent(11, $id, true);
        $this->call('PATCH', "/api/giveaways/$id", 1, ['data' => ['type' => 'giveaways', 'id' => (string) $id, 'attributes' => ['sponsorId' => null]]]);
        $this->assertSame(200, $this->consent(11, $id, false));
        $this->assertSame(0, OptIn::query()->count());

        // ...and the retention purge still reaches a campaign without a sponsor.
        $this->call('PATCH', "/api/giveaways/$id", 1, ['data' => ['type' => 'giveaways', 'id' => (string) $id, 'attributes' => ['sponsorId' => $second]]]);
        $this->consent(12, $id, true);
        $this->call('PATCH', "/api/giveaways/$id", 1, ['data' => ['type' => 'giveaways', 'id' => (string) $id, 'attributes' => ['sponsorId' => null]]]);
        $this->database()->table('giveaways')->where('id', $id)->update(['ends_at' => Carbon::now()->subDays(100)]);
        $this->app()->getContainer()->make(RetentionCommand::class)->run(new ArrayInput([]), new BufferedOutput());
        $this->assertSame(0, OptIn::query()->count());

        // Once it's over there's nothing to agree to.
        $this->call('PATCH', "/api/giveaways/$id", 1, ['data' => ['type' => 'giveaways', 'id' => (string) $id, 'attributes' => ['sponsorId' => $second]]]);
        $this->assertSame(422, $this->consent(10, $id, true));
    }

    #[Test]
    public function only_admins_export_emails_unless_granted()
    {
        // Elf manages giveaways (a mod group), but exporting is its own permission.
        $this->prepareDatabase([
            'groups' => [['id' => 4, 'name_singular' => 'Mod', 'name_plural' => 'Mods'], ['id' => 5, 'name_singular' => 'Exporter', 'name_plural' => 'Exporters']],
            'group_user' => [['user_id' => 10, 'group_id' => 4], ['user_id' => 11, 'group_id' => 4], ['user_id' => 11, 'group_id' => 5]],
            'group_permission' => [
                ['permission' => 'giveaways.manage', 'group_id' => 4],
                ['permission' => 'giveaways.exportSponsorOptIns', 'group_id' => 5],
            ],
        ]);
        $sponsor = $this->sponsor();

        [, $body] = $this->call('GET', "/api/giveaway-sponsors/{$sponsor['id']}", 10);
        $this->assertFalse($body['data']['canExport']);
        $this->assertSame(403, $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", 10)[0]);
        // Managing still works without it.
        $this->assertSame(200, $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/report-link", 10)[0]);

        // An admin has it; a group it is granted to has it.
        $this->assertTrue($this->call('GET', "/api/giveaway-sponsors/{$sponsor['id']}", 1)[1]['data']['canExport']);
        $this->assertSame(200, $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", 1)[0]);
        $this->assertSame(200, $this->call('POST', "/api/giveaway-sponsors/{$sponsor['id']}/export", 11)[0]);
    }
}
