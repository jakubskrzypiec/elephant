# Panel ELEPHANT — PHP 8.4

Właściwy panel `/panel/` zapisuje dane w bazie, publikuje realizacje, karuzelę,
galerie, artykuły i sitemapę. Korzysta z istniejących szablonów i arkuszy strony.
W wersji PHP formularze i własny kalendarz zastępują FormSubmit oraz Calendly.
Oddzielny `/admin-preview/` pozostaje prototypem działającym w localStorage.

## Lokalnie

Wymagane rozszerzenia PHP: PDO SQLite, PDO MySQL, GD/WebP, Fileinfo, OpenSSL, mbstring.
Ścieżkę PHP można ustawić w `ELEPHANT_PHP`.

```powershell
./panel-backend/local.ps1 -Action Start
```

Strona: http://127.0.0.1:4181/ . Panel: http://127.0.0.1:4181/panel/ .
Instalator generuje losowe hasło w `%LOCALAPPDATA%/ElephantPanel/local/local-login.txt`.
Konfiguracja, baza, przesłane zdjęcia i wiadomości testowe są poza repozytorium.
`local.ps1 -Action Mail` obsługuje lokalną kolejkę jako prywatne pliki `.eml`.
Transport `log` nie wysyła poczty; jej status to `simulated`, a nie `sent`.

## API i zapis

Endpoint: `api.php?action=NAZWA`.

| Akcja | Metoda | Dostęp |
| --- | --- | --- |
| session, public | GET | Sesja/CSRF i publiczna dostępność, bez danych klientów |
| login, logout | POST | CSRF, limit prób, rotacja sesji |
| slots&date=YYYY-MM-DD | GET | Wolne godziny bez danych klientów |
| contact, reserve | POST | CSRF, zgoda i walidacja serwerowa |
| state | GET, PUT | Administrator; PUT wymaga CSRF i aktualnej rewizji |
| upload | POST | Administrator, CSRF, pole `photo`, JPG/PNG/WebP do 2 MB |
| delivery | GET | Administrator; stan kolejki pocztowej |
| image&file=... | GET | Opublikowane zdjęcie lub administrator dla szkicu |

Treści są escapowane; artykuły przyjmują tekst, puste wiersze i `## Nagłówki`.
Zdjęcia są dekodowane i ponownie zapisywane jako WebP poza web rootem.
Zmiana adresu wpisu zachowuje przekierowanie 301. Konflikt rewizji 409 chroni nowe
zgłoszenia przed nadpisaniem starszą kartą panelu.

Treści, zapytania i kalendarz stanowią jedną wersjonowaną strukturę JSON w
`elephant_state`, blokowaną transakcyjnie podczas zapisu. Pozostałe tabele służą
kontom, limitom i kolejce pocztowej. Źródłem schematu jest `migrate.php`.
To panel jednej niewielkiej pracowni, nie wielodostępny CMS.

## Wdrożenie cyber_Folks

Potwierdzono DirectAdmin, PHP 8.4 i SSL. WordPress jest w
`domains/elephant.com.pl/public_html`. Główny skrót `public_html` konta prowadzi
do domeny technicznej, więc nie służy do wdrożenia ELEPHANT.

1. Wykonać i pobrać kopię plików WordPress i eksport jego bazy. Zachować obecną
   stronę oraz `.htaccess`; przygotować osobny katalog testowy i osobną bazę.
2. Potwierdzić wersję MySQL/MariaDB, InnoDB, wymagane rozszerzenia PHP i dostęp do
   zadań cron. Wgrać aplikację do katalogu testowego zablokowanego przed indeksowaniem.
3. `deploy/apache.htaccess` skopiować jako `.htaccess` wyłącznie nowej aplikacji.
   HTML musi przechodzić przez `site.php` mimo istnienia plików szablonów.
4. Utworzyć prywatny katalog poza web rootem. Skopiować tam `config.example.php`
   jako `config.php`, uzupełnić bazę, losowy sekret i autoryzowane SMTP. Domyślna
   ścieżka to `../elephant-private/config.php` względem web root; można użyć
   `ELEPHANT_CONFIG`. W podkatalogu ustawić `base_path` i `site_url` z jego adresem.
5. Uruchomić CLI: `php panel-backend/migrate.php`, następnie
   `php panel-backend/create-admin.php EMAIL /prywatna/sciezka/haslo.txt`.
   Hasło 14–72 bajty. Nie ma domyślnego konta. Skrypty odmawiają działania przez HTTP.
   Przy nieaktywnym SSH można wykonać te polecenia jednorazowym zadaniem cron
   z właściwym katalogiem roboczym, konfiguracją i ścieżką PHP.
6. Ustawić cron co minutę: `php /sciezka/aplikacji/panel-backend/worker.php 20`.
   Kolejka ponawia błędy SMTP do 5 prób. `sent` oznacza sukces SMTP; sprawdzić też
   doręczenie na uzgodnioną skrzynkę. Odbiorca startowo: `elephant.kontakt@gmail.com`.
   Sam adres odbiorcy Gmail nie konfiguruje nadawcy SMTP.
7. Sprawdzić na hostingu: HTTPS, uprawnienia, publikację, upload, formularz → panel
   → mail i rezerwację → potwierdzenie/anulowanie → mail. Godziny Europe/Warsaw,
   początek i koniec spotkania zapisane także w UTC.
8. Po testach przełączyć domenę, zachowując kopię i możliwość powrotu. Przygotować
   przekierowania starych URL WordPress, canonical, robots i sitemapę.

## Testy

```powershell
node panel-backend/tests/integration.cjs
$env:PLAYWRIGHT_MODULE = '/sciezka/do/playwright'
node panel-backend/tests/browser.cjs
```

Testy używają odizolowanych lokalnych baz i nie wysyłają prawdziwych maili.
Sprawdzają logowanie, uprawnienia, CSRF, rewizje, formularze, rezerwacje i kolizje,
weekendy, upload, publikację/szkice, sitemapę oraz przepływ w Edge i WebKit.
Testy SQLite nie zastępują testów MySQL, Apache i SMTP na docelowym serwerze.
