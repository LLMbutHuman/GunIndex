<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    die("Zugriff verweigert.");
}

// Wir setzen den globalen Logout-Zeitpunkt auf JETZT
$stmt = $pdo->prepare("UPDATE system_settings SET setting_value = NOW() WHERE setting_key = 'global_logout_time'");
$stmt->execute();

// Optional: Deine eigene Session zerstören, damit du dich auch neu anmelden musst
session_destroy();

header("Location: ../auth/login.php?msg=global_reset_active");
exit;