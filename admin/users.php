<?php
require_once('../config/db.php');
require_once('../config/functions.php');
session_start();

/**
 * ADMIN SECURITY CHECK
 */
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../marketplace/index.php");
    exit("Zugriff verweigert.");
}

$success_msg = "";
$error_msg = "";

/**
 * 1. NUTZER-DATEN UPDATE (Profil-Manipulation)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = (int)$_POST['id'];
    $email = trim($_POST['email']);
    $zip = trim($_POST['zip']);
    $city = trim($_POST['city']);
    $full_name = trim($_POST['full_name']);
    $new_pass = $_POST['new_password'];

    try {
        $sql = "UPDATE users SET email = ?, zip = ?, city = ?, full_name = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$email, $zip, $city, $full_name, $id])) {
            $success_msg = "Operator #$id erfolgreich modifiziert.";
            
            // Passwort nur ändern, wenn das Feld ausgefüllt wurde
            if (!empty($new_pass)) {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
                $success_msg .= " (Passwort-Sequenz überschrieben)";
            }
        }
    } catch (PDOException $e) {
        $error_msg = "Datenbankfehler: " . $e->getMessage();
    }
}

/**
 * 2. SCHNELL-AKTIONEN (Verify, Admin-Status, Löschen)
 */
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    
    if ($_GET['action'] === 'verify') {
        $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$target_id]);
    } elseif ($_GET['action'] === 'unverify') {
        $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([$target_id]);
    } elseif ($_GET['action'] === 'make_admin') {
        $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?")->execute([$target_id]);
    } elseif ($_GET['action'] === 'remove_admin' && $target_id != $_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET is_admin = 0 WHERE id = ?")->execute([$target_id]);
    } elseif ($_GET['action'] === 'delete' && $target_id != $_SESSION['user_id']) {
        // Löscht den Nutzer permanent
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
    }
    header("Location: users.php?success=Action_Executed");
    exit;
}

/**
 * 3. DATEN FÜR EDIT-MODUS LADEN
 */
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_user = $stmt->fetch();
}

/**
 * 4. GESAMT-LISTE LADEN
 */
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1400px] mx-auto px-4 lg:px-10 py-12">
    
    <div class="mb-10 flex flex-col md:flex-row justify-between items-end gap-6 border-b border-white/10 pb-10">
        <div>
            <a href="index.php" class="text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] mb-4 block italic">? Zurück zum Command Center</a>
            <h1 class="text-5xl font-black uppercase italic text-white tracking-tighter">Operator <span class="text-[#8A2BE2]">Management</span></h1>
        </div>
        <div class="bg-white/5 px-6 py-3 rounded-2xl border border-white/10">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest italic">Registrierte Operator</p>
            <p class="text-xl font-black text-white italic"><?= count($users) ?></p>
        </div>
    </div>

    <?php if($success_msg || isset($_GET['success'])): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-500 text-[10px] font-black uppercase p-5 rounded-2xl mb-10 italic tracking-widest animate-pulse">
            SYSLOG: <?= $success_msg ?: 'Aktion erfolgreich verarbeitet.' ?>
        </div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-500 text-[10px] font-black uppercase p-5 rounded-2xl mb-10 italic tracking-widest">
            ERROR: <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <?php if($edit_user): ?>
    <div class="glass-panel p-10 rounded-[40px] border border-[#8A2BE2]/40 bg-[#8A2BE2]/5 mb-16 animate-in fade-in slide-in-from-top-4 duration-500">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-white font-black uppercase italic tracking-widest text-lg">Manipulation: <span class="text-[#8A2BE2]"><?= h($edit_user['username']) ?></span></h2>
            <a href="users.php" class="text-[10px] text-gray-500 hover:text-white uppercase font-black italic">Abbrechen ?</a>
        </div>
        <form action="users.php" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
            
            <div class="md:col-span-2">
                <label class="text-[9px] text-gray-500 uppercase font-black mb-2 block italic">Vollständiger Name / Alias</label>
                <input type="text" name="full_name" value="<?= h($edit_user['full_name'] ?? '') ?>" class="w-full bg-black/50 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            <div class="md:col-span-2">
                <label class="text-[9px] text-gray-500 uppercase font-black mb-2 block italic">E-Mail Adresse (Komm-Schnittstelle)</label>
                <input type="email" name="email" value="<?= h($edit_user['email']) ?>" class="w-full bg-black/50 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            <div>
                <label class="text-[9px] text-gray-500 uppercase font-black mb-2 block italic">PLZ</label>
                <input type="text" name="zip" value="<?= h($edit_user['zip']) ?>" class="w-full bg-black/50 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            <div>
                <label class="text-[9px] text-gray-500 uppercase font-black mb-2 block italic">Stadt / Sektor</label>
                <input type="text" name="city" value="<?= h($edit_user['city'] ?? '') ?>" class="w-full bg-black/50 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            <div class="md:col-span-2">
                <label class="text-[9px] text-[#8A2BE2] uppercase font-black mb-2 block italic tracking-widest">Neues Passwort (Optional)</label>
                <input type="text" name="new_password" placeholder="Passwort überschreiben..." class="w-full bg-black/50 border border-[#8A2BE2]/30 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            
            <div class="md:col-span-4">
                <button type="submit" name="update_user" class="w-full bg-[#8A2BE2] py-5 rounded-xl text-white text-[10px] font-black uppercase tracking-[0.4em] hover:bg-[#9d44ff] transition italic shadow-lg shadow-[#8A2BE2]/20">
                    Datensatz permanent aktualisieren
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="glass-panel rounded-[40px] border border-white/10 overflow-hidden bg-white/[0.01]">
        <table class="w-full text-left">
            <thead class="bg-white/5 text-[10px] text-gray-500 font-black uppercase tracking-widest italic">
                <tr>
                    <th class="p-6">Operator</th>
                    <th class="p-6">Kontakt / Standort</th>
                    <th class="p-6 text-center">Status-Level</th>
                    <th class="p-6 text-right">Direkt-Zugriff</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                <?php foreach($users as $u): ?>
                <tr class="hover:bg-white/[0.02] transition-all group <?= (isset($_GET['edit_id']) && $_GET['edit_id'] == $u['id']) ? 'bg-[#8A2BE2]/5' : '' ?>">
                    <td class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-xs font-black text-[#8A2BE2] border border-white/10 group-hover:border-[#8A2BE2]/50 transition">
                                <?= strtoupper(substr($u['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <span class="font-black uppercase italic text-sm"><?= h($u['username']) ?></span>
                                <p class="text-[9px] text-gray-600 font-bold tracking-tighter uppercase italic">ID: #<?= $u['id'] ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="p-6">
                        <p class="text-xs text-gray-300 font-medium"><?= h($u['email']) ?></p>
                        <p class="text-[10px] text-gray-500 italic"><?= h($u['zip']) ?> <?= h($u['city'] ?? '') ?></p>
                    </td>
                    <td class="p-6 text-center">
                        <div class="flex justify-center gap-2">
                            <?php if($u['is_admin']): ?>
                                <span class="px-3 py-1 bg-[#8A2BE2]/10 text-[#8A2BE2] border border-[#8A2BE2]/30 rounded-lg text-[8px] font-black uppercase italic">Admin</span>
                            <?php endif; ?>
                            <?php if($u['is_verified']): ?>
                                <span class="px-3 py-1 bg-green-500/10 text-green-500 border border-green-500/30 rounded-lg text-[8px] font-black uppercase italic">Verified</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="p-6 text-right">
                        <div class="flex justify-end gap-2">
                            <a href="messages.php?user_id=<?= $u['id'] ?>" class="p-2.5 bg-blue-600/10 border border-blue-600/20 text-blue-500 rounded-xl hover:bg-blue-600 hover:text-white transition text-[9px] font-black uppercase italic" title="Chat-Log einsehen">
                                Chats
                            </a>

                            <a href="?edit_id=<?= $u['id'] ?>" class="p-2.5 bg-white/5 border border-white/10 rounded-xl hover:border-[#8A2BE2] hover:text-[#8A2BE2] transition text-[9px] font-black uppercase italic">
                                Edit
                            </a>
                            
                            <a href="?action=<?= $u['is_verified'] ? 'unverify' : 'verify' ?>&id=<?= $u['id'] ?>" class="p-2.5 <?= $u['is_verified'] ? 'text-yellow-500 border-yellow-500/20' : 'text-green-500 border-green-500/20' ?> bg-white/5 border rounded-xl hover:bg-white/10 transition text-[9px] font-black uppercase italic">
                                <?= $u['is_verified'] ? 'Revoke' : 'Verify' ?>
                            </a>

                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=<?= $u['is_admin'] ? 'remove_admin' : 'make_admin' ?>&id=<?= $u['id'] ?>" class="p-2.5 bg-white/5 border border-white/10 rounded-xl text-gray-400 hover:text-white transition text-[9px] font-black uppercase italic">
                                    Admin
                                </a>
                                <a href="?action=delete&id=<?= $u['id'] ?>" onclick="return confirm('Account #<?= $u['id'] ?> permanent löschen?')" class="p-2.5 bg-red-600/10 border border-red-600/20 text-red-500 rounded-xl hover:bg-red-600 hover:text-white transition text-[9px] font-black uppercase italic">
                                    X
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('../includes/footer.php'); ?>