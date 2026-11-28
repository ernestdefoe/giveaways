<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>{{ $trans('report_title', ['sponsor' => $sponsor->name]) }}</title>
<style>
  :root { color-scheme: light dark; --bg: #fff; --text: #1a2744; --muted: #5b6475; --line: #e4e7ee; }
  @media (prefers-color-scheme: dark) { :root { --bg: #15181f; --text: #e8ebf2; --muted: #9aa3b5; --line: #2a303c; } }
  body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
  main { max-width: 960px; margin: 0 auto; padding: 32px 16px; }
  h1 { margin: 0 0 4px; font-size: 24px; }
  p { color: var(--muted); margin: 0 0 20px; }
  .wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; min-width: 640px; }
  th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: right; white-space: nowrap; }
  th:first-child, td:first-child { text-align: left; white-space: normal; }
  th { color: var(--muted); font-weight: 600; font-size: 13px; }
  .few { color: var(--muted); font-style: italic; }
</style>
</head>
<body>
<main>
  <h1>{{ $trans('report_title', ['sponsor' => $sponsor->name]) }}</h1>
  <p>{{ $trans('report_intro', ['forum' => $forum, 'threshold' => \ErnestDefoe\Giveaways\Sponsors\Report::THRESHOLD]) }}</p>
  @if (! count($rows))
    <p>{{ $trans('report_empty') }}</p>
  @else
  <div class="wrap">
  <table>
    <thead><tr>
      <th>{{ $trans('report_campaign') }}</th><th>{{ $trans('report_dates') }}</th><th>{{ $trans('report_views') }}</th><th>{{ $trans('report_clicks') }}</th>
      <th>{{ $trans('report_entrants') }}</th><th>{{ $trans('report_new_members') }}</th><th>{{ $trans('report_optins') }}</th>
    </tr></thead>
    <tbody>
    @foreach ($rows as $row)
      <tr>
        <td>{{ $row['title'] }}</td>
        <td>{{ $row['starts']?->toDateString() }} – {{ $row['ends']->toDateString() }}</td>
        <td>{{ $row['views'] }}</td>
        <td>{{ $row['clicks'] }}</td>
        @foreach (['entrants', 'newMembers', 'optIns'] as $key)
          @php($n = $shown($row[$key]))
          <td>@if ($n === null)<span class="few">{{ $trans('report_few', ['threshold' => \ErnestDefoe\Giveaways\Sponsors\Report::THRESHOLD]) }}</span>@else{{ $n }}@endif</td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>
  @endif
</main>
</body>
</html>
