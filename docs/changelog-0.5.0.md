## v0.5.0

### Added

**Forfeit and redraw.** A giveaway with a skill-testing question can now set
**Attempts before forfeit**. If the drawn winner uses them all without answering
correctly, they forfeit and the prize passes to the next entrant. `0` — the
default, and what 0.4.0 did — means unlimited retries and nothing is ever
redrawn.

**The replacement does not break the fairness proof.** It is not a fresh random
pick. `DrawService::pick()` eliminates each chosen entrant as it goes, so asking
it for `winner_count + forfeits` winners simply continues the same seeded
sequence — the extra id at the end is the one the draw would have revealed next.
Anyone holding the published seed and entrant list can reproduce the replacement
exactly, and confirm the prize moved on the seed rather than on the host's
say-so. Verified: for several seeds, `pick(pool, seed, N + k)` returns
`pick(pool, seed, N)` unchanged as its prefix, every id distinct, and a pool
smaller than the number of slots simply runs out rather than inventing a winner.

**Everything about it is on the record:**

- The forfeit rule is published in **Official rules** from the moment the
  giveaway opens — entrants know the terms before they enter.
- The winner sees how many attempts they have left, every time, above the
  answer box.
- A forfeited winner keeps their row in the **Winners** list, struck through and
  marked *Forfeited — prize redrawn*, rather than vanishing. The fairness
  section explains the replacement rule whenever a prize was redrawn.
- A forfeited winner still sees their banner, now explaining what happened,
  instead of it silently disappearing.

**An empty submission never costs an attempt** — sending no answer is not a
wrong answer, so a mis-click or an empty field cannot burn someone's last try.
Attempts are capped at 10 so a typo in the admin form cannot make a giveaway
effectively unclaimable.

### Upgrading

One migration (`skill_attempts`, `forfeited_at` on `giveaway_winners`).
Existing giveaways default to `0` attempts — unlimited — so nothing changes for
them until a host sets a limit.
