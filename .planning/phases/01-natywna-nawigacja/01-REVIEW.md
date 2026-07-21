---
phase: 01-natywna-nawigacja
reviewed: 2026-07-21T14:45:00Z
depth: standard
files_reviewed: 6
files_reviewed_list:
  - hook.php
  - setup.php
  - src/Config.php
  - locales/pl_PL.po
  - locales/en_GB.po
  - README.md
findings:
  critical: 0
  warning: 0
  info: 1
  total: 1
status: clean
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-21T14:45:00Z
**Depth:** standard
**Files Reviewed:** 6
**Status:** clean

## Summary

This is a re-review (iteration 2) verifying fix commit `d4634ab` against the
findings from the previous review (2026-07-21T12:18:33Z). Both prior Warning
findings are resolved:

- **WR-01** (stale/missing tile on right changes) is resolved via
  documentation, per the original finding's explicit fallback option ("at
  minimum, document the limitation"). `hook.php:110-125` now carries a
  detailed KNOWN LIMITATION comment explaining the point-in-time-snapshot
  behavior and its consequences, and cross-references a new "Known
  limitations" section in `README.md:5-17` that gives admins a concrete
  workaround (repair/reinstall after changing profile rights). No
  authorization bypass exists either way — `Session::checkRight()` in
  `front/simcard.php` still enforces access server-side regardless of tile
  state.
- **WR-02** (uninstall tile-removal matched purely on URL) is resolved in
  code. `hook.php:181-191` now matches on URL **and** title
  (`Simcard::getMenuName()`, the same value `install()` sets when creating
  the tile at `hook.php:140`), narrowing the deletion match and reducing the
  risk of deleting an unrelated admin-authored tile that happens to target
  the same URL. The added `method_exists($tile, 'getTitle')` guard is a safe
  defensive fallback (degrades to the prior URL-only match rather than
  fatal-erroring if the API assumption doesn't hold) — traced for
  operator-precedence/short-circuit errors, found none.

The fix commit is correctly scoped (`hook.php` + `README.md` only), does not
touch `setup.php` or `src/Config.php`, and introduces no new bugs, security
issues, or regressions in the previously-verified right-gating/migration
logic.

One Info-tier item remains, carried forward unresolved per the original
review's decision (intentionally not fixed, does not block clean status):

## Info

### IN-01: Orphaned translation string left behind after `nav_selector` removal

**Status:** Acknowledged, not fixed (Info-tier, non-blocking).
**File:** `locales/pl_PL.po:78`, `locales/en_GB.po:77-78`
**Issue:** Both catalogs still contain
`msgid "Top nav-bar anchor (CSS selector)"` even though the only caller of
this string — the `nav_selector` config-form field in `src/Config.php` — was
removed in this phase (commit `fd3f91c`). Confirmed still present at
re-review time.
**Fix:** Remove the orphaned `msgid`/`msgstr` pair from both `.po` files and
regenerate `locales/*.mo` via `tools/po2mo.py`, per the project's existing
locale-update convention (see commit `06aa47f`), whenever the locale files
are next touched.

## Resolved Findings (this iteration)

### WR-01: Home-page tile does not track later right changes — RESOLVED (documented)

**File:** `hook.php:110-125`, `README.md:5-17`
**Original issue:** `plugin_simviewer_install()` adds an `ExternalPageTile`
once, at install/update time, unlike the live `Simcard::canView()` check
backing `HELPDESK_MENU_ENTRY` in `setup.php:86-90`. Revoking the right later
leaves a stale, dead-link tile; a profile created after install never gets a
tile until reinstall.
**Resolution:** Documented as a known limitation rather than made live,
consistent with the fix's own suggested fallback ("At minimum, document the
limitation"). `hook.php` carries a detailed inline comment; `README.md`
gains a "Known limitations" section describing user-facing impact and the
admin workaround (Setup > Plugins repair/reinstall). No authorization bypass
existed before or after — access is enforced server-side independent of
tile presence.

### WR-02: Uninstall tile removal matches purely on URL — RESOLVED (code fix)

**File:** `hook.php:181-191`
**Original issue:** `plugin_simviewer_uninstall()` deleted every
`ExternalPageTile` whose `getTileUrl()` equaled `Simcard::getListUrl()` on
any helpdesk profile, with no way to distinguish a plugin-created tile from
an admin-authored one targeting the same URL.
**Resolution:** Match now requires both URL and title equality
(`$tile->getTitle() === $tile_title`, where `$tile_title =
Simcard::getMenuName()` mirrors the title `install()` sets), guarded by
`method_exists($tile, 'getTitle')` to fail safe (URL-only match) if the
method is unavailable. Verified against `install()`'s tile-creation call
(`hook.php:139-144`) to confirm the title value used for matching is exactly
the value set at creation time — no mismatch risk introduced.

---

_Reviewed: 2026-07-21T14:45:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
