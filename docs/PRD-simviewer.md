# PRD: SIM Viewer (GLPI 11 plugin)

**Plugin slug:** `simviewer`
**Target:** GLPI 11.0.x, self-service (simplified / helpdesk) interface
**Author:** Bartek
**Status:** Draft v1
**Stack note:** PHP/GLPI plugin, MariaDB/MySQL backing store, GLPI hook system

## 1. Summary

A read-only plugin that adds a **SIM Viewer** menu entry to the GLPI self-service interface. Users on a self-service profile (gated by a dedicated plugin right) see a single table mapping each user to their work phone number, sourced from the `nr_telefonu` custom field on SIM card items. No central interface, no editing, no PIN/PUK, no asset management exposed.

## 2. Problem and background

The native self-service (simplified) interface in GLPI 11 exposes only tickets, reservations, FAQ, and the service catalog. It has no Assets or Setup menu, and SIM cards are modeled as a *component* (`DeviceSimcard`, instances `Item_DeviceSimcard`) whose visibility is governed by the Components READ right on the standard interface only. There is no built-in way to surface SIM data to self-service users. A small read-only plugin is the minimal supported route. The `nr_telefonu` field already exists on SIM items (created via the Fields plugin), so the plugin reads it rather than redefining it.

## 3. Goals

1. Self-service users with the plugin right see a SIM Viewer entry in their menu.
2. The view shows, per SIM: assigned **User**, **phone number** (`nr_telefonu`), and a small amount of context (SIM serial, status).
3. Results are entity-scoped: a user sees only SIMs in entities they are active in.
4. The view is strictly read-only. No create, update, delete, or export of personal data beyond on-screen display (export is a configurable option, default off).
5. Clean install and uninstall, including the plugin right and any plugin tables.
6. Polish (`pl_PL`) and English (`en_GB`) locales.

## 4. Non-goals

- No central-interface management UI (SIM creation and the `nr_telefonu` field stay in the standard interface / Fields plugin).
- No exposure of PIN, PUK, PIN2, PUK2, MSIN, or other sensitive SIM fields.
- No editing of users, SIMs, or lines.
- No replacement of the native Line object workflow.
- No per-row write permissions or approval flows.

## 5. Users and access model

| Actor | Need |
|-------|------|
| Self-service user (granted) | See the directory of users and their phone numbers |
| Self-service user (not granted) | Sees nothing; menu entry absent; direct URL blocked |
| Admin / Super-Admin | Toggles the plugin right per profile; configures options |

Access is controlled by a **dedicated plugin right** (`plugin_simviewer`, value READ) registered in `glpi_profilerights`. The right is exposed on the profile form so an admin decides which profiles (self-service or otherwise) see the viewer. Do not assume every self-service user should have it: granting is an explicit admin action.

## 6. Functional requirements

### FR-1 Menu entry (self-service)
- Use the `redefine_menus` hook with a callback that adds the entry only when `$_SESSION['glpiactiveprofile']['interface'] === 'helpdesk'` and the session holds `plugin_simviewer` READ. This is the robust approach recommended by GLPI maintainers.
- `helpdesk_menu_entry` (link passed as a string, not boolean `true`) is acceptable as a simpler fallback. Decide one; do not register both.
- Icon: a Tabler icon, e.g. `ti ti-device-sim`.

### FR-1a Menu placement (top nav bar — exact target)
- The link/button that opens the SIM Viewer listing page (FR-2) **must be placed in the top navigation bar** of the self-service interface, anchored to the existing nav element with CSS selector **`#menu_1595890973`**. The new entry is rendered as a sibling/child of that node so it appears inline in the same top nav bar, not in a side menu or a separate region.
- **Primary approach — native GLPI menu positioning.** Attempt to register the entry through GLPI's native menu API (`redefine_menus` / `helpdesk_menu_entry` per FR-1) so it lands in the top nav bar at the `#menu_1595890973` anchor. This is preferred because it survives GLPI re-renders, respects the menu permission model, and requires no DOM patching.
- **Fallback — JavaScript injection.** If native GLPI menu positioning cannot place the entry at `#menu_1595890973` (e.g. the helpdesk top nav does not expose a hook point for plugin entries, or the entry lands in the wrong region), inject the link via JavaScript:
  - Load a small plugin JS asset on the self-service pages (gated by the same `plugin_simviewer` READ check, so the script is only emitted for users who have the right).
  - On `DOMContentLoaded`, locate `#menu_1595890973`, build the anchor (`<a href="…/plugins/simviewer/front/simcard.php">` with the `ti ti-device-sim` icon and translated label), and insert it relative to that node (append as child or insert as adjacent sibling, matching the markup of the surrounding nav items).
  - Guard against double-insertion (check for an existing `#simviewer-nav-link` before adding) and degrade silently if the anchor node is absent (different interface mode / Service Catalog — see Risk 1).
- **Selector fragility note.** `#menu_1595890973` is a numeric/auto-generated GLPI menu id and may differ between GLPI instances, versions, or entity configurations. Treat the exact id as a deployment-configurable value (default `#menu_1595890973`), and verify it against the live target instance before release. The JS fallback should fail gracefully (no error, no broken layout) if the selector does not match.

### FR-2 Listing page
- Front controller at `front/simcard.php` renders a single read-only table.
- **The table must be a native GLPI table**, not a custom/hand-rolled HTML table. Render it with GLPI's standard list/table machinery so it inherits the platform look-and-feel, theming, responsive behaviour, sorting, and pagination for free. Acceptable native mechanisms (confirm exact GLPI 11 API before building):
  - GLPI's datatable/list rendering helpers (the same component the core list views use), or
  - a GLPI Twig table template via `TemplateRenderer` using the core table partial/macro, or
  - the GLPI Search engine output (`Search::showList` / search display) if the data is expressed as a searchable itemtype.
  - Do **not** ship bespoke `<table>` markup with custom CSS when a native GLPI table component covers the need.
- Columns: User (realname + firstname, fallback to login), Phone number (`nr_telefonu`), SIM serial, Status. Phone number and User are mandatory columns; serial and status are configurable.
- Sort default: User ascending. Sorting/pagination should come from the native GLPI table component rather than custom JS where possible.
- Empty state: clear "no SIM cards visible to you" message (use the native table's empty-state rendering if available).

### FR-3 Search / filter
- A single free-text filter box matching against User and Phone number (server-side or client-side depending on row count; assume server-side for safety).

### FR-4 Entity scoping
- All queries apply `getEntitiesRestrictCriteria('glpi_items_devicesimcards')` for the active entity and recursive children per session. Never return SIMs outside the user's entity scope.
- Respect `is_deleted = 0` and, if the SIM template flag exists, exclude templates.

### FR-5 Read-only enforcement
- No write endpoints exist in the plugin. The front controller is GET only.
- Direct access without the right calls `Html::displayRightError()` (verify exact GLPI 11 API) and exits.

### FR-6 Configuration (admin, central interface)
- Optional `config_page` for: show/hide serial column, show/hide status column, enable/disable CSV export (default disabled), and choice of phone-number source (see 7.2).

## 7. Data model and sources

### 7.1 Core query (conceptual)
```
glpi_items_devicesimcards (isc)              -- SIM instances: id, devicesimcards_id,
                                                users_id, entities_id, serial, states_id,
                                                is_deleted
  JOIN glpi_devicesimcards (dsc)             -- SIM model: designation
    ON dsc.id = isc.devicesimcards_id
  LEFT JOIN glpi_users (u)                    -- assigned user
    ON u.id = isc.users_id
  LEFT JOIN <nr_telefonu source> (f)          -- see 7.2
WHERE isc.is_deleted = 0
  AND <entity restriction>
```
The assigned user comes from `isc.users_id` on the SIM instance. Verify the exact column name in your instance (`users_id` vs `users_id_tech`).

### 7.2 Phone number source (decision point)
The `nr_telefonu` field is a Fields plugin custom field. Confirm where it physically lives before building:

- **Option A (primary, as specified): Fields plugin field on `Item_DeviceSimcard`.** Stored in a Fields container table, typically `glpi_plugin_fields_<container>` keyed by `items_id` and `itemtype = 'Item_DeviceSimcard'`. Get the exact table and column name from the Fields container config. This couples the plugin to the Fields plugin; declare Fields as a dependency and fail gracefully if absent.
- **Option B (fallback, fully native): the Line object.** GLPI's native Line carries "Caller number" (phone) and links to a user. If `nr_telefonu` ever migrates to native data, switch the join to `glpi_lines`. Expose the source as a config option (FR-6) so the deployment can pick A or B without code changes.

### 7.3 Plugin tables
- The plugin stores no SIM data of its own. It only reads. The only persisted plugin state is configuration (a small `glpi_plugin_simviewer_configs` row) and the registered right.

## 8. Permissions and security

- **Right gating:** every page load checks `Session::haveRight('plugin_simviewer', READ)`. No right, no data, no menu entry.
- **Entity isolation:** enforced in SQL, not in the view layer.
- **Output escaping:** all user and phone values escaped on render (Twig auto-escape or `htmlspecialchars`). Assume names may contain special characters.
- **SQL safety:** parameterized queries / GLPI `DBmysqlIterator` criteria arrays, never string-concatenated input.
- **CSRF:** register `csrf_compliant`. Page is GET-only so low risk, but keep it compliant.
- **No sensitive fields:** the query explicitly selects only User, phone, serial, status. PIN/PUK columns are never selected.

## 9. Privacy (RODO / GDPR)

Phone number plus full name is personal data (dane osobowe). Treat this view as an internal directory with a defined purpose.
- Limit the right to profiles with a legitimate need, not all self-service users by default.
- Document the processing purpose (internal contact directory) in the deployment notes.
- Default CSV export to **off** to discourage bulk extraction.
- Consider an access log entry per view if the organization's RODO policy requires it (optional, config-gated).
- Do not display SIM serial or status to end users unless there is a need; both are off-by-default candidates.

## 10. Plugin structure (GLPI 11)

```
simviewer/
  setup.php            # PLUGIN_SIMVIEWER_VERSION, plugin_init_simviewer(),
                       # plugin_version_simviewer(), prerequisite + config checks,
                       # hook registration (redefine_menus, csrf_compliant, config_page)
  hook.php             # plugin_simviewer_install() / _uninstall(),
                       # plugin_simviewer_redefine_menus($menu),
                       # plugin_simviewer_getRights()
  front/
    simcard.php        # read-only listing controller (GET only)
    config.form.php    # admin config (central interface)
  inc/
    simcard.class.php  # PluginSimviewerSimcard: query + render
    config.class.php   # PluginSimviewerConfig: options storage
    profile.class.php  # right registration on Profile form (optional tab)
  templates/
    list.html.twig     # the read-only table (native GLPI table via TemplateRenderer)
  js/
    nav-inject.js      # FR-1a JS fallback: inject the nav link at #menu_1595890973
                       # when native menu positioning is insufficient; loaded only
                       # for users holding plugin_simviewer READ
  locales/
    en_GB.po / pl_PL.po
```

### Install / uninstall
- Install: register the `plugin_simviewer` right via `ProfileRight::addProfileRights(['plugin_simviewer'])`, create the config table, seed default config.
- Uninstall: `ProfileRight::deleteProfileRights(['plugin_simviewer'])`, drop the config table. Leave all core SIM and Fields data untouched.

## 11. Acceptance criteria

1. A self-service user **with** the right sees the SIM Viewer entry **in the top navigation bar, anchored at `#menu_1595890973`** (via native menu positioning, or the JS fallback per FR-1a), and clicking it opens a **native GLPI table** of User + phone number.
2. A self-service user **without** the right sees no entry (neither native nor JS-injected) and gets a right error on the direct URL.
3. Only SIMs within the user's active entity (and recursive children) appear. Cross-entity SIMs never leak.
4. No control on any screen creates, edits, or deletes a SIM, user, line, or field.
5. PIN/PUK and other sensitive SIM fields never appear in the DOM or network response.
6. Install adds the right; uninstall removes it and the config table, leaving core data intact.
7. UI renders correctly in `pl_PL` and `en_GB`.
8. The filter narrows rows by name or number.

## 12. Risks and open questions

1. **Simplified interface vs Service Catalog.** GLPI 11 introduces a per-entity "Service catalog" mode that replaces the classic simplified interface. Verify whether target entities use the classic simplified interface or Service Catalog, because menu injection behavior may differ. Test in the actual deployment mode before committing to `redefine_menus` vs `helpdesk_menu_entry`.
2. **Fields plugin coupling.** Confirm the exact `nr_telefonu` storage table and column. If the Fields plugin is upgraded or the container renamed, the join breaks. Mitigate with the config-selectable source (Option A/B) and a graceful "field not found" state.
3. **GLPI 11 API churn.** GLPI 11 moved toward Twig templates, namespacing, and a reworked front end. Confirm current signatures for menu rendering, header/footer helpers for the simplified interface, `displayRightError`, and `TemplateRenderer` against the 11.0.x source before implementing. Treat older (GLPI 10) plugin examples as directional, not exact.
4. **User column source.** Confirm whether the assigned user is `users_id` on `Item_DeviceSimcard` in this instance.
5. **Scope of "who sees whom."** This PRD assumes a full directory (all users' numbers within entity scope). If the requirement is "user sees only their own number," that is a one-line WHERE change but a different product. Decide explicitly.
6. **Reload to see menu.** GLPI builds the menu at login; menu changes require logout/login to appear. Note this in deployment/testing.

## 13. Future (out of scope for v1)

- Click-through to a SIM detail card (still read-only).
- Group-based scoping instead of entity-only.
- Optional access logging surfaced to admins.
- A central-interface mirror of the same view for technicians.
