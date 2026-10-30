<?php
require_once('../config/db.php');
session_start();

if (isset($_SESSION['user_id']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ? AND seller_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
}

header("Location: dashboard.php");
exit;