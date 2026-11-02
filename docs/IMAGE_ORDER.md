# Reihenfolge vorhandener Inseratsbilder

Beim Bearbeiten eines eigenen Inserats zeigt das Formular private Vorschaubilder
und je Bild eine auswählbare Position. Jede Position darf genau einmal vorkommen;
Position 1 ist das Titelbild. Neue Uploads werden nach den bestehenden Bildern
angefügt. Die Texte stehen in allen acht Oberflächensprachen bereit.

Der Server prüft die vollständige Permutation der eigenen Bild-IDs sowie einen
Hash der zuvor angezeigten Reihenfolge. Veraltete Formulare, fremde, doppelte und
fehlende IDs werden abgewiesen. Die Prüfung erfolgt im vorhandenen Speicherprozess
unter dem Listing-Lock. Besitzerprüfung, Gebots- und Ablaufsperren gelten auch
für Bildänderungen. Temporäre negative Positionen vermeiden Kollisionen mit dem
eindeutigen Positionsindex innerhalb derselben Transaktion. Uploadfehler rollen
auch die Reihenfolge zurück. Vorhandene Bilddateien werden nicht verändert.

`/user/listing-image.php` liefert einem angemeldeten Besitzer nur seine eigenen
Vorschaubilder, einschließlich Entwürfen, mit `private, no-store`. GET/HEAD sind
erlaubt. Öffentliche positionsgebundene Bildantworten verlangen erneute Validierung,
damit nach einer Änderung keine alte Reihenfolge bleibt.

`tests/image_order_test.php` enthält elf datenbankfreie Prüfungen,
`tests/image_order_integration_test.php` elf synthetische MySQL-/HTTP-Fälle.
Aktuelle Ausführungsnachweise stehen in TESTING.md. Drag-and-drop und gezieltes
Löschen/Ersetzen bestehender Bilder sind hier nicht implementiert.
