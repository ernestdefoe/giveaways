<?php

namespace ErnestDefoe\Giveaways\Http;

use ErnestDefoe\Giveaways\Sponsors\Report;
use ErnestDefoe\Giveaways\Sponsors\Sponsor;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /giveaways/sponsors/report/{token}: a sponsor's campaign report, for
 * people with the link and no forum account, so a plain HTML page rather
 * than a JSON:API resource. The token is 256 random bits; only its SHA-256
 * is stored, and a wrong or revoked one is a 404. Counts only (see Report),
 * kept out of search engines, caches and referrers.
 */
class SponsorReport implements RequestHandlerInterface
{
    private const HEADERS = [
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'no-store',
    ];

    public function __construct(
        private readonly Report $report,
        private readonly Factory $views,
        private readonly TranslatorInterface $translator,
        private readonly SettingsRepositoryInterface $settings,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = (string) Arr::get($request->getAttribute('routeParameters', []), 'token');
        $hash = hash('sha256', $token);
        $sponsor = preg_match('/^[a-f0-9]{64}$/', $token) ? Sponsor::query()->where('report_token_hash', $hash)->first() : null;

        if (! $sponsor || ! hash_equals((string) $sponsor->report_token_hash, $hash)) {
            return new HtmlResponse($this->translator->trans('ernestdefoe-giveaways.forum.sponsors.report_missing'), 404, self::HEADERS);
        }

        $html = $this->views->make('ernestdefoe-giveaways::report', [
            'sponsor' => $sponsor,
            'rows' => $this->report->rows($sponsor),
            'forum' => (string) $this->settings->get('forum_title'),
            'trans' => fn (string $key, array $params = []) => $this->translator->trans("ernestdefoe-giveaways.forum.sponsors.$key", $params),
            'shown' => fn (int $n) => Report::shown($n),
        ])->render();

        return new HtmlResponse($html, 200, self::HEADERS);
    }
}
