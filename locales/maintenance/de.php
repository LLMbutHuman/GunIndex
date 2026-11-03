<?php
declare(strict_types=1);
return [
    'maintenance.title'=>'Wartungsarbeiten',
    'maintenance.heading'=>'Wir warten unseren Marktplatz.',
    'maintenance.message'=>'Der Marktplatz ist vorübergehend nicht verfügbar. Bitte versuche es später erneut.',
    'maintenance.retry'=>'Bitte warte mindestens fünf Minuten vor deinem nächsten Versuch. Wann die Wartung endet, steht noch nicht fest.',
    'maintenance.login'=>'Anmeldung für die Administration',
    'maintenance.administration'=>'Zu den Administrationseinstellungen',
    'maintenance.enable'=>'Wartungsmodus aktivieren',
    'maintenance.help'=>'Sperrt öffentliche Zugriffe und pausiert Worker. Laufende Anfragen werden vor der Aktivierung abgeschlossen. Noch nicht abgelaufene Auktionsfristen werden beim Ausschalten um die Wartungszeit verlängert. MFA-Administration und Zahlungsabgleich bleiben erreichbar. Kein vollständiger Backup-Schreibstopp.',
];
