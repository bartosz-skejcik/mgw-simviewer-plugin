---
phase: 02-eksport-csv-i-filtr-pustych-sim-w
plan: 02
subsystem: export
tags: [php, glpi-plugin, csv, datatable, security]

# Dependency graph
requires:
  - phase: 02-01
    provides: "Config::getConfig()['enable_export'] (default '1', migration-safe on upgrade) and Simcard::getRows() already carrying entity scope + show_unassigned filtering"
provides:
  - "front/export.php: GET-only CSV streaming controller, READ-right gated, enable_export 403 gated"
  - "Simcard::getExportUrl() helper + conditional csv_url key on the show() datatable array"
affects: [02-03 (behavioral/production verification checkpoint — Export button visibility, CSV content, 403 on disabled export)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Second gate pattern for a front controller: Session::checkRight() first, then a config-driven feature gate (enable_export) that reuses Html::displayRightError() for a consistent 403 UX — no bespoke error string"
    - "csv_url passthrough to GLPI core components/datatable.html.twig: adding a single conditional array key is sufficient to toggle the native Export button, no twig changes needed"
    - "CSV formula-injection escaping: leading apostrophe prefix on cells starting with =,+,-,@ or tab/CR, applied uniformly via a closure at write time"

key-files:
  created:
    - front/export.php
  modified:
    - src/Simcard.php

key-decisions:
  - "CSV formula-injection defense implemented as escape (leading apostrophe), matching the plan's D: EXP-01 decision, rather than stripping or rejecting cells"
  - "enable_export=0 blocks front/export.php via Html::displayRightError() (403-equivalent), the same right-error idiom Session::checkRight() itself uses, rather than a bespoke error page"
  - "csv_url built via new Simcard::getExportUrl() helper mirroring the existing getListUrl() idiom, keeping the URL-building pattern consistent across both front controllers"

patterns-established:
  - "Any future GET-only endpoint added to this plugin should follow front/export.php's two-gate shape (right check, then feature-flag check) when the feature has its own enable/disable config toggle"

requirements-completed: [EXP-01, EXP-02]

coverage:
  - id: D1
    description: "front/export.php streams the current directory view as a ';'-delimited, UTF-8-BOM CSV attachment (sim-viewer_YYYY-MM-DD.csv) via the shared Simcard::getRows() path, gated first by Session::checkRight(plugin_simviewer, READ) and second by Config enable_export (Html::displayRightError 403 when disabled), with CSV formula-injection escaping on every cell"
    requirement: "EXP-01"
    verification:
      - kind: unit
        ref: "grep verification in 02-02-PLAN.md Task 1 (all acceptance greps passed: single getRows() call, zero SELECT/DB->request, zero pin|puk|msin matches, zero Html::header/footer, text/csv + Content-Disposition + sim-viewer_ + date('Y-m-d') present, formula-escape regex present)"
        status: pass
    human_judgment: true
    rationale: "Actual CSV download behavior (button click, file content, header row, row count matching production's default 42) requires a running PHP/GLPI environment and a browser — not runnable on this development machine. Deferred to the Plan 03 checkpoint per this plan's <verification> section."
  - id: D2
    description: "Simcard::show() adds a csv_url key to the datatable array pointing at front/export.php with the current filter query param, but only when Config enable_export is truthy — GLPI core's components/datatable.html.twig renders the native Export button exactly when csv_url is non-empty, so disabling export removes the key and hides the button with no twig change"
    requirement: "EXP-02"
    verification:
      - kind: unit
        ref: "grep verification in 02-02-PLAN.md Task 2 (csv_url and front/export.php present, assignment guarded inside the enable_export conditional, filter propagated via urlencode when non-empty)"
        status: pass
    human_judgment: true
    rationale: "Native button visibility toggling (present when enable_export=1, absent when 0) and the resulting rendered anchor href require a running GLPI page render — not runnable on this development machine. Deferred to the Plan 03 checkpoint per this plan's <verification> section."

duration: 4min
completed: 2026-07-21
status: complete
---

# Phase 02 Plan 02: CSV export controller and native Export button wiring Summary

**New GET-only front/export.php streams the current SIM directory view as a `;`-delimited UTF-8-BOM CSV via the shared `Simcard::getRows()` path, gated by READ right and a second `enable_export` config check (403 via `Html::displayRightError()` when disabled); `Simcard::show()` now adds a conditional `csv_url` to the datatable array so GLPI core renders the native Export button only when export is enabled.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-07-21T13:24:44Z
- **Completed:** 2026-07-21T13:27:56Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `front/export.php` (new): GET-only CSV streaming controller — `Session::checkRight(Simcard::$rightname, READ)` first, then a config-driven `enable_export` gate (`Html::displayRightError()` 403 when disabled) before any CSV output; streams via `Simcard::getRows()` only (no bespoke query), mirroring `show()`'s conditional `show_serial`/`show_status` column set; UTF-8 BOM + `;` delimiter + `sim-viewer_YYYY-MM-DD.csv` filename; escapes cells starting with `=`, `+`, `-`, `@`, tab or CR against CSV formula injection
- `src/Simcard.php`: new `getExportUrl()` helper (mirrors `getListUrl()`); `show()` now builds the `datatable` array as a local variable and conditionally adds `'csv_url'` pointing at `front/export.php` (with the current `filter` query param, URL-encoded) only when `Config::getConfig()['enable_export']` is truthy — GLPI core's `components/datatable.html.twig` renders the native Export `<a>` exactly when `csv_url|length`, so no twig change was needed

## Task Commits

Each task was committed atomically:

1. **Task 1: front/export.php — GET-only CSV streaming controller with enable_export 403 gate** - `254b490` (feat)
2. **Task 2: Simcard::show() — conditional csv_url on the datatable array** - `35f7c3b` (feat)

_No TDD RED/GREEN split — plan tasks marked `tdd="true"` but no PHP test runner is available locally (environment constraint, consistent with 02-01); verification is via grep-based acceptance criteria matching the plan's `<verification>` section. `php -l` was skipped for the same reason (PHP not on PATH on this machine)._

## Files Created/Modified
- `front/export.php` - new GET-only CSV export controller (READ + enable_export gates, shared getRows() data path, BOM/`;` CSV streaming, formula-injection escaping)
- `src/Simcard.php` - new `getExportUrl()` helper; `show()` conditionally adds `csv_url` to the datatable array when `enable_export` is truthy

## Decisions Made
- CSV formula-injection defense implemented as apostrophe-prefix escaping (not stripping/rejecting), matching the plan's explicit decision
- `enable_export=0` gate reuses `Html::displayRightError()` for the 403 response, consistent with the existing `Session::checkRight()` error idiom already used elsewhere in the plugin — no new bespoke error page
- Added `getExportUrl()` rather than inlining the URL string in `show()`, to keep the URL-building idiom symmetric with the existing `getListUrl()`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Comment wording collided with the sensitive-field grep check, inflating false-positive match counts**
- **Found during:** Task 1 verification
- **Issue:** Two in-code comments literally spelled out "Simcard::getRows()" and "Html::header()/Html::footer()" for documentation purposes, which made `grep -c 'getRows('` report 2 (acceptance requires exactly 1) and `grep -c 'Html::header'`/`Html::footer` report 1 each (acceptance requires 0). Comment text was accidentally shadowing the acceptance-criteria greps that are meant to detect actual code usage.
- **Fix:** Reworded the two comments to describe the same intent without repeating the literal method-call tokens (e.g. "the shared row accessor below" instead of naming `Simcard::getRows()`; "this file never emits a page header or footer" instead of naming `Html::header()`/`Html::footer()`).
- **Files modified:** front/export.php
- **Verification:** Re-ran all Task 1 acceptance greps; `getRows(` count is 1, `Html::header`/`Html::footer` counts are 0, all other greps unchanged and passing.
- **Committed in:** `254b490` (Task 1 commit — fixed before commit, not a follow-up)

---

**Total deviations:** 1 auto-fixed (1 bug — grep-colliding comment wording, cosmetic/verification-only, no behavior change)
**Impact on plan:** No scope creep. Behavior identical to what the plan specified; only comment phrasing was adjusted so automated verification measures actual code, not incidental comment text.

## Issues Encountered
None beyond the auto-fixed comment-wording collision above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `front/export.php` and the conditional `csv_url` in `Simcard::show()` are both grep-verified complete and committed; ready for Plan 03's behavioral checkpoint on a real GLPI/PHP environment.
- Deferred to Plan 03 (per this plan's `<verification>` section, PHP/DB not runnable locally): with `enable_export=1`, confirm the Export button appears and downloads a `;`-delimited CSV of exactly the visible rows (42 default on production per Plan 01), sensitive fields absent, current filter respected; with `enable_export=0`, confirm the button is gone and a direct GET of `front/export.php` returns a 403/right error with no data.

---
*Phase: 02-eksport-csv-i-filtr-pustych-sim-w*
*Completed: 2026-07-21*

## Self-Check: PASSED

All modified/created files confirmed present (front/export.php, src/Simcard.php) and both task commit hashes (254b490, 35f7c3b) confirmed in git log.
