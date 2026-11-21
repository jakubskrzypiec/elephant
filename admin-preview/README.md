# Panel ELEPHANT — podgląd przed podłączeniem hostingu

Otwórz `index.html` w przeglądarce lub uruchom statyczny serwer w katalogu repozytorium i wejdź na `/admin-preview/`.

To działający **prototyp interfejsu**, nie publiczny panel produkcyjny. Dane są zapisywane w `localStorage` tej przeglądarki. Nie ma logowania, bazy serwerowej, wysyłki e-mail ani publikowania zmian na stronie. Informacja o tym jest stale widoczna. Nie wpisuj tutaj prawdziwych danych klientów na współdzielonym komputerze.

## Przygotowane

- Pulpit z liczbą realizacji, artykułów, nowych zapytań i spotkań.
- Pięć realizacji oraz sześć artykułów zaimportowanych z obecnej strony.
- Dodawanie, edycja, usuwanie, wyszukiwanie i filtrowanie treści.
- Zdjęcia realizacji: dodawanie JPG/PNG/WebP, kolejność, opisy alternatywne, pierwsze zdjęcie jako okładka.
- Kolejność realizacji i wybór projektów do karuzeli strony głównej.
- Artykuły: tytuł, adres, opis, treść, status szkicu, podgląd i pola SEO.
- Przykładowe zapytania: szczegóły, status i prywatna notatka. Bez pozornej wysyłki odpowiedzi.
- Kalendarz: przegląd miesiąca, szczegóły spotkań, ręczne dodanie, potwierdzanie i anulowanie.
- Dostępność: godziny każdego dnia tygodnia, wyłączone weekendy, pojedyncze blokady, długość spotkania, przerwy i wyprzedzenie.
- Walidacja lokalnych spotkań: godziny pracy, blokady, wyprzedzenie i kolizje z przerwą między spotkaniami.
- Ustawienia danych pracowni, eksport lokalnej kopii JSON, układ mobilny i obsługa klawiaturą.

Usunięcie danych witryny w przeglądarce przywraca materiał startowy. Kopia JSON jest do pobrania; import kopii nie jest jeszcze implementowany.

## Następny etap po otrzymaniu dostępu

1. Zweryfikować dostawcę, pakiet, PHP, MySQL/MariaDB, HTTPS, SFTP oraz możliwość skonfigurowania SMTP i zadania cron.
2. Wykonać kopię starej strony WordPress oraz bazy. Uzgodnić sposób przeniesienia i przekierowania starych adresów. Nie nadpisywać działającej strony bez planu migracji.
3. Utworzyć prywatną bazę i użytkownika z ograniczonymi uprawnieniami. Punkt wyjścia: `../panel-backend/schema.sql`. Sprawdzić wersję MySQL/MariaDB przed importem.
4. Uruchomić API PHP i logowanie: hashowane hasło, rotacja sesji, HttpOnly / Secure / SameSite cookies, CSRF, limity logowania, autoryzacja każdego prywatnego endpointu. Brak domyślnego hasła w repo.
5. Zastąpić lokalny zapis adapterem API. Migracja istniejących pięciu realizacji i sześciu artykułów. Identyfikatory obecnych adresów pozostawić bez zmian.
6. Zbudować publikowanie stron z zapisanej treści: portfolio, karuzela, galerie, artykuły, metadane oraz sitemap. Treść i adresy walidować; treści użytkownika nie wstawiać jako dowolnego HTML.
7. Podłączyć formularze do publicznego endpointu PHP: walidacja serwerowa, zgoda, ochrona przed spamem, zapis zgłoszenia, kolejka powiadomień do `elephant.kontakt@gmail.com`. Status wysyłki powinien wynikać z odpowiedzi SMTP, a nie z samego zapisu rekordu.
8. Podłączyć własny kalendarz do API dostępności i rezerwacji. Na serwerze ponownie sprawdzać godziny, weekendy, wyjątki, strefę Europe/Warsaw, wyprzedzenie i kolizje. W transakcji blokować dzień rezerwacji, żeby dwa jednoczesne zapytania nie zajęły nachodzących terminów. Zapis dat w bazie UTC; prezentacja Europe/Warsaw z uwzględnieniem zmiany czasu.
9. Powiadomienia o zapytaniach, potwierdzenia i anulowanie spotkań przesyłać przez SMTP i kolejkę z ponowieniami. Potwierdzenie w panelu ma zmienić status i wygenerować powiadomienie. Nie podłączać automatycznie spotkań Google Meet bez autoryzowanej integracji.
10. Sprawdzić pełny przepływ na hostingu testowym: logowanie, edycja i publikacja, zdjęcia, formularz → baza → e-mail, rezerwacja → blokada terminu → powiadomienia, uprawnienia oraz kopie danych.
11. Dopiero po tych testach zastąpić działające FormSubmit i Calendly. Właściwy panel ma adres `/panel/`; katalog podglądu nie zawiera produkcyjnych danych.

Nie zmieniono strony publicznej, jej formularzy ani Calendly. Podgląd przygotowano na osobnej gałęzi `feat/admin-panel`.

## Kontrakt API do połączenia z tym interfejsem

Wszystkie prywatne endpointy wymagają sesji, a mutacje dodatkowo tokenu CSRF. Nie umożliwiać odczytu zapytań ani danych rezerwacji przez API publiczne.

| Endpoint | Zastosowanie |
| --- | --- |
| `POST /api/login.php`, `POST /api/logout.php` | Sesja administratorki |
| `GET /api/session.php` | Użytkownik oraz token CSRF |
| `GET/POST/PATCH/DELETE /api/projects.php` | Realizacje i kolejność karuzeli |
| `POST /api/uploads.php` | Zdjęcia, walidacja MIME i rozmiaru, losowe nazwy, brak wykonywania skryptów |
| `GET/POST/PATCH/DELETE /api/articles.php` | Artykuły i SEO |
| `GET/PATCH /api/inquiries.php` | Prywatne zapytania, statusy, notatki |
| `GET/POST/PATCH /api/bookings.php` | Prywatna obsługa spotkań |
| `GET/PUT /api/availability.php` | Tydzień, blokady, zasady rezerwacji |
| `GET/PUT /api/settings.php` | Dane pracowni; bez zwracania hasła SMTP do przeglądarki |
| `POST /api/contact.php` | Publiczny zapis zgłoszenia z formularza |
| `GET /api/slots.php?date=YYYY-MM-DD` | Publiczna lista wolnych godzin, bez danych klientów |
| `POST /api/reserve.php` | Publiczna rezerwacja z ponowną walidacją terminu |

To kontrakt do wdrożenia, **nie lista działających obecnie endpointów**. Hasła i konfiguracja SMTP muszą trafić poza publiczny katalog hostingu i poza repozytorium.
