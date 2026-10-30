<?php
require_once('../config/db.php');
require_once('../config/functions.php');

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$my_id = $_SESSION['user_id'];

// Alle Chats abrufen (Letzte Nachricht pro Gesprächspartner & Inserat)
$sql = "SELECT m.*, l.title, u.username as partner_name, u.id as partner_id
        FROM messages m
        JOIN listings l ON m.listing_id = l.id
        JOIN users u ON (u.id = m.sender_id OR u.id = m.receiver_id) AND u.id != ?
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
        AND m.id IN (
            SELECT MAX(id) FROM messages 
            WHERE sender_id = ? OR receiver_id = ? 
            GROUP BY listing_id, 
                     CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
        )
        ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$my_id, $my_id, $my_id, $my_id, $my_id, $my_id]);
$chats = $stmt->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1200px] mx-auto px-4 py-10">
    <div class="mb-10">
        <h1 class="text-3xl font-black uppercase tracking-tighter text-white">Deine <span class="text-[#8A2BE2]">Nachrichten</span></h1>
        <p class="text-gray-500 text-xs uppercase font-bold tracking-widest mt-1 italic">Verhandlungen & Anfragen</p>
    </div>

    <div class="glass-panel rounded-[32px] overflow-hidden border border-white/5">
        <?php if (empty($chats)): ?>
            <div class="p-20 text-center">
                <p class="text-gray-600 uppercase text-[10px] font-bold tracking-widest">Noch keine Nachrichten vorhanden.</p>
                <a href="../marketplace/index.php" class="inline-block mt-4 text-[#8A2BE2] text-xs font-bold uppercase border-b border-[#8A2BE2]">Jetzt Marktplatz durchstöbern</a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-white/5">
                <?php foreach ($chats as $chat): ?>
                    <a href="chat.php?user_id=<?= $chat['partner_id'] ?>&listing_id=<?= $chat['listing_id'] ?>" class="flex items-center p-6 hover:bg-white/5 transition group">
                        <div class="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center text-[#8A2BE2] font-black border border-white/10 group-hover:border-[#8A2BE2] transition shadow-lg shadow-[#8A2BE2]/5">
                            <?= strtoupper(substr($chat['partner_name'], 0, 1)) ?>
                        </div>
                        
                        <div class="ml-6 flex-1">
                            <div class="flex justify-between items-start">
                                <h3 class="text-sm font-black text-white uppercase tracking-tight">
                                    <?= h($chat['partner_name']) ?>
                                    <?php if(!$chat['is_read'] && $chat['receiver_id'] == $my_id): ?>
                                        <span class="inline-block w-2 h-2 bg-[#8A2BE2] rounded-full ml-2 animate-pulse"></span>
                                    <?php endif; ?>
                                </h3>
                                <span class="text-[9px] text-gray-600 font-bold"><?= date('d.m. H:i', strtotime($m['created_at'] ?? $chat['created_at'])) ?></span>
                            </div>
                            <p class="text-[11px] text-[#8A2BE2] font-bold uppercase mt-1 italic"><?= h($chat['title']) ?></p>
                            <p class="text-xs text-gray-500 mt-1 truncate max-w-md italic">
                                <?= ($chat['sender_id'] == $my_id) ? 'Du: ' : '' ?><?= h($chat['message_text']) ?>
                            </p>
                        </div>

                        <div class="ml-4 opacity-0 group-hover:opacity-100 transition translate-x-[-10px] group-hover:translate-x-0 transition-all">
                            <svg class="w-5 h-5 text-[#8A2BE2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('../includes/footer.php'); ?>