<?php
require_once('../config/db.php');
require_once('../config/functions.php');

// Erzwingt UTF-8 für die PHP-Ausgabe (Fix für Fragezeichen)
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Sicherheit: Zugriffsschutz
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Parameter aus URL laden
$receiver_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$listing_id  = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

// 3. Validierung: Partner und Inserat müssen existieren
$stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmtUser->execute([$receiver_id]);
$receiver = $stmtUser->fetch();

$stmtListing = $pdo->prepare("SELECT title, seller_id, status, buyout_price FROM listings WHERE id = ?");
$stmtListing->execute([$listing_id]);
$listing = $stmtListing->fetch();

if (!$receiver || !$listing) {
    die("Fehler: Gesprächspartner oder Inserat existiert nicht.");
}

// 4. Nachricht senden Logik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg_text = trim((string)$_POST['message']);
    
    if (!empty($msg_text)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, listing_id, is_read) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$user_id, $receiver_id, $msg_text, $listing_id]);
        
        // Refresh um POST-Daten zu löschen
        header("Location: chat.php?user_id=$receiver_id&listing_id=$listing_id");
        exit;
    }
}

// 5. Nachrichten NUR für dieses Inserat laden (Trennung der Verläufe)
$stmtMsgs = $pdo->prepare("SELECT * FROM messages 
                           WHERE ((sender_id = ? AND receiver_id = ?) 
                           OR (sender_id = ? AND receiver_id = ?))
                           AND listing_id = ? 
                           ORDER BY created_at ASC");
$stmtMsgs->execute([$user_id, $receiver_id, $receiver_id, $user_id, $listing_id]);
$all_messages = $stmtMsgs->fetchAll();

// 6. Nachrichten für dieses Inserat als gelesen markieren
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND listing_id = ?")
    ->execute([$user_id, $receiver_id, $listing_id]);

include('../includes/header.php');
?>

<div class="max-w-[1100px] mx-auto px-4 py-10">
    <div class="glass-panel rounded-[40px] overflow-hidden flex flex-col h-[80vh] border border-white/5 shadow-2xl relative bg-black/20">
        
        <div class="p-6 border-b border-white/5 bg-white/[0.02] flex justify-between items-center relative z-10">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-[#8A2BE2] flex items-center justify-center text-white font-black text-sm shadow-lg shadow-[#8A2BE2]/20">
                    <?= strtoupper(substr(h($receiver['username']), 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-white font-black uppercase text-sm italic tracking-tight"><?= h($receiver['username']) ?></h2>
                    <p class="text-[10px] text-[#8A2BE2] font-black uppercase tracking-widest mt-0.5 italic">
                        Betreff: <?= h($listing['title']) ?>
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="rate-user.php?user_id=<?= $receiver_id ?>&listing_id=<?= $listing_id ?>" class="bg-yellow-500/10 border border-yellow-500/30 px-4 py-2 rounded-xl text-[9px] text-yellow-500 hover:bg-yellow-500 hover:text-black font-black uppercase tracking-widest transition flex items-center gap-2">
                    <span>★</span> Nutzer Bewerten
                </a>

                <a href="../marketplace/view-auction.php?id=<?= $listing_id ?>" class="bg-white/5 border border-white/10 px-4 py-2 rounded-xl text-[9px] text-gray-400 hover:text-white font-black uppercase tracking-widest transition">
                    Auktion öffnen ✕
                </a>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-8 space-y-6 bg-black/40 shadow-inner custom-scrollbar" id="chatWindow">
            <?php if(empty($all_messages)): ?>
                <div class="text-center py-20 opacity-20">
                    <p class="text-white text-[10px] font-black uppercase tracking-[0.3em]">Sichere Verbindung hergestellt...</p>
                </div>
            <?php endif; ?>

            <?php foreach($all_messages as $m): ?>
                <div class="flex <?= $m['sender_id'] == $user_id ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[70%] p-5 rounded-[28px] text-sm leading-relaxed <?= $m['sender_id'] == $user_id ? 'bg-[#8A2BE2] text-white rounded-tr-none' : 'bg-white/5 text-gray-300 border border-white/10 rounded-tl-none' ?>">
                        <?= nl2br(h($m['message_text'])) ?>
                        <div class="flex items-center justify-end gap-2 mt-2 opacity-30">
                            <p class="text-[8px] font-black uppercase"><?= date('H:i', strtotime($m['created_at'])) ?></p>
                            <?php if($m['sender_id'] == $user_id): ?>
                                <span class="text-[8px]"><?= $m['is_read'] ? '✓✓' : '✓' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($listing['seller_id'] == $user_id && $listing['status'] == 'active'): ?>
            <div class="p-6 bg-[#8A2BE2]/10 border-t border-[#8A2BE2]/20 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-center md:text-left">
                    <p class="text-white font-black uppercase italic text-[10px] tracking-widest">Kauf-Option für diesen Nutzer?</p>
                    <p class="text-[9px] text-gray-500 uppercase font-black italic">Aktion beendet das Listing sofort zu Gunsten von <?= h($receiver['username']) ?></p>
                </div>
                <form action="../actions/manage-auction.php" method="POST" onsubmit="return confirm('Verkauf an <?= h($receiver['username']) ?> final bestätigen?');">
                    <input type="hidden" name="listing_id" value="<?= $listing_id ?>">
                    <input type="hidden" name="buyer_id" value="<?= $receiver_id ?>">
                    <input type="hidden" name="action" value="confirm_buyout">
                    <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-8 py-3 rounded-xl font-black uppercase italic text-[10px] tracking-widest transition-all shadow-lg shadow-green-600/20">
                        Verkauf jetzt Bestätigen
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="p-6 border-t border-white/5 bg-white/[0.01]">
            <form action="" method="POST" class="flex gap-4">
                <input type="text" name="message" placeholder="Narchicht senden..." autocomplete="off" required
                       class="flex-1 bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white focus:outline-none focus:border-[#8A2BE2] transition italic">
                <button type="submit" class="bg-[#8A2BE2] px-10 py-5 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white italic transition-all active:scale-95 shadow-lg shadow-[#8A2BE2]/20">
                    Senden
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(138, 43, 226, 0.3); border-radius: 10px; }
</style>

<script>
    const chatWindow = document.getElementById('chatWindow');
    chatWindow.scrollTop = chatWindow.scrollHeight;
</script>

<?php include('../includes/footer.php'); ?>