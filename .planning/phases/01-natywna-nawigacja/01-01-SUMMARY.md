---
phase: 01-natywna-nawigacja
plan: 01
subsystem: navigation
tags: [glpi11, tiles-api, php, i18n, gettext]

# Dependency graph
requires: []
provides:
  - Native "Podgląd SIM" ExternalPageTile registered per helpdesk-interface profile at install/update, idempotently
  - Tile cleanup (Item_Tile association + tile row) on uninstall
  - Complete removal of custom JS-injection navigation (public/js/nav-inject.js, ADD_JAVASCRIPT/ADD_HEADER_TAG hooks, PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR constant, nav_selector config key/form field)
  - Plugin version bumped to 1.1.0 (forces update path from production 1.0.5)
  - Tile description locale strings (pl_PL/en_GB) + regenerated .mo files
affects: [01-02 (deploy + human-verify UAT of tile visibility/click-through and JS-removal on GLPI 11.0.4)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "GLPI 11 Tiles API: TilesManager::getInstance()->addTile(\\Profile, ExternalPageTile::class, [title, description, url]) + getTilesForItem()/deleteTile() for idempotent per-profile lifecycle management"
    - "Stale config-key migration: guarded isset() check + Config::deleteConfigurationValues() scoped to a single key, run unconditionally inside plugin_simviewer_install() so it self-heals on every update"

key-files:
  created: []
  modified:
    - hook.php
    - setup.php
    - src/Config.php
    - locales/pl_PL.po
    - locales/en_GB.po
    - locales/pl_PL.mo
    - locales/en_GB.mo
  deleted:
    - public/js/nav-inject.js

key-decisions:
  - "Illustration field omitted from ExternalPageTile params — falls back to IllustrationManager::DEFAULT_ILLUSTRATION per the plan's FLAGGED deploy-verification note (ti-device-sim is a Tabler menu icon class, not a confirmed GLPI 11 tile illustration id)"
  - ".mo files regenerated locally via `python tools/po2mo.py` (Python 3.13 was available on this machine) — not deferred to deploy"
  - "hook.php gained a plugin_simviewer_get_helpdesk_profiles() helper shared by install/uninstall to avoid duplicating the core \\Profile query+load loop"

requirements-completed: [NAV-01, NAV-02]

coverage:
  - id: D1
    description: "plugin_simviewer_install() registers exactly one ExternalPageTile per helpdesk-interface profile idempotently (skips profiles that already carry a tile pointing at the SIM catalog url)"
    requirement: "NAV-01"
    verification:
      - kind: manual_procedural
        ref: "grep-based source verification only (no local GLPI runtime); behavioral UAT deferred to Plan 01-02 human-verify checkpoint on the GLPI 11.0.4 Docker host"
        status: unknown
    human_judgment: true
    rationale: "PHP is not runnable locally (plugin executes inside the GLPI Docker host); the Tiles API call cannot be exercised against a live GLPI 11 instance from this machine. Grep verification confirms code shape only, not runtime behavior — requires the Plan 01-02 human-verify checkpoint after deploy."
  - id: D2
    description: "plugin_simviewer_uninstall() removes every matching ExternalPageTile (tile row + Item_Tile association) via deleteTile()"
    requirement: "NAV-02"
    verification:
      - kind: manual_procedural
        ref: "grep-based source verification only; behavioral UAT deferred to Plan 01-02"
        status: unknown
    human_judgment: true
    rationale: "Same as D1 — no local GLPI runtime to exercise plugin lifecycle hooks against a real database."
  - id: D3
    description: "Custom JS injection fully removed: public/js/nav-inject.js deleted, ADD_JAVASCRIPT/ADD_HEADER_TAG hooks and PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR constant gone from setup.php, nav_selector option removed from Config defaults/form and migrated out of stored glpi_configs"
    requirement: "NAV-02"
    verification:
      - kind: other
        ref: "grep -v comments setup.php | grep -c 'ADD_JAVASCRIPT|ADD_HEADER_TAG|PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR' -> 0; grep -v comments src/Config.php | grep -c nav_selector -> 0; test ! -f public/js/nav-inject.js -> DELETED_OK"
        status: pass
    human_judgment: false
  - id: D4
    description: "Version bumped to 1.1.0; HELPDESK_MENU_ENTRY menu entry and its icon are unchanged (no regression on the v1.0 mechanism)"
    requirement: "NAV-02"
    verification:
      - kind: other
        ref: "grep -c \"'1.1.0'\" setup.php -> 1; grep -n HELPDESK_MENU_ENTRY setup.php shows both entries intact and unmodified"
        status: pass
    human_judgment: false
  - id: D5
    description: "Tile description string ('Coworkers'' business phone numbers' / 'Służbowe numery telefonów współpracowników') added to both locales and compiled into .mo"
    verification:
      - kind: other
        ref: "grep -c \"Coworkers' business phone numbers\" locales/en_GB.po -> 2 (msgid+msgstr); grep -c 'Służbowe numery telefonów współpracowników' locales/pl_PL.po -> 1; python tools/po2mo.py output: compiled en_GB.po -> en_GB.mo (24 entries), compiled pl_PL.po -> pl_PL.mo (24 entries)"
        status: pass
    human_judgment: false

duration: 15min
completed: 2026-07-21
status: complete
---

# Phase 01 Plan 01: Native ExternalPageTile registration + JS-injection removal Summary

**Native GLPI 11 ExternalPageTile registered per helpdesk profile (idempotent install/uninstall via TilesManager), with the plugin's entire custom JS-injection navigation path (nav-inject.js, ADD_JAVASCRIPT/ADD_HEADER_TAG hooks, nav_selector config option) deleted and version bumped to 1.1.0.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-07-21T10:33:17Z
- **Tasks:** 3/3
- **Files modified:** 5 modified, 1 deleted

## Accomplishments
- `hook.php` now registers a "Podgląd SIM" `ExternalPageTile` for every helpdesk-interface `\Profile` in `plugin_simviewer_install()`, guarded by a `getTilesForItem()` scan comparing `getTileUrl()` against `Simcard::getListUrl()` so re-running install never creates a duplicate tile
- `plugin_simviewer_uninstall()` deletes every matching tile via `TilesManager::deleteTile()`, which removes both the `ExternalPageTile` row and its `Item_Tile` profile association
- `plugin_simviewer_install()` also migrates away the retired `nav_selector` config key from stored `glpi_configs` (context `plugin:simviewer`), guarded by an `isset()` check so it is a no-op once migrated and a no-op on fresh installs
- `setup.php`: `PLUGIN_SIMVIEWER_VERSION` bumped `'1.0.5'` → `'1.1.0'`; `PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR` constant and the entire `ADD_JAVASCRIPT`/`ADD_HEADER_TAG` block deleted; `HELPDESK_MENU_ENTRY`/`HELPDESK_MENU_ENTRY_ICON` block and the `use GlpiPlugin\Simviewer\Config;` import (still needed for `Plugin::registerClass`) left untouched
- `src/Config.php`: removed the `nav_selector` default from `getDefaults()` and its form-field `<tr>` from `showConfigForm()`; all other config keys/fields untouched
- `public/js/nav-inject.js` deleted (file, and the now-empty `public/js/` directory, are gone)
- New tile-description translatable string added to `locales/pl_PL.po` and `locales/en_GB.po`, `.mo` files regenerated locally via `python tools/po2mo.py`

## Task Commits

Each task was committed atomically:

1. **Task 1: Add tile description locale strings and regenerate .mo** - `06aa47f` (feat)
2. **Task 2: Register and clean up the native ExternalPageTile (hook.php)** - `bb6afdd` (feat)
3. **Task 3: Remove JS injection, drop the top-nav config option, migrate it away, bump to 1.1.0** - `fd3f91c` (feat)

_Note: no TDD tasks in this plan (PHP is not runnable locally; verification uses grep-based source assertions per the plan's `<verification>` block)._

## Files Created/Modified
- `hook.php` - Added `plugin_simviewer_get_helpdesk_profiles()` helper; `plugin_simviewer_install()` gained idempotent tile registration + nav_selector config-key migration; `plugin_simviewer_uninstall()` gained tile cleanup
- `setup.php` - Version bump to 1.1.0; removed `PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR` constant and the `ADD_JAVASCRIPT`/`ADD_HEADER_TAG` hook block; `HELPDESK_MENU_ENTRY` block unchanged
- `src/Config.php` - Removed `nav_selector` default and its config-form `<tr>` field
- `locales/pl_PL.po`, `locales/en_GB.po` - Added tile-description `msgid`/`msgstr` pair
- `locales/pl_PL.mo`, `locales/en_GB.mo` - Regenerated from the updated `.po` sources
- `public/js/nav-inject.js` - Deleted

## Decisions Made
- **Illustration field omitted:** per the plan's `<verified_tiles_api>` FLAGGED note, `ti ti-device-sim` is a Tabler menu-icon class, not a confirmed GLPI 11 tile illustration id. Omitting `illustration` from the `addTile()` params lets `ExternalPageTile` fall back to `IllustrationManager::DEFAULT_ILLUSTRATION`, so the tile still renders. This is a deploy-time verification item (see below) — if a specific illustration id is later confirmed against the running GLPI 11.0.4 instance, it can be added as a follow-up.
- **`.mo` regeneration ran locally, not deferred:** Python 3.13.7 was available on this Windows dev machine (`python` on PATH), so `tools/po2mo.py` ran successfully and both `.mo` files are committed as part of Task 1 — no deploy-time regeneration step is required for this plan's locale changes.
- **Shared `plugin_simviewer_get_helpdesk_profiles()` helper:** added in Task 2 rather than duplicating the `\Profile::find(['interface' => 'helpdesk'])` + `getFromDB()` loop in both `plugin_simviewer_install()` and `plugin_simviewer_uninstall()`. Not explicitly called out in the plan text but a direct, low-risk implementation of the plan's own guidance to reuse the same helpdesk-profile population for both registration and cleanup.

## Deviations from Plan

None - plan executed exactly as written. No Rule 1-4 auto-fixes were needed; all three tasks matched their `<action>` and `<acceptance_criteria>` blocks on the first pass.

## Issues Encountered

None. PHP is not on the local PATH (expected per the plan's `<verification>` note — the plugin runs inside Docker on the production host), so no `php -l` syntax check was attempted; the plan's grep-based static verification gates were used instead, and all passed as shown in the coverage table above.

## GLPI 11 Tiles API — verified vs. installed

The plan's `<verified_tiles_api>` block (verified against GitHub `11.0/bugfixes` source on 2026-07-21) was used as-is with no deviation:
- `Glpi\Helpdesk\Tile\TilesManager::getInstance()`, `Glpi\Helpdesk\Tile\ExternalPageTile`, `addTile()`, `getTilesForItem()`, `deleteTile()`, and core `\Profile implements LinkableToTilesInterface` were all used exactly as documented in the plan.
- No API-shape mismatch was found or needed to be reconciled during this plan — this repo has no local GLPI 11 core checkout, so the implementation could not be exercised (only grep-verified). **Confirming these calls actually execute without error against the running GLPI 11.0.4 instance is the primary purpose of the Plan 01-02 human-verify checkpoint** — if the installed 11.0.4 API differs from the `11.0/bugfixes` branch snapshot in any way (unlikely for a patch-version difference, but not ruled out), that will surface there.

## User Setup Required

None - no external service configuration required. Deployment is the existing git-pull + `plugin:install`/`plugin:activate` flow documented in `docs/deploy-z-laptopa.md`, covered by Plan 01-02.

## Next Phase Readiness

- All static/source verification gates pass; the plugin is ready for deploy to the GLPI 11.0.4 Docker host.
- Plan 01-02 must perform the actual `plugin:install` (idempotent update path from 1.0.5 → 1.1.0), then behaviorally verify SC1-SC4 in a real browser: tile presence + click-through for a right-holder, absence for non-right-holders/central-interface profiles, no `nav-inject.js`/`simviewer:*` meta tags in page source, unchanged `helpdesk_menu_entry` behavior, and a clean `plugin:uninstall` removing the tile.
- No blockers identified for 01-02.

---
*Phase: 01-natywna-nawigacja*
*Completed: 2026-07-21*

## Self-Check: PASSED

All files (`hook.php`, `setup.php`, `src/Config.php`, `locales/pl_PL.po`, `locales/en_GB.po`, `locales/pl_PL.mo`, `locales/en_GB.mo`) confirmed present on disk; `public/js/nav-inject.js` confirmed deleted; all four commits (`06aa47f`, `bb6afdd`, `fd3f91c`, `98b6c17`) confirmed in `git log`.
