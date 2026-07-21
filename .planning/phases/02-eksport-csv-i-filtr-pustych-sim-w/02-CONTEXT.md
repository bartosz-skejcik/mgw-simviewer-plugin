# Phase 2: Eksport CSV i filtr pustych SIM-ów - Context

**Gathered:** 2026-07-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Uprawniony użytkownik pobiera katalog SIM do CSV natywnym przyciskiem datatable (mechanizm `csv_url`), a tabela domyślnie pokazuje wyłącznie SIM-y z przypisanym użytkownikiem. Eksport jest sterowany opcją `enable_export` (po aktualizacji WŁĄCZONA), nowa opcja `show_unassigned` (default 0) przywraca pełną listę. Nigdy nie eksportujemy PIN/PUK/PIN2/PUK2/MSIN. Nawigacja (Phase 1) — poza zakresem.

</domain>

<decisions>
## Implementation Decisions

### Eksport CSV — endpoint i format
- Dedykowany GET-only kontroler `front/export.php`, podawany do datatable przez natywny mechanizm `csv_url` (kontrakt `csv_url` komponentu datatable zweryfikować w źródłach GLPI `11.0/bugfixes`, nie GLPI 10)
- Zawartość CSV = dokładnie bieżący widok: kolumny wg configu (Użytkownik, Numer telefonu + opcjonalnie serial/status), z filtrem tekstowym `filter`, entity scopingiem i filtrem pustych SIM-ów — wspólna ścieżka `Simcard::getRows()`; nigdy PIN/PUK/PIN2/PUK2/MSIN
- Format zgodny z natywnym eksportem CSV GLPI, jeśli core definiuje konwencję dla `csv_url`; przy własnej implementacji: UTF-8 z BOM, separator `;` (Excel PL)
- Nazwa pliku: `sim-viewer_YYYY-MM-DD.csv`

### Sterowanie enable_export i wersjonowanie
- Default `enable_export` zmienia się '0'→'1' ORAZ jednorazowa migracja przy update podbija zapisane '0'→'1' (produkcja ma dziś zapisane 0; kryterium sukcesu 2 wymaga WŁĄCZONE po aktualizacji; decyzja użytkownika 2026-07-20 świadomie łagodzi rekomendację RODO z PRD)
- Blokada serwerowa: `front/export.php` wymaga `Session::checkRight('plugin_simviewer', READ)`; gdy `enable_export=0` → błąd uprawnień (403), bez danych
- Widoczność przycisku: `csv_url` przekazywany do datatable tylko gdy `enable_export=1` (użytkownik na stronie ma już READ)
- Wersja wtyczki: podbicie do **1.2.0** (wymusza ścieżkę update na produkcji, live na 1.1.0)

### Filtr pustych SIM-ów (show_unassigned)
- Filtr w SQL w `Simcard::getRows()`: gdy `show_unassigned=0` dodaj `users_id > 0` do WHERE — spójnie z entity scopingiem (filtry w SQL, nie w widoku)
- Definicja „pustego" SIM-a: po `users_id` (0/NULL = nieprzypisany), nie po sformatowanej nazwie
- Eksport CSV respektuje ten sam filtr automatycznie (wspólna ścieżka `getRows()`) — na produkcji: 42 wiersze zamiast 136; z `show_unassigned=1` — 136
- Nowa opcja `show_unassigned` (default '0') jako Yes/No dropdown „Show SIMs without assigned user" w formularzu konfiguracji; stringi pl_PL/en_GB; seed przez istniejący idempotentny `Config::install()` (dosiewa brakujące klucze przy update)

### Claude's Discretion
- Dokładny kontrakt `csv_url` w komponencie datatable GLPI 11 (czy przycisk renderuje core, jakie parametry przekazuje) — zweryfikować na gałęzi `11.0/bugfixes` (`templates/components/datatable.html.twig`)
- Szczegóły implementacji CSV (fputcsv vs ręczne budowanie, nagłówki HTTP)
- Dokładne brzmienie etykiet konfiguracji pl/en

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Simcard::getRows(?string $filter)` (src/Simcard.php:98-202) — jedyna ścieżka danych (entity scoping, źródło numeru Fields/Line, filtr tekstowy, sort) — tu wchodzi filtr `users_id` i stąd czyta eksport
- `Simcard::show()` (src/Simcard.php:208-263) — buduje config datatable (`components/datatable.html.twig`); tu dochodzi `csv_url`
- `front/simcard.php` — wzorzec GET-only kontrolera z `Session::checkRight` — szablon dla `front/export.php`
- `Config::getDefaults()/getConfig()/install()` (src/Config.php) — enable_export default + nowy klucz show_unassigned; `Config::configUpdate()` — normalizacja checkboxów (dodać show_unassigned)
- `hook.php` — wzorzec migracji przy install/update (Phase 1 usuwała nav_selector — analog dla flipu enable_export)
- Locale pl_PL/en_GB + `tools/po2mo.py` (Python 3.13 działa lokalnie)

### Established Patterns
- Wszystkie filtry egzekwowane w SQL (`getEntitiesRestrictCriteria`), nie w widoku
- GET-only front kontrolery; prawo `plugin_simviewer` READ; wrażliwe pola nigdy nie są SELECT-owane
- Konfiguracja w `glpi_configs` (kontekst `plugin:simviewer`), seed idempotentny, checkboxy normalizowane w `configUpdate()`
- Wersjonowanie: bump `PLUGIN_SIMVIEWER_VERSION` wymusza update path (deploy: git pull + plugin:install + plugin:activate w dockerze)

### Integration Points
- `src/Simcard.php:247-261` — array `datatable` (dodać `csv_url` warunkowo); `:147-151` — WHERE (dodać filtr users_id)
- `src/Config.php:63-82` — getDefaults (enable_export '1', show_unassigned '0'); `:186-193` — configUpdate (dodać show_unassigned do checkboxów); formularz `:213-244` (dodać pole show_unassigned)
- `hook.php` plugin_simviewer_install() — migracja enable_export '0'→'1' (jednorazowa, wykrywana np. po wersji lub obecności klucza)
- Nowy plik: `front/export.php`
- GLPI core: `templates/components/datatable.html.twig` na gałęzi `11.0/bugfixes` — kontrakt `csv_url`

</code_context>

<specifics>
## Specific Ideas

- Produkcja: 136 SIM-ów w encji, 94 bez użytkownika → domyślny widok 42 wiersze; kryteria sukcesu odwołują się do tych liczb
- Uwaga na migrację: jednorazowy flip enable_export nie może nadpisywać późniejszej decyzji admina o wyłączeniu (flip tylko przy przejściu wersji < 1.2.0)
- Deferred z Phase 1: test deinstalacji kafelka (01-UAT.md) — poza zakresem Phase 2, ale deploy 1.2.0 to okazja do domknięcia

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
