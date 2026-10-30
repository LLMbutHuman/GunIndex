<?php
require_once('../config/db.php');
require_once('../config/functions.php');
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../marketplace/index.php");
    exit;
}

// Alle Logs laden mit Admin-Namen
$sql = "SELECT al.*, u.username as admin_name 
        FROM admin_logs al 
        JOIN users u ON al.admin_id = u.id 
        ORDER BY al.created_at DESC LIMIT 200";
$logs = $pdo->query($sql)->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1400px] mx-auto px-4 py-12">
    <div class="mb-10 flex justify-between items-end border-b border-white/10 pb-8">
        <div>
            <a href="index.php" class="text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] mb-4 block">? Command Center</a>
            <h1 class="text-4xl font-black text-white uppercase italic tracking-tighter">System <span class="text-[#8A2BE2]">Protocol</span></h1>
            <p class="text-gray-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">Vollständige Revisionsliste aller Admin-Eingriffe</p>
        </div>
    </div>

    <div class="glass-panel rounded-[32px] border border-white/10 bg-white/[0.01] overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-white/5 text-[10px] text-gray-500 font-black uppercase tracking-widest italic">
                <tr>
                    <th class="p-6">Zeitstempel</th>
                    <th class="p-6">Administrator</th>
                    <th class="p-6">Aktion</th>
                    <th class="p-6">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                <?php foreach($logs as $log): ?>
                <tr class="hover:bg-white/[0.02] transition">
                    <td class="p-6 text-[10px] font-mono text-gray-500">
                        <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                    </td>
                    <td class="p-6">
                        <span class="text-[#8A2BE2] font-black uppercase italic text-xs"><?= h($log['admin_name']) ?></span>
                    </td>
                    <td class="p-6">
                        <span class="px-2 py-1 bg-white/5 border border-white/10 rounded text-[9px] font-black uppercase italic">
                            <?= h($log['action_type']) ?>
                        </span>
                    </td>
                    <td class="p-6">
                        <p class="text-xs text-gray-400 italic"><?= h($log['details']) ?></p>
                        <?php if($log['target_id']): ?>
                            <span class="text-[8px] text-gray-600 font-bold uppercase tracking-widest">Objekt-ID: #<?= $log['target_id'] ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('../includes/footer.php'); ?>