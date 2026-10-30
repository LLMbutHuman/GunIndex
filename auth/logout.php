<?php
// Session starten, um auf sie zugreifen zu können
session_start();

// Alle Session-Variablen löschen
$_SESSION = array();

// Falls ein Session-Cookie existiert, dieses im Browser ungültig machen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Die Session auf dem Server komplett zerstören
session_destroy();

// Nutzer sofort zur Startseite (Marktplatz) weiterleiten
header("Location: ../marketplace/index.php");
exit;
?>