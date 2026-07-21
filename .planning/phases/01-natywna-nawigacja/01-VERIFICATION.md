---
phase: 01-natywna-nawigacja
verified: 2026-07-21T15:30:00Z
status: human_needed
score: 7/8 must-haves verified
behavior_unverified: 1
overrides_applied: 0
behavior_unverified_items:
  - truth: "plugin:uninstall removes the plugin's ExternalPageTile(s) and their Item_Tile profile associations (glpi_items_tiles) so no 'Podgląd SIM' tile remains on /Helpdesk (SC2 / NAV-02)."
    test: "On a staging/disposable copy of the GLPI 11.0.4 host, run the plugin CLI `plugin:uninstall` for simviewer while the tile is present, then reload /Helpdesk as the same Self-Service user."
    expected: "The 'Podgląd SIM' tile is gone from /Helpdesk, and a DB check of glpi_items_tiles / glpi_externalpagetiles shows no lingering row for the SIM catalog URL."
    why_human: "This is a cleanup/deletion invariant (deleteTile() called from plugin_simviewer_uninstall(), matched on URL+title after the WR-02 code-review fix) that only executes against a live GLPI 11 database and Tiles schema. It is code-present and wired (grep-verified, independently re-read by the code reviewer in 01-REVIEW.md), but Plan 01-02's checkpoint listed this as 'Optional but recommended' (step 8) and 01-02-SUMMARY.md documents no execution of it — the word 'uninstall' does not appear anywhere in that SUMMARY. No behavioral evidence exists that deleteTile() actually removes the row(s) on the real Tiles API/schema."
human_verification:
  - test: "On a staging/disposable copy of glpi.mgw1943.local (GLPI 11.0.4), with a Self-Service user's tile visible on /Helpdesk, run `plugin:uninstall` for simviewer."
    expected: "The 'Podgląd SIM' tile disappears from /Helpdesk; no orphaned rows remain in glpi_items_tiles/glpi_externalpagetiles for the SIM catalog URL. Re-running `plugin:install` afterwards restores exactly one tile."
    why_human: "Requires exercising the plugin's Tiles-API delete path against a real GLPI 11.0.4 database; not reachable or verifiable from this repo/machine."
---

# Phase 1: Natywna nawigacja Verification Report

**Phase Goal:** Użytkownik self-service trafia do katalogu SIM wyłącznie natywnymi mechanizmami GLPI — kafelek na stronie głównej /Helpdesk, bez customowego wstrzykiwania JS
**Verified:** 2026-07-21T15:30:00Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1/NAV-01: Self-Service READ-right user sees "Podgląd SIM" tile on `/Helpdesk`; clicking opens `front/simcard.php` with no console/network errors | ✓ VERIFIED | `hook.php:126-145` registers `ExternalPageTile` per helpdesk profile via `TilesManager`; **human-verified** on production `glpi.mgw1943.local` per 01-02-SUMMARY.md ("Confirmed the native 'Podglad SIM' tile appears... clicking it opens the SIM catalog with no console/network errors"), approved 2026-07-21 |
| 2 | SC2/NAV-01: Tile auto-registers on `plugin:install` (fresh + update path after 1.1.0 bump), no manual config; re-running install creates no duplicate | ✓ VERIFIED | `hook.php:129-145` — idempotent `getTilesForItem()` scan comparing `getTileUrl()` before `addTile()`; **human-verified**: "Confirmed `plugin:install` is idempotent: re-running it produced exactly one tile, no duplicates" (01-02-SUMMARY.md) |
| 3 | SC2/NAV-02: `plugin:uninstall` removes the ExternalPageTile(s) and their `Item_Tile` associations, leaving no tile on `/Helpdesk` | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | `hook.php:158-193` (`plugin_simviewer_uninstall()`) calls `TilesManager::deleteTile()` for every tile matching URL+title (tightened by WR-02 fix, commit `d4634ab`, independently re-reviewed clean in 01-REVIEW.md). Code present and wired, but **no behavioral evidence**: 01-02-SUMMARY.md's checkpoint marked this "optional" (step 8) and never mentions "uninstall" — not exercised on the live host. See Human Verification below. |
| 4 | SC3/NAV-02: Self-service pages no longer load `public/js/nav-inject.js` and emit no `simviewer:*` meta tags; file deleted, `ADD_JAVASCRIPT`/`ADD_HEADER_TAG` hooks removed from `setup.php` | ✓ VERIFIED | `test ! -f public/js/nav-inject.js` → `DELETED_OK`; `grep -v comments setup.php \| grep -c "ADD_JAVASCRIPT\|ADD_HEADER_TAG\|PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR"` → `0`; `grep "simviewer:" setup.php` → no matches. **Human-verified**: "Confirmed no `public/js/nav-inject.js` request and no `simviewer:*` meta tags anywhere in self-service page source/network traffic" (01-02-SUMMARY.md) |
| 5 | SC3/NAV-02: `nav_selector` (top-nav-anchor) option removed from config form and migrated out of stored `glpi_configs` | ✓ VERIFIED | `grep -v comments src/Config.php \| grep -c nav_selector` → `0` (default + form `<tr>` removed); `hook.php:96-102` migrates the stale key via `deleteConfigurationValues()` guarded by `isset()`. **Human-verified**: "Confirmed the 'Top nav-bar anchor (CSS selector)' field is gone from the plugin config form, all other fields intact" (01-02-SUMMARY.md) |
| 6 | SC4: `helpdesk_menu_entry` "Podgląd SIM" link still works exactly as in v1.0 — no regression | ✓ VERIFIED | `setup.php:88-89` — `HELPDESK_MENU_ENTRY`/`HELPDESK_MENU_ENTRY_ICON` block byte-identical to pre-phase logic (only file diff for this phase touched version const + removed the JS block; git diff of `fd3f91c` confirms no lines changed in the menu-entry block). **Human-verified**: "Confirmed the v1.0 'Podglad SIM' simplified-interface menu entry still works (no regression)" (01-02-SUMMARY.md) |
| 7 | Tile title/description are short, fixed, localized (pl_PL/en_GB), no overflow | ✓ VERIFIED | Title reuses `Simcard::getMenuName()` ('SIM Viewer' msgid, pre-existing); description is the new locked string `Coworkers' business phone numbers` / `Służbowe numery telefonów współpracowników`, present in both `.po` files and compiled into `.mo` (`grep -c` → 2/1/1 as expected). No overflow defect reported in the 01-02 checkpoint (which required a visual pass to reach "approved"). |
| 8 | Backstop: clicking tile with a live right lands on the catalog cleanly; a right revoked between load and click resolves to GLPI's standard 403/404, no plugin-specific error UI | ✓ VERIFIED (backstop, human-approved) | Covered by the same 01-02-SUMMARY.md checkpoint approval (step 2: "no errors, HTTP 200"); no plugin-specific error handling was added, matching the backstop's negative expectation by omission |

**Score:** 7/8 truths verified (1 present + wired, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `hook.php` | Tile registration (install) + cleanup (uninstall), idempotent, nav_selector migration | ✓ VERIFIED | Imports `TilesManager`/`ExternalPageTile`; `plugin_simviewer_install()` (lines 67-150) and `plugin_simviewer_uninstall()` (lines 158-194) both present and substantive, not stubs |
| `setup.php` | `PLUGIN_SIMVIEWER_VERSION = '1.1.0'`; JS-injection hooks + constant removed; menu entry kept | ✓ VERIFIED | Confirmed via grep gates above |
| `src/Config.php` | `nav_selector` default + form field removed | ✓ VERIFIED | `grep -c nav_selector` → 0; all other config keys (`show_serial`, `show_status`, `enable_export`, `phone_source`, `fields_table`, `fields_column`, `line_column`) intact |
| `public/js/nav-inject.js` | Deleted | ✓ VERIFIED | File absent; deletion confirmed in commit `fd3f91c` (118 lines removed) |
| `locales/pl_PL.po`, `locales/en_GB.po` (+ `.mo`) | Tile description string added, `.mo` regenerated | ✓ VERIFIED | `msgid`/`msgstr` pairs present; `.mo` files' git mtimes match the regeneration commit |
| `README.md` | Known-limitation note for WR-01 (stale tile after right revocation) | ✓ VERIFIED | "Known limitations" section present (lines 5-17), added in fix commit `d4634ab` |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| `plugin_simviewer_install()` | `Glpi\Helpdesk\Tile\TilesManager::addTile()` | Per-helpdesk-profile registration, `\Profile` object + `ExternalPageTile::class` + params | ✓ WIRED | `hook.php:139-144`; params include `title`, `description`, `url` as specified |
| Idempotency check | `getTilesForItem($profile)` scan | Compares `getTileUrl()` to `Simcard::getListUrl()` before insert | ✓ WIRED | `hook.php:129-137` |
| `plugin_simviewer_uninstall()` | `TilesManager::deleteTile()` | Matches on URL **and** title (WR-02 tightened) | ✓ WIRED (code) / ⚠️ behavior unverified | `hook.php:181-191`; see Truth #3 |
| `Config::getDefaults()` removal | `setup.php` constant removal | No dangling `PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR` reference | ✓ WIRED | Both removed together in commit `fd3f91c`; zero grep matches for either |
| `hook.php` config migration | `\Config::deleteConfigurationValues()` | Scoped to `Config::CONTEXT` + `nav_selector` key, guarded by `isset()` | ✓ WIRED | `hook.php:99-102` |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|--------------|--------|----------|
| NAV-01 | 01-01, 01-02 | Native tile registered automatically, visible + click-through on `/Helpdesk` | ✓ SATISFIED | Truths #1, #2, #7, #8 — code + human verified |
| NAV-02 | 01-01, 01-02 | Custom JS injection removed; native-only navigation; uninstall cleans up tile | ? PARTIALLY SATISFIED | Truths #4, #5, #6 human-verified; truth #3 (uninstall cleanup) is code-present/wired but **not behaviorally confirmed** — see Human Verification |

No orphaned requirements: REQUIREMENTS.md traceability maps NAV-01 and NAV-02 to Phase 1 only, matching both plans' `requirements:` frontmatter.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `locales/pl_PL.po` / `locales/en_GB.po` | pl_PL:78, en_GB:77-78 | Orphaned `msgid "Top nav-bar anchor (CSS selector)"` left after `nav_selector` field removal (IN-01, acknowledged in 01-REVIEW.md, non-blocking) | ℹ️ Info | Dead translation string only; not referenced by any code path (confirmed `nav_selector` absent from `src/Config.php`), no functional or security impact |
| `hook.php` | 110-125 | Documented (not code) limitation: home-page tile is a point-in-time snapshot — does not live-track right revocation (WR-01, resolved via documentation per code review, `README.md` "Known limitations") | ℹ️ Info | No authorization bypass (`Session::checkRight()` still enforced in `front/simcard.php`); a revoked-but-not-yet-reinstalled profile could show a stale dead-link tile until admin reruns install — accepted risk, explicitly documented for admins |

No `TBD`/`FIXME`/`XXX` debt markers found in any file modified by this phase (`hook.php`, `setup.php`, `src/Config.php`).

### Human Verification Required

1 item requires human confirmation before Phase 1 can be marked fully passed (all other SC1-SC4 items already carry recorded human approval from 01-02-SUMMARY.md, dated 2026-07-21, and are treated as verified per that evidence):

### 1. Uninstall removes the native tile

**Test:** On a staging/disposable copy of `glpi.mgw1943.local` (GLPI 11.0.4) — or during a planned maintenance window on production, immediately followed by reinstall — run `plugin:uninstall` for simviewer while a Self-Service user's "Podgląd SIM" tile is present on `/Helpdesk`.
**Expected:** The tile disappears from `/Helpdesk` for that user. A DB check (`SELECT * FROM glpi_items_tiles JOIN glpi_externalpagetiles ...`) shows no lingering row pointing at the SIM catalog URL. Re-running `plugin:install` afterwards restores exactly one tile (confirms idempotent re-registration still works post-cleanup).
**Why human:** `plugin_simviewer_uninstall()`'s `deleteTile()` call (tightened in code review fix `d4634ab` to match on URL+title) is code-present and wired, and was independently re-read by the code reviewer, but this is a cleanup/deletion invariant that can only be proven by executing it against the real Tiles API + `glpi_items_tiles`/`glpi_externalpagetiles` schema. Plan 01-02's checkpoint listed this as an optional step (step 8) and 01-02-SUMMARY.md does not record it as performed — "uninstall" does not appear anywhere in that summary.

### Gaps Summary

No FAILED truths, no MISSING/STUB artifacts, no NOT_WIRED key links, and no blocking anti-patterns. The single open item is a behavioral-evidence gap, not a code gap: `plugin_simviewer_uninstall()`'s tile-cleanup path (SC2 second half / NAV-02) is fully implemented, code-reviewed, and independently re-verified by the code reviewer (WR-02 fix), but it was never actually executed against a live GLPI 11.0.4 instance — the Plan 01-02 checkpoint marked this step optional and it was skipped. Since REQUIREMENTS.md and ROADMAP.md both explicitly list "deinstalacja usuwa kafelek z /Helpdesk" as part of SC2, this must be confirmed (or a defect logged) before the phase can move from `human_needed` to `passed`. This does not block Phase 2 planning, but should be closed out — ideally opportunistically the next time `plugin:uninstall` is run for any reason (e.g. a future update cycle), or via a dedicated staging-environment check.

---

*Verified: 2026-07-21T15:30:00Z*
*Verifier: Claude (gsd-verifier)*
