<?php
/**
 * TheGunSite - Zentrale Hilfsfunktionen
 */

/**
 * h($text)
 * Schützt vor Cross-Site Scripting (XSS).
 * Bereinigt Benutzereingaben für die sichere Anzeige im Browser.
 */
function h($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * formatPrice($price)
 * Formatiert Zahlen in das deutsche Währungsformat (z.B. 1.250,50 €).
 */
function formatPrice($price) {
    return number_format((float)$price, 2, ',', '.') . ' €';
}

/**
 * uploadAndCleanImage($tmpName, $destination)
 * Verarbeitet Bilder, entfernt EXIF/GPS-Metadaten und speichert als optimiertes JPG.
 * Schützt die Privatsphäre der Verkäufer (Standortdaten in Fotos).
 */
function uploadAndCleanImage($tmpName, $destination) {
    $info = getimagesize($tmpName);
    if ($info === false) return false;

    // Bildressource basierend auf Typ erstellen
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $img = imagecreatefromjpeg($tmpName); break;
        case IMAGETYPE_PNG:  
            $img = imagecreatefrompng($tmpName);
            imagepalettetotruecolor($img);
            break;
        case IMAGETYPE_GIF:  $img = imagecreatefromgif($tmpName); break;
        default: return false;
    }

    if ($img) {
        // Neues Bild ohne Metadaten speichern (85% Qualität)
        $success = imagejpeg($img, $destination, 85); 
        imagedestroy($img);
        return $success;
    }
    return false;
}

/**
 * getUserRating($pdo, $user_id)
 * Berechnet den Durchschnitt der Sterne für einen Nutzer.
 * Gibt Sterne-Icons (★) und die Anzahl der Bewertungen zurück.
 */
function getUserRating($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT AVG(stars) as avg_stars, COUNT(id) as count FROM ratings WHERE receiver_id = ?");
    $stmt->execute([$user_id]);
    $data = $stmt->fetch();
    
    // Falls noch keine Bewertungen vorhanden sind
    if ($data['count'] == 0) {
        return '<span class="text-gray-600 italic">Keine Bewertungen</span>';
    }
    
    $avg = round($data['avg_stars']);
    $fullStars = str_repeat('★', $avg);
    $emptyStars = str_repeat('☆', 5 - $avg);
    
    // Gibt die Sterne in lila zurück
    return '<span class="text-[#8A2BE2]">' . $fullStars . '</span><span class="text-gray-700">' . $emptyStars . '</span> <span class="text-gray-500 text-[10px]">(' . $data['count'] . ')</span>';
}

/**
 * getUnreadMessageCount($pdo, $user_id)
 * Zählt die ungelesenen Nachrichten für das Header-Badge.
 */
function getUnreadMessageCount($pdo, $user_id) {
    if (!$user_id) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * isAdmin($pdo, $user_id)
 * Prüft, ob der Nutzer Admin-Rechte hat (für das Admin-Panel).
 */
function isAdmin($pdo, $user_id) {
    if (!$user_id) return false;
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return (bool)$stmt->fetchColumn();
}


function logAdminAction($pdo, $admin_id, $action, $target_id = null, $details = "") {
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action_type, target_id, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$admin_id, $action, $target_id, $details]);
}


// GLOBALER LOGOUT CHECK
if (isset($_SESSION['user_id'])) {
    // 1. Hole den letzten Reset-Zeitpunkt aus der DB
    // (Tipp: In einer echten Produktion sollte man dies cachen, um DB-Last zu sparen)
    $stmtReset = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'global_logout_time' LIMIT 1");
    $globalLogoutTime = strtotime($stmtReset->fetchColumn());

    // 2. Wann hat sich der User eingeloggt? 
    // Wir brauchen ein 'login_at' in der Session. 
    // Falls nicht vorhanden (alter Login), loggen wir ihn zur Sicherheit aus.
    $userLoginTime = isset($_SESSION['login_at']) ? $_SESSION['login_at'] : 0;

    // 3. Wenn der Reset-Knopf NACH dem Login gedrückt wurde -> RAUS
    if ($globalLogoutTime > $userLoginTime) {
        session_unset();
        session_destroy();
        header("Location: ../auth/login.php?msg=session_expired_by_admin");
        exit;
    }
}