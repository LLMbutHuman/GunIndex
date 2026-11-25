<?php
return [
  'header' => <<<'MD'
**Status: Niezweryfikowany projekt. Weryfikacja merytoryczna i, w razie potrzeby, weryfikacja przez prawnika pozostają do przeprowadzenia.**
**Wersja dokumentu: draft-1.0 · Język: polski.**
**Operator:** [REQUIRED: pełna nazwa prawna, adres i dane kontaktowe].
**Obszar obowiązywania umowy:** [REQUIRED: kraje, dla których faktycznie udostępniono usługi, i niezbędne informacje dla konsumentów w poszczególnych krajach].

Projekt opisuje wyłącznie funkcje faktycznie dopuszczone do użytku. Nie gwarantuje skuteczności prawnej. Do publikacji w charakterze obowiązującej treści umowy wymagane jest udokumentowane zatwierdzenie przez specjalistę. Dane operatora, praktyka prowadzenia działalności i rzeczywiste interfejsy składania zamówień muszą być ze sobą zgodne.
MD,
  'documents' => [
    'terms' => [
      'title' => 'Ogólne warunki umów',
      'provisions' => <<<'MD'
## 1. Strony umowy i zakres stosowania
Niniejsze warunki dotyczą dostępu do platformy technicznej oraz wyraźnie zamówionych usług platformy. Operator pośredniczy w udostępnianiu ogłoszeń użytkowników. Co do zasady kupujący i sprzedawca zawierają umowę sprzedaży towaru między sobą. Platforma nie staje się w ten sposób właścicielem ani sprzedawcą towaru i nie przejmuje automatycznie wysyłki, przekazania ani przechowania. To, czy dana funkcja nakłada na operatora dodatkowe obowiązki, zależy od jej faktycznego działania i bezwzględnie obowiązujących przepisów.

## 2. Konto, ogłoszenia i zawieranie umów
Dane konta muszą być zgodne z prawdą. Nie wolno udostępniać danych logowania, a podejrzane przypadki dostępu należy zgłaszać niezwłocznie. Sprzedawcy mogą oferować wyłącznie dostępne i zgodne z prawem towary ogólne. Muszą rzetelnie podawać stan towaru, cenę, warunki dostawy i ewentualny status przedsiębiorcy. Zawarcie umowy w przypadku aukcji i ogłoszeń ze stałą ceną regulują odrębne warunki. Udostępnienie kategorii lub weryfikacja tożsamości nie stanowią ogólnego prawnego zezwolenia na handel.

## 3. Usługi, zmiany i zakończenie umowy
Zakres, cenę całkowitą i okres świadczenia odpłatnych usług wyświetla się przed złożeniem zamówienia i zapisuje wraz z właściwą wersją warunków. Zasad dostępności, moderacji i płatności nie wolno zmieniać z mocą wsteczną bez ujawnienia zmian. Przy istotnych zmianach umowy należy ustalić, czy konieczne jest powiadomienie użytkownika lub ponowne uzyskanie jego wyraźnej zgody. Dalsze korzystanie z platformy nie zastępuje oświadczenia wymaganego przez prawo. Użytkownicy mogą żądać dezaktywacji konta i rozpatrzenia możliwości usunięcia danych na podstawie przepisów o ochronie danych.

## 4. Obowiązki i odpowiedzialność
Platforma musi wykonywać własne ustawowe obowiązki w zakresie weryfikacji, informowania i reagowania. Nie stosuje się prawnie niedopuszczalnych wyłączeń odpowiedzialności za umyślne działanie, rażące niedbalstwo, szkody wynikające z utraty życia, uszkodzenia ciała lub rozstroju zdrowia ani odpowiedzialności wynikającej z bezwzględnie obowiązujących przepisów, w szczególności z niemieckiej ustawy o odpowiedzialności za produkt (Produkthaftungsgesetz), lub z udzielonych gwarancji. W razie naruszenia istotnych obowiązków umownych wskutek lekkiego niedbalstwa ograniczenie odpowiedzialności do przewidywalnej szkody typowej dla danej umowy jest możliwe jedynie w zakresie dozwolonym przez prawo. Zwolnienie operatora z odpowiedzialności przez użytkownika wymaga naruszenia prawa, za które odpowiada użytkownik, oraz uzasadnionych roszczeń. Uwzględnia się przyczynienie poszkodowanego i przysługujące zarzuty.

## 5. Weryfikacja przed publikacją
[REQUIRED: poddać specjalistycznej weryfikacji prawo właściwe, dopuszczalną klauzulę właściwości sądu dla przedsiębiorców, informacje dla konsumentów, komunikację dotyczącą umów i szczegółowy opis usług.]
MD,
    ],
    'private-use' => [
      'title' => 'Warunki korzystania dla użytkowników prywatnych',
      'provisions' => <<<'MD'
## Konto i dozwolone korzystanie
Użytkownicy prywatni działają we własnych celach prywatnych. Nie wolno przedstawiać działalności gospodarczej jako sprzedaży prywatnej. Nazwy kont nie mogą naruszać praw osób trzecich ani wprowadzać w błąd co do urzędowej weryfikacji lub sugerować, że przeprowadzono weryfikację, której faktycznie nie było. Wymagane dane konta muszą być aktualne. Niepotrzebnych dokumentów tożsamości nie wolno przesyłać w publicznych ogłoszeniach ani wiadomościach prywatnych.

## Ogłoszenia i kontakt
Ogłoszenia muszą w zrozumiały sposób opisywać towar faktycznie dostępny. Przed zawarciem umowy należy ustalić cenę, stan towaru, warunki odbioru, koszty wysyłki i widoczne wady. Zdjęcia można publikować tylko w przypadku posiadania odpowiednich praw do ich wykorzystania. Czat wewnętrzny służy do komunikacji dotyczącej konkretnego ogłoszenia. Zabronione są nękanie, masowa reklama, phishing, obchodzenie blokad oraz publikowanie cudzych prywatnych treści.

## Prawa i zakończenie korzystania
Bezpłatne prywatne ogłoszenia ze stałą ceną są dostępne wyłącznie w opublikowanym zakresie usług. Odpłatne wyróżnienie wymaga odrębnego zamówienia. Operator nie gwarantuje sprzedaży ani określonego zasięgu. Użytkownicy mogą zarządzać zapisanymi ogłoszeniami i funkcjami osobistymi w granicach faktycznie dostępnych możliwości oraz wnioskować o dezaktywację konta. Otwarte umowy, ustawowe obowiązki przechowywania i uzasadniona potrzeba zachowania dowodów na potrzeby sporów mogą uniemożliwić natychmiastowe usunięcie poszczególnych danych w całości. Decyzje muszą mieć zrozumiałe uzasadnienie i podlegać weryfikacji.

[REQUIRED: sprawdzić minimalny wiek posiadacza konta, dozwolone kraje, faktyczne limity bezpłatnych usług i zasady potwierdzania umowy.]
MD,
    ],
    'dealer' => [
      'title' => 'Dodatkowe warunki dla sprzedawców prowadzących działalność gospodarczą',
      'provisions' => <<<'MD'
## Dane przedsiębiorcy
Sprzedawcy prowadzący działalność gospodarczą muszą ujawniać swój rzeczywisty status i przekazywać w pełnym zakresie wymagane prawem dane przedsiębiorstwa, dane kontaktowe, rejestrowe i podatkowe oraz informacje dla konsumentów. Operator platformy sprawdza w przewidzianym trybie dane sprzedawców, do których weryfikacji zobowiązują go przepisy. Publiczny profil ani przesłane logo nie zastępują zezwolenia organu lub zatwierdzenia przez specjalistę.

## Ogłoszenia i obowiązki wobec konsumentów
W ramach własnych umów sprzedaży sprzedawcy odpowiadają za prawidłowy opis towaru, cenę całkowitą, koszty dostawy i dostępność, a także za ustawowe uprawnienia w razie wad oraz wymagane informacje o prawie odstąpienia. Platforma nadal samodzielnie wykonuje własne obowiązki ustawowe. Wystawianie ogłoszeń w różnych krajach wymaga ustalenia zasad dostawy i opodatkowania, informacji dla konsumentów oraz tego, czy dany rodzaj produktu jest dopuszczalny w każdym kraju, do którego kierowane jest ogłoszenie.

## Zarządzanie i reklama
Masowe importy muszą być zgodne z określonymi formatami i zasadami dotyczącymi produktów. Wierszy z błędami nie wolno wykazywać jako zaimportowanych pomyślnie. Gdy dostęp dla pracowników zostanie faktycznie udostępniony, będą oni potrzebować odrębnych uprawnień. Nie przewiduje się wspólnych, niekontrolowanych kont administracyjnych. Usługi reklamowe podlegają odrębnej rezerwacji, weryfikacji i warunkom reklamowym. Platforma nie gwarantuje wielkości sprzedaży ani uzyskania wymaganych prawem zezwoleń na prowadzenie działalności handlowej.

[REQUIRED: sprawdzić weryfikację tożsamości, dane kontaktowe sprzedawcy, dozwolone obszary sprzedaży, komunikację dotyczącą umów i ewentualne obowiązki wynikające z rozporządzenia P2B.]
MD,
    ],
    'auction' => [
      'title' => 'Warunki aukcji towarów ogólnych',
      'provisions' => <<<'MD'
## Zakres ogłoszeń i wiążące informacje
Aukcje są dozwolone wyłącznie dla towarów ogólnych dopuszczonych do obrotu na platformie. Sprzedawcy ustalają cenę wywoławczą, czas trwania, warunki przekazania oraz opcję „Kup teraz”, jeżeli jest dostępna. Licytowanie własnych aukcji, zmowa z fikcyjnymi licytującymi oraz sztuczne zawyżanie cen są zabronione. Tożsamość posiadacza konta i kategoria ogłoszenia nie zastępują wymaganego prawem dopuszczenia produktu do obrotu.

## Oferty licytacyjne i zakończenie aukcji
Maksymalna kwota oferty licytującego jest poufna. Mechanizm automatycznego licytowania podnosi widoczną kwotę wyłącznie zgodnie z opublikowanymi zasadami postąpienia. Przy równych maksymalnych kwotach system przyznaje pierwszeństwo ofercie przyjętej wcześniej. Ważna oferta złożona w ostatnich dwóch minutach przedłuża aukcję do co najmniej dwóch minut od jej przyjęcia. Rozstrzygający jest czas przyjęcia oferty przez serwer. Oferty spóźnione, niedozwolone lub składane przez sprzedawcę we własnej aukcji są odrzucane.

## Umowa i zakłócenia
[REQUIRED: na podstawie faktycznego interfejsu ustalić ze specjalistą dokładny moment zawarcia umowy i oświadczenia prowadzące do jej zawarcia.] Zwycięzca i sprzedawca otrzymują informacje niezbędne do wykonania umowy za pośrednictwem dostępnych kanałów kontaktu. Stan weryfikacji w razie rozbieżności danych nie uprawnia do dowolnego wyznaczenia zwycięzcy. Nie wolno istotnie zmieniać aukcji po złożeniu ofert, bezpodstawnie kończyć jej przed terminem ani obchodzić ważnych umów. Zakłócenia techniczne, kwestionowanie ważności umowy, uzasadnione wycofanie oraz udokumentowane przypadki niedojścia umów do skutku należy rozpatrywać zgodnie z udokumentowaną procedurą. Opłaty powstają jedynie po zdarzeniach uzgodnionych w tym celu. Niezapłaconych należności nie uznaje się za potwierdzone płatności.
MD,
    ],
    'fees' => [
      'title' => 'Zestawienie opłat i cen',
      'provisions' => <<<'MD'
## Zasady ustalania cen
Wiążąca jest cena całkowita wyświetlona dla danej rezerwacji przed złożeniem zamówienia z obowiązkiem zapłaty i zapisana wraz z wersją cennika. Bieżące ustawienia administracyjne nie mogą wstecznie podnosić cen wcześniejszych zamówień. Podatek i kwoty netto oraz brutto należy wykazać osobno zgodnie z faktycznie zweryfikowaną klasyfikacją podatkową. [REQUIRED: status podatkowy operatora, zasady rozliczania VAT i ewentualne informacje o zwolnieniach.]

## Planowane ceny standardowe
Prywatne ogłoszenia ze stałą ceną, ogłoszenia zakupu i alerty wyszukiwania są bezpłatne w określonym zakresie. Planowane ceny to 0,50 EUR za wystawienie aukcji, 1,99 EUR za jedno podbicie, 4,99 EUR za trzy podbicia, 9,90 EUR za wyróżnienie w kategorii przez siedem dni, 19,90 EUR za wyróżnienie na stronie głównej przez siedem dni i 129 EUR za baner sprzedawcy przez miesiąc. Umieszczenie funkcji w zestawieniu nie powoduje jej udostępnienia, jeżeli nie jest jeszcze oferowana. Jeśli skonfigurowane ceny są inne, należy je wyraźnie wyświetlić przed złożeniem zamówienia.

## Progresywna prowizja od aukcji
Prowizja wynosi 3 procent od pierwszych 100 EUR ceny sprzedaży, 2 procent od części powyżej 100 EUR do 500 EUR, 1 procent od części powyżej 500 EUR do 2 000 EUR i 0,5 procent od pozostałej części, przy czym jej maksymalna wysokość to 29,90 EUR. Opłatę za wystawienie pobiera się dodatkowo. Prowizja od sprzedaży jest należna, jeśli aukcja zakończyła się skutecznie i ważnie zgodnie z uzgodnionymi zasadami. Anulowania, transakcje, które w sposób udokumentowany nie doszły do skutku, oraz zwroty wymagają udokumentowanych korekt. Podgląd opłaty nie jest fakturą, a zawiadomienie od klienta nie potwierdza wpływu płatności.
MD,
    ],
    'premium' => [
      'title' => 'Warunki podbić i miejsc premium',
      'provisions' => <<<'MD'
## Zakres usług
Podbicie zmienia pozycję istniejącego własnego ogłoszenia w kolejności sortowania według aktualności. Pierwotna data utworzenia ogłoszenia, złożone oferty licytacyjne i termin zakończenia aukcji pozostają bez zmian. Ten sam przedmiot można podbić najwyżej raz w ciągu 72 godzin. Kredyty na podbicia udostępnia się dopiero po przewidzianej weryfikacji płatności, a następnie użytkownik samodzielnie je aktywuje.

## Ograniczona liczba miejsc i czas trwania
Wyróżnienie w kategorii i wyróżnienie na stronie głównej można zamówić jedynie w faktycznie udostępnionym zakresie. Przed zamówieniem wyświetla się liczbę dostępnych miejsc, przypisany kraj lub kategorię, cenę całkowitą, termin wygaśnięcia rezerwacji i czas trwania. Nieopłacona rezerwacja po upływie terminu nie gwarantuje dalszej rezerwacji miejsca. Jeżeli płatność wpłynie po utracie dostępności miejsca, nie wolno przekroczyć liczby dostępnych miejsc ani pozornie aktywować usługi. Operator musi zaproponować udokumentowane rozwiązanie zastępcze lub rozpatrzenie zwrotu płatności.

## Oznaczenie, wyniki i prawa
Płatne miejsca oznacza się jako reklamowe lub sponsorowane. Określoną liczbę wyświetleń, kontaktów lub sprzedaży gwarantuje się tylko na podstawie odrębnego, wyraźnego uzgodnienia. Ustawowe prawa konsumentów pozostają w mocy. Natychmiastowe rozpoczęcie świadczenia wymaga wyraźnego oświadczenia wymaganego przez prawo. Samo złożenie zamówienia nie jest ogólnym zrzeczeniem się prawa odstąpienia. W przypadku częściowo niewykorzystanych pakietów i awarii technicznych należy przewidzieć odpowiednią korektę oraz rozpatrzenie prawa do zwrotu zgodnie z przepisami.

[REQUIRED: sprawdzić faktyczną liczbę miejsc, podział według krajów, zasady rozpoczęcia usługi, rekompensatę za awarie i procedurę odstąpienia.]
MD,
    ],
    'advertising' => [
      'title' => 'Warunki reklamy dla sprzedawców',
      'provisions' => <<<'MD'
## Kampania i weryfikacja
Reklamę można zamawiać wyłącznie dla zgodnych z prawem usług lub towarów ogólnych odpowiadających przeznaczeniu platformy. Dane reklamodawcy, adres docelowy, materiał reklamowy i obowiązkowe informacje muszą być prawidłowe. Zabrania się wprowadzających w błąd twierdzeń o zezwoleniach, produktów niezgodnych z prawem, złośliwego oprogramowania i ukrytych przekierowań. Zatwierdzenie materiału reklamowego nie stanowi urzędowego ani prawnego zezwolenia na stosowanie danego modelu działalności.

## Emisja i dostępność
Przed rezerwacją określa się kraj, kategorię, czas trwania, przydział miejsc i cenę całkowitą. Emisja według kraju nie oznacza, że można dostarczać towary wszystkim konsumentom, do których reklama dociera w tym kraju, bez spełnienia dalszych obowiązków. Kampanię można przedstawiać jako trwającą dopiero po jej zweryfikowaniu, opłaceniu i rozpoczęciu zaplanowanego okresu emisji. Ograniczonej liczby miejsc nie wolno sprzedawać wielokrotnie.

## Statystyki i zakończenie kampanii
Liczby kliknięć i wyświetleń to wewnętrzne wskaźniki obliczane zgodnie z opublikowaną metodą liczenia. Nie gwarantują określonej liczby różnych osób ani nie stanowią prognozy sprzedaży. Zabronione są manipulowanie kliknięciami we własne reklamy, zautomatyzowane nadużycia i bezprawne profilowanie. Kampanię można wstrzymać w sposób proporcjonalny w razie uzasadnionych problemów prawnych lub związanych z bezpieczeństwem. Należy udokumentować uzasadnienie, możliwość złożenia skargi i sposób rozliczenia opłaconego, niewykorzystanego czasu emisji.

[REQUIRED: uzupełnić dane identyfikacyjne usługodawcy, miesięczny okres obowiązywania umowy, zasady przedłużania i wypowiadania umowy, definicje statystyk oraz zasady dotyczące awarii i zwrotów.]
MD,
    ],
    'verification' => [
      'title' => 'Warunki weryfikacji i bezpieczeństwa',
      'provisions' => <<<'MD'
## Odrębne ustalenia
Tożsamość i osiągnięcie minimalnego wieku weryfikuje się oddzielnie. Żaden z tych wyników nie potwierdza własności towaru, uprawnień do jego nabycia, zdolności płatniczej ani nie gwarantuje uczciwego postępowania. Weryfikacja nie odblokowuje wyłączonych funkcji transakcyjnych dla towarów regulowanych.

## Dokumenty i cel weryfikacji
Przetwarzać wolno jedynie pola dokumentu niezbędne do wskazanego celu weryfikacji. Użytkownik powinien mieć możliwość zamazania zbędnych danych. Dokumentów nie należy zamieszczać na publicznych zdjęciach produktów ani w zwykłym czacie. Przed przekazaniem dokumentów należy w zrozumiały sposób wskazać podmiot odpowiedzialny, cel, podstawę prawną, faktyczny okres przechowywania oraz kontakt do zadawania pytań. Gromadzenie dokumentów pozostaje wyłączone do czasu zatwierdzenia tych warunków i technicznego sposobu przechowywania.

## Dostęp, decyzja i usuwanie
Obrazy dokumentów sprawdza się, ponownie koduje i przechowuje w postaci zaszyfrowanej poza publicznym katalogiem serwera WWW. Ograniczony czasowo dostęp mają wyłącznie weryfikujący z uprawnieniami do danej sprawy, korzystający z uwierzytelniania wieloskładnikowego. Odnotowuje się przypadki wglądu w dokumenty i podjęte decyzje. Zatwierdzenie, żądanie uzupełnienia albo odmowa muszą być możliwe do prześledzenia. Po upływie niezbędnego okresu przechowywania dokumentów, określonego przez specjalistę, pliki usuwa się w sposób bezpieczny. Minimalny zapis decyzji weryfikacyjnej może być przechowywany dłużej tylko na odrębnej podstawie prawnej.

[REQUIRED: ustalić ze specjalistą podmiot odpowiedzialny za weryfikację, podstawę prawną przetwarzania każdego dowodu, rodzaje dokumentów, konkretne terminy i ponowną weryfikację.]
MD,
    ],
    'privacy' => [
      'title' => 'Informacja o ochronie danych osobowych',
      'provisions' => <<<'MD'
## Administrator danych i kontakt
[REQUIRED: pełne dane administratora, w tym adres, kontakt w sprawach ochrony danych i dane inspektora ochrony danych, jeżeli jego wyznaczenie jest wymagane.] Informacja musi odzwierciedlać faktycznie stosowane systemy i kraje, dla których udostępniono usługi. Poniższy projekt nie zawiera stwierdzenia, że przeprowadzono już weryfikację w zakresie ochrony danych.

## Cele i podstawy prawne
Dane konta, ogłoszenia i niezbędną komunikację dotyczącą umów przetwarza się w celu świadczenia usług i wykonania umowy o korzystanie z platformy w zakresie objętym art. 6 ust. 1 lit. b RODO. Dane finansowe lub dane dowodowe wymagane prawem mogą opierać się na podstawie z lit. c. Proporcjonalne przeciwdziałanie nadużyciom i rejestrowanie zdarzeń bezpieczeństwa wymagają udokumentowanego wyważenia konkretnych interesów zgodnie z lit. f. Zgoda na podstawie lit. a musi być rzeczywiście dobrowolna i nie można jej wywodzić ogólnie z samego korzystania z usług. W odniesieniu do dokumentów tożsamości i szczególnych informacji o sprawach należy odrębnie ustalić konkretną konieczność przetwarzania.

## Dane, odbiorcy i bezpieczeństwo
W zależności od funkcji przetwarza się dane konta i dane kontaktowe, publiczne ogłoszenia, prywatne wiadomości w zakresie, w jakim dostęp jest uprawniony, oferty licytacyjne, status zamówień i płatności, preferencje oraz minimalny zakres danych dotyczących bezpieczeństwa i moderacji. Dostęp do prywatnych wiadomości jest ograniczony, a po odpowiednim wdrożeniu są one szyfrowane po stronie serwera. Szyfrowanie po stronie serwera nie jest szyfrowaniem end-to-end. Dostawcy usług płatniczych mogą być odrębnymi administratorami danych w zakresie własnych usług. Należy szczegółowo wskazać hosting, lokalny transport poczty elektronicznej i pozostałych faktycznych odbiorców. [REQUIRED: odbiorcy, przetwarzanie na zlecenie, miejsca przechowywania, przekazywanie danych do państw trzecich i zabezpieczenia.]

## Okresy przechowywania i prawa
[REQUIRED: zrozumiałe okresy przechowywania dla każdej kategorii danych, w tym kont, umów, wiadomości, dzienników bezpieczeństwa i dokumentów.] Użytkownicy mogą skorzystać z prawa dostępu do danych, ich sprostowania, usunięcia w przypadkach przewidzianych prawem, ograniczenia przetwarzania, przenoszenia danych, a w stosownych przypadkach sprzeciwu lub cofnięcia zgody. Złożenie wniosku nie znosi bezwzględnie obowiązujących wymogów przechowywania danych. Można wnieść skargę do właściwego organu nadzorczego ds. ochrony danych. [REQUIRED: właściwy organ nadzorczy i czytelne dane kontaktowe do wykonywania praw.]
MD,
    ],
    'imprint' => [
      'title' => 'Informacje prawne i dane operatora',
      'provisions' => <<<'MD'
## Usługodawca
Pełna nazwa prawna: [REQUIRED: nazwa usługodawcy i forma prawna].
Adres do doręczeń: [REQUIRED: ulica, numer budynku, kod pocztowy, miejscowość i kraj].
Osoba uprawniona do reprezentacji: [REQUIRED: dane faktycznego przedstawiciela w wymaganym zakresie].
Kontakt: [REQUIRED: adres e-mail i inny kanał faktycznie odpowiedni do bezpośredniego kontaktu].

## Dane rejestrowe i podatkowe
Rejestr i numer rejestrowy: [REQUIRED: faktyczne dane rejestrowe, jeżeli istnieją i są wymagane].
Numer identyfikacyjny VAT lub wymagany numer identyfikacyjny przedsiębiorcy: [REQUIRED: właściwe dane lub sporządzone przez specjalistę uzasadnienie braku zastosowania].
Wymagane informacje o zezwoleniach, działalności zawodowej lub nadzorze: [REQUIRED: wyłącznie informacje faktycznie potrzebne i prawidłowe]. Nie wolno wymyślać kompetencji organów ani zezwoleń.

## Odpowiedzialność za treści i rozstrzyganie sporów
[REQUIRED: w stosownych przypadkach osoba odpowiedzialna za treści zgodnie z właściwym prawem].
Informacje o rozstrzyganiu sporów konsumenckich muszą rzetelnie odzwierciedlać faktyczną gotowość do udziału lub ustawowy obowiązek udziału. Nie wolno zamieszczać bez weryfikacji nieaktualnych odsyłaczy do platform ani ogólnych zapewnień o rozstrzyganiu sporów. Kontakt do spraw ochrony danych i kanał zgłoszeń muszą być dostępne. Publikacja tych informacji prawnych wymaga uzupełnienia danych i weryfikacji przez specjalistę.
MD,
    ],
    'cookies' => [
      'title' => 'Informacje o plikach cookie i przechowywaniu danych',
      'provisions' => <<<'MD'
## Funkcje niezbędne technicznie
Przy logowaniu stosuje się identyfikator sesji, który powiązuje uprawniony dostęp do konta i formularze z tą samą sesją. Stosuje się atrybuty bezpieczeństwa, takie jak HttpOnly, SameSite, a przy HTTPS także Secure. Token CSRF zabezpiecza formularze zmieniające stan aplikacji. Funkcje te nie stanowią zgody na śledzenie w celach reklamowych.

## Preferencje wyświetlania
Motyw kolorystyczny, ustawienie wstrzymania animacji oraz w stosownych przypadkach język lub kraj rynku mogą być zapisywane lokalnie albo w sesji, aby odtworzyć wybrany sposób wyświetlania. [REQUIRED: sprawdzić faktyczne nazwy wpisów, rodzaje i okresy przechowywania oraz cel każdego wpisu]. Interfejs musi respektować zmianę wyboru. Jeżeli przepisy o ochronie danych wymagają zgody, pole zgody nie może być zaznaczone domyślnie.

## Statystyki i podmioty zewnętrzne
Sam projekt nie uruchamia żadnych sieci reklamowych ani zewnętrznych usług analitycznych. Należy przejrzyście opisać faktycznie stosowane pomiary lokalne, liczniki nadużyć i ewentualne przekierowania do PayPal. Przed uruchomieniem zbędnego do działania dostępu do pamięci należy ustalić, czy właściwe przepisy wymagają zgody. Narzędzie dodane później wymaga ponownej oceny technicznej i prawnej. Zezwolenie dotyczące bieżącej sesji nie obejmuje go automatycznie.

[REQUIRED: sporządzić wykaz wszystkich faktycznie ustawianych plików cookie i wpisów w pamięci lokalnej (Local Storage) oraz wskazać kontakt i sposób zarządzania zgodami.]
MD,
    ],
    'withdrawal' => [
      'title' => 'Informacja o prawie odstąpienia dotyczącym odpowiednich usług platformy',
      'provisions' => <<<'MD'
## Zakres stosowania
Niniejszą wersję należy dostosować do faktycznie oferowanych usług odpłatnych i konsumentów objętych ochroną ustawową. [REQUIRED: poddać specjalistycznej weryfikacji kraje, dla których udostępniono usługi, status konsumenta i objęte rodzaje umów.] Nie można wyłączać odmiennych, bezwzględnie obowiązujących praw konsumentów wynikających z właściwych przepisów.

## Prawo odstąpienia
Jeżeli przysługuje ustawowe prawo odstąpienia, konsument może odstąpić od umowy w ciągu czternastu dni bez podania przyczyny. W przypadku umowy o świadczenie usług termin co do zasady rozpoczyna się z chwilą zawarcia umowy, o ile spełniono ustawowe obowiązki informacyjne. Do skorzystania z prawa potrzebne jest jednoznaczne oświadczenie złożone wobec [REQUIRED: nazwa, adres i adres e-mail operatora]. Można użyć wzoru formularza, lecz nie jest to obowiązkowe. Wysłanie oświadczenia w terminie wystarcza do jego zachowania na warunkach określonych w przepisach.

## Skutki odstąpienia i wcześniejsze rozpoczęcie świadczenia
Otrzymane płatności należy zwrócić na warunkach i w terminach przewidzianych przepisami, co do zasady tą samą metodą płatności, chyba że skutecznie uzgodniono inaczej. Jeżeli konsument wyraźnie zażądał rozpoczęcia świadczenia przed upływem terminu odstąpienia, przewidziane prawem proporcjonalne wynagrodzenie za już wykonaną część usługi może być należne wyłącznie po spełnieniu wymogów informacyjnych i wymogów dotyczących oświadczenia. Całkowite wygaśnięcie prawa odstąpienia w przypadku usług wymaga spełnienia odpowiednich przesłanek ustawowych. Samo aktywowanie podbicia lub dokonanie płatności nie jest ogólnym zrzeczeniem się praw dotyczących niewykorzystanych środków lub pozostałych usług w pakiecie.

[REQUIRED: sprawdzić konkretny proces zamawiania, potwierdzania, rozpoczęcia świadczenia i zwrotu. Ustalić właściwą kwalifikację przed zrównaniem usługi z treścią cyfrową.]
MD,
    ],
    'withdrawal-form' => [
      'title' => 'Wzór formularza odstąpienia od umowy',
      'provisions' => <<<'MD'
## Stosowanie
Formularz ten można wykorzystać do odstąpienia od umowy konsumenckiej o usługę platformy, jeżeli takiej umowy dotyczy prawo odstąpienia. Skuteczne jednoznaczne oświadczenie można złożyć również w innej formie. Pełne informacje zawiera odrębna informacja o prawie odstąpienia. Niepotrzebne dane należy skreślić.

## Oświadczenie
Do [REQUIRED: pełna nazwa prawna operatora, pełny adres i adres e-mail]:

Niniejszym ja/my odstępuję/odstępujemy od zawartej przeze mnie/przez nas umowy o następującą usługę platformy:

Usługa i ewentualny numer referencyjny zamówienia: ______________________________
Data zamówienia: ______________________________
Imię i nazwisko konsumenta lub konsumentów: ______________________________
Adres konsumenta lub konsumentów: ______________________________
Data: ______________________________
Podpis konsumenta lub konsumentów, tylko jeśli oświadczenie składane jest na papierze: ______________________________

## Rozpatrywanie
Należy podać wyłącznie informacje niezbędne do przypisania i rozpatrzenia oświadczenia. Co do zasady nie wymaga to przesyłania kopii dokumentów tożsamości, danych logowania ani pełnych poufnych danych płatniczych. Operator musi zapewnić faktycznie dostępny kanał odbioru oraz udokumentowaną procedurę zwrotu. Przyjęcie formularza nie jest uzależnione od uiszczenia opłaty za rozpatrzenie.

[REQUIRED: przed publikacją uzgodnić dane kontaktowe i wskazanie konkretnych umów z treścią informacji o prawie odstąpienia.]
MD,
    ],
    'moderation' => [
      'title' => 'Zasady moderacji',
      'provisions' => <<<'MD'
## Zasady ogólne
Moderacja odbywa się zgodnie ze zrozumiałymi zasadami platformy i właściwymi przepisami. Niedozwolone są niezgodne z prawem treści, oszustwa, przesyłanie niebezpiecznych plików, nękanie, manipulowanie ocenami i oferowanie niedopuszczonych towarów regulowanych. Platforma nie może przerzucać wszystkich swoich obowiązków ustawowych na użytkowników.

## Postępowanie
Zgłoszeniom nadaje się numer sprawy i rozpatruje się je według pilności i właściwości. Dostęp należy ograniczyć do niezbędnych informacji. Sama ogólna rola administratora nie daje pełnego wglądu w prywatne rozmowy. Dobrowolnie przekazane dodatkowe informacje należy wykorzystywać wyłącznie w celu, w jakim je przekazano. Decyzje należy uzasadniać zgodnie z właściwymi przepisami i informować użytkowników, których dotyczą, o dostępnych sposobach składania skarg.

## Środki i granice
Możliwe środki to powiadomienie, upomnienie, czasowe wstrzymanie lub ukrycie konkretnych treści, a przy odpowiednich uprawnieniach także ograniczenie konta. Należy odpowiednio uwzględnić zakres, czas trwania, wagę i powtarzalność. W razie rozbieżności aukcję z ofertami licytacyjnymi kieruje się do weryfikacji. Nie wolno po cichu wymyślać ani usuwać ofert czy zwycięzców. Rozstrzygnięcie sprawy przez platformę nie zastępuje orzeczenia sądu w sprawie roszczeń cywilnoprawnych.

[REQUIRED: poddać specjalistycznej weryfikacji rzeczywiste kompetencje, wyjątki ustawowe, wymaganą treść uzasadnienia, stosowanie DSA i terminy.]
MD,
    ],
    'complaints' => [
      'title' => 'Procedura zgłaszania i składania skarg',
      'provisions' => <<<'MD'
## Zgłoszenie
Użytkownicy mogą zgłaszać za pośrednictwem przewidzianego kanału konkretne ogłoszenia, użytkowników, wiadomości, do których mają uprawniony dostęp, oceny i opublikowane reklamy. Konieczne jest wystarczająco dokładne wskazanie przedmiotu zgłoszenia oraz podanie zrozumiałego powodu. Dobrowolne informacje dodatkowe należy ograniczyć do niezbędnego zakresu. Rozpatrzenie uzasadnionej sprawy nie może zależeć od ujawnienia niepotrzebnych danych dotyczących tożsamości lub płatności.

## Rozpatrywanie i decyzja
Platforma nadaje numer sprawy i dokumentuje weryfikację, odpowiedzialny podmiot, decyzję i jej uzasadnienie. Samo zgłoszenie nie skutkuje automatycznie nałożeniem sankcji. W zakresie wymaganym prawem uprawnionym uczestnikom przekazuje się potwierdzenie otrzymania zgłoszenia i decyzję. Danych identyfikujących zgłaszającego ani poufnych dowodów nie wolno ujawniać drugiej stronie bez oceny okoliczności.

## Skarga
Uprawnieni uczestnicy, których dotyczy przekazana decyzja, mogą złożyć uzasadniony wniosek o jej weryfikację za pośrednictwem przewidzianego kanału sprawy. Skargę należy faktycznie rozpatrzyć. Samo jej zarejestrowanie nie uchyla automatycznie zastosowanego środka. Ponowne rozpatrzenie sprawy lub potwierdzenie decyzji należy udokumentować. Ustawowe zewnętrzne drogi skargowe, polubowne i prawne pozostają dostępne. Wewnętrzny limit techniczny wielokrotnego składania identycznych zgłoszeń nie może w niedozwolony sposób ograniczać bezwzględnie obowiązujących praw ustawowych.

[REQUIRED: wskazać kontakt do zgłaszania treści niezgodnych z prawem, faktyczne terminy, stosowanie DSA i pozasądowe drogi składania skarg.]
MD,
    ],
    'disputes' => [
      'title' => 'Warunki dotyczące sporów',
      'provisions' => <<<'MD'
## Strony umowy
Spory dotyczące stanu towaru, płatności, dostawy lub wykonania umowy sprzedaży co do zasady dotyczą kupującego i sprzedawcy jako odrębnych stron umowy. Platforma techniczna nie staje się automatycznie sprzedawcą, dostawcą usług przewozowych, ubezpieczycielem ani sądem polubownym. Wykonuje swoje własne obowiązki ustawowe i wyraźnie przyjęte zobowiązania.

## Pomoc
Platforma może udostępniać istniejące kanały komunikacji, do których strony mają uprawniony dostęp, i wewnętrzną procedurę zgłoszeń. W tym celu udostępnia się wyłącznie niezbędne dane. Użytkownicy nie mogą przedstawiać sfałszowanych dowodów, korzystać z cudzych kont ani inicjować nieuzasadnionych reklamacji płatności. Samo skierowanie do wewnętrznej weryfikacji nie potwierdza roszczenia żadnej ze stron.

## Dowody i środki prawne
Nie wolno potajemnie manipulować zapisami zdarzeń dotyczących ofert licytacyjnych, płatności i spraw. Niezbędne dowody zabezpiecza się na udokumentowanej podstawie prawnej i zgodnie z udokumentowanymi zasadami przechowywania. Ręczne potwierdzenie płatności odzwierciedla weryfikację przez operatora i nie stanowi niezależnego potwierdzenia sądowego ani kryptograficznego. Roszczenia ustawowe, postępowania sądowe i faktycznie oferowane pozasądowe rozstrzyganie sporów pozostają dostępne. Platforma nie przyrzeka świadczenia usług powierniczych ani kompleksowej gwarancji ceny zakupu.

[REQUIRED: sprawdzić dokładny zakres dobrowolnie oferowanego wsparcia, jego nieodpłatność lub cenę, właściwość organów lub podmiotów oraz wymagane prawem informacje o polubownym rozstrzyganiu sporów.]
MD,
    ],
    'payments' => [
      'title' => 'Warunki płatności za usługi platformy',
      'provisions' => <<<'MD'
## Odrębność od zakupu towaru
Warunki te dotyczą opłat za własne usługi operatora i nie obejmują ogólnego przyjmowania należności z tytułu umów sprzedaży między użytkownikami. Metody płatności dostępne dla konkretnego zamówienia wyświetla się przed zawarciem umowy. Samo wyświetlenie nieaktywnej opcji nie oznacza dostępności przelewu bankowego, płatności w Bitcoinie ani usługi powierniczej.

## Weryfikacja ręczna i automatyczna
Ręczne zlecenie płatności PayPal zawiera ustaloną kwotę w EUR, rzeczywisty opis usługi, odbiorcę i jednoznaczny numer referencyjny. Operator sprawdza faktyczny wpływ zakończonej płatności i przypisuje jednoznaczny identyfikator transakcji. Zgłoszenie użytkownika, zrzut ekranu ani parametr przeglądarki nie są dowodem płatności. Ręczna procedura nie zastępuje wymogów dotyczących konta PayPal i nie uzasadnia wybrania opcji Friends & Family do płatności za usługi komercyjne. Automatyczna integracja wymaga faktycznie zatwierdzonej konfiguracji dostawcy i zweryfikowanych zdarzeń po stronie serwera.

## Rozbieżności i realizacja
Płatności o rozbieżnej kwocie, numerze referencyjnym lub odbiorcy bądź o niejasnym przebiegu nie oznacza się jako w pełni opłacone. Tej samej transakcji nie wolno księgować wielokrotnie. Moment aktywacji usługi wynika z potwierdzonego zamówienia. Po wygaśnięciu rezerwacji nie wolno jej później realizować kosztem przekroczenia liczby miejsc. Błędy, anulowania i zwroty należy dokumentować za pomocą zdarzeń korygujących. Nie wolno potajemnie zmieniać wcześniejszych faktur.

[REQUIRED: sprawdzić, czy konto odbiorcy jest odpowiednie, terminy płatności, zasady dotyczące opóźnień, faktyczne warunki dostawcy i potwierdzenie umowy.]
MD,
    ],
    'refunds' => [
      'title' => 'Warunki anulowania i zwrotów płatności',
      'provisions' => <<<'MD'
## Podstawy rozpatrzenia
Wnioski o zwrot mogą dotyczyć w szczególności odstąpienia, niewykonanych zamówionych usług, podwójnych płatności, udokumentowanych uzasadnionych korekt umów lub awarii technicznych. Niniejszy opis nie może ograniczać roszczeń ustawowych. Konkretne roszczenie podlega weryfikacji na podstawie zamówienia i faktycznie wykonanego zakresu usług w sposób umożliwiający prześledzenie podstaw decyzji.

## Dokumentacja i przypisanie
Należy udokumentować wniosek, numer referencyjny, powód, decyzję, kwotę i rzeczywistą metodę płatności. Oznaczenie zwrotu jako wnioskowanego nie oznacza jego wykonania. Automatyczny zwrot można uznać za zakończony dopiero po zweryfikowanym potwierdzeniu dostawcy. Zwrot ręczny wymaga jednoznacznego przypisania do faktycznej transakcji. Wystawionych wcześniej dokumentów nie wolno po cichu nadpisywać. W razie potrzeby wystawia się dokumenty anulujące lub korygujące.

## Środki i świadczenia częściowe
W przypadku pakietów podbić i usług świadczonych przez określony czas należy osobno oceniać część wykorzystaną i niewykorzystaną. Natychmiastowe wykonanie jednego podbicia nie uzasadnia ogólnego wyłączenia wszystkich praw dotyczących pozostałej części pakietu. Gdy wskutek opóźnionej płatności miejsce premium stało się niedostępne, należy rozważyć rzeczywiste rozwiązanie. Miejsce jedynie oznaczone jako aktywne nie stanowi wykonania usługi. Terminy zwrotu i dopuszczalne metody płatności wynikają z przepisów prawa i skutecznie uzgodnionych warunków.

[REQUIRED: poddać specjalistycznej weryfikacji faktyczne kanały obsługi, ustawowe terminy, dokumenty korygujące i procedury dostawcy.]
MD,
    ],
    'allowed-products' => [
      'title' => 'Zasady dotyczące produktów dozwolonych i niedozwolonych',
      'provisions' => <<<'MD'
## Aktywny rynek
Aktywny rynek przeznaczony jest dla zgodnych z prawem towarów ogólnych niepodlegających szczególnej regulacji, takich jak artykuły outdoorowe, kempingowe, odzieżowe, sportowe, optyczne, gospodarstwa domowego, narzędzia i media. Kategoria nie zastępuje oceny produktu ani nie zwalnia z wymogów kraju dostawy lub kraju odbiorcy. Sama zmiana nazwy dawnych ogłoszeń specjalistycznych nie zmienia oferowanych produktów w towary ogólne.

## Produkty zablokowane i o nieustalonej klasyfikacji
W kategoriach ogólnych nie wolno handlować produktami niezgodnymi z prawem, podróbkami, skradzionymi towarami ani zamieszczać oszukańczych ofert. Zakaz dotyczy również niedopuszczonych regulowanych grup produktów obejmujących broń, amunicję lub inne produkty mające znaczenie prawne. Dowolnie wpisana nazwa ani status weryfikacji nie stanowią dopuszczenia do obrotu. W razie niejasnej klasyfikacji wymagana jest specjalistyczna weryfikacja. Do tego czasu odnośne transakcje pozostają zablokowane.

## Obowiązki i egzekwowanie zasad
Oferujący muszą mieć prawo rozporządzania towarem, podawać prawidłowe informacje w wymaganym zakresie i przestrzegać wymogów dotyczących produktu, wysyłki i kraju docelowego. Operator musi wykonywać własne obowiązki ustawowe i moderacyjne. Filtry tekstowe są jedynie narzędziem pomocniczym i nie zapewniają pełnej kwalifikacji prawnej. Próby obejścia zasad mogą skutkować proporcjonalnymi ograniczeniami nałożonymi w ramach procedury moderacji z podaniem uzasadnienia.

[REQUIRED: ustalić ze specjalistą wiążący wykaz grup produktów, kraje, dla których udostępniono usługi, właściwy podmiot weryfikujący i ograniczenia ustawowe dla każdego rodzaju produktu.]
MD,
    ],
    'account-suspension' => [
      'title' => 'Zasady ograniczania i blokowania kont',
      'provisions' => <<<'MD'
## Podstawy i proporcjonalność
Konta mogą zostać czasowo ograniczone w razie uzasadnionych problemów prawnych, podejrzeń oszustwa lub zagrożeń bezpieczeństwa w zakresie dopuszczalnym przez prawo i niezbędnym do zapobieżenia zagrożeniu. Przy zastosowaniu środka należy uwzględnić rodzaj, wagę, czas trwania, powtarzalność i skutki problemu. Nie przewiduje się nieuzasadnionych ani arbitralnych blokad.

## Skutki i sprawy w toku
Dezaktywacja konta unieważnia aktywne sesje i uniemożliwia podejmowanie nowych niedozwolonych działań. Dotychczasowe oferty licytacyjne, umowy, płatności i skargi pozostają zapisane. Nie wolno manipulować trwającymi aukcjami z ofertami licytacyjnymi przez usunięcie uczestnika bez ujawnienia tej czynności. Techniczny status weryfikacji i odrębne rozpatrywanie sprawy pozwalają zachować niezbędne dowody.

## Uzasadnienie i weryfikacja
W przypadkach wymaganych prawem przekazuje się powód, zakres, czas trwania i dostępne możliwości weryfikacji. Stosowanie wyjątków ustawowych, na przykład w celu ochrony postępowań wyjaśniających lub osób trzecich, należy oceniać w każdym przypadku. Użytkownicy muszą mieć możliwość wniesienia uzasadnionej skargi. Prawa przysługujące poza platformą pozostają w mocy. Dobrowolne usunięcie konta należy odróżnić od sankcji i podjąć w tym przypadku osobną decyzję o przechowywaniu danych zgodnie z przepisami o ochronie danych.

[REQUIRED: sprawdzić terminy, ponowną weryfikację, komunikację po dezaktywacji, stosowanie DSA i P2B oraz umowne zasady wypowiedzenia.]
MD,
    ],
  ],
];
