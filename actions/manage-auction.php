<?php
require_once('../config/db.php');
require_once('../config/functions.php');

// UTF-8 Header für saubere Fehlermeldungen
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * 1. ZUGRIFFSPRÜFUNG
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: ../marketplace/index.php");
    exit;
}

$listing_id = (int)$_POST['listing_id'];
$action     = $_POST['action']; // sell_to_highest, confirm_buyout, cancel_auction
$user_id    = $_SESSION['user_id'];

try {
    /**
     * 2. DATENBANK-TRANSAKTION STARTEN
     * Stellt sicher, dass Statusänderung und Nachricht gleichzeitig passieren.
     */
    $pdo->beginTransaction();

    // Inserat laden & Sicherheit prüfen (Gehört das Item dem User?)
    $stmt = $pdo->prepare("SELECT seller_id, title, status FROM listings WHERE id = ? FOR UPDATE");
    $stmt->execute([$listing_id]);
    $item = $stmt->fetch();

    if (!$item || $item['seller_id'] != $user_id) {
        throw new Exception("Nicht autorisiert oder Objekt nicht gefunden.");
    }

    if ($item['status'] !== 'active') {
        throw new Exception("Dieses Inserat ist bereits abgeschlossen oder inaktiv.");
    }

    $success_msg = "";

    /**
     * 3. LOGIK FÜR DIE VERSCHIEDENEN AKTIONEN
     */
    if ($action === 'sell_to_highest') {
        // Fall A: Auktion vorzeitig beenden (Verkauf an aktuellen Höchstbieter)
        $stmtHigh = $pdo->prepare("SELECT user_id FROM bids WHERE listing_id = ? ORDER BY bid_amount DESC LIMIT 1");
        $stmtHigh->execute([$listing_id]);
        $winner = $stmtHigh->fetch();

        if (!$winner) {
            throw new Exception("Es liegen keine Gebote vor, die akzeptiert werden koennten.");
        }

        $update = $pdo->prepare("UPDATE listings SET status = 'ended', highest_bidder_id = ? WHERE id = ?");
        $update->execute([$winner['user_id'], $listing_id]);
        
        $success_msg = "Auktion beendet. Die Einheit wurde an den Hoechstbietenden verkauft.";

    } elseif ($action === 'confirm_buyout') {
        // Fall B: Sofort-Kauf Anfrage aus dem Chat bestätigen
        if (!isset($_POST['buyer_id'])) throw new Exception("Kein Kaeufer identifiziert.");
        $buyer_id = (int)$_POST['buyer_id'];

        $update = $pdo->prepare("UPDATE listings SET status = 'ended', highest_bidder_id = ? WHERE id = ?");
        $update->execute([$buyer_id, $listing_id]);
        
        // System-Nachricht in den Chat posten
        $notif_text = "PROTOKOLL: Der Verkaeufer hat den Sofort-Kauf bestaetigt. Die Transaktion ist nun bindend.";
        $stmtMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, listing_id) VALUES (?, ?, ?, ?)");
        $stmtMsg->execute([$user_id, $buyer_id, $notif_text, $listing_id]);

        $success_msg = "Sofort-Kauf bestaetigt. Die Einheit wurde erfolgreich übertragen.";

    } elseif ($action === 'cancel_auction') {
        // Fall C: Auktion abbrechen (Kein Verkauf)
        $update = $pdo->prepare("UPDATE listings SET status = 'canceled' WHERE id = ?");
        $update->execute([$listing_id]);
        
        $success_msg = "Die Auktion wurde erfolgreich abgebrochen und vom Markt entfernt.";
    } else {
        throw new Exception("Ungültige Operation angefordert.");
    }

    // Alles okay? Speichern!
    $pdo->commit();
    header("Location: ../marketplace/view-auction.php?id=$listing_id&success=" . urlencode($success_msg));
    exit;

} catch (Exception $e) {
    // Bei Fehlern alles rückgängig machen
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../marketplace/view-auction.php?id=$listing_id&error=" . urlencode($e->getMessage()));
    exit;
}