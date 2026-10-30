<?php
require_once('../config/db.php');
require_once('../config/functions.php');

session_start();

$error = "";
$success = false;

// Hilfsfunktion zur Bildverarbeitung
function imagecreatefromany($filepath) {
    $type = @exif_imagetype($filepath);
    switch ($type) {
        case IMAGETYPE_GIF: return imagecreatefromgif($filepath);
        case IMAGETYPE_JPEG: return imagecreatefromjpeg($filepath);
        case IMAGETYPE_PNG: return imagecreatefrompng($filepath);
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $zip = trim($_POST['zip']); // Erfassen der PLZ

    // Validierung der Pflichtfelder
    if (empty($username) || empty($email) || empty($password) || empty($zip)) {
        $error = "Bitte alle Basisdaten ausfüllen.";
    } else {
        // Prüfen ob E-Mail oder Username bereits existiert
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
        
        if ($check->rowCount() > 0) {
            $error = "Nutzername oder E-Mail bereits vergeben.";
        } else {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            
            // FIX: Wir schreiben die PLZ in 'zip' UND 'city', um den DB-Error (Field city doesn't have a default value) zu umgehen.
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, zip, city, verification_status, is_verified) VALUES (?, ?, ?, ?, ?, 'none', 0)");
            
            if ($stmt->execute([$username, $email, $passHash, $zip, $zip])) {
                $userId = $pdo->lastInsertId();
                
                // OPTIONALER BILD-UPLOAD (Verifizierung)
                if (!empty($_FILES['id_front']['tmp_name'])) {
                    $uploadDir = "../assets/uploads/verification/";
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                    $files = [
                        'id_front' => $userId . "_front.jpg",
                        'id_back'  => $userId . "_back.jpg",
                        'id_selfie'=> $userId . "_selfie.jpg"
                    ];

                    $uploadCount = 0;
                    foreach ($files as $formName => $fileName) {
                        if (!empty($_FILES[$formName]['tmp_name'])) {
                            $img = imagecreatefromany($_FILES[$formName]['tmp_name']);
                            if ($img) {
                                // Durch Neuerstellung werden alle GPS-Metadaten gelöscht (Datenschutz)
                                imagejpeg($img, $uploadDir . $fileName, 85);
                                imagedestroy($img);
                                $uploadCount++;
                            }
                        }
                    }

                    // Wenn Bilder hochgeladen wurden, Status auf 'pending' setzen
                    if ($uploadCount > 0) {
                        $pdo->prepare("UPDATE users SET verification_status = 'pending' WHERE id = ?")->execute([$userId]);
                    }
                }
                $success = true;
            } else {
                $error = "Ein Systemfehler ist aufgetreten.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TheGunSite | Join the Community</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #030303; color: #e5e7eb; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .glass-panel { background: rgba(10, 10, 10, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .input-field { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); color: white; width: 100%; padding: 16px; border-radius: 14px; font-size: 15px; outline: none; transition: 0.3s; }
        .input-field:focus { border-color: #8A2BE2; background: rgba(255, 255, 255, 0.06); box-shadow: 0 0 20px rgba(138, 43, 226, 0.1); }
        .file-label { display: block; background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1); padding: 14px; border-radius: 14px; cursor: pointer; text-align: center; transition: 0.3s; }
        .file-label:hover { border-color: #8A2BE2; background: rgba(138, 43, 226, 0.05); }
    </style>
</head>
<body class="antialiased">

    <div class="w-full max-w-2xl">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-black italic tracking-tighter uppercase">
                <span class="text-white">GUN</span><span class="text-[#8A2BE2]">DIRECT</span>.de
            </h1>
            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-[0.4em] mt-3">Identity Verification System</p>
        </div>

        <div class="glass-panel p-8 md:p-14 rounded-[3rem] shadow-2xl">
            <?php if($success): ?>
                <div class="text-center py-10 animate-fade-in">
                    <div class="w-24 h-24 bg-[#8A2BE2]/20 text-[#8A2BE2] rounded-full flex items-center justify-center text-5xl mx-auto mb-8 shadow-inner">✓</div>
                    <h2 class="text-3xl font-black uppercase text-white tracking-tight">Einsatzbereit!</h2>
                    <p class="text-gray-400 text-sm mt-5 leading-relaxed max-w-xs mx-auto">Dein Account wurde erstellt. Du kannst dich jetzt einloggen. Dein Verifizierungsstatus wird manuell durch unsere Admins geprüft.</p>
                    <a href="login.php" class="inline-block mt-12 bg-white text-black px-12 py-5 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-[#8A2BE2] hover:text-white transition duration-500">Zum Login ✕</a>
                </div>
            <?php else: ?>
                
                <?php if($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-500 text-[11px] font-black uppercase p-5 rounded-2xl mb-10 text-center tracking-widest italic animate-pulse">
                        ⚠️ <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        
                        <div class="space-y-6">
                            <h3 class="text-[11px] font-black text-white uppercase tracking-[0.3em] mb-6 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-[#8A2BE2] rounded-full"></span> Basis-Daten
                            </h3>
                            <input type="text" name="username" placeholder="Nutzername" required class="input-field">
                            <input type="email" name="email" placeholder="E-Mail Adresse" required class="input-field">
                            <input type="password" name="password" placeholder="Passwort" required class="input-field">
                            <input type="text" name="zip" placeholder="Postleitzahl (z.B. 10117)" required class="input-field">
                        </div>

                        <div class="space-y-6">
                            <h3 class="text-[11px] font-black text-white uppercase tracking-[0.3em] mb-6 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-[#8A2BE2] rounded-full"></span> Verifizierung (Optional)
                            </h3>
                            <p class="text-[10px] text-gray-500 uppercase font-bold leading-tight italic">Dokumente für den <span class="text-[#8A2BE2]">Lila Haken</span> (Vertrauens-Badge).</p>
                            
                            <label class="file-label">
                                <span class="text-[10px] text-gray-400 font-bold uppercase block">Ausweis Vorderseite</span>
                                <input type="file" name="id_front" accept="image/*" class="hidden">
                            </label>
                            
                            <label class="file-label">
                                <span class="text-[10px] text-gray-400 font-bold uppercase block">Ausweis Rückseite</span>
                                <input type="file" name="id_back" accept="image/*" class="hidden">
                            </label>
                            
                            <label class="file-label border-solid border-[#8A2BE2]/40 bg-[#8A2BE2]/5">
                                <span class="text-[10px] text-[#8A2BE2] font-black uppercase block">Selfie mit Ausweis</span>
                                <input type="file" name="id_selfie" accept="image/*" class="hidden">
                            </label>
                        </div>
                    </div>

                    <div class="mt-14 pt-8 border-t border-white/5">
                        <button type="submit" class="w-full bg-[#8A2BE2] py-6 rounded-2xl text-white text-xs font-black uppercase tracking-[0.4em] shadow-2xl shadow-[#8A2BE2]/30 hover:scale-[1.02] active:scale-95 transition-all duration-300 italic">
                            Account jetzt ersetellen
                        </button>
                        <p class="text-center text-[10px] text-gray-600 mt-8 uppercase font-bold tracking-widest">
                            Du hast bereits Zugriff? <a href="login.php" class="text-white hover:text-[#8A2BE2] transition-colors underline underline-offset-4">Anmelden</a>
                        </p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>