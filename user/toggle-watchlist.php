<?php
require_once('../config/db.php');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../marketplace/index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$listing_id = (int)$_GET['id'];

// Prüfen, ob bereits auf der Liste
$check = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND listing_id = ?");
$check->execute([$user_id, $listing_id]);

if ($check->fetch()) {
    // Entfernen
    $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND listing_id = ?")->execute([$user_id, $listing_id]);
} else {
    // Hinzufügen
    $pdo->prepare("INSERT INTO watchlist (user_id, listing_id) VALUES (?, ?)")->execute([$user_id, $listing_id]);
}

// Zurück zur vorherigen Seite
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;