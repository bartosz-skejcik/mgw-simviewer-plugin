---
status: testing
phase: 01-natywna-nawigacja
source: [01-VERIFICATION.md]
started: 2026-07-21T12:45:00Z
updated: 2026-07-21T12:45:00Z
---

## Current Test

number: 1
name: Uninstall removes the native tile
expected: |
  Run `plugin:uninstall` for simviewer on a staging copy (or maintenance window) with the
  „Podgląd SIM" tile present. The tile disappears from `/Helpdesk` and no orphaned rows
  remain in `glpi_items_tiles` / `glpi_externalpagetiles`. Then `plugin:install` +
  `plugin:activate` restores exactly one tile.
awaiting: user response

## Tests

### 1. Uninstall removes the native tile
expected: `plugin:uninstall` removes the „Podgląd SIM" tile from `/Helpdesk` (no orphaned tile rows); reinstall restores exactly one tile
result: [pending]

## Summary

total: 1
passed: 0
issues: 0
pending: 1
skipped: 0
blocked: 0

## Gaps
