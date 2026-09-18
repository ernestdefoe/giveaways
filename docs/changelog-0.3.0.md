## v0.3.0

### Added

**Official rules.** Giveaways get a `rules` field of their own, separate from the short description, and their own **Official rules** section on the giveaway page. Contest law in many places requires the full terms — who may enter, how and when winners are selected, the entry deadline, how winners are contacted, odds, sponsor — to be published before entries open, and they run much longer than the blurb above the Enter button. Until now the only place to put them was `description`, whose placeholder even suggested it ("Rules, eligibility, how the prize is delivered…"), where they crowded out the pitch.

Stored in a `mediumText` column, capped at 20,000 characters, rendered as plain text with line breaks preserved.

**Skill-testing question.** A giveaway can now require entrants to answer a question correctly before their entry counts — the mechanism that keeps a prize draw a game of mixed skill rather than an illegal lottery in Canada and elsewhere.

- The question is set per giveaway, in the create/edit form, with its expected answer.
- Entrants see the question in the rules section and again above the Enter button; the answer goes with the entry request.
- `POST /api/giveaways/{id}/enter` rejects a missing or wrong answer, so **no entry row can exist without a correct answer** — the gate is server-side, not a frontend prompt.
- Answer checking is deliberately lenient: case, surrounding whitespace, a trailing full stop and thousands separators are ignored, and two numeric answers are compared as numbers, so `26.0`, `26.` and ` 26 ` all match `26`.
- The expected answer is only ever sent back to someone who can manage the giveaway (it populates the edit form). Entrants receive the question alone.
- Saving a question without an answer is rejected — it would lock every entrant out.

A question is also listed under **Requirements** on the giveaway page, next to the minimum-post and account-age rules.

### Upgrading

Adds one migration (`rules` on `giveaways`). Both features are optional and off by default: existing giveaways are untouched and keep their one-click entry.
