# Produktbilder

Die Bildannahme ist ausschließlich für allgemeine Produktfotos vorgesehen,
nicht für Identitätsdokumente. `GENERAL_IMAGE_DIR` muss ein privates absolutes
Verzeichnis außerhalb des gesamten Repositorys bezeichnen. Der Server benötigt
GD mit JPEG-, PNG- und WebP-Unterstützung und ausreichend Arbeitsspeicher.

- Bis zu fünf Bilder pro Inserat, auch nach nachträglichem Anhängen.
- Je Datei höchstens 5 MiB, 20 Megapixel und 8.000 Pixel pro Seite.
- Nur tatsächliche erfolgreiche HTTP-Uploads; Inhaltstyp mit Fileinfo und
  Bildheader prüfen und anschließend tatsächlich decodieren.
- JPEG/PNG/WebP werden als neues WebP mit höchstens 1.600 Pixeln Seitenlänge
  gespeichert; zusätzliche Vorschau mit höchstens 400 Pixeln. Unnötige Metadaten
  werden durch die Neucodierung entfernt.
- Zufällige 48-stellige Hexschlüssel, keine Nutzerdateinamen im Dateisystem,
  private Verzeichnis-/Dateirechte und exklusive Dateianlage.
- Bei Validierungs- oder Datenbankfehlern werden nur die im aktuellen Versuch
  erzeugten Dateien entfernt. Bestehende Bilder bleiben erhalten.

Die kontrollierte Route `/marketplace/image.php?id=…&position=0&size=thumb`
beziehungsweise `size=large` prüft das sichtbare allgemeine Angebot einschließlich
Kategorievorfahren. Entwürfe, archivierte und gesperrte Angebote liefern keine
Bilder. HEAD liefert keinen Bildkörper. Direkte alte Uploadpfade sind nicht
freizugeben. Der Webroot muss ausschließlich `public/` bleiben.

PHP sollte `upload_max_filesize=5M`, `post_max_size=28M` und eine Uploadanzahl
oberhalb fünf verwenden, damit sechs eingereichte Dateien sichtbar abgewiesen
und nicht still durch PHP abgeschnitten werden. Die Anwendung kann ein
überschrittenes `post_max_size` nicht als normalen leeren Speichervorgang werten;
der zentrale Requestguard muss dies vor Formularverarbeitung abweisen.

Vor Produktionsbetrieb benötigen Bildentfernung/Retention, Dateibackups und
Restore eine festgelegte Betriebsstrategie. Diese Dokumentation ersetzt keinen
tatsächlich ausgeführten Upload- und Manipulationstest.
