# Deploy pluginu `simviewer` z laptopa na serwer GLPI

Ten dokument opisuje praktyczny sposób wdrażania zmian w pluginie `simviewer`
(SIM Viewer) z tego laptopa na serwer produkcyjny GLPI.

## Założenia

- Serwer: `192.168.9.250`
- Użytkownik SSH: `administrator` (logowanie po kluczu SSH)
- Katalog GLPI na serwerze: `/home/administrator/glpi`
- Katalog pluginu na serwerze: `/home/administrator/glpi/storage/glpi/plugins/simviewer`
- GLPI działa w Dockerze, więc pliki widoczne w tym katalogu są od razu dostępne dla kontenera
- Plugin to **osobne repozytorium** (inaczej niż `formularze`, które jest częścią repo GLPI):
  `https://github.com/bartosz-skejcik/mgw-simviewer-plugin.git`, gałąź `master`

> Ważne: katalog pluginu na serwerze **musi** nazywać się `simviewer` (slug pluginu),
> nawet jeśli repo na GitHubie nazywa się `mgw-simviewer-plugin`. Dlatego przy
> klonowaniu podajemy nazwę docelowego folderu jawnie (`... simviewer`).

## Pierwszy deploy (folder pluginu jeszcze nie istnieje)

To jednorazowy krok — sklonowanie repo wprost do katalogu wtyczek GLPI.

1. Na laptopie wypchnij kod do zdalnego repo (patrz sekcja "Deploy przez Git").

2. Połącz się z serwerem i sklonuj repo pod właściwą nazwą folderu:

   ```bash
   ssh administrator@192.168.9.250
   cd /home/administrator/glpi/storage/glpi/plugins
   git clone https://github.com/bartosz-skejcik/mgw-simviewer-plugin.git simviewer
   ```

3. Aktywuj plugin w GLPI (patrz sekcja "Aktywacja w GLPI").

## Zalecany wariant: deploy przez Git (kolejne wdrożenia)

To domyślny sposób, gdy zmiany są już w commicie i chcesz przenieść całą wersję na serwer.

1. Na laptopie zrób commit zmian i wypchnij je do zdalnego repo:

   ```powershell
   git add .
   git commit -m "opis zmian"
   git push origin master
   ```

2. Połącz się z serwerem przez SSH:

   ```powershell
   ssh administrator@192.168.9.250
   ```

3. Na serwerze wejdź do katalogu **pluginu** i pobierz zmiany:

   ```bash
   cd /home/administrator/glpi/storage/glpi/plugins/simviewer
   git pull origin master
   ```

4. Jeżeli zmieniałeś wersję pluginu, upewnij się, że `PLUGIN_SIMVIEWER_VERSION`
   (w `setup.php`) została podbita. GLPI wykryje wtedy, że dostępna jest nowa
   wersja i pokaże ją na liście wtyczek (Konfiguracja → Wtyczki).

5. Odśwież GLPI i sprawdź, czy nowa wersja działa poprawnie.

## Aktywacja w GLPI

Plugin `simviewer` jest read-only i nie tworzy własnych tabel (konfiguracja
trafia do rdzeniowej tabeli `glpi_configs`, kontekst `plugin:simviewer`).

1. Zaloguj się do GLPI jako administrator (interfejs standardowy).
2. Wejdź w **Konfiguracja → Wtyczki** (Setup → Plugins).
3. Przy „SIM Viewer” kliknij **Instaluj**, a potem **Aktywuj**.
   - Instalacja rejestruje prawo `plugin_simviewer` (READ) na wszystkich profilach,
     domyślnie **bez dostępu** — nadanie prawa to świadoma decyzja administratora.
   - Profile mogące edytować konfigurację GLPI (super-admin) dostają READ od razu.
4. Nadaj prawo wybranym profilom: **Administracja → Profile → [profil] → zakładka
   „SIM Viewer” → Odczyt**. To samo dotyczy profilu self-service, który ma widzieć
   katalog numerów.
5. **Wyloguj i zaloguj ponownie** użytkownika self-service — GLPI buduje menu przy
   logowaniu, więc nowy wpis w górnym pasku pojawi się dopiero po przelogowaniu.

> Alternatywnie instalację/aktywację można zrobić z konsoli GLPI w kontenerze:
> `docker exec -u www-data <nazwa_kontenera_glpi> php bin/console plugin:install simviewer`
> oraz `... plugin:activate simviewer`.

## Szybki hotfix: deploy pojedynczych plików przez `scp`

Wygodne, gdy trzeba szybko wrzucić tylko kilka plików bez pełnego `git pull`.

1. Skopiuj zmienione pliki z laptopa na serwer (zachowując podkatalogi):

   ```powershell
   scp .\setup.php administrator@192.168.9.250:/home/administrator/glpi/storage/glpi/plugins/simviewer/
   scp .\public\js\nav-inject.js administrator@192.168.9.250:/home/administrator/glpi/storage/glpi/plugins/simviewer/public/js/
   ```

2. Pliki klas kopiuj do `src/`, szablony do `templates/`, tłumaczenia do `locales/`
   itd. — zawsze do odpowiadającego podkatalogu w
   `/home/administrator/glpi/storage/glpi/plugins/simviewer`.

3. Po skopiowaniu sprawdź na serwerze, że pliki faktycznie są w katalogu pluginu.

4. Jeśli zmiana dotyczy instalacji/migracji albo prawa pluginu, podbij wersję
   (`PLUGIN_SIMVIEWER_VERSION`) i zrób pełny deploy przez Git, żeby GLPI wykrył aktualizację.

## Kiedy trzeba zrobić coś dodatkowo

- **Tłumaczenia**: jeśli zmieniasz `locales/*.po`, przekompiluj `.mo` przed commitem.
  Na laptopie bez `msgfmt` można użyć dołączonego skryptu:
  `python tools/po2mo.py` (kompiluje wszystkie `.po` w `locales/` do `.mo`).
  GLPI ładuje tłumaczenia z plików `.mo`.
- **Zależności PHP**: plugin nie ma zależności produkcyjnych (`composer.json` nie
  wymaga pakietów runtime). Gdyby się pojawiły, uruchom na serwerze
  `composer install --no-dev` w katalogu pluginu.
- **Pliki frontendowe w `public/`**: GLPI 11 serwuje zasoby pluginu z katalogu
  `public/` (stąd `public/js/nav-inject.js`). Zwykle wystarczy `git pull`/`scp`.
- **Selektor górnego menu**: link do widoku jest wstrzykiwany do paska nawigacji
  w kotwicy `#menu_1595890973` (konfigurowalne w ustawieniach pluginu). Jeśli na
  docelowym GLPI id menu jest inne, zmień je w **Konfiguracja → Wtyczki → SIM Viewer**.
- Jeśli po deployu coś się nie odświeża w przeglądarce, zrób twardy refresh albo
  sprawdź, czy numer wersji pluginu się zmienił.

## Szybka checklista po deployu

- Zmiany są na serwerze w `/home/administrator/glpi/storage/glpi/plugins/simviewer`
- GLPI widzi plugin „SIM Viewer” na liście wtyczek (i nową wersję po podbiciu)
- Plugin zainstalowany i aktywny; prawo `plugin_simviewer` nadane właściwym profilom
- Użytkownik self-service po przelogowaniu widzi wpis w górnym pasku i tabelę
- Nie ma błędów w logach GLPI ani w kontenerze

## Uwagi praktyczne

- Najbezpieczniej deployować przez Git, a `scp` zostawić na małe hotfixy.
- Jeśli na serwerze pojawią się konflikty po `git pull`, rozwiąż je przed dalszym wdrożeniem.
- Folder pluginu na serwerze musi nazywać się `simviewer` — inaczej GLPI go nie wykryje.
- Po zmianie wersji pluginu pamiętaj, że to ona uruchamia ścieżkę aktualizacji po stronie GLPI.
