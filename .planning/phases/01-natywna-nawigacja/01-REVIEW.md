---
phase: 01-natywna-nawigacja
reviewed: 2026-07-21T12:18:33Z
depth: standard
files_reviewed: 7
files_reviewed_list:
  - hook.php
  - setup.php
  - src/Config.php
  - locales/pl_PL.po
  - locales/en_GB.po
  - locales/pl_PL.mo
  - locales/en_GB.mo
findings:
  critical: 0
  warning: 2
  info: 1
  total: 3
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-21T12:18:33Z
**Depth:** standard
**Files Reviewed:** 7
**Status:** issues_found

## Summary

Phase 1 replaces the JS-injected top-nav link with a native `ExternalPageTile`
registered/deregistered through `TilesManager` in `hook.php`, removes the
`nav_selector` config option and its `ADD_JAVASCRIPT`/`ADD_HEADER_TAG` hooks
from `setup.php` and `src/Config.php`, and bumps the plugin to 1.1.0. The
removal is clean and complete: no remaining source references to
`nav_selector`, `PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR`, `nav-inject.js`,
`ADD_JAVASCRIPT`, or `ADD_HEADER_TAG` outside `.planning/` docs; the install-
time migration that deletes the stale `nav_selector` config key is idempotent
and correctly scoped. The right-gating for the plugin's own controller
(`front/simcard.php`) is unaffected and still enforced server-side via
`Session::checkRight()`, so no direct security bypass was introduced.

Two functional gaps remain in the new install/uninstall tile-management code
in `hook.php`: the tile is a one-time snapshot taken at install/update time
rather than a live reflection of each profile's right, and the uninstall
tile-removal logic matches purely on target URL, which can delete a tile it
did not create. Both are real defects worth fixing but neither is an
authorization bypass (the underlying page-level right check still holds).
One dead translation string survives the removal of the `nav_selector`
feature.

## Warnings

### WR-01: Home-page tile does not track later right changes (stale or missing tile)

**File:** `hook.php:104-128` (install) and `setup.php:78-90` (contrast with the dynamic menu-entry hook)
**Issue:** `plugin_simviewer_install()` adds an `ExternalPageTile` once, at
install/update time, to every profile that currently has `interface =>
'helpdesk'`. This is a point-in-time snapshot, unlike the
`HELPDESK_MENU_ENTRY` hook in `setup.php:86-90`, which re-evaluates
`Simcard::canView()` (i.e. `Session::haveRight()`) on every request.
Consequences:
- If an admin later revokes the `plugin_simviewer` READ right for a specific
  helpdesk profile via the per-profile admin tab (`src/Profile.php`,
  independent of re-running `plugin_simviewer_install()`), that profile keeps
  the home-page tile indefinitely — clicking it now 403s via
  `Session::checkRight()` in `front/simcard.php`, but the tile itself is
  never removed. Users see a broken/inaccessible tile.
- Conversely, a helpdesk profile created *after* the plugin was installed
  never receives the tile (nor, for the same install-time-only reason, the
  READ right via `addRightByInterface`) until `plugin_simviewer_install()`
  runs again (e.g. on the next version bump).

This is a regression in navigation-entry consistency introduced by this
phase: previously both entry points (top-nav JS injection and the classic
GLPI menu link) were gated live per-session; now one of the two navigation
surfaces (the tile) is static.
**Fix:** Reconcile the tile with the right on every save of the plugin's
right in `src/Profile.php::showForm()` (add/remove the tile for that single
profile when its `plugin_simviewer` right changes), mirroring what
`addRightByInterface()` + the tile loop already do at install time. At
minimum, document the limitation so admins know to trigger a plugin
"repair"/reinstall after granting or revoking the right on a profile.

### WR-02: Uninstall tile removal matches purely on URL, risking deletion of an unrelated tile

**File:** `hook.php:152-158`
**Issue:** `plugin_simviewer_uninstall()` deletes every `ExternalPageTile`
whose `getTileUrl()` equals `Simcard::getListUrl()` on any helpdesk profile.
There is no marker distinguishing a tile the plugin created from one an
administrator manually added (via GLPI's own Tiles admin UI) that happens to
target the same URL (e.g. a duplicate created while testing, or a bookmark-
style tile set up independently of this plugin). Uninstalling the plugin
will silently delete that unrelated, admin-authored tile along with the
plugin's own.
**Fix:** Either accept and document this as a known trade-off of the Tiles
API (no first-class "owner" metadata), or track which tile IDs the plugin
created (e.g., persisted alongside the plugin config under
`Config::CONTEXT`) and delete only those on uninstall instead of matching by
URL.

## Info

### IN-01: Orphaned translation string left behind after `nav_selector` removal

**File:** `locales/pl_PL.po:78-79`, `locales/en_GB.po:77-78`
**Issue:** Both catalogs still contain
`msgid "Top nav-bar anchor (CSS selector)"` even though the only caller of
this string — the `nav_selector` config-form field in `src/Config.php` — was
removed in this phase (commit `fd3f91c`). The dead entry is compiled into
the shipped `.mo` files as well.
**Fix:** Remove the orphaned `msgid`/`msgstr` pair from both `.po` files and
regenerate `locales/*.mo` via `tools/po2mo.py`, per the project's existing
locale-update convention (see commit `06aa47f`).

---

_Reviewed: 2026-07-21T12:18:33Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
