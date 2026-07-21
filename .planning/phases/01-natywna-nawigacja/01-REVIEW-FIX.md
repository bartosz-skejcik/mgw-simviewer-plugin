---
phase: 01-natywna-nawigacja
fixed_at: 2026-07-21T14:24:01+02:00
review_path: .planning/phases/01-natywna-nawigacja/01-REVIEW.md
iteration: 1
findings_in_scope: 2
fixed: 2
skipped: 0
status: all_fixed
---

# Phase 01: Code Review Fix Report

**Fixed at:** 2026-07-21T14:24:01+02:00
**Source review:** .planning/phases/01-natywna-nawigacja/01-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 2 (critical: 0, warning: 2; `fix_scope: critical_warning` excludes IN-01)
- Fixed: 2
- Skipped: 0

## Fixed Issues

### WR-01: Home-page tile does not track later right changes (stale or missing tile)

**Files modified:** `hook.php`, `README.md`
**Commit:** `d4634ab`
**Applied fix:** No robust, verifiable live-sync fix was safely implementable. GLPI's Tiles
system (`TilesManager`/`ExternalPageTile`) has no per-request "should this tile render"
callback a plugin can hook into — tiles are explicit profile-scoped rows, added/removed only
when `install()`/`uninstall()` run, unlike the `HELPDESK_MENU_ENTRY` hook which re-evaluates
`Simcard::canView()` live on every request. The reviewer's own fix guidance offered "reconcile
on every save" as the primary option and "at minimum, document the limitation" as a fallback.
Reconciling on every profile-rights save would require hooking into GLPI core's `\Profile`
rights-matrix save path (`_profileright` POST processing / a core item-update hook) — internals
that are not present in this repo (no vendored GLPI core, `php -l` unavailable in this
environment) and could not be verified without risking a fragile, unverifiable guess at
undocumented core plumbing. Per the fallback option, this was instead documented:
- `hook.php`: added a `KNOWN LIMITATION` comment directly above the tile-registration loop in
  `plugin_simviewer_install()` explaining the point-in-time-snapshot behavior, its two concrete
  consequences (stale tile after right revocation; missing tile for profiles created after
  install), and the admin-facing remediation (re-run install via plugin repair/reinstall).
- `README.md`: added a new "Known limitations" section explaining the same behavior to admins
  and pointing them at the repair/reinstall remediation, and clarifying that the underlying
  page access is still correctly enforced (revoked users get a 403, not a bypass) even when the
  tile itself goes stale.

No source logic was changed for this finding — this is a documentation-only disposition,
consistent with the finding's own "at minimum, document the limitation" fallback guidance.

### WR-02: Uninstall tile removal matches purely on URL, risking deletion of an unrelated tile

**Files modified:** `hook.php`
**Commit:** `d4634ab` (same commit as WR-01 — see note below)
**Applied fix:** Tightened the tile-match condition in `plugin_simviewer_uninstall()` from
URL-only to URL **and** title. The plugin's `install()` already sets both `title` (
`Simcard::getMenuName()`) and `url` (`Simcard::getListUrl()`) when it creates the tile, so
matching on both — instead of URL alone — meaningfully narrows the chance of an uninstall
accidentally deleting an unrelated, admin-created tile that happens to target the same URL
(e.g. a duplicate bookmark tile). The title check is wrapped in
`!method_exists($tile, 'getTitle') || $tile->getTitle() === $tile_title` so that, if the
installed GLPI version's tile object does not expose a `getTitle()` accessor, the code falls
back to the pre-fix URL-only match rather than fatal-erroring — no regression risk if that
assumption doesn't hold on a given GLPI build. A code comment documents that this narrows but
does not eliminate the risk, since GLPI's Tiles schema has no first-class "owner"/creator
column to match on definitively (per the review's own accepted trade-off framing).

**Note on shared commit:** WR-01 and WR-02 both modify `hook.php` (different functions,
non-overlapping line ranges). Each was verified independently and staged as a separate git
index state via `git add -p` (hunk-level) before commit. However, the `gsd-tools query commit`
helper stages the full working-tree content of every named file rather than respecting a
pre-built partial index, so the WR-02 hunk (already present in the working tree, unstaged) was
swept into the same commit as WR-01 when that commit ran. Both fixes are correct and verified
independently (see Tier-1 re-reads performed for each finding); they simply landed in one
commit (`d4634ab`) instead of two due to this tooling limitation, not a process error in fix
selection or verification.

## Skipped Issues

None — both in-scope findings were fixed.

---

_Fixed: 2026-07-21T14:24:01+02:00_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
