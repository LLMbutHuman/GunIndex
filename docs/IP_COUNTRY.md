# Länder- und Sprachvorauswahl

Die Sprache folgt der gespeicherten Auswahl, der unterstützten Browsersprache und danach der lokal ermittelten Länderkennung. Für den Markt hat die gespeicherte Auswahl Vorrang. Danach folgen die lokale IP-Zuordnung und der unterstützte Länderzusatz des Browsers. Ein öffentlicher Sprachpfad behält seine Pfadsprache. Als letzter Rückfall gilt Deutsch und der aktive deutsche oder alphabetisch erste aktive Markt. Automatische Vorschläge verändern keine Sitzungsauswahl, Kontoadresse oder Profilangabe. Die Benutzer können Land und Sprache im Menü ändern.

Die IP-Ländersuche verwendet ausschließlich `REMOTE_ADDR` aus der Webserververbindung und unterstützt IPv4, IPv6 und auf IPv6 abgebildete IPv4-Adressen. Private, ungültige und nicht gefundene Adressen führen zum Rückfall. `Forwarded`, `X-Forwarded-For`, CDN-Länderfelder und andere Client-Header beeinflussen die Auswahl nicht. Hinter einem Reverse-Proxy mit privater Peer-Adresse ist der Rückfall zu erwarten, solange der Betreiber die tatsächliche Peer-Adresse nicht bereits am Webserver aus einem vertrauenswürdigen Netz ermittelt. Die geographische Suche speichert die Adresse nicht und sendet zur Laufzeit keine Anfrage an einen Dritten. Bestehende Server-, Missbrauchs- und Authentifizierungsprotokolle haben eigene Regeln. IP-Zuordnungen sind grobe Vorschläge und eignen sich nicht für Wohnsitz, Identität, Zuständigkeit oder Zugriffsentscheidungen.

## Herkunft und Aktualisierung

Die Dateien sind eine nach Adressfamilie getrennte Auswahl der IP-Bereiche für unterstützte Märkte und Oberflächensprachen aus DB-IP Country Lite vom 1. November 2026. Die Bereiche wurden verlustfrei in Start-/Endadresse im Netzwerkformat und zweistellige Länderkennung umgepackt. Die Datengrundlage steht unter CC BY 4.0. Auf Seiten, deren Vorauswahl aus den Daten stammt, erscheint der Anbieterlink. [DB-IP Country Lite](https://db-ip.com/db/download/ip-to-country-lite) · [CSV-Format](https://db-ip.com/db/format/ip-to-country-lite/csv.html) · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)

| Prüfgegenstand | Prüfsumme |
|---|---|
| Veröffentlichte SHA-1 der entpackten CSV mit 732.796 Zeilen | `e2061015c43ec91c94a12191a6a613279eb94c0c` |
| Geprüfte SHA-256 der gzip-Datei | `e5c8459d638a272ece7f4d7cfa49dce18d91b4f403f6940ba9249bf0db3781fd` |
| Geprüfte SHA-256 der entpackten CSV | `5e968a64a29b973f794c5abc46d0711a0ba87c8a16c411e1cd70312db7a5d4e1` |
| `ipv4.bin`, 200.897 Bereiche | `835d091f789ee85a215ff79e73793e1eb1d657a09084a1164abaff2bd02adb19` |
| `ipv6.bin`, 250.741 Bereiche | `4dbdfe0fa43bef49b4f3459d3294c70d605ab356c5df65602643bfe61788d83c` |

Aktualisierungen werden vor einem Release separat geprüft. Prüfe die veröffentlichte Prüfsumme für die entpackte Datei, dokumentiere deren SHA-256 und führe den Generator mit einem neuen Ausgabeordner aus. Prüfe IPv4, IPv6 und die Rückfälle mit `php tests/ip_country_test.php`. Übernimm die neuen Binärdateien, die Prüfsummen und den Datenstand gemeinsam.

```sh
mkdir -p /tmp/gunindex-ip-country
php ops/build-ip-country.php dbip-country-lite-2026-11.csv.gz /tmp/gunindex-ip-country 5e968a64a29b973f794c5abc46d0711a0ba87c8a16c411e1cd70312db7a5d4e1
```
