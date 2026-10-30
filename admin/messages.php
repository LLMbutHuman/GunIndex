<?php
require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// Admin-Sicherheitscheck
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../marketplace/index.php");
    exit;
}

// Filter-Logik
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if ($filter_user_id) {
    // Nur Nachrichten dieses Users laden
    $sql = "SELECT m.*, u1.username as sender_name, u2.username as receiver_name 
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            ORDER BY m.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter_user_id, $filter_user_id]);
    $messages = $stmt->fetchAll();
} else {
    // Alle Nachrichten laden
    $sql = "SELECT m.*, u1.username as sender_name, u2.username as receiver_name 
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            ORDER BY m.created_at DESC LIMIT 100";
    $messages = $pdo->query($sql)->fetchAll();
}

include('../includes/header.php');
?>

<div class="max-w-[1400px] mx-auto px-4 py-12">
    <div class="mb-10 flex justify-between items-end border-b border-white/10 pb-8">
        <div>
            <a href="users.php" class="text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] mb-4 block">? Zurück zur Operator-Liste</a>
            <h1 class="text-4xl font-black text-white uppercase italic tracking-tighter">Intercept <span class="text-[#8A2BE2]">Logs</span></h1>
            <?php if($filter_user_id): ?>
                <p class="text-[#8A2BE2] text-[10px] font-black uppercase mt-2 italic animate-pulse">Überwachung aktiv: User #<?= $filter_user_id ?></p>
            <?php endif; ?>
        </div>
        <?php if($filter_user_id): ?>
            <a href="messages.php" class="bg-white/5 border border-white/10 px-4 py-2 rounded-lg text-[9px] font-black text-white uppercase hover:bg-red-600 transition">Filter löschen</a>
        <?php endif; ?>
    </div>

    <div class="glass-panel rounded-[32px] border border-white/10 bg-white/[0.01] overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-white/5 text-[10px] text-gray-500 font-black uppercase tracking-widest italic">
                <tr>
                    <th class="p-6">Zeit</th>
                    <th class="p-6">Von ? Zu</th>
                    <th class="p-6">Inhalt</th>
                    <th class="p-6 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                <?php foreach($messages as $m): ?>
                <tr class="hover:bg-white/[0.02] transition">
                    <td class="p-6 text-[10px] font-mono text-gray-500"><?= date('H:i', strtotime($m['created_at'])) ?></td>
                    <td class="p-6">
                        <span class="font-black text-xs <?= $m['sender_id'] == $filter_user_id ? 'text-[#8A2BE2]' : 'text-white' ?>"><?= h($m['sender_name']) ?></span>
                        <span class="text-gray-600">?</span>
                        <span class="text-xs"><?= h($m['receiver_name']) ?></span>
                    </td>
                    <td class="p-6">
                        <div class="bg-white/[0.03] p-3 rounded-xl border border-white/5 text-xs text-gray-300"><?= h($m['message_text']) ?></div>
                    </td>
                    <td class="p-6 text-right">
                        <span class="text-[8px] font-black uppercase <?= $m['is_read'] ? 'text-green-500' : 'text-yellow-500' ?>">
                            <?= $m['is_read'] ? 'Gelesen' : 'Neu' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('../includes/footer.php'); ?>