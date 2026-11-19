# Bitcoin-Zahlungen für Plattformleistungen

Der Betreiber stellt eine synchronisierte Bitcoin-Core-Node mit einer
Watch-only-Descriptor-Wallet bereit. Die Wallet braucht einen aktiven
öffentlichen SegWit-Empfangsdeskriptor. GunIndex verwendet die Node zur
Adressvergabe und zum Lesen von Eingängen und Blockbestätigungen. Absicherung,
Backups und Verwahrung der privaten Schlüssel liegen beim Betreiber.
[Bitcoin Core Wallet-Status](https://bitcoincore.org/en/doc/30.0.0/rpc/wallet/getwalletinfo/)

Die private Serverkonfiguration enthält `BITCOIN_RPC_URL`,
`BITCOIN_RPC_USER`, `BITCOIN_RPC_PASSWORD` und `BITCOIN_WALLET`. Für HTTP
akzeptiert der Client Loopback. Eine getrennte Node benötigt einen
geschützten HTTPS-Endpunkt. Für einen neuen Auftrag müssen außerdem ein
geprüfter Kurs in EUR-Cent je BTC (`BITCOIN_RATE_CENTS`) und dessen Ablauf
als UTC-Zeit in `BITCOIN_RATE_UNTIL` gesetzt sein. GunIndex speichert den Kurs
und rundet den Betrag auf ganze Satoshis auf. Der Betrag gilt bis zu
30 Minuten und höchstens bis zum Ablauf des Kurses.

`BITCOIN_ENABLED` und `BITCOIN_LIVE_APPROVED` sind standardmäßig aus.
`BITCOIN_NETWORK=regtest` ist der lokale Testweg. Für Echtzahlungen
gelten `BITCOIN_NETWORK=main`, beide Freigaben, die aktive Rechnungsfunktion
und die Abnahme der tatsächlichen Node und Wallet. Die Bestätigungszahl
`BITCOIN_CONFIRMATIONS` ist konfigurierbar und beträgt standardmäßig sechs.

Ein Worker weist einer Auftragsreferenz eine neue Empfangsadresse zu. Nach
dem exakten Eingang eines einzelnen Transaktionsausgangs und Prüfung der
Mindestbestätigungen auf der aktiven Kette werden Leistung und Rechnung
einmalig freigegeben. Mehrfache, abweichende, verspätete oder stornierte
Eingänge bleiben im geschützten Bitcoin-Abgleich zur Prüfung sichtbar.
Bestätigte Ausgänge werden weiter überwacht. Bei Verlust ihrer Bestätigung
oder einem geänderten Blocknachweis markiert GunIndex die Buchung zur Prüfung
und sperrt verbleibendes Guthaben oder Platzierungen. Der Rechnungsnachweis
bleibt als Buchungshistorie erhalten. Erstattungen und Auszahlungsvorgänge
benötigen eine gesonderte Abnahme. Die App arbeitet mit einer Empfangswallet.

Der isolierte Node-Test verwendet eine per SHA256 geprüfte Bitcoin-Core-
Version und eine temporäre Loopback-Regtest-Chain mit synthetischen Coins.
Die MySQL-Tests prüfen Quote, Eingangszuordnung, Schutz vor Kanalwechsel,
Buchung und Folgen einer Reorganisation. Regtest-Aufträge erzeugen keine
Finanzbuchung, Rechnung oder Plattformleistung.
[Bitcoin Core 30.4](https://bitcoincore.org/en/releases/30.4/)
