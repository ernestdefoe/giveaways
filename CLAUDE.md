# Working in this repo

## Standing rules

**Never open a pull request.** Commit to `main` and push. PRs are noise on
these repos — Ernest maintains them alone.

**Conventional commit subjects.** `feat:` for a feature, `fix:` for a fix,
`feat!:` or `BREAKING CHANGE` for a break. `.github/workflows/draft-release.yml`
picks the version bump by grepping the SUBJECT line between the last published
tag and HEAD, so a subject written as prose ships a feature as a patch.

**Fix what is broken; don't ask first.** Report what was done. Confirm only for
things that are not fixes: publishing outward, destructive operations, anything
touching a live site's data.

**Rebuild `js/dist` before committing.** `cd js && npm run build` — dist is
committed and is what Flarum loads. On a merge conflict in dist, rebuild from
merged source rather than resolving it by hand.

## Releasing

Pushing to `main` is NOT a release. The push drafts a release; **a human
publishes it**, which creates the tag and fires `publish-to-flarum.yml` to
announce on the support site. Packagist syncs from the tag, so nothing is
installable via `composer update` until the draft is published.

🚨 **Cloud / web Claude Code sessions cannot tag or publish.** Both are refused
at the proxy:

- `git push origin <tag>` → `HTTP 403`
- `PATCH /repos/.../releases/<id>` → `403 {"message":"Creating, editing, or
  deleting releases is not permitted for this session type."}`

This is the session type, not a missing tool or a permission that can be
granted in-session. Do not promise a release from a cloud session, and do not
burn turns discovering it again: push the commits, confirm the draft exists,
and say plainly that publishing is Ernest's click. Local sessions with `gh` and
a PAT can publish directly.

## Memory

The standing rules across all of Ernest's work live in the private repo
`ernestdefoe/claude-memory` (`MEMORY.md` is the index). Local machines load it
via `bootstrap.sh`, which sets `autoMemoryDirectory` and a Stop hook that syncs
it. **Cloud sessions do not load it** unless it is attached as a source to the
environment — which is why this file exists.
