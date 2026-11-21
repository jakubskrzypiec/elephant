# SEO i publikacja na elephant.com.pl

Repozytorium jest statyczną stroną. Pliki należy opublikować w katalogu głównym domeny **https://elephant.com.pl/** razem z katalogiem `assets`. Nie wymaga procesu budowania ani serwera Node.js.

## Przygotowane w repo

- Unikalne tytuły, opisy, adresy canonical i podglądy Open Graph dla 16 właściwych stron.
- Jeden H1 na każdej stronie. W artykułach H1 jest pełnym tytułem tekstu, a H2 opisują kolejne zagadnienia. Zachowano styl dotychczasowych nagłówków.
- Dane JSON-LD: Organization, WebSite, WebPage / ContactPage / CollectionPage, Article, BreadcrumbList oraz usługi na stronie oferty. Bez wymyślonych dat publikacji, adresów siedziby, oddziałów i ocen.
- Obszar współpracy potwierdzony przez właściciela projektu: Wrocław, Kraków, Poznań, Łódź, Warszawa i okolice. To obszar usług, nie lista biur.
- `sitemap.xml` obejmuje 16 właściwych stron. Nie zawiera sześciu dawnych szablonów projektów; mają one `noindex, follow`.
- `robots.txt` pozwala na indeksowanie i wskazuje mapę strony.
- `llms.txt` to dodatkowy, tekstowy spis usług i materiałów dla narzędzi, które go obsługują. Nie jest wyszukiwarką ani gwarancją widoczności w AI. Nie dodano pozornej wyszukiwarki ani danych SearchAction, skoro strona nie ma funkcji wyszukiwania.
- Trzy formularze (start, kontakt i zapytanie o projekt) wysyłają POST do FormSubmit na `elephant.interiordesignstudio@gmail.com`. Usunięto przechwytywanie wysyłki przez mailto. Zachowano weryfikację antyspamową i dodano honeypot. Po pierwszym zgłoszeniu właścicielka musi potwierdzić adres linkiem aktywacyjnym FormSubmit; potem należy sprawdzić rzeczywiste doręczenie. Testy techniczne nie wysyłały maili. Calendly wymaga linku do wydarzenia klientki; obecny kalendarz nadal zapisuje preferencję, nie dokonuje rezerwacji.

## Przy uruchomieniu hostingu

1. Włączyć HTTPS oraz przekierowanie HTTP i `www` na `https://elephant.com.pl/`. Przekierować `/index.html` na `/` kodem 301. Pozostałe istniejące adresy `.html` zachować.
2. Skonfigurować 301 ze znanych adresów starej strony: `/contact/` → `/kontakt.html`, `/about/` → `/#omnie`, `/services/` → `/projekty.html`, `/oferta2/` → `/oferty.html`. Przed migracją sprawdzić pozostałe adresy starego WordPressa w Search Console i rozszerzyć mapę przekierowań. Nie przekierowywać wszystkich nieistniejących stron na start.
3. Nie włączać przepisywania dowolnych adresów na `index.html`. Brakujące adresy powinny zwracać HTTP 404.
4. Sprawdzić odpowiedź HTTP 200 dla stron, `robots.txt` i `sitemap.xml`, poprawny typ MIME oraz brak hasła lub blokady indeksowania na hostingu.
5. Zweryfikować domenę w Google Search Console i zgłosić `https://elephant.com.pl/sitemap.xml`. Dodać również Bing Webmaster Tools, jeśli ma być używany.
6. Sprawdzić canonical, dane strukturalne i podglądy udostępniania po publikacji. Ponowić pomiar PageSpeed Insights na docelowym hostingu.

Nie dodano pliku `CNAME`, aby przygotowanie docelowych adresów SEO nie zmieniło działającego podglądu GitHub Pages. Repo jest przygotowane do migracji; domena i jej obecny hosting nie zostały przełączone.

## Aktualizacje

Po dodaniu projektu lub artykułu uzupełnić jego metadane, JSON-LD, linki wewnętrzne, `sitemap.xml` i `llms.txt`. Aktualizować `lastmod` tylko na podstawie rzeczywistej daty istotnej zmiany; obecna mapa celowo nie podaje sztucznych dat. Dane strukturalne muszą odpowiadać treści widocznej na stronie.

Podstawa: [Google Search Essentials](https://developers.google.com/search/docs/essentials), [mapy witryn](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap), [funkcje AI i witryna](https://developers.google.com/search/docs/appearance/ai-features). Widoczność w wyszukiwarkach wymaga publikacji, indeksacji i dalszej obserwacji; nie jest gwarantowana przez samo dodanie plików SEO.
