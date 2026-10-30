<?php
require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * 1. ZUGRIFFS-SCHUTZ
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php?error=auth_required");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../marketplace/index.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$listing_id = (int)$_POST['listing_id'];
$bid_amount = (float)$_POST['bid_amount'];

try {
    /**
     * 2. TRANSAKTION STARTEN
     * Verhindert, dass zwei Nutzer gleichzeitig bieten und dabei die Logik umgehen.
     */
    $pdo->beginTransaction();

    // Inserat-Daten mit Sperre (FOR UPDATE) laden
    $stmt = $pdo->prepare("SELECT seller_id, price, auction_end_datetime, status FROM listings WHERE id = ? FOR UPDATE");
    $stmt->execute([$listing_id]);
    $item = $stmt->fetch();

    if (!$item) {
        throw new Exception("Die Ziel-Einheit wurde nicht in der Datenbank gefunden.");
    }

    // Sicherheits-Check: Ist es eine aktive Auktion?
    if ($item['status'] !== 'active') {
        throw new Exception("Die Auktions-Sequenz für dieses Objekt ist nicht mehr aktiv.");
    }

    // Sicherheits-Check: Zeit abgelaufen?
    if (strtotime($item['auction_end_datetime']) < time()) {
        throw new Exception("Die Übermittlungs-Zeit ist bereits abgelaufen.");
    }

    // Sicherheits-Check: Eigengebot?
    if ($item['seller_id'] == $user_id) {
        throw new Exception("Interne Kollision: Gebote auf eigene Einheiten sind untersagt.");
    }

    /**
     * 3. HÖCHSTGEBOT PRÜFEN
     */
    $stmtHigh = $pdo->prepare("SELECT MAX(bid_amount) FROM bids WHERE listing_id = ?");
    $stmtHigh->execute([$listing_id]);
    $current_high = $stmtHigh->fetchColumn();

    // Falls noch kein Gebot existiert, zählt der Startpreis
    $min_required = ($current_high) ? $current_high : $item['price'];

    if ($bid_amount <= $min_required) {
        throw new Exception("Das Gebot von " . number_format($bid_amount, 2) . "€ ist zu niedrig. Minimum: " . number_format($min_required + 0.01, 2) . "€");
    }

    /**
     * 4. GEBOT SCHREIBEN
     */
    $insert = $pdo->prepare("INSERT INTO bids (listing_id, user_id, bid_amount) VALUES (?, ?, ?)");
    $insert->execute([$listing_id, $user_id, $bid_amount]);

    // OPTIONAL: Listing-Cache aktualisieren (Höchster Bieter spiegeln für Performance)
    $update = $pdo->prepare("UPDATE listings SET highest_bidder_id = ? WHERE id = ?");
    $update->execute([$user_id, $listing_id]);

    /**
     * 5. ANTI-SNIPE LOGIK (Optional aber empfohlen)
     * Wenn in den letzten 2 Minuten geboten wird, verlängert sich die Auktion um 2 Minuten.
     */
    $time_left = strtotime($item['auction_end_datetime']) - time();
    if ($time_left < 120) {
        $new_end = date('Y-m-d H:i:s', strtotime($item['auction_end_datetime'] . ' + 120 seconds'));
        $pdo->prepare("UPDATE listings SET auction_end_datetime = ? WHERE id = ?")->execute([$new_end, $listing_id]);
    }

    // Alles okay, speichern!
    $pdo->commit();

    header("Location: ../marketplace/view-auction.php?id=$listing_id&bid_success=true");
    exit;

} catch (Exception $e) {
    // Fehler? Alles zurückrollen!
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Mit Fehlermeldung zurückleiten
    header("Location: ../marketplace/view-auction.php?id=$listing_id&error=" . urlencode($e->getMessage()));
    exit;
}