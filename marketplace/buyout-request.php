<?php
require_once('../config/db.php');
require_once('../config/functions.php');

// Erzwingt UTF-8 für die Ausgabe (Fix für die Fragezeichen)
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$listing_id = (int)$_GET['id'];
$buyer_id   = $_SESSION['user_id'];

// 1. Inserat laden
$stmt = $pdo->prepare("SELECT title, seller_id, buyout_price, price, status FROM listings WHERE id = ?");
$stmt->execute([$listing_id]);
$item = $stmt->fetch();

// Sicherheit: Existiert das Item und ist es noch aktiv?
if (!$item || $item['status'] !== 'active') {
    header("Location: index.php?error=not_available");
    exit;
}

// Sicherheits-Check: Nicht bei sich selbst anfragen
if ($item['seller_id'] == $buyer_id) {
    header("Location: view-auction.php?id=$listing_id&error=self_buy");
    exit;
}

// Preis bestimmen für die Nachricht
$final_price = ($item['buyout_price'] > 0) ? $item['buyout_price'] : $item['price'];

try {
    // Wir starten KEINE Transaktion, die das Listing beendet.
    // Wir senden nur die Nachricht und starten den Chat.

    // 2. System-Nachricht generieren (Ohne Umlaute zur Sicherheit oder mit korrektem DB-Fix)
    $system_msg = "ANFRAGE: Ein Nutzer hat Interesse an einem Sofort-Kauf fuer '" . $item['title'] . "' zum Preis von " . number_format($final_price, 2, ',', '.') . " EUR. Die Auktion laeuft aktuell noch weiter. Bitte besprechen Sie hier die Details.";
    
    // Prüfen, ob bereits eine Nachricht zu diesem Listing existiert (optional, um Spam zu vermeiden)
    $stmtMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, listing_id) VALUES (?, ?, ?, ?)");
    $stmtMsg->execute([$buyer_id, $item['seller_id'], $system_msg, $listing_id]);

    // 3. Weiterleitung zum Chat mit Erfolgsmeldung
    header("Location: ../user/chat.php?listing_id=$listing_id&user_id=" . $item['seller_id'] . "&note=request_sent");
    exit;

} catch (Exception $e) {
    die("Kritischer Fehler bei der Kauf-Anfrage: " . $e->getMessage());
}