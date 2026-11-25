<?php
return [
  'header' => <<<'MD'
**Stav: Nepřezkoumaný návrh. Návrh čeká na odborné a případně právní posouzení advokátem.**
**Verze dokumentu: draft-1.0 · Jazyk: Čeština.**
**Provozovatel:** [REQUIRED: úplný právní název, adresa a kontaktní údaje].
**Územní působnost smlouvy:** [REQUIRED: skutečně schválené země a potřebné informace pro spotřebitele v jednotlivých zemích].

Tento návrh popisuje výhradně skutečně schválené funkce. Nezaručuje právní účinnost a bez zdokumentovaného odborného schválení nesmí být zveřejněn jako smluvní text pro ostrý provoz. Údaje o provozovateli, obchodní praxe a konkrétní objednávkové rozhraní musejí být v souladu.
MD,
  'documents' => [
    'terms' => [
      'title' => 'Všeobecné obchodní podmínky',
      'provisions' => <<<'MD'
## 1. Smluvní strany a působnost
Tyto podmínky se vztahují na přístup k technické platformě a na výslovně objednané služby platformy. Provozovatel zprostředkovává nabídky uživatelů. Kupující a prodávající uzavírají kupní smlouvu na zboží v zásadě mezi sebou. Platforma se tím nestává vlastníkem ani prodávajícím a automaticky nezajišťuje přepravu, předání ani úschovu. Zda určitá funkce zakládá další povinnosti provozovatele, závisí na jejím skutečném provedení a na kogentním právu.

## 2. Účet, nabídky a uzavření smlouvy
Údaje o účtu musejí být pravdivé. Přístupové údaje nesmějí být předávány dál a podezřelé přístupy je nutné bezodkladně nahlásit. Prodávající smějí nabízet pouze dostupné a legální běžné zboží. Musejí pravdivě uvést jeho stav, cenu, dodací podmínky a případné postavení podnikatele. Uzavření smlouvy v konkrétní aukci nebo u nabídky s pevnou cenou se řídí samostatnými podmínkami. Zpřístupnění kategorie nebo ověření totožnosti není všeobecným právním povolením k obchodování.

## 3. Služby, změny a ukončení
Rozsah, celková cena a doba trvání placených služeb se zobrazují před objednáním a uchovávají se s příslušnou verzí podmínek. Pravidla dostupnosti, moderování a plateb se nesmějí skrytě měnit se zpětnou účinností. U podstatných změn smlouvy je nutné posoudit, zda je zapotřebí uživatele informovat nebo vyžádat nový výslovný souhlas. Pouhé pokračování v používání nenahrazuje projev vůle vyžadovaný zákonem. Uživatelé mohou požádat o deaktivaci účtu a o posouzení výmazu podle předpisů o ochraně osobních údajů.

## 4. Odpovědnost a náhrada škody
Platforma musí plnit vlastní zákonné povinnosti kontroly, informování a reakce. Při úmyslu, hrubé nedbalosti, újmě na životě, těle nebo zdraví ani u kogentní zákonné odpovědnosti, zejména podle německého zákona o odpovědnosti za výrobek (Produkthaftungsgesetz) nebo převzatých záruk, se neuplatní nepřípustná vyloučení odpovědnosti. Při porušení podstatných smluvních povinností z lehké nedbalosti lze odpovědnost omezit na předvídatelnou škodu typickou pro danou smlouvu pouze v zákonem povoleném rozsahu. Povinnost uživatele poskytnout odškodnění předpokládá porušení práva, za které odpovídá, a oprávněné nároky. Nadále se zohledňuje spoluzavinění a případné námitky.

## 5. Posouzení před zveřejněním
[REQUIRED: odborně posuďte rozhodné právo, přípustná ujednání o příslušnosti soudu pro podnikatele, informace pro spotřebitele, smluvní komunikaci a konkrétní popis služby.]
MD,
    ],
    'private-use' => [
      'title' => 'Podmínky používání pro soukromé uživatele',
      'provisions' => <<<'MD'
## Účet a povolené používání
Soukromí uživatelé jednají pro vlastní soukromé účely. Podnikatelskou činnost nelze zastírat jako soukromý prodej. Názvy účtů nesmějí porušovat práva třetích osob ani vytvářet mylný dojem úředního či neprověřeného ověření. Povinné údaje o účtu musejí být aktuální. Doklady totožnosti, které nejsou zapotřebí, se nesmějí zasílat prostřednictvím veřejných inzerátů nebo soukromých zpráv.

## Nabídky a kontakt
Nabídky musejí srozumitelně popisovat skutečně dostupné zboží. Před uzavřením smlouvy je nutné vyjasnit cenu, stav, způsob vyzvednutí, náklady na dopravu a zjevné vady. Obrázky lze zveřejnit pouze při odpovídajícím oprávnění k jejich užití. Interní chat slouží ke komunikaci o konkrétní nabídce. Obtěžování, hromadná reklama, phishing, obcházení blokací a zveřejňování soukromého obsahu jiných osob jsou zakázány.

## Práva a ukončení
Bezplatné soukromé inzeráty s pevnou cenou se poskytují jen v rozsahu uvedeném ve zveřejněném popisu služeb. Placené zvýraznění vyžaduje samostatnou objednávku. Prodej ani určitý dosah se nezaručují. Uživatelé mohou podle skutečně dostupných možností spravovat uložené nabídky a osobní funkce a požádat o deaktivaci účtu. Neuzavřené záležitosti ze smluv, zákonné povinnosti uchovávání a oprávněné důkazní potřeby ve sporech mohou bránit okamžitému úplnému výmazu jednotlivých záznamů. Rozhodnutí musejí být srozumitelně odůvodněná a přezkoumatelná.

[REQUIRED: posuďte minimální věk pro účet, povolené země, skutečná omezení bezplatných služeb a potvrzení smlouvy.]
MD,
    ],
    'dealer' => [
      'title' => 'Doplňující podmínky pro obchodníky',
      'provisions' => <<<'MD'
## Totožnost podnikatele
Podnikatelé musejí zveřejnit své skutečné postavení a uvést všechny zákonem vyžadované firemní, kontaktní, rejstříkové a daňové údaje a informace pro spotřebitele. Provozovatel platformy kontroluje údaje o obchodnících, které musí ze zákona ověřovat, stanoveným postupem. Veřejný profil ani nahrané logo nenahrazuje úřední či odborné schválení.

## Nabídky a povinnosti vůči spotřebitelům
Obchodníci odpovídají u vlastní kupní smlouvy za správný popis zboží, celkovou cenu, náklady na dodání, dostupnost, zákonná práva z vad a požadované informace o odstoupení od smlouvy. Platforma nadále sama plní vlastní zákonné povinnosti. Přeshraniční nabídky předpokládají vyjasnění dodání, daňového režimu, informací pro spotřebitele a přípustného druhu zboží pro konkrétně oslovené země.

## Správa a reklama
Hromadné importy musejí dodržet stanovené formáty a pravidla pro produkty. Chybné řádky nesmějí být vykázány jako úspěšně importované. Přístupy zaměstnanců vyžadují oddělená oprávnění, jakmile je tato funkce skutečně nabízena. Společné nekontrolované účty správce nejsou předpokládány. Reklamní služby se řídí samostatnou rezervací, posouzením a reklamními podmínkami. Nezaručuje se objem prodeje ani zákonné oprávnění k podnikání.

[REQUIRED: posuďte kontrolu totožnosti, kontakt pro obchodníky, povolená území prodeje, smluvní komunikaci a případné povinnosti podle P2B.]
MD,
    ],
    'auction' => [
      'title' => 'Podmínky aukcí běžného zboží',
      'provisions' => <<<'MD'
## Rozsah nabídek a závazné údaje
Aukce jsou přípustné jen pro běžné zboží schválené pro transakce. Prodávající stanoví vyvolávací cenu, dobu trvání, podmínky předání a případnou možnost okamžitého nákupu. Přihazování ve vlastní aukci, domluvení fingovaní účastníci a umělé zvyšování ceny jsou zakázány. Totožnost účtu ani zařazení do kategorie nenahrazují zákonné schválení produktu.

## Příhozy a ukončení
Soukromý nejvyšší příhoz se veřejně nezobrazuje. Automatické přihazování zvyšuje viditelnou částku jen podle zveřejněného pravidla pro výši příhozu. Při shodných maximálních příhozech má podle technického pravidla přednost první přijatý příhoz. Platný příhoz v posledních dvou minutách prodlouží aukci nejméně do dvou minut od přijetí. Rozhoduje čas přijetí na serveru. Pozdní nebo nepřípustné příhozy a příhozy ve vlastní aukci se zamítají.

## Smlouva a poruchy
[REQUIRED: odborně stanovte přesný okamžik uzavření smlouvy a projevy vůle podle skutečné podoby rozhraní.] Vítěz a prodávající obdrží informace nezbytné k plnění prostřednictvím nabízených kontaktních kanálů. Stav přezkumu při rozporných údajích není svévolným rozhodnutím o vítězi. Podstatné změny po přijetí příhozů, neoprávněné předčasné ukončení a obcházení platných smluv jsou zakázány. Technické poruchy, napadení smlouvy, oprávněné zpětvzetí a prokazatelně neúspěšné smlouvy se řeší podle zdokumentovaného postupu. Poplatky vznikají pouze po dohodnutých událostech. Neuhrazené pohledávky nejsou potvrzenými platbami.
MD,
    ],
    'fees' => [
      'title' => 'Přehled poplatků a cen',
      'provisions' => <<<'MD'
## Cenové základy
Závazná je celková cena, která byla pro konkrétní rezervaci zobrazena před objednávkou s povinností platby a uložena s příslušnou verzí sazeb. Aktuální nastavení správy nesmí zpětně zdražovat dřívější objednávky. Daň, čistá částka a částka včetně daně se uvádějí odděleně podle skutečně posouzeného daňového režimu. [REQUIRED: daňové postavení provozovatele, režim DPH a případné informace o osvobození.]

## Předpokládané standardní ceny
Soukromé inzeráty s pevnou cenou, poptávky a upozornění na vyhledávání jsou ve stanoveném rozsahu zdarma. Předpokládané ceny jsou 0,50 EUR za vystavení aukce, 1,99 EUR za jeden bump, 4,99 EUR za tři bumpy, 9,90 EUR za sedm dní zvýraznění v kategorii, 19,90 EUR za sedm dní zvýraznění na úvodní stránce a 129 EUR za měsíční banner obchodníka. Tento výčet nezapíná dosud nenabízené funkce. Odlišné ceny nastavené správcem musejí být před objednáním zřetelně zobrazeny.

## Progresivní aukční provize
Provize se vypočte jako 3 procenta z prvních 100 EUR, 2 procenta z části nad 100 do 500 EUR, 1 procento z části nad 500 do 2.000 EUR a 0,5 procenta z částky nad tuto hranici, nejvýše však 29,90 EUR. K tomu se připočte poplatek za vystavení. Prodejní provize předpokládá platné úspěšné dokončení prodeje podle dohodnutých pravidel. Storna, prokazatelně neuskutečněné transakce a vrácení plateb je nutné zdokumentovaně opravit. Náhled poplatku není faktura. Oznámení zákazníka není přijetím platby.
MD,
    ],
    'premium' => [
      'title' => 'Podmínky pro bumpování a prémiové umístění',
      'provisions' => <<<'MD'
## Rozsah služby
Bump posune již existující vlastní nabídku v řazení podle aktuálnosti. Původní datum vytvoření, příhozy a konec aukce zůstávají beze změny. Stejnou nabídku lze bumpovat nejvýše jednou za 72 hodin. Kredit se zpřístupní teprve po stanovené kontrole platby a následně jej uživatel vědomě aktivuje.

## Omezená místa a doby trvání
Zvýraznění v kategorii a na úvodní stránce lze rezervovat jen ve skutečně schváleném rozsahu. Před objednáním se zobrazí počet dostupných míst, přidělená země nebo kategorie, celková cena, lhůta rezervace a doba trvání. Prošlá neuhrazená rezervace nezaručuje trvalé umístění. Jestliže platba dorazí poté, co místo přestalo být dostupné, nesmí dojít k přeplnění kapacity ani k předstíranému zapnutí služby. Provozovatel musí nabídnout zdokumentovanou náhradní možnost nebo posouzení vrácení platby.

## Označení, výsledek a práva
Placené umístění se označuje jako reklama nebo sponzorovaný obsah. Bez samostatné výslovné dohody není zaručen určitý počet zobrazení, kontaktů ani prodejů. Zákonná práva spotřebitelů zůstávají zachována. Okamžité zahájení služby vyžaduje zákonem stanovený výslovný projev vůle. Objednávka sama nepředstavuje plošné vzdání se práva odstoupit od smlouvy. U částečně nevyužitých balíčků a technických výpadků se stanoví přiměřená oprava a zákonem požadované posouzení nároku na vrácení platby.

[REQUIRED: posuďte skutečný počet míst, rozdělení podle zemí, pravidla zahájení, náhradu při výpadku a postup při odstoupení.]
MD,
    ],
    'advertising' => [
      'title' => 'Reklamní podmínky pro obchodníky',
      'provisions' => <<<'MD'
## Kampaň a posouzení
Reklamu lze objednat pouze pro legální služby nebo běžné zboží odpovídající zaměření platformy. Údaje o zadavateli reklamy, cílové adrese, obsahu a povinných informacích musejí být správné. Klamavá povolení, protiprávní produkty, škodlivý software a skrytá přesměrování jsou zakázány. Schválení reklamy nepředstavuje úřední ani právní schválení obchodního modelu.

## Zobrazování a dostupnost
Země, kategorie, doba trvání, počet míst a celková cena se stanoví před rezervací. Zobrazování podle země nezaručuje, že lze všechny tam dosažitelné spotřebitele obsloužit bez dalších povinností. Dokud kampaň neprojde kontrolou, nebude zaplacena a aktivována na určené období, nesmí se zobrazovat jako probíhající reklama. Omezená místa se nesmějí prodat dvakrát.

## Statistiky a ukončení
Počty kliknutí a zobrazení jsou interní měření podle zveřejněné metodiky. Nezaručují počet jedinečných osob ani nepředpovídají prodeje. Zmanipulovaná vlastní kliknutí, automatizované zneužívání a protiprávní profilování jsou zakázány. Kampaně lze při řádně doložených právních nebo bezpečnostních problémech přiměřeně pozastavit. Je třeba zdokumentovat odůvodnění, možnost stížnosti a vypořádání zaplacené nevyužité doby.

[REQUIRED: doplňte totožnost poskytovatele, měsíční dobu trvání smlouvy, prodloužení, ukončení, definici statistik a pravidla pro výpadky a vracení plateb.]
MD,
    ],
    'verification' => [
      'title' => 'Podmínky ověřování a bezpečnosti',
      'provisions' => <<<'MD'
## Samostatná zjištění
Totožnost a minimální věk jsou samostatnými předměty ověření. Žádné z těchto zjištění nepotvrzuje vlastnictví zboží, zákonné oprávnění je nabýt, platební schopnost ani zaručenou poctivost. U regulovaného zboží se tím nezpřístupňují blokované transakční funkce.

## Podklady a účel
Lze zpracovat jen údaje z dokladů, které jsou nezbytné pro uvedený účel ověření. Nadbytečné údaje by mělo být možné začernit. Podklady nepatří do veřejných fotografií produktů ani do běžného chatu. Před předáním musí být srozumitelně uvedeny odpovědný subjekt, účel, právní základ, skutečná doba uchovávání a kontaktní místo pro dotazy. Sběr zůstává vypnutý, dokud tyto předpoklady a technické úložiště nejsou schváleny.

## Přístup, rozhodnutí a výmaz
Snímky dokladů jsou kontrolovány, znovu kódovány a v šifrované podobě ukládány mimo kořenový adresář webu. Časově omezený přístup mají pouze pracovníci oprávnění pro daný případ, kteří se přihlásí vícefaktorově. Nahlédnutí a rozhodnutí se zaznamenávají. Schválení, výzva k doplnění nebo zamítnutí musejí být srozumitelně odůvodněny. Po uplynutí nezbytné odborně stanovené doby uchování jsou soubory bezpečně odstraněny. Minimální záznam o výsledku kontroly lze uchovávat déle pouze na základě vlastního právního důvodu.

[REQUIRED: odborně určete odpovědné kontrolní místo, právní základ pro každý doklad, druhy dokumentů, konkrétní lhůty a postup opětovného ověření.]
MD,
    ],
    'privacy' => [
      'title' => 'Prohlášení o ochraně osobních údajů',
      'provisions' => <<<'MD'
## Správce a kontakt
[REQUIRED: úplné údaje o správci včetně adresy, kontaktu pro ochranu údajů a případného pověřence pro ochranu osobních údajů.] Toto prohlášení musí odpovídat skutečně používaným systémům a schváleným zemím. Návrh netvrdí, že již bylo dokončeno posouzení ochrany osobních údajů.

## Účely a právní základy
Údaje o účtu, nabídky a potřebná smluvní komunikace se zpracovávají za účelem poskytování a plnění smlouvy o užívání, pokud to umožňuje čl. 6 odst. 1 písm. b GDPR. Zákonem požadované finanční údaje a důkazní záznamy se případně opírají o písm. c. Přiměřené předcházení zneužití a bezpečnostní záznamy vyžadují konkrétně zdokumentované posouzení oprávněných zájmů podle písm. f. Skutečně dobrovolný souhlas podle písm. a nelze plošně nahrazovat pouhým užíváním. U dokladů totožnosti a zvláštních informací o případech je nutné nezbytnost stanovit samostatně.

## Údaje, příjemci a zabezpečení
Podle konkrétní funkce se zpracovávají údaje o účtu a kontaktní údaje, veřejné nabídky, soukromé zprávy s oprávněným přístupem, příhozy, stav objednávek a plateb, preference a minimální bezpečnostní a moderační údaje. Přístup k soukromým zprávám je omezen a při odpovídajícím provedení jsou šifrovány na serveru. Nejde o koncové šifrování. Poskytovatelé plateb mohou za vlastní služby odpovídat jako samostatní správci. Je třeba konkrétně uvést poskytovatele hostingu, místní způsob přepravy e-mailu a ostatní skutečné příjemce. [REQUIRED: příjemci, zpracování zpracovatelem, místa uložení, předávání do třetích zemí a záruky.]

## Doby uchování a práva
[REQUIRED: srozumitelné lhůty pro jednotlivé oblasti údajů včetně účtů, smluv, zpráv, bezpečnostních záznamů a dokumentů.] Uživatelé mohou uplatňovat právo na přístup, opravu, zákonem stanovený výmaz, omezení zpracování, přenositelnost a případně vznést námitku nebo odvolat souhlas. Žádost neruší závazné povinnosti uchovávání. Stížnost u příslušného dozorového úřadu pro ochranu osobních údajů zůstává možná. [REQUIRED: příslušný dozorový úřad a srozumitelný kontakt pro uplatnění práv.]
MD,
    ],
    'imprint' => [
      'title' => 'Tiráž a údaje o provozovateli',
      'provisions' => <<<'MD'
## Poskytovatel služby
Úplný právní název: [REQUIRED: název a právní forma poskytovatele].
Adresa pro doručování: [REQUIRED: ulice, číslo domu, poštovní směrovací číslo, obec, stát].
Osoba oprávněná k zastupování: [REQUIRED: skutečný zástupce, je-li vyžadován].
Kontakt: [REQUIRED: e-mail a další skutečně vhodný způsob přímé komunikace].

## Rejstříkové a daňové údaje
Rejstřík a registrační číslo: [REQUIRED: skutečné rejstříkové údaje, pokud existují a jsou vyžadovány].
Identifikační číslo pro DPH nebo požadovaný identifikátor podnikatele: [REQUIRED: správné údaje nebo odborně odůvodněná nepoužitelnost].
Požadované údaje o povolení, povolání či dohledu: [REQUIRED: pouze skutečně nezbytné a pravdivé údaje]. Nesmějí se vymýšlet pravomoci úřadů ani povolení.

## Odpovědnost za obsah a řešení sporů
[REQUIRED: případná osoba odpovědná za obsah podle rozhodného práva].
Informace o řešení spotřebitelských sporů musejí pravdivě vyjadřovat skutečnou ochotu účastnit se řízení nebo zákonnou povinnost. Zastaralé odkazy na platformy nebo obecná tvrzení o řešení sporů nelze přebírat bez ověření. Kontakty pro ochranu údajů a podávání hlášení musejí být dostupné. Tuto strukturu lze zveřejnit jako tiráž teprve po vyplnění a odborném posouzení.
MD,
    ],
    'cookies' => [
      'title' => 'Informace o cookies a ukládání',
      'provisions' => <<<'MD'
## Technicky nezbytné funkce
Při přihlášení se používá identifikátor relace, aby bylo možné přiřadit oprávněné přístupy k účtu k příslušným formulářům. Používají se bezpečnostní atributy jako HttpOnly, SameSite a při HTTPS také Secure. Formuláře měnící stav chrání token CSRF. Tyto funkce nelze zaměňovat za souhlas se sledováním pro reklamní účely.

## Předvolby zobrazení
Barevné schéma, pozastavení animací a případně jazyk nebo země trhu mohou být uloženy lokálně nebo v relaci, aby bylo možné obnovit zvolené zobrazení. [REQUIRED: ověřte skutečné názvy položek, druhy uložení, doby uchovávání a účely jednotlivých položek]. Rozhraní musí respektovat změnu výběru. Souhlas požadovaný právem ochrany osobních údajů nesmí být předem zaškrtnutý.

## Statistiky a třetí strany
Pouze na základě tohoto návrhu se nezapínají žádné reklamní sítě ani externí analytické služby. Skutečně používaná místní měření, počítadla zneužití a případná přesměrování na PayPal musejí být transparentně popsány. Před zapnutím přístupu k úložišti, který není nezbytný, je nutné ověřit, zda rozhodné právo vyžaduje souhlas. Později přidaný nástroj vyžaduje nové technické a právní posouzení. Dosavadní souhlas s relací jej automaticky nepokrývá.

[REQUIRED: soupis všech skutečně ukládaných cookies a položek v Local Storage, kontakt a správa souhlasů.]
MD,
    ],
    'withdrawal' => [
      'title' => 'Poučení o odstoupení od smlouvy u příslušných služeb platformy',
      'provisions' => <<<'MD'
## Rozsah použití
Toto znění je nutné přizpůsobit skutečně nabízeným placeným službám a spotřebitelům, kterým zákon poskytuje ochranu. [REQUIRED: odborně posuďte schválené země, postavení spotřebitele a dotčené typy smluv.] Jiná kogentní práva spotřebitelů podle konkrétního rozhodného práva nelze vyloučit.

## Právo na odstoupení od smlouvy
Pokud existuje zákonné právo na odstoupení, mohou spotřebitelé od smlouvy odstoupit do čtrnácti dnů bez uvedení důvodu. U smlouvy o poskytování služeb začíná lhůta v zásadě uzavřením smlouvy, jsou-li splněny zákonné informační požadavky. K uplatnění práva je zapotřebí jednoznačné prohlášení adresované [REQUIRED: název, adresa a e-mail provozovatele]. Vzorový formulář lze použít, není však povinný. Lhůta je zachována včasným odesláním podle zákonných podmínek.

## Důsledky a předčasné zahájení
Přijaté platby se vracejí podle zákonných lhůt a požadavků, v zásadě stejným platebním prostředkem, pokud nebylo účinně dohodnuto jinak. Pokud spotřebitel výslovně požádal o zahájení služby během lhůty pro odstoupení, může vzniknout nárok na zákonem stanovenou přiměřenou úhradu jen při splnění informačních požadavků a požadavků na projev vůle. Úplný zánik práva odstoupit od smlouvy o službách předpokládá splnění příslušných zákonných podmínek. Samotná aktivace bumpu nebo platba nepředstavuje plošné vzdání se práv k nevyužitému kreditu či dalším plněním z balíčku.

[REQUIRED: posuďte konkrétní postup objednání, potvrzení, zahájení plnění a vrácení platby. Neztotožňujte službu s digitálním obsahem bez posouzení.]
MD,
    ],
    'withdrawal-form' => [
      'title' => 'Vzorový formulář pro odstoupení od smlouvy',
      'provisions' => <<<'MD'
## Použití
Tento formulář lze použít k odstoupení od příslušné spotřebitelské smlouvy o službě platformy. Nejde o povinnou formu platného jednoznačného prohlášení a nenahrazuje úplné poučení o odstoupení. Nepotřebné údaje je třeba vyškrtnout.

## Prohlášení
Adresát [REQUIRED: právní název provozovatele, úplná adresa a e-mailová adresa]:

Tímto odstupuji/odstupujeme od smlouvy, kterou jsem/jsme uzavřeli o následující službě platformy:

Služba a případná reference objednávky: ______________________________
Datum objednání: ______________________________
Jméno spotřebitele/spotřebitelů: ______________________________
Adresa spotřebitele/spotřebitelů: ______________________________
Datum: ______________________________
Podpis spotřebitele/spotřebitelů, pouze při podání v listinné podobě: ______________________________

## Vyřízení
Uvádějí se jen informace potřebné pro přiřazení a vyřízení. Zasílání kopií dokladů totožnosti, přístupových údajů nebo úplných důvěrných platebních údajů k tomu v zásadě není nutné. Provozovatel musí zajistit skutečně dostupný způsob doručení a zdokumentovaný postup vrácení platby. Přijetí formuláře není podmíněno poplatkem za zpracování.

[REQUIRED: před zveřejněním slaďte kontakt a konkrétní dotčené smlouvy s poučením o odstoupení.]
MD,
    ],
    'moderation' => [
      'title' => 'Pravidla moderování',
      'provisions' => <<<'MD'
## Zásady
Moderování probíhá podle srozumitelných pravidel platformy a rozhodného práva. Protiprávní obsah, podvody, nebezpečné nahrávané soubory, obtěžování, zmanipulovaná hodnocení a neschválené regulované zboží jsou nepřípustné. Platforma nesmí vlastní zákonné povinnosti zcela přenést na uživatele.

## Postup
Hlášením se přiděluje číslo případu a posuzují se podle naléhavosti a příslušnosti. Přístup je možný pouze k nezbytným informacím. Soukromé konverzace nejsou v plném rozsahu zpřístupněny k nahlížení jen na základě obecné role správce. Dobrovolně poskytnuté doplňující údaje se zpracovávají jen pro daný účel. Rozhodnutí musejí být podle rozhodného práva odůvodněna a dotčení uživatelé informováni o dostupných možnostech stížnosti.

## Opatření a meze
Možná opatření zahrnují upozornění, výstrahu, dočasné pozastavení nebo skrytí konkrétního obsahu a při odpovídající příslušnosti také omezení účtu. Přiměřeně se zohlední rozsah, doba trvání, závažnost a opakování. Aukce s příhozy se při rozporech převede do stavu přezkumu. Příhozy ani vítězové se skrytě nevymýšlejí ani neodstraňují. Rozhodnutí v případu nenahrazuje soudní rozhodnutí o soukromoprávních nárocích.

[REQUIRED: odborně posuďte skutečnou příslušnost, zákonné výjimky, obsah odůvodnění, použitelnost DSA a lhůty.]
MD,
    ],
    'complaints' => [
      'title' => 'Postup podávání hlášení a stížností',
      'provisions' => <<<'MD'
## Hlášení
Uživatelé mohou stanoveným způsobem hlásit konkrétní nabídky, uživatele, zprávy, k nimž mají oprávněný přístup, hodnocení a zveřejněnou reklamu. Hlášení musí dostatečně určit předmět a uvést srozumitelný důvod. Dobrovolně uváděné další informace mají být omezeny na nezbytný rozsah. Oprávněný podnět nesmí být podmíněn sdělením nadbytečných identifikačních nebo platebních údajů.

## Vyřízení a rozhodnutí
Platforma přidělí číslo případu a zdokumentuje kontrolu, odpovědné vyřízení, rozhodnutí a jeho odůvodnění. Samotné hlášení automaticky nevede k sankci. Pokud to vyžaduje zákon, informují se oprávnění účastníci o přijetí a rozhodnutí. Totožnost oznamovatele a důvěrné důkazy se nesmějí protistraně sdělovat plošně.

## Stížnost
Dotčení oprávnění účastníci mohou prostřednictvím určeného přístupu k případu požádat o odůvodněný přezkum sděleného rozhodnutí. Stížnost musí být skutečně vyřízena. Její zaevidování automaticky neruší opatření. Nové věcné posouzení nebo potvrzení se dokumentuje. Zákonné vnější možnosti stížnosti, smírného řešení a právní ochrany zůstávají nedotčeny. Interní technické omezení opakovaných totožných podání nesmí nepřípustně krátit kogentní zákonná práva.

[REQUIRED: doplňte kontakt pro protiprávní obsah, skutečné lhůty, použitelnost DSA a mimosoudní možnosti stížnosti.]
MD,
    ],
    'disputes' => [
      'title' => 'Podmínky pro spory',
      'provisions' => <<<'MD'
## Smluvní strany
Spory o stav, platbu, dodání nebo plnění kupní smlouvy na zboží se v zásadě týkají kupujícího a prodávajícího jako samostatných smluvních stran. Technická platforma není automaticky prodávajícím, poskytovatelem přepravy, pojistitelem ani rozhodčím soudem. Přesto plní vlastní zákonné a výslovně převzaté povinnosti.

## Podpora
Platforma může poskytnout existující oprávněné komunikační cesty a interní postup hlášení. Zpřístupňují se pouze nezbytné údaje. Uživatelé nesmějí předkládat padělané doklady, používat cizí účty ani vyvolávat neoprávněné reklamace plateb. Interní stav přezkumu automaticky nepotvrzuje nárok žádné strany.

## Důkazy a právní prostředky
Události u příhozů, plateb a případů se nesmějí dodatečně skrytě pozměňovat. Nezbytné doklady jsou zabezpečeny podle zdokumentovaného právního základu a pravidel uchovávání. Ruční potvrzení platby označuje ověření provozovatelem. Nepředstavuje nezávislé soudní nebo kryptografické potvrzení. Zákonné nároky, soudní řízení a skutečně nabízené mimosoudní řešení sporů zůstávají zachovány. Není slibována služba úschovy ani úplná záruka kupní ceny.

[REQUIRED: posuďte přesný rozsah dobrovolné podpory, bezplatnost nebo cenu, příslušnost a zákonem vyžadované informace o řešení sporů.]
MD,
    ],
    'payments' => [
      'title' => 'Platební podmínky služeb platformy',
      'provisions' => <<<'MD'
## Oddělení od koupě zboží
Tyto podmínky se týkají vlastních poplatků provozovatele, nikoli obecného přijímání kupních cen mezi uživateli. Dostupné platební metody se u konkrétní objednávky zobrazí před uzavřením smlouvy. Bankovní převod, bitcoin nebo úschovu nelze považovat za použitelné jen proto, že je viditelná vypnutá volba.

## Ruční a automatická kontrola
Ruční platební příkaz PayPal obsahuje pevnou částku v EUR, skutečný popis služby, příjemce a jedinečnou referenci. Provozovatel zkontroluje skutečně dokončené přijetí platby a přiřadí jedinečný identifikátor transakce. Upozornění uživatele, snímek obrazovky ani parametr prohlížeče nejsou dokladem o platbě. Ruční postup nenahrazuje požadavky na účet PayPal a neodůvodňuje platbu Friends & Family za obchodní služby. Automatická integrace vyžaduje skutečně schválenou konfiguraci poskytovatele a ověřené události na straně serveru.

## Odchylky a plnění
Odlišné částky, reference, příjemci nebo nejasné platební transakce se nesmějí označit za plně uhrazené. Transakce se nesmí zaúčtovat vícekrát. Okamžik aktivace služby vyplývá z potvrzené objednávky. Po uplynutí rezervace místa nesmí být její kapacita dodatečně přečerpána. Chyby, storna a vrácení plateb musejí být zdokumentovány opravnými událostmi. Dřívější faktury se nesmějí skrytě měnit.

[REQUIRED: posuďte vhodnost účtu příjemce, splatnost, prodlení, skutečné podmínky poskytovatele a potvrzení smlouvy.]
MD,
    ],
    'refunds' => [
      'title' => 'Podmínky storna a vrácení plateb',
      'provisions' => <<<'MD'
## Důvod posouzení
Žádosti o vrácení platby se mohou týkat zejména odstoupení, neposkytnutých objednaných služeb, duplicitních plateb, prokazatelně oprávněných smluvních oprav nebo technických výpadků. Tento popis nesmí omezit zákonné nároky. Konkrétní nárok se srozumitelně posuzuje podle objednávky a skutečně poskytnutého rozsahu služeb.

## Dokumentace a přiřazení
Je nutné zdokumentovat žádost, referenci, důvod, rozhodnutí, částku a skutečnou platební metodu. Vrácení platby označené jako vyžádané dosud neproběhlo. Automatické vrácení lze považovat za dokončené až po ověřeném potvrzení poskytovatele. Ruční vrácení vyžaduje doložitelné přiřazení skutečné transakce. Vystavené doklady se nesmějí skrytě přepisovat. Podle potřeby se vystaví storno nebo opravné doklady.

## Kredit a částečná plnění
U balíčků bumpů a služeb s určitou dobou trvání je nutné využitou a nevyužitou část posuzovat odděleně. Jediný okamžitě provedený bump neodůvodňuje plošné vyloučení všech práv ke zbytku balíčku. Pokud prémiové místo kvůli pozdní úhradě není dostupné, je nutné posoudit skutečné řešení. Zdánlivě aktivní místo není splněním služby. Lhůty pro vrácení a přípustné platební prostředky se řídí zákonnými a účinně sjednanými požadavky.

[REQUIRED: odborně posuďte skutečné postupy vyřízení, zákonné lhůty, opravné doklady a postupy poskytovatelů.]
MD,
    ],
    'allowed-products' => [
      'title' => 'Pravidla pro přípustné a nepřípustné produkty',
      'provisions' => <<<'MD'
## Aktivní tržiště
Aktivní tržiště je určeno pro legální běžné neregulované zboží, například vybavení pro pobyt venku a kempování, oděvy, sportovní potřeby, optiku, domácí potřeby, nářadí a média. Konkrétní kategorie nenahrazuje kontrolu produktu ani požadavky země odeslání či země příjemce. Dřívější specializované nabídky se nestávají běžným zbožím pouhým přejmenováním.

## Blokované produkty a produkty s nejasným zařazením
Protiprávní produkty, padělky, odcizené zboží, podvodné nabídky a neschválené skupiny regulovaných zbraní, střeliva či jiných právně významných produktů nesmějí být obchodovány v obecných kategoriích. Volně zadané označení ani stav ověření nepředstavují schválení. Při nejasném zařazení je nutné odborné posouzení. Dotčené transakce zůstávají do té doby blokovány.

## Povinnosti a prosazování pravidel
Poskytovatelé musejí být oprávněni se zbožím nakládat, správně uvést potřebné informace a dodržovat požadavky platné pro produkt, přepravu a cílovou zemi. Provozovatel musí plnit vlastní zákonné a moderační povinnosti. Textové filtry jsou pouze doplňkem a nepředstavují úplnou právní klasifikaci. Pokusy o obcházení pravidel mohou vést k přiměřeným omezením podle odůvodněného moderačního postupu.

[REQUIRED: odborně stanovte závazný seznam skupin produktů, schválené země, příslušnost k posouzení a zákonná omezení podle druhů zboží.]
MD,
    ],
    'account-suspension' => [
      'title' => 'Pravidla omezení a blokování účtů',
      'provisions' => <<<'MD'
## Důvody a přiměřenost
Účty lze dočasně omezit při řádně doložených právních, podvodných nebo bezpečnostních problémech v právně přípustném rozsahu nezbytném k odvrácení nebezpečí. Opatření musí zohlednit povahu, závažnost, dobu trvání, opakování a dopady. Neodůvodněné nebo čistě svévolné blokace nejsou předpokládány.

## Dopady a probíhající záležitosti
Deaktivace účtu ukončí aktivní relace a zabrání novým nepřípustným akcím. Existující příhozy, smlouvy, platby a stížnosti tím nezanikají. Probíhající aukce s příhozy nesmějí být manipulovány tichým odstraněním účastníka. Technické stavy přezkumu a samostatné vyřízení zachovávají nezbytné důkazy.

## Odůvodnění a přezkum
Pokud to vyžaduje právo, sdělí se důvod, rozsah, doba trvání a dostupné možnosti přezkumu. Zákonné výjimky, například ochrana vyšetřování nebo třetích osob, vyžadují individuální posouzení. Uživatelé musejí mít možnost podat oprávněnou stížnost. Vnější práva zůstávají zachována. Dobrovolný výmaz účtu musí být odlišen od sankce a vyžaduje samostatné rozhodnutí o uchovávání údajů podle předpisů o ochraně osobních údajů.

[REQUIRED: posuďte lhůty, opětovný přezkum, komunikaci po deaktivaci, použitelnost DSA a P2B a smluvní pravidla ukončení.]
MD,
    ],
  ],
];
