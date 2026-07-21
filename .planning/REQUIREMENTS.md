# Requirements: SIM Viewer

**Defined:** 2026-07-20
**Core Value:** Pracownik self-service w kilka sekund znajduje służbowy numer telefonu współpracownika — bez dostępu do zasobów, edycji ani wrażliwych pól SIM.

## v1.1 Requirements

Requirements for milestone v1.1 „Natywna nawigacja i eksport". Each maps to roadmap phases.

### Nawigacja (NAV)

- [x] **NAV-01**: Użytkownik self-service widzi na stronie głównej `/Helpdesk` natywny kafelek „Podgląd SIM" (system Tiles GLPI 11, `ExternalPageTile`/`TilesManager`) otwierający katalog SIM; kafelek jest rejestrowany automatycznie przy instalacji/aktualizacji wtyczki
- [x] **NAV-02**: Wtyczka nie zawiera customowego wstrzykiwania nawigacji — `public/js/nav-inject.js`, opcja `nav_selector` i meta tagi `simviewer:*` są usunięte; nawigacja opiera się wyłącznie na natywnych mechanizmach GLPI (`helpdesk_menu_entry` + kafelek); deinstalacja sprząta kafelek

### Eksport (EXP)

- [ ] **EXP-01**: Uprawniony użytkownik pobiera aktualnie widoczny katalog (po filtrze i entity scopingu, bez pól wrażliwych) jako CSV natywnym przyciskiem eksportu datatable (`csv_url`)
- [ ] **EXP-02**: Eksport jest sterowany opcją `enable_export` (domyślnie WŁĄCZONY — decyzja 2026-07-20); wyłączenie ukrywa przycisk i blokuje endpoint eksportu po stronie serwera

### Tabela (TBL)

- [ ] **TBL-01**: SIM-y bez przypisanego użytkownika są domyślnie ukryte; nowa opcja konfiguracji `show_unassigned` (domyślnie 0) przywraca pełną listę

## Future Requirements

(brak — przyszłe pomysły trafiają tu po dyskusji)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Karta szczegółów SIM | Decyzja 2026-07-20 — tabela w składzie z PRD wystarcza |
| Scoping po grupach | Entity scoping wystarcza |
| Logowanie dostępu (RODO) | Decyzja 2026-07-20 — niepotrzebne w tym wdrożeniu |
| Widok dla techników (interfejs standardowy) | Technicy mają zakładkę adminową „Elementy kart SIM" |
| Operacje zapisu na SIM/użytkownikach/liniach | Wtyczka jest z definicji read-only |
| Ekspozycja PIN/PUK/PIN2/PUK2/MSIN | Nigdy — wymóg bezpieczeństwa z PRD |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| NAV-01 | Phase 1 | Complete |
| NAV-02 | Phase 1 | Complete |
| EXP-01 | Phase 2 | Pending |
| EXP-02 | Phase 2 | Pending |
| TBL-01 | Phase 2 | Pending |

**Coverage:**

- v1.1 requirements: 5 total
- Mapped to phases: 5
- Unmapped: 0 ✓

---
*Requirements defined: 2026-07-20*
*Last updated: 2026-07-20 after roadmap creation (traceability mapped)*
