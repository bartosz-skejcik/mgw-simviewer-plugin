# Phase 1: Natywna nawigacja - Context

**Gathered:** 2026-07-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Użytkownik self-service trafia do katalogu SIM wyłącznie natywnymi mechanizmami GLPI: kafelek „Podgląd SIM" na stronie głównej `/Helpdesk` (system Tiles GLPI 11), rejestrowany automatycznie przy instalacji/aktualizacji wtyczki i usuwany przy deinstalacji. Całe customowe wstrzykiwanie JS (`nav-inject.js`, meta tagi `simviewer:*`, opcja `nav_selector`) znika z wtyczki. Wpis `helpdesk_menu_entry` z v1.0 pozostaje bez zmian (brak regresji). Eksport CSV i filtr pustych SIM-ów to Phase 2 — poza zakresem.

</domain>

<decisions>
## Implementation Decisions

### Kafelek — wygląd i treść
- Typ kafelka: `ExternalPageTile` wskazujący na stronę wtyczki (`/plugins/simviewer/front/simcard.php`) — wtyczka używa legacy front kontrolera, a `GlpiPageTile` obsługuje tylko strony core
- Tytuł kafelka: „Podgląd SIM" — identyczny z wpisem menu (`Simcard::getMenuName()`, lokalizowany pl_PL/en_GB)
- Opis na kafelku: krótki opis w stylu „Służbowe numery telefonów współpracowników" (lokalizowany pl_PL/en_GB)
- Ikona: `ti ti-device-sim` — spójna z wpisem menu z v1.0

### Rejestracja i cykl życia kafelka
- Rejestracja w `plugin_simviewer_install()` (ścieżka install i update po podbiciu wersji — GLPI wywołuje install przy aktualizacji), idempotentnie: sprawdź czy kafelek już istnieje zanim dodasz
- Kafelek dla wszystkich profili z interfejsem uproszczonym (helpdesk) — spójnie z `addRightByInterface(..., 'helpdesk')` z v1.0.3
- Pozycja: na końcu listy istniejących kafelków profilu (nie przestawiamy układu Service Catalog)
- `plugin_simviewer_uninstall()` usuwa wszystkie kafelki wtyczki (wpisy tile + powiązania profili); dezaktywacja bez deinstalacji zostawia kafelek

### Usunięcie JS injection i migracja konfiguracji
- Kompletne usunięcie: plik `public/js/nav-inject.js`, hooki `ADD_JAVASCRIPT` i `ADD_HEADER_TAG`, stała `PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR`
- Migracja przy update usuwa klucz `nav_selector` z `glpi_configs` (kontekst `plugin:simviewer`) — czysty stan zgodnie z kryterium sukcesu 3
- Pole „Top nav-bar anchor (CSS selector)" znika z formularza konfiguracji; reszta pól bez zmian
- Wersja wtyczki podbita do **1.1.0** — wymusza ścieżkę update na produkcji (live na 1.0.5)

### Claude's Discretion
- Dokładna nazwa klasy/API Tiles — zweryfikować względem gałęzi GLPI `11.0/bugfixes` (`src/Glpi/Helpdesk/Tile/`), nie przykładów z GLPI 10 (blocker z STATE.md)
- Szczegóły implementacji idempotencji (jak wykrywać istniejący kafelek wtyczki)
- Dokładne brzmienie opisu kafelka w pl/en

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Simcard::getMenuName()` — lokalizowany tytuł „Podgląd SIM" do reużycia na kafelku
- `Config::getConfig()` / `Config::install()` / `Config::uninstall()` — wzorzec configu w `glpi_configs` (kontekst `plugin:simviewer`)
- `Migration` w `hook.php` — istniejący wzorzec migracji (addRight, addRightByInterface, cache reset)
- Locale pl_PL/en_GB (`locales/*.po` + `tools/po2mo.py`) — dodać stringi opisu kafelka

### Established Patterns
- Instalacja idempotentna (Config::install seeduje tylko brakujące klucze; Migration::addRight pomija istniejące wiersze)
- Prawa: `Simcard::$rightname` (READ) + whitelist w `\Profile::$helpdesk_rights` w `plugin_init`
- Hooki rejestrowane warunkowo w `plugin_init_simviewer()` (setup.php:57-128); JS injection tylko dla uprawnionych użytkowników helpdesk

### Integration Points
- `setup.php:84-127` — blok helpdesk_menu_entry (zostaje) + ADD_JAVASCRIPT/ADD_HEADER_TAG (do usunięcia)
- `setup.php:39` — `PLUGIN_SIMVIEWER_VERSION` '1.0.5' → '1.1.0'; `setup.php:51` — stała nav_selector do usunięcia
- `src/Config.php:63-82` — getDefaults() z kluczem `nav_selector` (usunąć); `Config.php:242-244` — pole formularza (usunąć)
- `hook.php:44-76` — plugin_simviewer_install() (dodać rejestrację kafelka + migrację usuwającą nav_selector); `hook.php:84-91` — uninstall (dodać sprzątanie kafelka)
- `public/js/nav-inject.js` — plik do skasowania
- API Tiles GLPI 11: `src/Glpi/Helpdesk/Tile/` (TilesManager, ExternalPageTile) — zweryfikować na gałęzi 11.0/bugfixes

</code_context>

<specifics>
## Specific Ideas

- Kryterium weryfikowalne w przeglądarce: źródło strony self-service bez `nav-inject.js` i meta `simviewer:*` (network tab)
- Deploy na produkcję: git push → git pull na serwerze → `plugin:install` + `plugin:activate` w konsoli dockera (docs/deploy-z-laptopa.md)
- Produkcja: GLPI 11.0.4, encja z Service Catalog, `/Helpdesk` to strona Symfony bez legacy menu wtyczek

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
