<?php

return [
    'header' => <<<'MD'
**Status: Ikke-gennemgået udkast. Faglig gennemgang og eventuel gennemgang ved advokat udestår.**
**Dokumentversion: draft-1.0 · Sprog: Dansk.**
**Operatør:** [REQUIRED: fuldt juridisk navn, adresse og kontaktoplysninger].
**Aftaleområde:** [REQUIRED: de lande, hvor funktionerne faktisk er gjort tilgængelige, og de nødvendige landespecifikke forbrugeroplysninger].

Dette udkast beskriver udelukkende funktioner, der faktisk er gjort tilgængelige. Det garanterer ikke juridisk gyldighed og må ikke offentliggøres som gældende aftaletekst uden dokumenteret faglig godkendelse. Operatørens oplysninger, forretningspraksis og de konkrete bestillingsgrænseflader skal stemme overens.
MD,
    'documents' => [
        'terms' => [
            'title' => 'Almindelige forretningsbetingelser',
            'provisions' => <<<'MD'
## 1. Aftaleparter og anvendelsesområde
Disse betingelser gælder for adgangen til den tekniske platform og de platformstjenester, der udtrykkeligt bestilles. Operatøren formidler brugernes tilbud. Som udgangspunkt indgår køber og sælger aftalen om køb af varen med hinanden. Platformen bliver derved hverken ejer eller sælger og påtager sig ikke automatisk forsendelse, overdragelse eller opbevaring. Om en funktion medfører yderligere pligter for operatøren, afhænger af, hvordan den faktisk er udformet, og af ufravigelige retsregler.

## 2. Konto, tilbud og aftaleindgåelse
Kontooplysningerne skal være korrekte. Loginoplysninger må ikke videregives. Mistænkelig adgang skal omgående anmeldes. Sælgere må kun tilbyde tilgængelige, lovlige almindelige varer og skal give korrekte oplysninger om varens stand, pris, leveringsvilkår og en eventuel erhvervsmæssig status. Indgåelsen af aftaler ved auktioner og tilbud med fast pris følger de særskilte betingelser. Aktivering af en kategori eller identitetskontrol udgør ikke en generel retlig tilladelse til handel.

## 3. Tjenester, ændringer og ophør
Omfang, samlet pris og løbetid vises før bestilling af tjenester mod betaling og registreres sammen med den gældende version. Regler om tilgængelighed, moderation og betaling må ikke ændres skjult med tilbagevirkende kraft. Ved væsentlige aftaleændringer skal det afklares, om der kræves underretning eller nyt udtrykkeligt samtykke. Fortsat brug kan ikke i sig selv erstatte en lovpligtig erklæring. Brugere kan anmode om deaktivering af kontoen og om en vurdering af sletning efter databeskyttelsesreglerne.

## 4. Ansvar og erstatningsansvar
Platformen skal overholde sine egne lovbestemte pligter til at kontrollere, oplyse og reagere. Ingen ulovlige ansvarsfraskrivelser gælder ved forsæt, grov uagtsomhed, skade på liv, legeme eller helbred eller ved ufravigeligt ansvar, navnlig efter den tyske produktansvarslov (Produkthaftungsgesetz) eller i henhold til garantier, der er stillet. Ved simpelt uagtsom tilsidesættelse af væsentlige aftaleforpligtelser kan ansvaret kun begrænses til et forudsigeligt tab, der er typisk for aftalen, i det omfang loven tillader det. En pligt for brugerne til at holde operatøren skadesløs forudsætter en retskrænkelse, som de er ansvarlige for, og berettigede krav. Egen skyld og indsigelser tages fortsat i betragtning.

## 5. Gennemgang før offentliggørelse
[REQUIRED: foretag en faglig gennemgang af gældende ret, tilladte værnetingsbestemmelser for erhvervsdrivende, forbrugeroplysninger, kommunikation om aftalen og den konkrete beskrivelse af tjenesterne.]
MD,
        ],
        'private-use' => [
            'title' => 'Brugsvilkår for private brugere',
            'provisions' => <<<'MD'
## Konto og tilladt brug
Private brugere handler til egne private formål. Erhvervsmæssig aktivitet må ikke præsenteres som privat salg. Kontonavne må ikke krænke tredjemands rettigheder eller give indtryk af officiel kontrol eller af en verifikation, der ikke har fundet sted. Påkrævede kontooplysninger skal være ajourførte. Identitetsdokumenter, der ikke er nødvendige, må ikke sendes via offentlige annoncer eller private beskeder.

## Tilbud og kontakt
Tilbud skal beskrive den vare, der faktisk er til rådighed, på en forståelig måde. Pris, stand, afhentning, forsendelsesomkostninger og synlige mangler skal afklares inden aftaleindgåelsen. Billeder må kun offentliggøres med den relevante brugsret. Den interne chat bruges til kommunikation om det konkrete tilbud. Chikane, massereklame, phishing, omgåelse af spærringer og offentliggørelse af andres private indhold er forbudt.

## Rettigheder og ophør
Gratis private annoncer med fast pris tilbydes kun inden for det offentliggjorte tjenesteomfang. Betalt fremhævelse kræver en særskilt bestilling. Der gives ingen garanti for et salg eller en bestemt rækkevidde. Brugere kan administrere gemte tilbud og personlige funktioner i det omfang, funktionerne er tilgængelige, og anmode om deaktivering af kontoen. Åbne aftaler, lovbestemt opbevaring og berettiget dokumentation i tvister kan forhindre, at enkelte dataposter straks slettes fuldstændigt. Afgørelser skal forklares på en forståelig måde og kunne efterprøves.

[REQUIRED: gennemgå minimumsalder for at oprette konto, tilladte lande, de faktiske grænser for gratis brug og aftalebekræftelse.]
MD,
        ],
        'dealer' => [
            'title' => 'Supplerende betingelser for erhvervssælgere',
            'provisions' => <<<'MD'
## Erhvervsmæssig identitet
Erhvervssælgere skal oplyse deres faktiske status og fremlægge alle lovpligtige virksomheds-, kontakt-, registrerings-, skatte- og forbrugeroplysninger. Platformens operatør anvender den fastlagte procedure til at kontrollere de oplysninger om forhandleren, som operatøren efter loven skal kontrollere. En offentlig profil eller et uploadet logo erstatter ikke en offentlig myndigheds tilladelse eller en faglig godkendelse.

## Tilbud og forbrugerforpligtelser
Erhvervssælgere har ansvaret for korrekt varebeskrivelse, samlet pris, leveringsomkostninger, tilgængelighed, lovbestemte rettigheder ved mangler og nødvendige oplysninger om fortrydelsesret for deres egen købsaftale. Platformen er fortsat ansvarlig for at overholde sine egne lovbestemte pligter. Tilbud på tværs af landegrænser forudsætter, at levering, skattemæssig behandling, forbrugeroplysninger og tilladt produkttype er afklaret for de lande, tilbuddet konkret retter sig mod.

## Administration og reklame
Masseimport skal overholde de fastlagte formater og produktregler. Linjer med fejl må ikke markeres som importeret uden fejl. Medarbejderadgang kræver særskilte tilladelser, når denne funktion faktisk tilbydes. Fælles, ukontrollerede administratorkonti er ikke en del af ordningen. Reklametjenester er underlagt særskilt booking, gennemgang og reklamebetingelserne. Der gives ingen garanti for salgsvolumen eller for en lovbestemt forhandlergodkendelse.

[REQUIRED: gennemgå identitetskontrol, kontaktoplysninger for erhvervssælgere, tilladte salgsområder, kommunikation om aftalen og eventuelle P2B-forpligtelser.]
MD,
        ],
        'auction' => [
            'title' => 'Auktionsbetingelser for almindelige varer',
            'provisions' => <<<'MD'
## Udbuddets omfang og bindende oplysninger
Auktioner er kun tilladt for almindelige varer, som er godkendt til handel på platformen. Sælgeren fastsætter startpris, løbetid, vilkår for overdragelse og en eventuel mulighed for direkte køb via »Køb nu«. Bud på egne varer, aftalt brug af proformabydere og kunstig prisforhøjelse er forbudt. Identiteten knyttet til en konto og en kategori erstatter ikke en lovbestemt produktgodkendelse.

## Bud og afslutning
Det fortrolige maksimumbud vises ikke offentligt. Automatisk budgivning forhøjer kun det synlige beløb efter den offentliggjorte regel om budintervaller. Ved ens maksimumbud har det først accepterede bud forrang som fastsat i systemet. Et gyldigt bud inden for de sidste to minutter forlænger auktionen til mindst to minutter efter buddets accept. Serverens accepttidspunkt er afgørende. Sene eller ikke tilladte bud samt bud fra sælgeren afvises.

## Aftale og driftsforstyrrelser
[REQUIRED: fastlæg efter faglig gennemgang det præcise tidspunkt for og erklæringerne ved aftaleindgåelsen ud fra den faktiske grænseflade.] Vinderen og sælgeren får de oplysninger, der er nødvendige for opfyldelse af aftalen, gennem de kontaktmuligheder, der tilbydes. Når modstridende oplysninger fører til, at sagen sættes til kontrol, giver det ikke adgang til at udpege en vilkårlig vinder. Væsentlige ændringer efter afgivelse af bud, uberettiget tidlig afslutning og omgåelse af gyldige aftaler er ikke tilladt. Tekniske driftsforstyrrelser, anfægtelse, berettiget tilbagetrækning og aftaler, som dokumenterbart ikke er blevet gennemført, skal behandles efter en dokumenteret procedure. Gebyrer opstår kun ved de aftalte begivenheder. Ubetalte krav udgør ikke bekræftede betalinger.
MD,
        ],
        'fees' => [
            'title' => 'Gebyr- og prisliste',
            'provisions' => <<<'MD'
## Prisgrundlag
Den bindende samlede pris er den, der vises for den konkrete booking inden bestillingen med betalingspligt, og som registreres sammen med gebyrversionen. Operatørens aktuelle prisindstillinger må ikke med tilbagevirkende kraft gøre tidligere bestillinger dyrere. Skat, nettobeløb og bruttobeløb skal fremgå særskilt i overensstemmelse med den skattemæssige behandling, der er efterprøvet. [REQUIRED: operatørens skattemæssige status, momsbehandling og eventuelle oplysninger om fritagelse.]

## Planlagte standardpriser
Private annoncer med fast pris, søgeannoncer og søgealarmer er gratis i det fastlagte omfang. De planlagte priser er 0,50 EUR for oprettelse af en auktion, 1,99 EUR pr. Bump, 4,99 EUR for tre Bumps, 9,90 EUR for topplacering i en kategori i syv dage, 19,90 EUR for topplacering på forsiden i syv dage og 129 EUR for et forhandlerbanner i en måned. Listen aktiverer ikke funktioner, der endnu ikke tilbydes. Afvigende priser fastsat i administrationen skal vises tydeligt inden bestilling.

## Progressiv auktionsprovision
Salgsprovisionen beregnes som 3 procent af de første 100 EUR, 2 procent af den del over 100 EUR og op til 500 EUR, 1 procent af den del over 500 EUR og op til 2.000 EUR og 0,5 procent af den del, der ligger derover, dog højst 29,90 EUR. Oprettelsesgebyret kommer oveni. Salgsprovision forudsætter en gyldig og gennemført afslutning efter de aftalte regler. Annulleringer, handler, der dokumenterbart ikke er blevet indgået, og tilbagebetalinger skal korrigeres på en dokumenteret måde. Et gebyroverslag er ikke en faktura, og en meddelelse fra en kunde er ikke en modtaget betaling.
MD,
        ],
        'premium' => [
            'title' => 'Betingelser for Bumps og premiumplaceringer',
            'provisions' => <<<'MD'
## Tjenestens omfang
Et Bump flytter en af brugerens egne eksisterende annoncer op i sorteringen efter aktualitet. Den oprindelige oprettelsesdato, buddene og auktionens sluttidspunkt forbliver uændrede. Den samme vare kan højst få ét Bump inden for 72 timer. Tilgodehavendet frigives først efter den fastsatte betalingskontrol og aktiveres derefter ved en aktiv handling fra brugeren.

## Begrænsede pladser og løbetider
Topplacering i en kategori og topplacering på forsiden kan kun bookes i det omfang, de faktisk er gjort tilgængelige. Antal ledige pladser, det tilknyttede land eller den tilknyttede kategori, samlet pris, reservationsfrist og løbetid vises inden bestilling. En udløbet, ubetalt reservation giver ingen garanti for en varigt booket plads. Modtages betalingen, efter at pladsen ikke længere er tilgængelig, må der ikke ske overbooking eller aktivering af en plads, der reelt ikke er til rådighed. Operatøren skal tilbyde et dokumenteret alternativ eller en vurdering af tilbagebetaling.

## Mærkning, resultater og rettigheder
Betalte placeringer mærkes som reklame eller sponsoreret indhold. Medmindre det særskilt aftales udtrykkeligt, gives der ingen garanti for et bestemt antal visninger, henvendelser eller salg. Lovbestemte forbrugerrettigheder består. Omgående påbegyndelse af tjenesten kræver den udtrykkelige erklæring, loven kræver. Bestillingen alene udgør ikke et generelt afkald på fortrydelsesretten. Ved delvist ubrugte pakker og tekniske driftsafbrydelser skal der være mulighed for en rimelig korrektion og vurdering af tilbagebetaling efter loven.

[REQUIRED: gennemgå det faktiske antal pladser, opdelingen efter lande, reglerne for start, kompensation ved driftsafbrydelser og proceduren for fortrydelse.]
MD,
        ],
        'advertising' => [
            'title' => 'Reklamebetingelser for erhvervssælgere',
            'provisions' => <<<'MD'
## Kampagne og gennemgang
Reklame må kun bookes for lovlige tjenester eller almindelige varer, der passer til platformen. Annoncørens identitet, destinationsadressen, annoncematerialet og de obligatoriske oplysninger skal være korrekte. Vildledende oplysninger om godkendelser, ulovlige produkter, skadelig software og skjulte omdirigeringer er forbudt. Godkendelse af annoncemateriale udgør hverken myndighedsgodkendelse eller retlig godkendelse af forretningsmodellen.

## Visning og tilgængelighed
Land, kategori, løbetid, antal tildelte pladser og samlet pris fastlægges før booking. Landerettet visning giver ingen garanti for, at der må leveres til alle forbrugere, der kan nås i det pågældende land, uden yderligere forpligtelser. En kampagne må først vises som aktiv, når den er gennemgået, betalt og aktiveret for den fastlagte periode. Begrænsede pladser må ikke sælges to gange.

## Statistik og ophør
Klik- og visningstal er interne målinger efter den offentliggjorte optællingsmetode. De giver ingen garanti for antallet af forskellige personer og udgør ingen salgsprognose. Manipulerede klik på egne annoncer, automatiseret misbrug og ulovlig profilering er forbudt. Kampagner kan sættes i bero i et forholdsmæssigt omfang, når retlige eller sikkerhedsmæssige problemer kan begrundes. Begrundelsen, klagemuligheden og håndteringen af den betalte, ubrugte løbetid skal dokumenteres.

[REQUIRED: tilføj udbyderens identitet, den månedlige aftaleperiode, forlængelse, opsigelse, definition af statistik samt regler om driftsafbrydelser og tilbagebetaling.]
MD,
        ],
        'verification' => [
            'title' => 'Verifikations- og sikkerhedsbetingelser',
            'provisions' => <<<'MD'
## Særskilte kontrolresultater
Identitet og opfyldelse af minimumsalderen kontrolleres særskilt. Ingen af kontrollerne bekræfter ejerskab til en vare, lovlig ret til at erhverve den, betalingsevne eller pålidelig adfærd. Kontrollerne åbner ikke for spærrede handelsfunktioner for regulerede varer.

## Dokumenter og formål
Kun de oplysninger i dokumenterne, der er nødvendige for det angivne kontrolformål, må behandles. Brugeren bør kunne skjule oplysninger, der ikke er nødvendige. Dokumenter må ikke indgå i offentlige produktbilleder eller sendes i den almindelige chat. Før indsendelse skal den ansvarlige enhed, formålet, retsgrundlaget, den faktiske opbevaringsperiode og kontaktmuligheden ved spørgsmål fremgå tydeligt. Indsamlingen forbliver deaktiveret, indtil disse forudsætninger og den tekniske opbevaringsløsning er godkendt.

## Adgang, afgørelse og sletning
Billeder af dokumenterne kontrolleres, omkodes og opbevares krypteret uden for webserverens rodmappe. Kun sagsbehandlere med adgang til den pågældende sag og multifaktorgodkendelse får tidsbegrænset adgang. Opslag og afgørelser logges. Godkendelser, anmodninger om flere oplysninger og afslag skal kunne forklares. Filerne fjernes sikkert efter udløbet af den nødvendige opbevaringsperiode for dokumenterne, som fastsættes efter faglig vurdering. En registrering med de mindst nødvendige oplysninger om afgørelsen kan kun opbevares længere med et særskilt retsgrundlag.

[REQUIRED: fastlæg efter faglig vurdering den ansvarlige kontrolenhed, retsgrundlaget for hvert bevis, dokumenttyperne, de konkrete frister og fornyet kontrol.]
MD,
        ],
        'privacy' => [
            'title' => 'Erklæring om databeskyttelse',
            'provisions' => <<<'MD'
## Dataansvarlig og kontaktoplysninger
[REQUIRED: den dataansvarliges fulde identitet og adresse, kontaktoplysninger vedrørende databeskyttelse og en databeskyttelsesrådgiver, hvis påkrævet.] Erklæringen skal afspejle de systemer, der faktisk bruges, og de lande, hvor de er gjort tilgængelige. Udkastet forudsætter ikke, at en databeskyttelsesgennemgang allerede er afsluttet.

## Formål og retsgrundlag
Kontooplysninger, tilbud og nødvendig kommunikation om aftalen behandles for at levere tjenesterne og opfylde brugsaftalen, i det omfang der er grundlag for det efter artikel 6, stk. 1, litra b, i databeskyttelsesforordningen (GDPR). Lovpligtige finansielle oplysninger eller dokumentation kan efter omstændighederne behandles efter litra c. Forholdsmæssig forebyggelse af misbrug og sikkerhedslogning kræver en konkret dokumenteret interesseafvejning efter litra f. Et faktisk frivilligt samtykke efter litra a kan ikke generelt erstattes af den blotte brug af tjenesten. For identitetsdokumenter og særlige sagsoplysninger skal nødvendigheden vurderes særskilt.

## Oplysninger, modtagere og sikkerhed
Afhængigt af funktionen behandles konto- og kontaktoplysninger, offentlige tilbud, private beskeder, der må tilgås, bud, status for bestillinger og betalinger, præferencer samt de mindst nødvendige oplysninger til sikkerhed og moderation. Adgangen til private beskeder er begrænset. Beskederne krypteres på serversiden, hvis den relevante løsning er implementeret. Der er i så fald ikke tale om ende-til-ende-kryptering. Betalingsudbydere kan være selvstændigt dataansvarlige for egne tjenester. Hosting, lokal mailtransport og andre faktiske modtagere skal angives konkret. [REQUIRED: modtagere, databehandling på vegne af den dataansvarlige, opbevaringssteder, overførsler til tredjelande og garantier.]

## Frister og rettigheder
[REQUIRED: begrundede opbevaringsfrister for hver kategori af oplysninger, herunder konti, aftaler, beskeder, sikkerhedslogfiler og dokumenter.] Brugere kan udøve deres rettigheder til indsigt, berigtigelse, sletning i de tilfælde, loven fastsætter, begrænsning af behandling, dataportabilitet og efter omstændighederne indsigelse eller tilbagetrækning af samtykke. En anmodning ophæver ikke ufravigelige opbevaringspligter. Det er fortsat muligt at klage til en kompetent databeskyttelsesmyndighed. [REQUIRED: kompetent tilsynsmyndighed og tydelig kontaktmulighed for udøvelse af rettigheder.]
MD,
        ],
        'imprint' => [
            'title' => 'Lovpligtige oplysninger og oplysninger om operatøren',
            'provisions' => <<<'MD'
## Tjenesteudbyder
Fuldt juridisk navn: [REQUIRED: udbyderens navn og retlige form].
Adresse, hvor forkyndelse kan ske: [REQUIRED: vej, husnummer, postnummer, by, land].
Tegningsberettiget person: [REQUIRED: faktisk repræsentant, hvor det er påkrævet].
Kontakt: [REQUIRED: e-mail og en yderligere direkte kontaktmulighed, der faktisk er egnet].

## Register- og skatteoplysninger
Register og registreringsnummer: [REQUIRED: faktiske registeroplysninger, hvis de findes og er påkrævede].
Momsregistreringsnummer eller krævet virksomhedsidentifikation: [REQUIRED: korrekte oplysninger eller faglig begrundelse for, at kravet ikke finder anvendelse].
Påkrævede oplysninger om tilladelse, erhverv eller tilsyn: [REQUIRED: kun faktisk nødvendige og korrekte oplysninger]. Myndigheders kompetence eller tilladelser må ikke opfindes.

## Indholdsansvar og tvistbilæggelse
[REQUIRED: i givet fald den indholdsansvarlige efter gældende ret].
Oplysninger om bilæggelse af forbrugertvister skal korrekt afspejle den faktiske villighed til at deltage eller en lovbestemt pligt hertil. Forældede links til platforme eller generelle påstande om tvistbilæggelse må ikke overtages uden gennemgang. Kontaktmuligheden vedrørende databeskyttelse og kanalen til indberetninger skal være tilgængelige. Denne struktur kan først offentliggøres som lovpligtige udbyderoplysninger, når den er udfyldt og fagligt gennemgået.
MD,
        ],
        'cookies' => [
            'title' => 'Oplysninger om cookies og lagring',
            'provisions' => <<<'MD'
## Teknisk nødvendige funktioner
Ved login bruges et sessions-id, så adgangen til kontoen og formularerne knyttes til den rette session. Der bruges sikkerhedsattributter som HttpOnly og SameSite samt Secure ved HTTPS. Et CSRF-token beskytter formularer, der ændrer systemets tilstand. Disse funktioner udgør ikke samtykke til sporing til reklameformål.

## Visningspræferencer
Farvetema, pause af animationer og eventuelt sprog eller markedsland kan gemmes lokalt eller i sessionen, så den valgte visning kan gendannes. [REQUIRED: gennemgå de faktiske lagringsnavne, lagringstyper, varigheder og formål for hver post]. Grænsefladen skal respektere et ændret valg. Hvis databeskyttelsesreglerne kræver samtykke, må det ikke være forudvalgt.

## Statistik og tredjeparter
Reklamenetværk eller eksterne analysetjenester aktiveres ikke alene som følge af dette udkast. Lokale målinger, der faktisk anvendes, tællere til forebyggelse af misbrug og eventuelle omdirigeringer til PayPal skal beskrives åbent. Inden ikke-nødvendig brug af lagring aktiveres, skal det afklares, om gældende ret kræver samtykke. Et værktøj, der tilføjes senere, kræver en ny teknisk og juridisk vurdering. Den eksisterende tilladelse til sessionen omfatter ikke automatisk værktøjet.

[REQUIRED: oversigt over alle cookies, der faktisk sættes, og alle poster i lokal lagring samt kontaktoplysninger og administration af samtykke.]
MD,
        ],
        'withdrawal' => [
            'title' => 'Oplysninger om fortrydelsesret for relevante platformstjenester',
            'provisions' => <<<'MD'
## Anvendelsesområde
Denne version skal tilpasses de tjenester mod betaling, der faktisk tilbydes, og de forbrugere, der er beskyttet efter loven. [REQUIRED: foretag en faglig gennemgang af de lande, hvor tjenesterne tilbydes, forbrugerstatus og de berørte aftaletyper.] Afvigende ufravigelige forbrugerrettigheder efter den lovgivning, der finder anvendelse, må ikke udelukkes.

## Fortrydelsesret
Når der gælder en lovbestemt fortrydelsesret, kan forbrugeren fortryde aftalen inden for fjorten dage uden at angive en grund. Ved tjenesteydelsesaftaler løber fristen som udgangspunkt fra aftalens indgåelse, forudsat at de lovbestemte oplysningskrav er opfyldt. Fortrydelsesretten udøves ved en utvetydig erklæring til [REQUIRED: operatørens navn, adresse og e-mail]. Standardformularen kan bruges og er frivillig. Fristen er overholdt, hvis erklæringen er afsendt i tide under de betingelser, der følger af loven.

## Følger og tidlig påbegyndelse
Modtagne betalinger skal tilbagebetales inden for lovens frister og på de betingelser, loven fastsætter, som udgangspunkt med samme betalingsmiddel, medmindre andet er gyldigt aftalt. Har forbrugeren udtrykkeligt anmodet om, at tjenesten påbegyndes inden fortrydelsesfristens udløb, kan der kun opkræves en lovbestemt rimelig godtgørelse for den leverede del, når oplysnings- og erklæringskravene er opfyldt. Fuldstændigt bortfald af fortrydelsesretten for tjenester forudsætter, at de relevante lovbestemte betingelser er opfyldt. Aktivering af et Bump eller betaling alene udgør ikke et generelt afkald på rettigheder vedrørende ubrugt tilgodehavende eller andre tjenester i en pakke.

[REQUIRED: gennemgå det faktiske forløb for bestilling, bekræftelse, påbegyndelse af tjenesten og tilbagebetaling. Tjenester og digitalt indhold må ikke sidestilles uden forudgående gennemgang.]
MD,
        ],
        'withdrawal-form' => [
            'title' => 'Standardformular til fortrydelse',
            'provisions' => <<<'MD'
## Brug
Formularen kan anvendes til at fortryde en berørt forbrugeraftale om en platformstjeneste. En gyldig, utvetydig erklæring kan afgives uden denne formular. Formularen erstatter ikke de fuldstændige oplysninger om fortrydelsesret. Overflødige oplysninger overstreges.

## Erklæring
Til [REQUIRED: operatørens juridiske navn, fulde adresse og e-mailadresse]:

Jeg/vi meddeler herved, at jeg/vi ønsker at gøre fortrydelsesretten gældende for min/vores aftale om følgende platformstjeneste:

Tjeneste og eventuel bestillingsreference: ______________________________
Bestilt den: ______________________________
Forbrugerens eller forbrugernes navn: ______________________________
Forbrugerens eller forbrugernes adresse: ______________________________
Dato: ______________________________
Forbrugerens eller forbrugernes underskrift, kun ved erklæring på papir: ______________________________

## Behandling
Der skal kun angives de oplysninger, der er nødvendige for at henføre erklæringen til den rette bestilling og behandle den. Kopier af identitetsdokumenter, loginoplysninger eller fuldstændige fortrolige betalingsoplysninger er som udgangspunkt ikke nødvendige. Operatøren skal sørge for en modtagelseskanal, der faktisk er tilgængelig, og en dokumenteret tilbagebetalingsproces. Modtagelsen af formularen er ikke betinget af et behandlingsgebyr.

[REQUIRED: afstem kontaktoplysningerne og de konkrete berørte aftaler med oplysningerne om fortrydelsesret inden offentliggørelse.]
MD,
        ],
        'moderation' => [
            'title' => 'Retningslinjer for moderation',
            'provisions' => <<<'MD'
## Principper
Moderation sker efter forståelige platformsregler og gældende ret. Ulovligt indhold, svig, farlige uploads, chikane, manipulerede anmeldelser og regulerede varer, der ikke er godkendt på platformen, er ikke tilladt. Platformen må ikke overføre alle sine egne lovpligter til brugerne.

## Procedure
Anmeldelser tildeles et sagsnummer og vurderes efter, hvor hastende de er, og hvem der har kompetence til at behandle dem. Der må kun tilgås nødvendige oplysninger. En generel administratorrolle giver ikke i sig selv fuld adgang til at læse private samtaler. Frivilligt indsendte supplerende oplysninger må kun behandles til det angivne formål. Afgørelser skal begrundes efter gældende ret, og berørte brugere skal informeres om de klagemuligheder, der findes.

## Foranstaltninger og grænser
Mulige foranstaltninger er en meddelelse, en advarsel, midlertidig tilbageholdelse eller skjul af konkret indhold og, hvor der er kompetence til det, begrænsning af en konto. Omfang, varighed, alvor og gentagelse skal tages i betragtning på en rimelig måde. Hvis der foreligger modstridende oplysninger, sættes en auktion med bud til kontrol. Bud eller vindere må ikke i det skjulte opfindes eller fjernes. En afgørelse i sagen erstatter ikke en domstolsafgørelse om civilretlige krav.

[REQUIRED: foretag en faglig gennemgang af den faktiske kompetencefordeling, lovbestemte undtagelser, begrundelsens indhold, DSA’s anvendelse og frister.]
MD,
        ],
        'complaints' => [
            'title' => 'Anmeldelses- og klageprocedure',
            'provisions' => <<<'MD'
## Anmeldelse
Brugere kan via den fastlagte anmeldelseskanal anmelde konkrete tilbud, brugere, beskeder, som de har lovlig adgang til, bedømmelser og offentliggjorte reklamer. Det skal tydeligt fremgå, hvad anmeldelsen vedrører, og begrundelsen skal være forståelig. Frivillige supplerende oplysninger bør begrænses til det nødvendige. En berettiget henvendelse må ikke gøres afhængig af, at der videregives unødvendige identitets- eller betalingsoplysninger.

## Behandling og afgørelse
Platformen tildeler et sagsnummer og dokumenterer undersøgelsen, hvem der behandler sagen, afgørelsen og begrundelsen. En anmeldelse fører ikke automatisk til en sanktion. Når loven kræver det, underrettes de berettigede parter om modtagelsen og afgørelsen. Anmelderens identitet og fortrolig dokumentation må ikke rutinemæssigt videregives til modparten.

## Klage
Berørte parter med klageret kan via den fastlagte adgang til sagen indgive en begrundet anmodning om at få den meddelte afgørelse efterprøvet. Klagen skal faktisk behandles. Registreringen ophæver ikke automatisk foranstaltningen. En fornyet gennemgang af sagen eller en stadfæstelse af afgørelsen dokumenteres. Lovbestemte eksterne klagemuligheder, tvistbilæggelse og adgang til domstolene berøres ikke. En intern teknisk begrænsning af gentagne identiske indsendelser må ikke ulovligt indskrænke ufravigelige lovbestemte rettigheder.

[REQUIRED: tilføj kontaktmulighed for anmeldelse af ulovligt indhold, de faktiske frister, DSA’s anvendelse og udenretslige klagemuligheder.]
MD,
        ],
        'disputes' => [
            'title' => 'Vilkår ved tvister',
            'provisions' => <<<'MD'
## Aftaleparter
Tvister om varens stand, betaling, levering eller opfyldelse af købsaftalen angår som udgangspunkt køber og sælger som selvstændige aftaleparter. Den tekniske platform er ikke automatisk sælger, forsendelsesudbyder, forsikringsgiver eller voldgiftsret. Platformen opfylder sine egne lovpligter og de forpligtelser, den udtrykkeligt har påtaget sig.

## Bistand
Platformen kan stille eksisterende godkendte kommunikationskanaler og en intern anmeldelsesprocedure til rådighed. I den forbindelse gives der kun adgang til nødvendige oplysninger. Brugere må ikke fremlægge falsk dokumentation, bruge andres konti eller fremsætte uberettigede indsigelser mod betalinger. En igangværende intern gennemgang bekræfter ikke automatisk en parts krav.

## Dokumentation og retsmidler
Hændelser vedrørende bud, betalinger og sager må ikke manipuleres efterfølgende i det skjulte. Nødvendig dokumentation sikres efter et dokumenteret retsgrundlag og dokumenterede opbevaringsregler. En manuel betalingsbekræftelse betyder, at operatøren har foretaget sin kontrol. Den udgør ikke en uafhængig retslig eller kryptografisk bekræftelse. Lovbestemte krav, retssager og udenretslig tvistbilæggelse, der faktisk tilbydes, berøres ikke. Der gives intet tilsagn om deponeringstjeneste eller en omfattende garanti for købesummen.

[REQUIRED: gennemgå den præcise frivillige bistand, om den er gratis eller hvad den koster, hvem der har kompetencen, og lovpligtige oplysninger om tvistbilæggelse.]
MD,
        ],
        'payments' => [
            'title' => 'Betalingsbetingelser for platformstjenester',
            'provisions' => <<<'MD'
## Adskillelse fra varekøbet
Disse betingelser vedrører betalinger for operatørens egne tjenester. De gælder ikke for en generel opkrævning af købesummer mellem brugere. Betalingsmulighederne for den konkrete bestilling vises før aftaleindgåelsen. En synlig, deaktiveret valgmulighed betyder ikke i sig selv, at bankoverførsel, Bitcoin eller deponering kan bruges.

## Manuel og automatisk kontrol
En manuel betalingsanmodning via PayPal indeholder et fast beløb i EUR, en korrekt beskrivelse af tjenesten, en modtager og en entydig reference. Operatøren kontrollerer, om betalingen faktisk er modtaget og gennemført, og knytter et entydigt transaktions-id til den. En brugermeddelelse, et skærmbillede eller en browserparameter er ikke dokumentation for betaling. Den manuelle procedure erstatter ikke PayPals kontokrav og berettiger ikke til betaling med Friends & Family for erhvervsmæssige tjenester. En automatisk integration kræver den faktisk godkendte konfiguration hos betalingsudbyderen og verificerede serverhændelser.

## Afvigelser og opfyldelse
Ved afvigelser i beløb, reference eller modtager eller ved uklare betalingsforløb må bestillingen ikke godkendes som fuldt betalt. En transaktion må ikke bogføres flere gange. Tidspunktet for aktivering af tjenesten fremgår af den bekræftede bestilling. En udløbet pladsreservation må ikke efterfølgende overbookes. Fejl, annulleringer og tilbagebetalinger skal dokumenteres med korrigerende hændelser. Tidligere fakturaer må ikke ændres i det skjulte.

[REQUIRED: gennemgå modtagerkontoens egnethed, betalingsfrister, forsinket betaling, betalingsudbyderens faktiske betingelser og aftalebekræftelsen.]
MD,
        ],
        'refunds' => [
            'title' => 'Betingelser for annullering og tilbagebetaling',
            'provisions' => <<<'MD'
## Grundlag for vurdering
Anmodninger om tilbagebetaling kan navnlig vedrøre fortrydelse, bestilte tjenester, der ikke er leveret, dobbeltbetalinger, dokumenterbart berettigede aftalekorrektioner eller tekniske driftsafbrydelser. Beskrivelsen må ikke begrænse lovbestemte krav. Kravet vurderes på en måde, der kan efterprøves, på grundlag af bestillingen og omfanget af det, der faktisk er leveret.

## Dokumentation og tilknytning til transaktioner
Anmodning, reference, begrundelse, afgørelse, beløb og det faktisk anvendte betalingsmiddel skal dokumenteres. En registreret anmodning om tilbagebetaling betyder ikke, at tilbagebetalingen er gennemført. En automatisk tilbagebetaling må først anses for gennemført efter verificeret bekræftelse fra betalingsudbyderen. Ved manuel tilbagebetaling skal tilknytningen til den faktisk gennemførte transaktion kunne efterprøves. Udstedte dokumenter overskrives ikke i det skjulte. Der udstedes annullerings- eller korrektionsbilag, hvor det er nødvendigt.

## Tilgodehavender og delvist leverede tjenester
For Bump-pakker og tjenester med en fast løbetid skal den anvendte og den ubrugte del vurderes hver for sig. Et enkelt Bump, der leveres straks, berettiger ikke til generelt at udelukke alle rettigheder vedrørende resten af pakken. Hvis en premiumplacering ikke længere er tilgængelig efter en forsinket betaling, skal en reel løsning vurderes. En plads, der kun fremstår som aktiv, udgør ikke opfyldelse. Frister for tilbagebetaling og tilladte betalingsmidler følger lovbestemte og gyldigt aftalte vilkår.

[REQUIRED: foretag en faglig gennemgang af de faktiske procedurer for behandling, lovbestemte frister, korrektionsbilag og betalingsudbyderens procedurer.]
MD,
        ],
        'allowed-products' => [
            'title' => 'Regler for tilladte og forbudte produkter',
            'provisions' => <<<'MD'
## Aktiv markedsplads
Den aktive markedsplads er indrettet til lovlige, almindelige og uregulerede varer, f.eks. friluftsudstyr, campingudstyr, beklædning, sportsudstyr, optik, husholdningsartikler, værktøj og medieprodukter. En kategori fritager hverken for at gennemgå produktet eller for at opfylde kravene i leverings- eller modtagerlandet. Omdøbning af tidligere specialiserede tilbud gør ikke de udbudte produkter til almindelige varer.

## Spærrede og uafklarede produkter
Ulovlige produkter, forfalskninger, stjålne varer, svigagtige tilbud og ikke-godkendte regulerede produktgrupper, herunder våben, ammunition og andre produkter af retlig betydning, må ikke handles gennem almindelige kategorier. En frit indtastet betegnelse eller en verifikationsstatus udgør ikke en godkendelse. Ved tvivl om klassificeringen kræves en faglig gennemgang. De berørte transaktioner forbliver spærrede indtil da.

## Forpligtelser og håndhævelse
Sælgere skal have ret til at disponere over varen, give korrekte og nødvendige oplysninger og overholde de krav, der gælder for produktet, forsendelsen og destinationslandet. Operatøren skal opfylde sine egne lovpligter og moderationsforpligtelser. Tekstfiltre er kun et hjælpeværktøj og udgør ikke en fuldstændig retlig klassificering. Forsøg på omgåelse kan føre til forholdsmæssige begrænsninger efter moderationsproceduren med angivelse af begrundelsen.

[REQUIRED: fastlæg efter faglig gennemgang den bindende liste over produktgrupper, de lande, hvor de kan handles, ansvaret for gennemgangen og lovbestemte begrænsninger for hver produkttype.]
MD,
        ],
        'account-suspension' => [
            'title' => 'Regler for begrænsning og spærring af konti',
            'provisions' => <<<'MD'
## Grunde og proportionalitet
Konti kan midlertidigt begrænses ved begrundede retlige problemer, svig eller sikkerhedsproblemer i det omfang, det er lovligt og nødvendigt for at afværge fare. Ved foranstaltningen skal der tages højde for forholdets art, alvor, varighed, gentagelse og virkninger. Ubegrundede eller rent vilkårlige spærringer er ikke en del af ordningen.

## Virkning og igangværende forhold
Deaktivering af en konto ophæver aktive sessioner og forhindrer nye handlinger, der ikke er tilladt. Eksisterende bud, aftaler, betalinger og klager består. Igangværende auktioner med bud må ikke manipuleres ved i det skjulte at slette en deltager. Systemets status for igangværende kontrol og særskilt sagsbehandling bevarer den nødvendige dokumentation.

## Begrundelse og efterprøvelse
I det omfang loven kræver det, meddeles begrundelse, omfang, varighed og de tilgængelige muligheder for efterprøvelse. Lovbestemte undtagelser, f.eks. for at beskytte undersøgelser eller tredjeparter, skal vurderes konkret. Brugere skal kunne fremsætte en berettiget klage. Eksterne rettigheder består. Frivillig sletning af en konto skal holdes adskilt fra en sanktion og kræver en særskilt beslutning om opbevaring efter databeskyttelsesreglerne.

[REQUIRED: gennemgå frister, fornyet gennemgang, kommunikation efter deaktivering, anvendelsen af DSA og P2B-reglerne samt aftalevilkår for opsigelse.]
MD,
        ],
    ],
];
