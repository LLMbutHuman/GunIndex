<?php
/**
 * DATENBANK-KONFIGURATION & VERBINDUNG (UTF-8mb4 Optimiert)
 */
// In config/db.php ganz oben hinzufügen:
require_once('security.php');
// 1. Zugangsdaten
$host    = 'localhost';
$db      = 'thegunsite_db'; 
$user    = 'root';      
$pass    = 'CrackIt';   
$charset = 'utf8mb4';

// 2. Data Source Name
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

/**
 * 3. PDO OPTIONEN (WICHTIG FÜR DIE STABILITÄT & UMLAUTE)
 */
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, 
    
    // WICHTIG: Setzt nicht nur den Zeichensatz, sondern auch die Sortierreihenfolge (Collation)
    // Dies behebt Fehler bei Sonderzeichen in SQL-Queries und Datenbank-Einträgen.
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];



try {
    // Verbindung herstellen
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Fehler abfangen
    die("<div style='background:#111; color:#8A2BE2; padding:20px; border:1px solid #8A2BE2; font-family:monospace;'>
            <h2 style='color:#fff'>KRITISCHER DATENBANK-FEHLER</h2>
            Verbindung fehlgeschlagen: " . $e->getMessage() . "
         </div>");
}

/**
 * ZUSÄTZLICHE SICHERHEIT:
 */
if (basename($_SERVER['PHP_SELF']) == 'db.php') {
    die('Direkter Zugriff nicht gestattet.');
}