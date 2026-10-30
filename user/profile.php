<?php
require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// 1. Profil-Update verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $zip   = trim($_POST['zip']);
    $city  = trim($_POST['city']);
    $full_name = trim($_POST['full_name']);
    $new_pass  = $_POST['new_password'];

    // Validierung
    if (empty($email) || empty($zip)) {
        $error_msg = "E-Mail und PLZ sind Pflichtfelder.";
    } else {
        // Basis-Daten Update
        $sql = "UPDATE users SET email = ?, zip = ?, city = ?, full_name = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $zip, $city, $full_name, $user_id]);

        // Passwort ändern, falls ausgefüllt
        if (!empty($new_pass)) {
            if (strlen($new_pass) < 6) {
                $error_msg = "Passwort muss mindestens 6 Zeichen haben.";
            } else {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                $success_msg = "Profil und Passwort aktualisiert.";
            }
        } else {
            $success_msg = "Profil erfolgreich aktualisiert.";
        }
    }
}

// 2. Aktuelle Daten laden (Stelle sicher, dass full_name und city in deiner Tabelle existieren!)
// Falls nicht, führe diesen SQL aus: 
// ALTER TABLE users ADD COLUMN full_name VARCHAR(100), ADD COLUMN city VARCHAR(100);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

include('../includes/header.php');
?>

<div class="max-w-[1200px] mx-auto px-4 py-12 md:py-20">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-16 gap-6">
        <div>
            <a href="dashboard.php" class="group text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] transition flex items-center gap-2">
                <span class="group-hover:-translate-x-1 transition-transform">←</span> Dashboard
            </a>
            <h1 class="text-5xl font-black uppercase italic text-white tracking-tighter mt-4">
                Nutzer <span class="text-[#8A2BE2]">Profil</span>
            </h1>
        </div>
        <div class="bg-white/[0.02] border border-white/10 px-6 py-4 rounded-2xl flex items-center gap-4">
            <div class="text-right">
                <p class="text-[9px] text-gray-500 font-black uppercase tracking-widest">Nutzer Status</p>
                <p class="text-white font-black text-xs uppercase italic"><?= $user['is_verified'] ? 'Verifiziert' : 'Standard Nutzer' ?></p>
            </div>
            <div class="w-10 h-10 rounded-full border border-white/20 flex items-center justify-center text-[#8A2BE2]">
                <?php if($user['is_verified']): ?>
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812z"></path></svg>
                <?php else: ?>
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form action="profile.php" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        
        <div class="lg:col-span-4 space-y-6">
            <div class="glass-panel p-10 rounded-[40px] border border-white/10 bg-white/[0.02] text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-[#8A2BE2]/10 to-transparent opacity-50"></div>
                <div class="relative z-10">
                    <div class="w-24 h-24 bg-[#8A2BE2] rounded-3xl flex items-center justify-center text-4xl font-black text-white mx-auto mb-6 rotate-3 shadow-2xl shadow-[#8A2BE2]/40">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                    <h2 class="text-2xl font-black uppercase italic text-white"><?= h($user['username']) ?></h2>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-[0.3em] mt-2">Identität überprüft</p>
                </div>
            </div>

            <div class="glass-panel p-8 rounded-[32px] border border-white/10 bg-white/[0.01]">
                <h3 class="text-[10px] font-black text-[#8A2BE2] uppercase tracking-[0.2em] mb-4 italic">Wichtig!</h3>
                <p class="text-[11px] text-gray-500 leading-relaxed italic">Stellen Sie sicher, dass Ihre PLZ korrekt ist. Diese wird für die Umkreissuche verwendet.</p>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="glass-panel p-10 md:p-12 rounded-[48px] border border-white/10 bg-white/[0.01] backdrop-blur-xl">
                
                <?php if($success_msg): ?>
                    <div class="bg-[#8A2BE2]/10 border border-[#8A2BE2]/40 text-[#8A2BE2] text-[10px] font-black uppercase p-5 rounded-2xl mb-10 text-center tracking-widest animate-pulse">
                        DONE: <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase ml-2 tracking-widest italic">Vollständiger Name (Optional)</label>
                        <input type="text" name="full_name" value="<?= h($user['full_name'] ?? '') ?>" placeholder="Max Mustermann"
                               class="w-full bg-white/[0.03] border border-white/10 p-5 rounded-2xl text-white text-sm outline-none focus:border-[#8A2BE2] transition-all focus:bg-white/[0.05]">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase ml-2 tracking-widest italic">E-Mail Adresse</label>
                        <input type="email" name="email" value="<?= h($user['email']) ?>" required
                               class="w-full bg-white/[0.03] border border-white/10 p-5 rounded-2xl text-white text-sm outline-none focus:border-[#8A2BE2] transition-all focus:bg-white/[0.05]">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase ml-2 tracking-widest italic">Postleitzahl</label>
                        <input type="text" name="zip" value="<?= h($user['zip']) ?>" required
                               class="w-full bg-white/[0.03] border border-white/10 p-5 rounded-2xl text-white text-sm outline-none focus:border-[#8A2BE2] transition-all focus:bg-white/[0.05]">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase ml-2 tracking-widest italic">Stadt</label>
                        <input type="text" name="city" value="<?= h($user['city'] ?? '') ?>" placeholder="Berlin"
                               class="w-full bg-white/[0.03] border border-white/10 p-5 rounded-2xl text-white text-sm outline-none focus:border-[#8A2BE2] transition-all focus:bg-white/[0.05]">
                    </div>

                    <div class="md:col-span-2 space-y-2 pt-6 border-t border-white/5">
                        <label class="text-[10px] font-black text-[#8A2BE2] uppercase ml-2 tracking-widest italic">Neues Passwort (leer lassen für keine Änderung)</label>
                        <input type="password" name="new_password" placeholder="••••••••"
                               class="w-full bg-white/[0.03] border border-white/10 p-5 rounded-2xl text-white text-sm outline-none focus:border-[#8A2BE2] transition-all focus:bg-white/[0.05]">
                    </div>
                </div>

                <div class="mt-12">
                    <button type="submit" class="w-full group bg-[#8A2BE2] py-6 rounded-3xl text-white text-[11px] font-black uppercase tracking-[0.4em] shadow-2xl shadow-[#8A2BE2]/30 hover:bg-[#9d44ff] transition-all italic relative overflow-hidden">
                        <span class="relative z-10">Daten verschlüsselt speichern</span>
                        <div class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-500"></div>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

<?php include('../includes/footer.php'); ?>