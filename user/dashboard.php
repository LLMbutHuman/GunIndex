<?php
require_once('../config/db.php');
require_once('../config/functions.php');

session_start();

// 1. Sicherheit: Zugriffsschutz
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Logik: Identitäts-Upload verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_front'])) {
    $uploadDir = "../assets/uploads/verification/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $files = [
        'id_front' => $user_id . "_front.jpg",
        'id_back'  => $user_id . "_back.jpg",
        'id_selfie'=> $user_id . "_selfie.jpg"
    ];

    $success_upload = false;
    $allowed_extensions = ['png', 'jpg', 'jpeg']; // Definierte erlaubte Typen

    foreach ($files as $formName => $fileName) {
        if (!empty($_FILES[$formName]['tmp_name'])) {
            
            // STRIKTE DATEITYP-PRÜFUNG
            $fileExtension = strtolower(pathinfo($_FILES[$formName]['name'], PATHINFO_EXTENSION));
            
            if (in_array($fileExtension, $allowed_extensions)) {
                if (uploadAndCleanImage($_FILES[$formName]['tmp_name'], $uploadDir . $fileName)) {
                    $success_upload = true;
                }
            }
        }
    }

    if ($success_upload) {
        $pdo->prepare("UPDATE users SET verification_status = 'pending' WHERE id = ?")->execute([$user_id]);
        header("Location: dashboard.php?msg=verify_sent");
        exit;
    }
}

// 3. Daten-Abfragen
$stmtUser = $pdo->prepare("SELECT username, is_verified, verification_status, is_admin FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

$stmtStats = $pdo->prepare("SELECT SUM(views) as total_views, COUNT(id) as total_items FROM listings WHERE seller_id = ?");
$stmtStats->execute([$user_id]);
$stats = $stmtStats->fetch();

$stmtListings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? ORDER BY created_at DESC");
$stmtListings->execute([$user_id]);
$my_listings = $stmtListings->fetchAll();

$stmtMsgs = $pdo->prepare("SELECT m.*, u.username as sender_name FROM messages m 
                           JOIN users u ON m.sender_id = u.id 
                           WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT 3");
$stmtMsgs->execute([$user_id]);
$recent_msgs = $stmtMsgs->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1600px] mx-auto px-4 lg:px-10 py-12">
    
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-12 gap-8">
        <div>
            <div class="flex items-center gap-4">
                <h1 class="text-4xl font-black uppercase tracking-tighter text-white">Dash <span class="text-[#8A2BE2]">board</span></h1>
                <?php if($user['is_admin']): ?>
                    <a href="../admin/moderation.php" class="bg-red-600/20 text-red-500 border border-red-600/30 px-3 py-1 rounded text-[9px] font-black uppercase tracking-widest animate-pulse">Admin Access</a>
                <?php endif; ?>
            </div>
            <p class="text-gray-500 text-xs font-bold uppercase tracking-[0.3em] mt-2 italic">Nutzer: <?= h($user['username']) ?></p>
        </div>
        <div class="flex flex-wrap gap-4 w-full lg:w-auto">
            <a href="../marketplace/index.php" class="flex-1 lg:flex-none text-center bg-white/5 border border-white/20 px-8 py-4 rounded-2xl text-[10px] font-black uppercase text-white hover:bg-white/10 transition">Marktplatz</a>
            <a href="create-listing.php" class="flex-1 lg:flex-none text-center bg-[#8A2BE2] px-10 py-4 rounded-2xl text-[10px] font-black uppercase text-white shadow-xl shadow-[#8A2BE2]/20 hover:scale-105 transition-transform">Neues Inserat +</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
        <div class="glass-panel p-8 rounded-[32px] border border-white/20 border-l-4 border-l-[#8A2BE2] bg-white/[0.02]">
            <p class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Reichweite</p>
            <p class="text-3xl font-black text-white"><?= number_format($stats['total_views'] ?? 0, 0, ',', '.') ?></p>
            <p class="text-[9px] text-[#8A2BE2] font-bold uppercase mt-2">Views gesamt</p>
        </div>
        <div class="glass-panel p-8 rounded-[32px] border border-white/20 bg-white/[0.02]">
            <p class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Reputation</p>
            <div class="text-xl font-black text-white py-1 italic">
                <?= getUserRating($pdo, $user_id) ?>
            </div>
            <p class="text-[9px] text-gray-600 font-bold uppercase mt-2">Sterne & Rezensionen</p>
        </div>
        <div class="glass-panel p-8 rounded-[32px] border border-white/20 bg-white/[0.02]">
            <p class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Aktive Deals</p>
            <p class="text-3xl font-black text-white"><?= $stats['total_items'] ?? 0 ?></p>
            <p class="text-[9px] text-gray-600 font-bold uppercase mt-2">Offene Inserate</p>
        </div>
        <div class="glass-panel p-8 rounded-[32px] border border-white/20 bg-white/[0.02]">
            <p class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Posteingang</p>
            <p class="text-3xl font-black text-[#8A2BE2] italic"><?= getUnreadMessageCount($pdo, $user_id) ?></p>
            <p class="text-[9px] text-gray-600 font-bold uppercase mt-2">Ungelesene Chats</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        
        <div class="lg:col-span-4 space-y-8">
            
            <div class="glass-panel p-8 rounded-[40px] border border-white/20 bg-white/[0.02]">
                <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-6 italic">Profil</h3>
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-[#8A2BE2]/20 border border-[#8A2BE2]/40 flex items-center justify-center text-white font-black">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="text-white font-black uppercase text-sm italic"><?= h($user['username']) ?></p>
                        <p class="text-[9px] text-gray-500 font-bold uppercase">Status: <?= $user['is_verified'] ? 'Verified' : 'Unverified' ?></p>
                    </div>
                </div>
                <a href="profile.php" class="block w-full text-center bg-white/5 border border-white/10 py-4 rounded-2xl text-[10px] font-black uppercase text-white hover:bg-[#8A2BE2] transition-colors tracking-widest">
                    Profil bearbeiten
                </a>
            </div>
            
            <div class="glass-panel p-8 rounded-[40px] border border-white/20 bg-white/[0.02]">
                <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-4 italic">Verifizierung</h3>
                
                <?php if($user['is_verified']): ?>
                    <div class="bg-green-500/10 border border-green-500/20 p-6 rounded-3xl flex items-center gap-4">
                        <div class="text-green-500 text-2xl">✓</div>
                        <p class="text-[10px] text-green-500 font-black uppercase tracking-widest">Händler-Status aktiv.<br>Haken freigeschaltet.</p>
                    </div>
                <?php elseif($user['verification_status'] == 'pending'): ?>
                    <div class="bg-[#8A2BE2]/10 border border-[#8A2BE2]/20 p-6 rounded-3xl text-center">
                        <p class="text-[#8A2BE2] text-[10px] font-black uppercase tracking-widest animate-pulse italic">In Verifizierung...</p>
                    </div>
                <?php else: ?>
                    <div class="mb-6 space-y-3">
                        <div class="flex gap-3">
                            <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-black text-[#8A2BE2] border border-white/10 shrink-0">1</div>
                            <p class="text-[9px] text-gray-400 font-bold uppercase leading-tight">Lade die <span class="text-white">Vorder- & Rückseite</span> deines gültigen Ausweisdokuments hoch.</p>
                        </div>
                        <div class="flex gap-3">
                            <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-black text-[#8A2BE2] border border-white/10 shrink-0">2</div>
                            <p class="text-[9px] text-gray-400 font-bold uppercase leading-tight">Mache ein <span class="text-white">Selfie</span> von dir, auf dem du einen Zettel mit GunDirect (handschriftlich) neben dein Gesicht hältst.</p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('idUploadForm').classList.toggle('hidden')" class="w-full bg-white/5 border border-white/10 py-4 rounded-2xl text-[10px] font-black uppercase text-white hover:bg-[#8A2BE2] transition tracking-widest">Verifizierung starten</button>
                    <div id="idUploadForm" class="hidden mt-6 pt-6 border-t border-white/5">
                        <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <div>
                                <label class="text-[8px] text-gray-500 uppercase font-black ml-2 mb-1 block">Ausweis Vorderseite</label>
                                <input type="file" name="id_front" accept=".png, .jpg, .jpeg" required class="w-full text-[9px] bg-black/50 p-3 rounded-xl border border-white/10">
                            </div>
                            <div>
                                <label class="text-[8px] text-gray-500 uppercase font-black ml-2 mb-1 block">Ausweis Rückseite</label>
                                <input type="file" name="id_back" accept=".png, .jpg, .jpeg" required class="w-full text-[9px] bg-black/50 p-3 rounded-xl border border-white/10">
                            </div>
                            <div>
                                <label class="text-[8px] text-gray-500 uppercase font-black ml-2 mb-1 block">Selfie mit Ausweis</label>
                                <input type="file" name="id_selfie" accept=".png, .jpg, .jpeg" required class="w-full text-[9px] bg-black/50 p-3 rounded-xl border border-white/10">
                            </div>
                            <button type="submit" class="w-full bg-[#8A2BE2] py-4 rounded-xl text-white text-[10px] font-black uppercase italic tracking-widest hover:opacity-90 transition-opacity">Upload & Senden</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="glass-panel p-8 rounded-[40px] border border-white/20 bg-white/[0.02]">
                <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-6 italic">Letzte Nachrichten</h3>
                <div class="space-y-4">
                    <?php if(empty($recent_msgs)): ?>
                        <p class="text-gray-700 text-[10px] font-black uppercase italic py-4 text-center">Keine neuen Nachrichten.</p>
                    <?php else: ?>
                        <?php foreach($recent_msgs as $msg): ?>
                            <a href="messages.php" class="block p-4 bg-white/5 rounded-2xl border border-transparent transition group">
                                <p class="text-[10px] text-[#8A2BE2] font-black uppercase tracking-widest"><?= h($msg['sender_name']) ?></p>
                                <p class="text-[11px] text-gray-400 mt-1 line-clamp-1 italic group-hover:text-white transition-colors">"<?= h($msg['message_text']) ?>"</p>
                            </a>
                        <?php endforeach; ?>
                        <a href="messages.php" class="block text-center text-[9px] text-gray-600 uppercase font-black mt-4 hover:text-[#8A2BE2] transition-colors tracking-widest italic">Alle Nachrichten öffnen</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="glass-panel rounded-[40px] overflow-hidden border border-white/20 min-h-[600px] flex flex-col bg-white/[0.01]">
                <div class="p-8 border-b border-white/10 flex justify-between items-center bg-white/[0.01]">
                    <h3 class="text-white font-black uppercase text-sm tracking-widest italic">Anzeigen <span class="text-[#8A2BE2]">Management</span></h3>
                    <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest"><?= count($my_listings) ?> Inserate</span>
                </div>
                
                <div class="p-8 flex-1">
                    <?php if(empty($my_listings)): ?>
                        <div class="text-center py-32">
                            <div class="text-gray-800 text-6xl mb-6">∅</div>
                            <p class="text-gray-600 text-[10px] font-black uppercase tracking-[0.3em] mb-8">Dein Lager ist leer.</p>
                            <a href="create-listing.php" class="bg-[#8A2BE2]/10 text-[#8A2BE2] px-10 py-5 rounded-2xl font-black text-[10px] uppercase border border-[#8A2BE2]/20 hover:bg-[#8A2BE2] hover:text-white transition-all tracking-[0.3em] italic">Jetzt Inserieren</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($my_listings as $item): ?>
                                <div class="flex flex-col sm:flex-row items-center justify-between p-6 bg-white/[0.03] rounded-[32px] border border-white/10 hover:border-[#8A2BE2]/40 hover:bg-white/[0.05] transition-all group gap-6">
                                    <div class="flex items-center gap-6 w-full">
                                        <div class="w-20 h-20 shrink-0 overflow-hidden rounded-2xl bg-black border border-white/5">
                                            <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" onerror="this.src='../assets/img/no-image.png'" class="w-full h-full object-cover group-hover:scale-110 transition duration-1000">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-black text-white uppercase tracking-tight truncate"><?= h($item['title']) ?></p>
                                            <div class="flex flex-wrap items-center gap-4 mt-2">
                                                <span class="text-[#8A2BE2] text-[11px] font-black italic tracking-widest"><?= formatPrice($item['price']) ?></span>
                                                <span class="w-[1px] h-3 bg-white/10"></span>
                                                <span class="text-gray-500 text-[9px] font-black uppercase italic"><?= number_format($item['views'], 0, ',', '.') ?> Aufrufe</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                                        <a href="../marketplace/view-item.php?id=<?= $item['id'] ?>" class="p-4 bg-white/5 rounded-2xl text-gray-500 hover:text-white transition-all" title="Ansehen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2"/></svg>
                                        </a>
                                        <a href="edit-listing.php?id=<?= $item['id'] ?>" class="p-4 bg-white/5 rounded-2xl text-gray-500 hover:text-[#8A2BE2] transition-all" title="Bearbeiten">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                        <a href="delete-listing.php?id=<?= $item['id'] ?>" onclick="return confirm('Inserat wirklich unwiderruflich löschen?')" class="p-4 bg-white/5 rounded-2xl text-gray-500 hover:text-red-500 transition-all" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>