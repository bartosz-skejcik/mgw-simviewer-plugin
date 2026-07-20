# SIM Viewer (GLPI 11 plugin)

## What This Is

Read-only wtyczka GLPI 11 dodająca do interfejsu uproszczonego (self-service) katalog „Podgląd SIM": tabelę użytkownik → służbowy numer telefonu, czytaną z pola `nr_telefonu` (plugin Fields) na kartach SIM. Dla pracowników MGW logujących się kontami synchronizowanymi z firmowego Active Directory.

## Core Value

Pracownik self-service w kilka sekund znajduje służbowy numer telefonu współpracownika — bez dostępu do zasobów, edycji ani wrażliwych pól SIM (PIN/PUK).

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

- ✓ Wpis „Podgląd SIM" w menu interfejsu uproszczonego (natywny `helpdesk_menu_entry`) — v1.0
- ✓ Natywna tabela GLPI (datatable) z kolumnami: Użytkownik, Numer telefonu (+ opcjonalnie serial, status) — v1.0
- ✓ Numer telefonu z pola Fields `nr_telefonu` (auto-detekcja tabeli kontenera) z fallbackiem na natywny Line — v1.0
- ✓ Dedykowane prawo `plugin_simviewer` (READ); od 1.0.3 profile helpdesk dostają READ przy instalacji — v1.0
- ✓ Entity scoping w SQL (`getEntitiesRestrictCriteria`), filtr tekstowy po użytkowniku/numerze — v1.0
- ✓ Locale pl_PL / en_GB — v1.0
- ✓ Czysta instalacja/deinstalacja (prawa + config w `glpi_configs`, bez własnych tabel) — v1.0

### Active

<!-- Current scope. Building toward these. -->

- [ ] Natywny kafelek „Podgląd SIM" na stronie głównej `/Helpdesk` (system Tiles GLPI 11), rejestrowany przy instalacji
- [ ] Usunięcie customowego wstrzykiwania JS (`nav-inject.js`, `nav_selector`, meta tagi) — nawigacja wyłącznie natywna
- [ ] Działający eksport CSV natywnym przyciskiem datatable (config-gated, domyślnie włączony)
- [ ] Domyślne ukrywanie SIM-ów bez przypisanego użytkownika (opcja w konfiguracji przywraca pełną listę)

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Karta szczegółów SIM — decyzja 2026-07-20: tabela w składzie z PRD wystarcza
- Scoping po grupach — entity scoping wystarcza
- Logowanie dostępu (RODO) — decyzja 2026-07-20: bez sensu w tym wdrożeniu
- Widok dla techników w interfejsie standardowym — technicy mają zakładkę adminową „Elementy kart SIM"
- Jakiekolwiek operacje zapisu na SIM/użytkownikach/liniach — wtyczka jest z definicji read-only
- Ekspozycja PIN/PUK/PIN2/PUK2/MSIN — nigdy

## Context

- Produkcja: GLPI 11.0.4 w Dockerze (`glpi/glpi:latest` + MySQL 9.5) na 192.168.9.250 (`glpi.mgw1943.local`); wtyczka w `/home/administrator/glpi/storage/glpi/plugins/simviewer`; deploy przez git pull (docs/deploy-z-laptopa.md)
- Użytkownicy synchronizowani z firmowego AD; logowanie tylko dla pracowników; profil Self-Service (id 1, interfejs helpdesk)
- Encja używa Service Catalog; nowa strona główna `/Helpdesk` (Symfony) NIE renderuje legacy menu wtyczek — stąd potrzeba kafelka
- Id elementów menu (`#menu_…`) są losowe per render — podejście z selektorem z pierwotnego PRD jest martwe
- Dane: 136 SIM-ów w zasięgu encji, z czego 94 bez przypisanego użytkownika (stan na 2026-07-20)
- Plugin Fields 1.24.0 aktywny; kolumna `nrtelefonufield` w tabeli kontenera wykrywana automatycznie
- PRD: docs/PRD-simviewer.md (zaktualizowany 2026-07-20 o domyślne prawo self-service)

## Constraints

- **Tech stack**: PHP / GLPI 11.0.x plugin API, MariaDB/MySQL, Twig — wymóg platformy
- **Compatibility**: GLPI 11.0.0–11.0.99 (`setup.php`); weryfikować API względem gałęzi `11.0/bugfixes`, nie przykładów z GLPI 10
- **Security**: read-only (GET-only front), prawo `plugin_simviewer`, entity scoping w SQL, bez pól wrażliwych
- **Deployment**: katalog wtyczki na serwerze musi nazywać się `simviewer`; podbicie `PLUGIN_SIMVIEWER_VERSION` wymusza ścieżkę aktualizacji w GLPI

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Prawo READ dla profili helpdesk z automatu przy instalacji | Katalog jest budowany dla self-service; czysty kontener ma działać bez ręcznych grantów | ✓ Good |
| Rezygnacja z `ProfileRight::addProfileRights()` na rzecz samego `Migration::addRight()` | addProfileRights robi ślepe INSERT-y i wywala instalację duplikatem | ✓ Good |
| Wpis do `\Profile::$helpdesk_rights` w plugin_init | `cleanProfile()` wycina prawa spoza whitelisty z sesji helpdesk — bez tego wieczne 403 | ✓ Good |
| Nawigacja wyłącznie natywna (helpdesk_menu_entry + kafelek Tiles), usunąć JS injection | Losowe id menu pogrzebały selektor z PRD; użytkownik nie chce customowych rozwiązań | — Pending |
| Eksport CSV domyślnie włączony | Decyzja użytkownika 2026-07-20 (świadomie łagodzi pierwotną rekomendację RODO z PRD) | — Pending |
| SIM-y bez użytkownika domyślnie ukryte | 94/136 pustych wierszy zaśmieca katalog; opcja w konfigu przywraca pełną listę | — Pending |

## Current Milestone: v1.1 Natywna nawigacja i eksport

**Goal:** Użytkownik self-service widzi wejście do katalogu SIM od razu po zalogowaniu (natywnie, bez custom JS), a uprawnieni pobierają katalog do CSV.

**Target features:**
- Natywny kafelek „Podgląd SIM" na `/Helpdesk` rejestrowany przy instalacji
- Usunięcie `nav-inject.js` / `nav_selector` / meta tagów
- Eksport CSV przez natywny mechanizm datatable (domyślnie włączony)
- Domyślne ukrywanie SIM-ów bez przypisanego użytkownika

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-07-20 after starting milestone v1.1*
