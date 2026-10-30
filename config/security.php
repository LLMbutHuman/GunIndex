<?php
/**
 * SECTOR-SCAN ULTIMATE AUTO-SHIELD
 * Rundumschutz ohne Code-Anpassung in anderen Dateien.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Sicherere Session-Einstellungen vor dem Start
    ini_set('session.cookie_httponly', 1); // Schutz gegen JS-Diebstahl
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * 1. FIREWALL FÜR HEADER (Browser-Ebene)
 */
header("X-Frame-Options: DENY"); // Schutz gegen Clickjacking
header("X-Content-Type-Options: nosniff"); // Schutz gegen MIME-Sniffing
header("X-XSS-Protection: 1; mode=block"); // Erzwungener Browser XSS-Filter
header("Referrer-Policy: strict-origin-when-cross-origin");

/**
 * 2. AUTO-SANITIZER (Schutz gegen SQLi & XSS)
 * Diese Funktion läuft automatisch über alle eingehenden Daten ($_GET, $_POST).
 */
array_walk_recursive($_GET, function(&$val) {
    $val = trim(htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8'));
});
array_walk_recursive($_POST, function(&$val) {
    // Erlaubt minimalen Whitespace, aber entfernt gefährliche HTML-Tags
    $val = trim(htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8'));
});

/**
 * 3. RATE LIMITING (Schutz gegen Flooding & Brute Force)
 * Blockiert IPs, die mehr als 100 Anfragen pro Minute senden.
 */
$ip_hash = md5($_SERVER['REMOTE_ADDR']);
$time_now = time();

if (!isset($_SESSION['flood_control'][$ip_hash])) {
    $_SESSION['flood_control'][$ip_hash] = ['count' => 1, 'start' => $time_now];
} else {
    $_SESSION['flood_control'][$ip_hash]['count']++;
    
    // Zeitfenster abgelaufen? Reset.
    if ($time_now - $_SESSION['flood_control'][$ip_hash]['start'] > 60) {
        $_SESSION['flood_control'][$ip_hash] = ['count' => 1, 'start' => $time_now];
    }
    
    // Limit überschritten (100 Requests/Min)
    if ($_SESSION['flood_control'][$ip_hash]['count'] > 100) {
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: 60');
        die("<div style='background:#050505; color:#8A2BE2; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif; text-align:center;'>
                <h1 style='font-size:100px; margin:0; filter:drop-shadow(0 0 20px #8A2BE2);'>429</h1>
                <p style='text-transform:uppercase; letter-spacing:0.5em; font-weight:900;'>Sektor-Sperre Aktiv</p>
                <p style='color:#444; font-style:italic;'>Zu viele Signaturen erkannt. Bitte warten Sie 60 Sekunden.</p>
             </div>");
    }
}

/**
 * 4. SESSION-VALIDIERUNG (Schutz gegen Session Hijacking)
 */
$user_agent_hash = md5($_SERVER['HTTP_USER_AGENT'] . "SECTOR_SALT_2024");

if (!isset($_SESSION['security_fingerprint'])) {
    $_SESSION['security_fingerprint'] = $user_agent_hash;
} else {
    if ($_SESSION['security_fingerprint'] !== $user_agent_hash) {
        // Browser hat sich während der Session geändert -> Verdacht auf Hijacking
        session_destroy();
        header("Location: ../auth/login.php?msg=security_reauth");
        exit;
    }
}

/**
 * 5. ANTI-SQL-INJECTION (Zusatzfilter für böse Keywords)
 */
$blacklist = ["union select", "substring(", "load_file(", "outfile", "information_schema"];
$request_uri = strtolower($_SERVER['REQUEST_URI']);

foreach ($blacklist as $badword) {
    if (strpos($request_uri, $badword) !== false) {
        header('HTTP/1.1 403 Forbidden');
        die("System-Intervention: Unzulässige Anfrage-Signatur.");
    }
}