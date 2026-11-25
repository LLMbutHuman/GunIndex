<?php
declare(strict_types=1);

return [
    'header' => <<<'MD'
**Stato: bozza in attesa di revisione professionale e, ove necessario, legale.**
**Versione del documento: draft-1.0 · Lingua: italiano.**
**Gestore:** [REQUIRED: denominazione legale completa, indirizzo e recapiti].
**Territorio contrattuale:** [REQUIRED: paesi effettivamente autorizzati e informazioni ai consumatori richieste in ciascun paese].

La bozza descrive esclusivamente le funzionalità effettivamente autorizzate. L’efficacia giuridica richiede verifica e la pubblicazione come testo contrattuale operativo richiede un’approvazione professionale documentata. Le informazioni sul gestore, le prassi commerciali e le schermate di ordinazione devono corrispondere.
MD,
    'documents' => [
        'terms' => [
            'title' => 'Condizioni generali',
            'provisions' => <<<'MD'
## 1. Parti del contratto e ambito di applicazione
Queste condizioni riguardano l’accesso alla piattaforma tecnica e i servizi della piattaforma ordinati espressamente. Il gestore fa da intermediario per le offerte degli utenti. Di norma, acquirente e venditore concludono la compravendita tra loro. La piattaforma non assume il ruolo di proprietario o venditore, né si occupa automaticamente di spedizione, consegna o custodia. Gli eventuali ulteriori obblighi del gestore dipendono dalle caratteristiche effettive della funzione e dalle disposizioni inderogabili.

## 2. Account, offerte e conclusione del contratto
I dati dell’account devono essere esatti. Le credenziali sono personali e gli accessi sospetti devono essere segnalati tempestivamente. I venditori possono proporre soltanto beni di uso generale disponibili e leciti. Devono fornire informazioni veritiere su condizioni, prezzo, consegna e status professionale. Le modalità di conclusione dei contratti per aste e inserzioni a prezzo fisso sono disciplinate dalle condizioni specifiche. L’attivazione di una categoria o di una verifica dell’identità non equivale a un’autorizzazione generale alla vendita.

## 3. Servizi, modifiche e cessazione
Prima dell’ordine, la piattaforma indica il contenuto, il prezzo totale e la durata dei servizi a pagamento e ne conserva una versione. È vietato modificare retroattivamente e senza comunicarlo le regole sulla disponibilità, sulla moderazione e sui pagamenti. Per modifiche sostanziali va verificato se occorra informare l’utente o acquisire nuovamente il suo consenso espresso. Il semplice utilizzo continuato non sostituisce una dichiarazione richiesta dalla legge. Gli utenti possono chiedere la disattivazione dell’account e la verifica della cancellazione ai sensi delle norme sulla protezione dei dati.

## 4. Obblighi e responsabilità
La piattaforma adempie ai propri obblighi di legge in materia di verifica, informazione e intervento. Restano ferme le responsabilità per dolo, colpa grave, lesioni alla vita, all’integrità fisica o alla salute e ogni responsabilità inderogabile, in particolare ai sensi della legge tedesca sulla responsabilità per danno da prodotti (Produkthaftungsgesetz) o delle garanzie assunte. In caso di violazione per colpa lieve di un obbligo contrattuale essenziale, la responsabilità può limitarsi al danno prevedibile tipico del contratto nei limiti consentiti dalla legge. Gli utenti sono tenuti a manlevare la piattaforma quando la violazione è a loro imputabile e la pretesa è fondata. Si tiene conto del concorso di colpa e delle eccezioni opponibili.

## 5. Verifica prima della pubblicazione
[REQUIRED: verificare con competenza la legge applicabile, le clausole sul foro consentite per i professionisti, le informazioni ai consumatori, le comunicazioni contrattuali e la descrizione concreta dei servizi.]
MD,
        ],
        'private-use' => [
            'title' => 'Condizioni d’uso per utenti privati',
            'provisions' => <<<'MD'
## Account e uso consentito
Gli utenti privati agiscono per scopi personali. È vietato presentare un’attività professionale come vendita privata. I nomi degli account non possono violare diritti di terzi né dare l’impressione di una verifica ufficiale o mai convalidata. I dati richiesti devono essere aggiornati. I documenti d’identità non necessari non vanno trasmessi nelle inserzioni pubbliche o nei messaggi privati.

## Offerte e contatti
Le offerte devono descrivere chiaramente il bene effettivamente disponibile. Prima di concludere il contratto occorre chiarire prezzo, stato del bene, ritiro, costi di spedizione e difetti riconoscibili. La pubblicazione di immagini richiede il diritto di utilizzarle. La chat interna serve a discutere l’offerta specifica. Sono vietati molestie, pubblicità di massa, phishing, aggiramento delle sospensioni e pubblicazione di contenuti privati di terzi.

## Diritti e cessazione
Le inserzioni private gratuite a prezzo fisso sono disponibili entro i limiti comunicati. La promozione a pagamento richiede un ordine separato. Il servizio non garantisce una vendita o un determinato numero di persone raggiunte. Gli utenti possono gestire offerte salvate e opzioni personali in base alle funzionalità disponibili e chiedere la disattivazione dell’account. Contratti ancora aperti, obblighi di conservazione e prove legittimamente necessarie per una controversia possono impedire la cancellazione integrale immediata di singoli dati. Le decisioni devono essere motivate in modo comprensibile e poter essere verificate.

[REQUIRED: verificare l’età minima per l’account, i paesi ammessi, i limiti effettivi dei servizi gratuiti e la conferma del contratto.]
MD,
        ],
        'dealer' => [
            'title' => 'Condizioni integrative per venditori professionali',
            'provisions' => <<<'MD'
## Identità professionale
I venditori professionali devono dichiarare il loro effettivo status e fornire per intero le informazioni richieste dalla legge relative all’impresa, ai recapiti, ai registri, alla fiscalità e ai consumatori. Il gestore verifica i dati del venditore che è tenuto a controllare secondo la procedura prevista. Un profilo pubblico o un logo caricato non sostituiscono un’autorizzazione dell’autorità o una convalida professionale.

## Offerte e obblighi verso i consumatori
I venditori rispondono dell’esattezza della descrizione dell’articolo, del prezzo complessivo, dei costi di consegna, della disponibilità, dei rimedi di legge per i difetti e delle informazioni obbligatorie sul recesso per i propri contratti di vendita. Il gestore adempie direttamente ai propri obblighi di legge. Per le offerte transfrontaliere devono essere chiarite le condizioni di consegna, il trattamento fiscale, le informazioni ai consumatori e l’ammissibilità del prodotto in ciascun paese destinatario.

## Gestione e pubblicità
Le importazioni in massa devono rispettare i formati prescritti e le regole sui prodotti. Le righe errate devono essere segnalate come tali. Quando è disponibile la funzionalità per i collaboratori, l’accesso richiede autorizzazioni separate. L’uso condiviso di account amministrativi senza controllo è escluso. I servizi pubblicitari seguono un ordine e una verifica separati e sono disciplinati dalle condizioni pubblicitarie. Il servizio non garantisce un volume di vendita o l’abilitazione del venditore ai sensi di legge.

[REQUIRED: verificare il controllo dell’identità, il recapito del venditore, le aree di vendita ammesse, le comunicazioni contrattuali e gli eventuali obblighi P2B.]
MD,
        ],
        'auction' => [
            'title' => 'Condizioni d’asta per beni di uso generale',
            'provisions' => <<<'MD'
## Oggetto dell’offerta e informazioni vincolanti
Le aste sono ammesse per i soli beni di uso generale autorizzati alla transazione. Il venditore stabilisce il prezzo iniziale, la durata, le condizioni di consegna e l’eventuale opzione di acquisto immediato. Sono vietati i rilanci propri, le offerte fittizie concordate e l’aumento artificiale del prezzo. L’identificazione di un account e la categoria dell’inserzione non sostituiscono l’autorizzazione richiesta dalla legge per il prodotto.

## Offerte e chiusura
L’offerta massima privata resta riservata. I rilanci automatici aumentano l’importo visibile secondo la regola pubblicata sugli incrementi. In caso di offerte massime uguali, ha la priorità la prima offerta accettata dal sistema. Un’offerta valida effettuata negli ultimi due minuti posticipa la chiusura ad almeno due minuti dalla sua accettazione. Fa fede l’ora di accettazione sul server. Le offerte tardive, non ammesse o dello stesso venditore vengono respinte.

## Contratto e disservizi
[REQUIRED: stabilire con competenza il momento esatto e le dichiarazioni di conclusione del contratto in base alla concreta interfaccia.] L’aggiudicatario e il venditore ricevono nei canali di contatto previsti le informazioni necessarie per eseguire il contratto. Se i dati sono in conflitto, l’esito resta in verifica. Sono vietati modifiche sostanziali successive a un’offerta, chiusura anticipata priva di giustificazione ed elusione di contratti validi. Disservizi, contestazioni, ritiri legittimi e contratti comprovati come non andati a buon fine devono essere gestiti con una procedura documentata. Le commissioni sorgono solo agli eventi pattuiti. I crediti ancora insoluti non costituiscono un pagamento confermato.
MD,
        ],
        'fees' => [
            'title' => 'Tariffario e commissioni',
            'provisions' => <<<'MD'
## Criteri di determinazione del prezzo
Fa fede il prezzo totale indicato per la singola prenotazione prima dell’ordine con obbligo di pagamento e registrato insieme alla versione delle tariffe. I prezzi aggiornati dall’amministratore non possono aumentare retroattivamente il costo degli ordini storici. Imposta, importo netto e lordo devono figurare separatamente, in conformità al trattamento fiscale effettivamente verificato. [REQUIRED: posizione fiscale del gestore, trattamento IVA ed eventuali indicazioni sulle esenzioni.]

## Prezzi standard previsti
Le inserzioni private a prezzo fisso, le richieste di acquisto e gli avvisi di ricerca sono gratuiti entro i limiti stabiliti. I prezzi previsti sono 0,50 EUR per inserire un’asta, 1,99 EUR per un rilancio di visibilità, 4,99 EUR per tre rilanci, 9,90 EUR per sette giorni in cima alla categoria, 19,90 EUR per sette giorni in cima alla pagina iniziale e 129 EUR per un mese di banner del venditore. Restano applicabili i limiti delle funzionalità attive. Eventuali prezzi diversi impostati dall’amministratore devono essere indicati chiaramente prima dell’ordine.

## Commissione d’asta progressiva
La commissione di vendita è del 3 per cento sui primi 100 EUR, 2 per cento sulla parte oltre 100 EUR e fino a 500 EUR, 1 per cento sulla parte oltre 500 EUR e fino a 2.000 EUR e 0,5 per cento sulla parte eccedente, fino a 29,90 EUR. Si aggiunge la tariffa di pubblicazione. La commissione di vendita presuppone una conclusione valida e positiva secondo le regole pattuite. Annullamenti, contratti comprovati come non conclusi e rimborsi richiedono una rettifica documentata. Preventivo delle commissioni, fattura, segnalazione del cliente e conferma dell’incasso sono registrazioni distinte.
MD,
        ],
        'premium' => [
            'title' => 'Condizioni per rilanci di visibilità e posizionamenti premium',
            'provisions' => <<<'MD'
## Contenuto del servizio
Un rilancio di visibilità modifica l’ordinamento cronologico di una propria inserzione già esistente. La data originaria di creazione, le offerte e la chiusura dell’asta restano inalterate. Lo stesso articolo può essere rilanciato al massimo una volta ogni 72 ore. Il credito viene reso disponibile dopo la verifica del pagamento prevista e viene attivato successivamente su richiesta dell’utente.

## Disponibilità limitata e durata
Le prime posizioni di categoria e della pagina iniziale si possono prenotare entro la disponibilità effettivamente abilitata. Prima dell’ordine vengono indicati il numero di posti disponibili, il paese o la categoria, il prezzo totale, il termine della prenotazione e la durata. La scadenza di una prenotazione non pagata fa venir meno la garanzia del posto. Se arriva un pagamento dopo l’esaurimento della disponibilità, sono vietate la sovraprenotazione e l’attivazione fittizia. Il gestore deve offrire un’alternativa documentata o avviare la verifica per il rimborso.

## Indicazione, risultati e diritti
I posizionamenti a pagamento sono contrassegnati come pubblicitari o sponsorizzati. Una garanzia sul numero di visualizzazioni, contatti o vendite richiede un accordo espresso separato. I diritti di legge dei consumatori restano impregiudicati. L’avvio immediato richiede la dichiarazione espressa prescritta dalla legge. Il solo ordine non comporta una rinuncia generale al diritto di recesso. Per pacchetti utilizzati in parte e disservizi tecnici vanno previste una rettifica adeguata e la verifica degli obblighi di rimborso.

[REQUIRED: verificare il numero effettivo di posti, la segmentazione geografica, le regole di attivazione, la compensazione per i disservizi e il processo di recesso.]
MD,
        ],
        'advertising' => [
            'title' => 'Condizioni pubblicitarie per venditori professionali',
            'provisions' => <<<'MD'
## Campagna e verifica
La pubblicità può essere prenotata solo per servizi leciti e compatibili con la piattaforma o per beni di uso generale. Inserzionista, destinazione del collegamento, creatività e informazioni obbligatorie devono essere corretti. Sono vietati autorizzazioni ingannevoli, prodotti illeciti, software dannoso e reindirizzamenti dissimulati. L’approvazione di una creatività pubblicitaria non coincide con un’autorizzazione amministrativa o legale del modello di attività.

## Pubblicazione e disponibilità
Paese, categoria, durata, contingente di posti e prezzo totale vengono determinati prima della prenotazione. La pubblicazione per un paese non garantisce che tutti i consumatori raggiungibili possano essere serviti senza ulteriori adempimenti. Una campagna può essere presentata come attiva solo dopo verifica, pagamento e attivazione per il periodo previsto. Ogni posto a disponibilità limitata può essere venduto una sola volta.

## Statistiche e cessazione
I conteggi di clic e impressioni sono misurazioni interne effettuate secondo il metodo pubblicato. Non garantiscono un numero di utenti unici né costituiscono una previsione delle vendite. Sono vietati clic propri manipolati, abusi automatizzati e profilazioni illecite. Per motivi giuridici o di sicurezza documentabili, le campagne possono essere sospese in modo proporzionato. Motivazione, possibilità di reclamo e trattamento della durata pagata e inutilizzata devono essere documentati.

[REQUIRED: aggiungere l’identità del fornitore, la durata contrattuale mensile, le regole su rinnovo e disdetta, la definizione delle statistiche e le regole per disservizi e rimborsi.]
MD,
        ],
        'verification' => [
            'title' => 'Condizioni di verifica e sicurezza',
            'provisions' => <<<'MD'
## Accertamenti distinti
L’identità e l’età minima sono due accertamenti distinti. Questi controlli non attestano la proprietà di un bene, il diritto di acquistarlo ai sensi di legge, la solvibilità o la buona condotta. Le funzioni di transazione disabilitate per beni regolamentati restano bloccate.

## Documenti e finalità
Possono essere trattati soltanto i campi dei documenti necessari alla finalità di verifica indicata. Deve essere possibile oscurare i dati non necessari. I documenti vanno trasmessi tramite il canale apposito, fuori dalle immagini pubbliche delle inserzioni e dalla chat ordinaria. Prima dell’invio vanno indicati chiaramente il soggetto responsabile, la finalità, la base giuridica, il periodo effettivo di conservazione e il recapito per eventuali domande. La raccolta resta disabilitata fino all’approvazione di questi presupposti e del sistema di archiviazione.

## Accesso, decisione e cancellazione
Le immagini dei documenti sono controllate, ricodificate e archiviate cifrate al di fuori della directory pubblica del sito. Hanno accesso temporaneo solo i verificatori autorizzati per il singolo caso e autenticati con più fattori. Consultazioni e decisioni sono registrate. Approvazione, richiesta di ulteriori elementi e rifiuto devono poter essere ricostruiti. Alla scadenza del periodo di conservazione necessario, definito in sede di verifica professionale, i file sono rimossi in modo sicuro. L’esito minimo della verifica può essere conservato più a lungo solo su una base giuridica propria.

[REQUIRED: definire con competenza l’unità responsabile, la base giuridica per ogni prova, i tipi di documento, i termini concreti e i casi di nuova verifica.]
MD,
        ],
        'privacy' => [
            'title' => 'Informativa sulla protezione dei dati',
            'provisions' => <<<'MD'
## Titolare del trattamento e recapiti
[REQUIRED: dati completi del titolare, indirizzo, contatto per la protezione dei dati ed eventuale responsabile della protezione dei dati.] L’informativa deve rispecchiare i sistemi effettivamente usati e i paesi autorizzati. La verifica in materia di protezione dei dati resta da completare per questa bozza.

## Finalità e basi giuridiche
I dati dell’account, le offerte e le comunicazioni contrattuali necessarie sono trattati per fornire ed eseguire il contratto di utilizzo nella misura in cui lo consente l’art. 6, paragrafo 1, lettera b), del RGPD. I dati finanziari e probatori obbligatori possono fondarsi sulla lettera c). Misure proporzionate contro gli abusi e registri di sicurezza richiedono un bilanciamento documentato in concreto ai sensi della lettera f). Il mero utilizzo non sostituisce un consenso effettivamente libero ai sensi della lettera a). La necessità di trattare documenti d’identità e informazioni relative a casi particolari va stabilita separatamente.

## Dati, destinatari e sicurezza
A seconda della funzione, si trattano dati di account e contatto, offerte pubbliche, messaggi privati ai quali si è autorizzati ad accedere, offerte d’asta, stato di ordini e pagamenti, preferenze e dati minimi di sicurezza e moderazione. L’accesso ai messaggi privati è limitato. Quando implementata, la cifratura lato server è distinta dalla cifratura end-to-end. I fornitori di servizi di pagamento possono essere titolari autonomi per il proprio servizio. Vanno indicati in concreto l’hosting, il trasporto locale della posta elettronica e gli altri destinatari effettivi. [REQUIRED: destinatari, responsabili del trattamento, luoghi di archiviazione, trasferimenti verso paesi terzi e garanzie.]

## Conservazione e diritti
[REQUIRED: termini motivati per ogni categoria di dati, inclusi account, contratti, messaggi, registri di sicurezza e documenti.] Gli utenti possono chiedere accesso, rettifica, cancellazione nei casi previsti dalla legge, limitazione, portabilità e, ove applicabile, opposizione o revoca del consenso. Una richiesta lascia impregiudicati gli obblighi inderogabili di conservazione. Resta possibile presentare reclamo all’autorità competente per la protezione dei dati. [REQUIRED: autorità di controllo competente e recapito chiaro per l’esercizio dei diritti.]
MD,
        ],
        'imprint' => [
            'title' => 'Informazioni legali e dati del gestore',
            'provisions' => <<<'MD'
## Fornitore del servizio
Denominazione legale completa: [REQUIRED: nome e forma giuridica del fornitore].
Indirizzo valido per le notifiche: [REQUIRED: via, numero civico, codice postale, località e paese].
Rappresentante autorizzato: [REQUIRED: rappresentante effettivo, se richiesto].
Contatti: [REQUIRED: e-mail e un ulteriore canale diretto effettivamente idoneo].

## Registri e informazioni fiscali
Registro e numero di iscrizione: [REQUIRED: dati effettivi, se esistenti e obbligatori].
Partita IVA o identificativo economico richiesto: [REQUIRED: informazioni corrette o motivazione professionale della non applicabilità].
Autorizzazioni, informazioni professionali o di vigilanza obbligatorie: [REQUIRED: sole informazioni necessarie e corrette]. È vietato dichiarare competenze o autorizzazioni di autorità inesistenti.

## Responsabilità dei contenuti e risoluzione delle controversie
[REQUIRED: responsabile dei contenuti, se previsto dalla legge applicabile].
Le informazioni sulla conciliazione dei consumatori devono riflettere la disponibilità a partecipare o l’obbligo legale effettivo. Collegamenti obsoleti ad altre piattaforme e dichiarazioni generiche sulla risoluzione delle controversie vanno verificati prima dell’uso. Il contatto per la protezione dei dati e il canale di segnalazione devono essere raggiungibili. La pubblicazione delle informazioni legali richiede compilazione e verifica professionale.
MD,
        ],
        'cookies' => [
            'title' => 'Informazioni sui cookie e sulla memorizzazione',
            'provisions' => <<<'MD'
## Funzioni tecnicamente necessarie
L’accesso usa un identificatore di sessione per associare gli accessi autorizzati all’account ai relativi moduli. Si utilizzano attributi di sicurezza come HttpOnly, SameSite e, con HTTPS, Secure. Un token CSRF protegge i moduli che modificano lo stato. Queste funzioni non costituiscono un consenso al tracciamento pubblicitario.

## Preferenze di visualizzazione
Il tema cromatico, la pausa delle animazioni ed eventualmente la lingua e il paese del mercato possono essere conservati localmente o nella sessione per ripristinare la visualizzazione scelta. [REQUIRED: verificare nomi effettivi, tipi di memoria, durata e finalità di ciascun elemento]. L’interfaccia deve rispettare le modifiche alle preferenze. Il consenso richiesto dalla normativa sulla protezione dei dati non può essere preselezionato.

## Statistiche e terzi
Questa bozza da sola non attiva reti pubblicitarie o servizi esterni di analisi. Vanno descritti con trasparenza le misurazioni locali effettivamente utilizzate, i contatori antabuso ed eventuali reindirizzamenti a PayPal. Prima di attivare accessi non necessari alla memoria, occorre verificare se la legge applicabile richieda il consenso. Uno strumento introdotto successivamente richiede una nuova valutazione tecnica e legale. L’autorizzazione di sessione già esistente non lo copre automaticamente.

[REQUIRED: inventario di tutti i cookie e valori di Local Storage effettivamente impostati, recapiti e gestione del consenso.]
MD,
        ],
        'withdrawal' => [
            'title' => 'Informativa sul recesso per i servizi della piattaforma interessati',
            'provisions' => <<<'MD'
## Ambito di applicazione
Questa versione deve essere adeguata ai servizi a pagamento effettivamente offerti e ai consumatori tutelati per legge. [REQUIRED: verificare con competenza i paesi autorizzati, lo status del consumatore e le tipologie contrattuali interessate.] Restano fermi i diritti inderogabili dei consumatori stabiliti dalla legge applicabile.

## Diritto di recesso
Ove sussista un diritto di recesso previsto dalla legge, i consumatori possono recedere dal contratto entro quattordici giorni senza indicare il motivo. Per un contratto di servizi, il termine decorre di norma dalla conclusione del contratto, se sono soddisfatti gli obblighi informativi di legge. Per esercitare il diritto è necessaria una dichiarazione inequivoca indirizzata a [REQUIRED: nome, indirizzo e indirizzo e-mail del gestore]. L’uso del modulo tipo è facoltativo. L’invio tempestivo rispetta il termine alle condizioni di legge.

## Conseguenze e avvio anticipato
I pagamenti ricevuti vanno rimborsati nei termini e alle condizioni previsti dalla legge, di norma con lo stesso strumento di pagamento, salvo diverso accordo valido. Se il consumatore ha richiesto esplicitamente l’inizio del servizio durante il periodo di recesso, un compenso proporzionato previsto dalla legge è ipotizzabile soltanto se sono soddisfatti gli obblighi di informazione e di dichiarazione. L’estinzione integrale del diritto di recesso per i servizi è soggetta alle condizioni di legge applicabili. L’attivazione di un rilancio di visibilità o il solo pagamento non comporta una rinuncia generale ai diritti sul credito inutilizzato o sui servizi residui del pacchetto.

[REQUIRED: verificare concretamente il percorso di ordinazione, conferma, avvio del servizio e rimborso. Valutare distintamente la qualificazione di servizio e di contenuto digitale.]
MD,
        ],
        'withdrawal-form' => [
            'title' => 'Modulo tipo di recesso',
            'provisions' => <<<'MD'
## Utilizzo
Il modulo può essere utilizzato per recedere da un contratto del consumatore relativo a un servizio della piattaforma a cui si applica il diritto di recesso. È possibile rendere una dichiarazione inequivoca valida con altre modalità. Resta applicabile l’informativa completa sul recesso. Cancellare le parti non pertinenti.

## Dichiarazione
A [REQUIRED: denominazione legale del gestore, indirizzo completo e indirizzo e-mail]:

Con la presente io/noi comunico/comunichiamo il recesso dal contratto concluso da me/noi per il seguente servizio della piattaforma:

Servizio ed eventuale riferimento dell’ordine: ______________________________
Ordinato il: ______________________________
Nome del/dei consumatore/i: ______________________________
Indirizzo del/dei consumatore/i: ______________________________
Data: ______________________________
Firma del/dei consumatore/i, solo per dichiarazioni cartacee: ______________________________

## Trattamento
Vanno fornite solo le informazioni necessarie a individuare e gestire il caso. Di norma, non occorrono copie di documenti d’identità, credenziali di accesso o dati segreti di pagamento completi. Il gestore deve fornire un canale di ricezione effettivamente accessibile e una procedura di rimborso documentata. La ricezione del modulo è gratuita.

[REQUIRED: allineare il recapito e i contratti interessati con l’informativa sul recesso prima della pubblicazione.]
MD,
        ],
        'moderation' => [
            'title' => 'Regole di moderazione',
            'provisions' => <<<'MD'
## Principi
La moderazione segue regole della piattaforma comprensibili e la legge applicabile. Sono vietati contenuti illeciti, frodi, caricamenti pericolosi, molestie, recensioni manipolate e beni regolamentati non autorizzati. La piattaforma mantiene i propri obblighi di legge e non può trasferirli integralmente agli utenti.

## Procedura
Alle segnalazioni viene attribuito un numero di pratica. Sono esaminate in base a urgenza e competenza. L’accesso è limitato alle informazioni necessarie. Una generica funzione amministrativa non dà accesso integrale alle conversazioni private. Le informazioni volontarie aggiuntive devono essere trattate per la finalità prevista. Le decisioni sono motivate secondo la legge applicabile. Agli utenti interessati sono comunicati i canali di reclamo disponibili.

## Misure e limiti
Le possibili misure comprendono un avviso, un richiamo, la sospensione temporanea o l’occultamento di singoli contenuti e, da parte di chi ne ha competenza, una limitazione dell’account. Occorre considerare in misura proporzionata portata, durata, gravità e ripetizione. In caso di incongruenze l’asta che ha ricevuto offerte viene messa in verifica. Non si creano o rimuovono occultamente offerte o aggiudicatari. Una decisione sulla segnalazione non sostituisce una pronuncia giurisdizionale su pretese civili.

[REQUIRED: verificare con competenza le responsabilità effettive, le eccezioni di legge, il contenuto della motivazione, l’applicabilità del DSA e i termini.]
MD,
        ],
        'complaints' => [
            'title' => 'Procedura di segnalazione e reclamo',
            'provisions' => <<<'MD'
## Segnalazione
Gli utenti possono segnalare offerte specifiche, utenti, messaggi a cui possono legittimamente accedere, recensioni e pubblicità pubblicate attraverso il canale previsto. Occorre individuare sufficientemente l’oggetto della segnalazione e fornire un motivo comprensibile. Le informazioni volontarie aggiuntive devono limitarsi a quanto necessario. Un’istanza legittima non può essere subordinata alla comunicazione di dati di identità o di pagamento superflui.

## Trattamento e decisione
La piattaforma attribuisce un numero di pratica e documenta l’istruttoria, il responsabile, la decisione e la motivazione. La presentazione di una segnalazione da sola non comporta sanzioni automatiche. Ricezione e decisione sono comunicate alle parti legittimate ove richiesto dalla legge. L’identità del segnalante e le prove riservate non sono comunicate indiscriminatamente alla controparte.

## Reclamo
Le parti interessate e legittimate possono chiedere un riesame motivato della decisione comunicata attraverso il canale della pratica. Il reclamo deve essere esaminato effettivamente. La sua registrazione non revoca automaticamente la misura. Il nuovo esame nel merito o la conferma sono documentati. Restano disponibili i rimedi di legge esterni, i procedimenti di conciliazione e i rimedi giurisdizionali. Un limite tecnico interno per richieste identiche ripetute deve rispettare i diritti inderogabili.

[REQUIRED: inserire il recapito per i contenuti illeciti, i termini effettivi, l’applicabilità del DSA e i rimedi extragiudiziali.]
MD,
        ],
        'disputes' => [
            'title' => 'Condizioni per le controversie',
            'provisions' => <<<'MD'
## Parti del contratto
Le controversie su stato del bene, pagamento, consegna o esecuzione della compravendita riguardano in linea generale l’acquirente e il venditore quali parti autonome. La piattaforma tecnica non assume automaticamente il ruolo di venditore, vettore, assicuratore o arbitro. Adempie ai propri obblighi di legge e a quelli che ha assunto espressamente.

## Assistenza
La piattaforma può mettere a disposizione i canali di comunicazione accessibili agli aventi diritto e una procedura interna di segnalazione. Si rendono accessibili solo i dati necessari. Gli utenti non possono presentare prove false, usare account di terzi o avviare contestazioni di pagamento prive di fondamento. L’apertura di una verifica interna non conferma automaticamente la pretesa di una parte.

## Prove e rimedi
È vietato manipolare occultamente a posteriori eventi relativi a offerte d’asta, pagamenti e pratiche. Le prove necessarie sono conservate sulla base giuridica documentata e secondo le regole di conservazione. Una conferma manuale del pagamento indica che il gestore ha effettuato il controllo e non costituisce una conferma giurisdizionale o crittografica indipendente. Restano salvi i diritti di legge, i procedimenti giudiziari e gli strumenti extragiudiziali di risoluzione effettivamente offerti. Il servizio non promette custodia fiduciaria né una garanzia generale del prezzo di acquisto.

[REQUIRED: verificare il servizio volontario di assistenza, la sua eventuale gratuità o il suo prezzo, le responsabilità e le informazioni obbligatorie sulla conciliazione.]
MD,
        ],
        'payments' => [
            'title' => 'Condizioni di pagamento per i servizi della piattaforma',
            'provisions' => <<<'MD'
## Separazione dalla compravendita
Queste condizioni riguardano i corrispettivi spettanti al gestore. L’incasso dei prezzi d’acquisto tra utenti ne resta escluso. Prima della conclusione del contratto, per ogni ordine si mostrano gli strumenti disponibili. Bonifico, Bitcoin e custodia fiduciaria sono utilizzabili solo quando la corrispondente opzione è effettivamente attiva.

## Controllo manuale e automatico
Un ordine di pagamento manuale tramite PayPal comprende l’importo fisso in euro, la descrizione effettiva del servizio, il destinatario e un riferimento univoco. Il gestore verifica l’incasso effettivo e completato e associa un identificativo univoco di transazione. Una comunicazione dell’utente, uno screenshot o un parametro del browser non costituiscono prova dell’incasso. La procedura manuale richiede comunque il rispetto dei requisiti dell’account PayPal. I servizi commerciali non devono essere pagati con l’opzione Amici e familiari. L’integrazione automatica richiede una configurazione del fornitore effettivamente autorizzata ed eventi lato server verificati.

## Difformità ed esecuzione
Operazioni con importo, riferimento o destinatario difforme oppure prive di riscontro chiaro non vengono registrate come saldate. Ogni transazione può essere contabilizzata una sola volta. L’attivazione del servizio segue le condizioni dell’ordine confermato. La scadenza di una prenotazione non consente di vendere due volte lo stesso posto. Errori, annullamenti e rimborsi vanno documentati con registrazioni di rettifica e le fatture storiche vanno conservate.

[REQUIRED: verificare l’idoneità del conto ricevente, le scadenze, il ritardo di pagamento, le condizioni effettive del fornitore e la conferma contrattuale.]
MD,
        ],
        'refunds' => [
            'title' => 'Condizioni di annullamento e rimborso',
            'provisions' => <<<'MD'
## Presupposti della verifica
Le richieste di rimborso possono riguardare in particolare il recesso, servizi prenotati e non eseguiti, doppi pagamenti, rettifiche contrattuali comprovate e giustificate o disservizi. I diritti di legge restano impregiudicati. Il diritto concreto viene verificato in modo tracciabile in base all’ordine e alla parte di servizio effettivamente prestata.

## Documentazione e associazione
Occorre documentare domanda, riferimento, motivo, decisione, importo e strumento di pagamento effettivo. La registrazione della richiesta di rimborso precede l’esecuzione. Un rimborso automatico si considera concluso solo dopo la conferma verificata del fornitore. Una restituzione manuale richiede un collegamento tracciabile con la transazione effettiva. I documenti già emessi si conservano e, ove necessario, si emettono storni o documenti di rettifica.

## Crediti e prestazioni parziali
Per pacchetti di rilanci e servizi a durata, le quote utilizzate e inutilizzate si esaminano separatamente. L’esecuzione immediata di un solo rilancio non comporta la perdita generalizzata dei diritti sul resto del pacchetto. Se un pagamento tardivo fa perdere una posizione premium, bisogna esaminare una soluzione effettiva. Un posto visualizzato come attivo senza prestazione non costituisce adempimento. Termini di restituzione e strumenti ammessi seguono la legge e quanto validamente pattuito.

[REQUIRED: verificare con competenza i canali effettivi di gestione, i termini di legge, i documenti di rettifica e le procedure del fornitore.]
MD,
        ],
        'allowed-products' => [
            'title' => 'Regole sui prodotti ammessi e vietati',
            'provisions' => <<<'MD'
## Mercato attivo
Il mercato attivo è destinato a beni di uso generale leciti e non regolamentati, come articoli per attività all’aperto, campeggio, abbigliamento, sport, ottica, casa, utensili e supporti multimediali. La categoria non sostituisce la verifica del prodotto o gli obblighi del paese di spedizione o destinazione. La sola modifica del nome di vecchie offerte specializzate non trasforma i relativi prodotti in beni di uso generale.

## Prodotti bloccati e da chiarire
Prodotti illeciti, contraffazioni, refurtiva, offerte fraudolente e gruppi non autorizzati di armi regolamentate, munizioni o altri prodotti soggetti a requisiti giuridici non possono essere scambiati nelle categorie generali. Denominazioni a testo libero e stati di verifica non costituiscono autorizzazioni. Una classificazione incerta richiede una verifica professionale. Fino alla verifica, le transazioni interessate restano bloccate.

## Obblighi e applicazione
Gli offerenti devono poter disporre legittimamente del bene, fornire dati corretti e rispettare i requisiti per il prodotto, la spedizione e il paese di destinazione. Il gestore adempie ai propri obblighi di legge e di moderazione. I filtri testuali integrano la verifica senza sostituire una classificazione giuridica completa. I tentativi di elusione possono comportare misure proporzionate secondo la procedura di moderazione motivata.

[REQUIRED: definire con competenza le categorie vincolanti di prodotti, i paesi autorizzati, le responsabilità di verifica e i limiti di legge per ciascun tipo di prodotto.]
MD,
        ],
        'account-suspension' => [
            'title' => 'Regole per la limitazione e la sospensione degli account',
            'provisions' => <<<'MD'
## Motivi e proporzionalità
In presenza di problemi legali, di frode o di sicurezza documentabili, gli account possono essere limitati temporaneamente nei limiti consentiti dalla legge e nella misura necessaria a scongiurare il pericolo. La misura tiene conto di tipo, gravità, durata, ripetizione ed effetti. Ogni sospensione deve avere una motivazione concreta.

## Effetti e operazioni in corso
La disattivazione dell’account revoca le sessioni attive e impedisce nuove operazioni non ammesse. Offerte, contratti, pagamenti e reclami esistenti restano registrati. È vietato manipolare aste in corso con offerte già presentate cancellando in silenzio un partecipante. Gli stati tecnici di verifica e una gestione separata conservano le prove necessarie.

## Motivazione e riesame
Ove previsto dalla legge, vengono comunicati motivo, portata, durata e possibilità di riesame. Le eccezioni di legge, per esempio per proteggere indagini o terzi, vanno valutate nel caso concreto. Gli utenti devono poter presentare un reclamo legittimo e conservano i rimedi esterni. La cancellazione volontaria dell’account richiede una valutazione autonoma della conservazione ai sensi della normativa sulla protezione dei dati. La sanzione segue la sua procedura.

[REQUIRED: verificare termini, riesame, comunicazioni dopo la disattivazione, applicabilità del DSA e delle norme P2B e regole contrattuali di cessazione.]
MD,
        ],
    ],
];
