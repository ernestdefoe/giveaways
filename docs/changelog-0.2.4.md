## v0.2.4

### Fixed

**Every single-giveaway page was blank.** Opening any giveaway on 0.2.3 gave a white page and two console errors:

```
TypeError: In fragments, vnodes must either all have keys or none have keys.
NotFoundError: Failed to execute 'removeChild' on 'Node'
```

The loading skeleton added in 0.2.3 built one children array holding three unkeyed vnodes and two keyed ones. Mithril's rule is per children array, not per element — every vnode in one array either has a key or none of them do — so `m.render` threw before drawing anything. That skeleton is the first thing a single-giveaway page renders, so the page never appeared at all.

The second error is the half-updated DOM that follows, not a separate fault. Fixing the first removes both.

The giveaway **list** page was never affected, which is why this got past me.

Reported by **@Mikasa1** — thank you, and sorry it took a version to catch.

### Also

`npm test` now renders both skeletons through real Mithril and fails the build if either one throws. It renders the actual source file rather than a copy of its markup, and it was confirmed to fail against the 0.2.3 file before being kept — a test that has never failed on the bug it was written for proves nothing.

**Upgrade:**

```
composer update ernestdefoe/giveaways
```

Then clear the asset cache.
