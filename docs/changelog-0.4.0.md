## v0.4.0

### Changed

**The skill-testing question is now put to the winner, not to everyone.** 0.3.0
asked every entrant to answer before their entry counted. That is the wrong
shape for what the question is for: in Canada and the other jurisdictions that
require one, the question is administered to the **selected winner** before the
prize is awarded — it qualifies the award, not the entry.

So entering is one click again, and the question is asked at **claim** time:

- The winner sees the question inside their "You won!" banner, above the Claim
  button, with the answer field.
- `POST /api/giveaways/{id}/claim` rejects a missing or wrong answer, so the
  prize cannot be marked claimed — and the host is not notified to fulfil it —
  until this person answers correctly. The gate is server-side.
- `POST /api/giveaways/{id}/enter` no longer takes or checks an answer.
- A prize that is already claimed is never re-gated, so adding a question to a
  giveaway later cannot strand a winner who has already claimed.
- The question stays published in the **Official rules** section from the
  moment the giveaway opens, where entrants can read it before entering. It is
  no longer listed under Requirements, because it is not an entry requirement.

Answer checking is unchanged: case, surrounding whitespace, a trailing full
stop and thousands separators are ignored, and two numeric answers are compared
as numbers.

A wrong answer can be retried — it does not forfeit the prize or trigger a
redraw. Forfeiting a winner who fails the question is a policy decision with
real consequences for the entrant, so it is deliberately not automatic; a host
who needs it can draw again.

### Upgrading

No migration. Giveaways created on 0.3.0 keep their question and answer; the
question simply moves from the entry step to the claim step. Entries already
taken stay valid.
