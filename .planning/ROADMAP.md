# Roadmap: SIM Viewer

## Overview

Milestone v1.1 „Natywna nawigacja i eksport" domyka dwa znane braki v1.0 na produkcji (glpi.mgw1943.local, GLPI 11.0.4): brak wejścia do katalogu na nowej stronie głównej `/Helpdesk` oraz martwy eksport CSV. Najpierw nawigacja staje się w pełni natywna — kafelek w systemie Tiles GLPI 11 rejestrowany przy instalacji, a cały customowy JS injection (`nav-inject.js`, `nav_selector`, meta tagi) znika. Następnie datatable dostaje działający natywny eksport CSV (config-gated, domyślnie włączony) i domyślny filtr ukrywający 94 SIM-y bez przypisanego użytkownika. Każda faza kończy się wdrożeniem na produkcję (git pull + `plugin:install`/`plugin:activate` w konsoli dockera) z podbiciem `PLUGIN_SIMVIEWER_VERSION`.

## Milestones

- ✅ **v1.0 Read-only katalog SIM** — shipped 2026-07-20 jako 1.0.0→1.0.5, przed GSD (bez faz GSD; zob. `.planning/MILESTONES.md`)
- 🚧 **v1.1 Natywna nawigacja i eksport** — Phases 1-2 (in progress)

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [ ] **Phase 1: Natywna nawigacja** - Kafelek „Podgląd SIM" na `/Helpdesk` (system Tiles) + usunięcie JS injection
- [ ] **Phase 2: Eksport CSV i filtr pustych SIM-ów** - Natywny eksport datatable (config-gated) + domyślne ukrycie SIM-ów bez użytkownika

## Phase Details

### Phase 1: Natywna nawigacja

**Goal**: Użytkownik self-service trafia do katalogu SIM wyłącznie natywnymi mechanizmami GLPI — kafelek na stronie głównej `/Helpdesk`, bez customowego wstrzykiwania JS
**Depends on**: Nothing (first phase)
**Requirements**: NAV-01, NAV-02
**Success Criteria** (what must be TRUE):

  1. Zalogowany użytkownik profilu Self-Service widzi na stronie głównej `/Helpdesk` (glpi.mgw1943.local) kafelek „Podgląd SIM", a jego kliknięcie otwiera katalog SIM
  2. Kafelek pojawia się automatycznie po instalacji/aktualizacji wtyczki (ścieżka `plugin:install` oraz update po podbiciu `PLUGIN_SIMVIEWER_VERSION`) — bez ręcznej konfiguracji kafelków; deinstalacja usuwa kafelek z `/Helpdesk`
  3. Strony self-service nie ładują `public/js/nav-inject.js` ani meta tagów `simviewer:*` (weryfikowalne w źródle strony / zakładce network), a opcja `nav_selector` znika z formularza konfiguracji i z zapisanej konfiguracji
  4. Wpis „Podgląd SIM" w menu interfejsu uproszczonego (`helpdesk_menu_entry`) nadal działa jak w v1.0 (brak regresji)

**Plans**: 1/2 plans executed

- [x] 01-01-PLAN.md — Rejestracja natywnego kafelka Tiles + usunięcie JS injection, migracja configu, bump 1.1.0
- [ ] 01-02-PLAN.md — Checkpoint: deploy na GLPI 11.0.4 i weryfikacja w przeglądarce (SC1–SC4)

**UI hint**: yes

### Phase 2: Eksport CSV i filtr pustych SIM-ów

**Goal**: Uprawniony użytkownik pobiera katalog do CSV natywnym przyciskiem datatable, a tabela domyślnie pokazuje wyłącznie SIM-y z przypisanym użytkownikiem
**Depends on**: Phase 1 (kolejność wdrożeń; technicznie niezależna)
**Requirements**: EXP-01, EXP-02, TBL-01
**Success Criteria** (what must be TRUE):

  1. Uprawniony użytkownik widzi na datatable natywny przycisk eksportu (mechanizm `csv_url`) i pobiera plik CSV odzwierciedlający bieżący widok — po filtrze tekstowym i entity scopingu, wyłącznie kolumny katalogu, nigdy PIN/PUK/PIN2/PUK2/MSIN
  2. Wyłączenie opcji `enable_export` w konfiguracji ukrywa przycisk eksportu, a bezpośrednie wywołanie endpointu CSV kończy się błędem uprawnień; po aktualizacji wtyczki opcja jest domyślnie WŁĄCZONA
  3. Katalog domyślnie nie pokazuje SIM-ów bez przypisanego użytkownika (na produkcji: 42 wiersze zamiast 136), a eksport CSV respektuje ten sam filtr
  4. Włączenie nowej opcji `show_unassigned` w konfiguracji przywraca pełną listę (136 wierszy na produkcji)

**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Natywna nawigacja | 1/2 | In Progress|  |
| 2. Eksport CSV i filtr pustych SIM-ów | 0/TBD | Not started | - |
