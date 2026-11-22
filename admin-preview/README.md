# Panel ELEPHANT — interfejs

Ten katalog zawiera statyczny prototyp. Uruchomienie /admin-preview/ zapisuje dane
wyłącznie w localStorage, bez logowania i prawdziwej wysyłki. Dane klientów są przykładowe.
Nie wprowadzać prywatnych zgłoszeń do tego podglądu.

Właściwy panel PHP korzysta z tego samego interfejsu i czcionki Montserrat, ale po
zalogowaniu pobiera dane z API i zapisuje je w bazie. Adres: /panel/.
Działa edycja i publikowanie realizacji, galerii, karuzeli oraz artykułów; skrzynka
formularzy, kalendarz konsultacji, potwierdzanie, anulowanie i dostępność.

Konfiguracja, rzeczywisty kontrakt API, testy i kroki wdrożenia:
[panel-backend/README.md](../panel-backend/README.md).

Gałąź: feat/admin-panel. Publiczna wersja statyczna i WordPress pozostają niezależne
od lokalnego serwera PHP do czasu świadomego przełączenia hostingu.
