# Milestones

## v1.0 — Read-only katalog SIM (shipped 2026-07-20)

**Delivered:** Wtyczka `simviewer` 1.0.x na produkcji (glpi.mgw1943.local, GLPI 11.0.4): wpis „Podgląd SIM" w menu interfejsu uproszczonego, natywna datatable Użytkownik + numer telefonu (`nr_telefonu` z pluginu Fields, auto-detekcja kontenera, fallback Line), dedykowane prawo `plugin_simviewer` z automatycznym READ dla profili helpdesk, entity scoping w SQL, filtr tekstowy, konfiguracja admina, locale pl/en.

**Wersje:** 1.0.0 → 1.0.5 (seria hotfixów przy uruchomieniu produkcyjnym):
- 1.0.1 — dostosowanie do zweryfikowanych API GLPI 11 (usunięty deprecated `csrf_compliant`, poprawione `helpHeader`/`header`, meta tagi dla JS, poprawki datatable)
- 1.0.2 — idempotentna instalacja praw (usunięty kolidujący `ProfileRight::addProfileRights()`)
- 1.0.3 — READ dla profili helpdesk z automatu (`addRightByInterface`) + reset cache `all_possible_rights`
- 1.0.4 — wpis prawa do whitelisty `\Profile::$helpdesk_rights` (naprawa 403 dla self-service)
- 1.0.5 — naprawa zapisu formularza konfiguracji (`<button name='update' value='1'>`)

**Znane ograniczenia na koniec v1.0:** brak linku na nowej stronie głównej `/Helpdesk` (losowe id menu unieważniły selektor z PRD; JS injection martwy), eksport CSV nieaktywny (opcja w konfigu bez implementacji), 94/136 SIM-ów bez przypisanego użytkownika widocznych w tabeli.

---
