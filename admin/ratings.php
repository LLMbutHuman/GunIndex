<?php
require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * 1. ADMIN SICHERHEITS-CHECK
 */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../marketplace/index.php");
    exit("Zugriff verweigert.");
}

$admin_id = $_SESSION['user_id'];
$success_msg = "";

/**
 * 2. LÖSCH-LOGIK
 */
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    
    // Log vor dem Löschen vorbereiten (um zu wissen, was gelöscht wurde)
    $stmtData = $pdo->prepare("SELECT r.rating, u.username FROM ratings r JOIN users u ON r.from_user_id = u.id WHERE r.id = ?");
    $stmtData->execute([$del_id]);
    $ratingInfo = $stmtData->fetch();

    $stmt = $pdo->prepare("DELETE FROM ratings WHERE id = ?");
    if ($stmt->execute([$del_id])) {
        // Logbuch Eintrag
        if (function_exists('logAdminAction')) {
            $details = "Bewertung von " . ($ratingInfo['username'] ?? 'Unbekannt') . " (" . ($ratingInfo['rating'] ?? '?') . " Sterne) entfernt.";
            logAdminAction($pdo, $admin_id, 'DELETE_RATING', $del_id, $details);
        }
        $success_msg = "Bewertung wurde erfolgreich aus dem Protokoll getilgt.";
    }
}

/**
 * 3. DATEN LADEN (Alle Bewertungen mit User-Informationen)
 */
$sql = "SELECT r.*, 
               u1.username as from_user, 
               u2.username as to_user 
        FROM ratings r
        JOIN users u1 ON r.from_user_id = u1.id
        JOIN users u2 ON r.to_user_id = u2.id
        ORDER BY r.created_at DESC";
$ratings = $pdo->query($sql)->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1600px] mx-auto px-4 lg:px-10 py-12">
    
    <div class="mb-12 flex flex-col md:flex-row justify-between items-end gap-6 border-b border-white/10 pb-10">
        <div>
            <a href="index.php" class="text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] mb-4 block italic transition">
                ? Return to Command Center
            </a>
            <h1 class="text-6xl font-black uppercase italic text-white tracking-tighter">
                Reputation <span class="text-[#8A2BE2]">Audit</span>
            </h1>
            <p class="text-gray-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">Überwachung und Moderation des Feedback-Systems</p>
        </div>
        <div class="bg-white/5 px-8 py-4 rounded-[2rem] border border-white/10 text-right">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest italic mb-1">Feedback-Einträge</p>
            <p class="text-2xl font-black text-white italic"><?= count($ratings) ?></p>
        </div>
    </div>

    <?php if($success_msg): ?>
        <div class="bg-red-600/10 border border-red-600/30 text-red-600 text-[10px] font-black uppercase p-5 rounded-2xl mb-12 italic tracking-widest animate-pulse">
            SYSLOG: <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <div class="glass-panel rounded-[3rem] border border-white/10 bg-white/[0.01] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-white/5 text-[10px] text-gray-500 font-black uppercase tracking-widest italic border-b border-white/10 text-white">
                        <th class="p-8 w-40">Datum</th>
                        <th class="p-8 w-64">Sektor (Von ? Zu)</th>
                        <th class="p-8 w-32 text-center">Bewertung</th>
                        <th class="p-8">Kommentar / Log</th>
                        <th class="p-8 text-right">Eingriff</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if(empty($ratings)): ?>
                        <tr><td colspan="5" class="p-24 text-center text-gray-700 font-black uppercase italic tracking-[0.4em]">Keine Reputationsdaten im System gefunden.</td></tr>
                    <?php endif; ?>

                    <?php foreach($ratings as $r): ?>
                    <tr class="hover:bg-white/[0.02] transition-all group">
                        <td class="p-8 text-[10px] font-mono text-gray-600 italic">
                            <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?>
                        </td>
                        <td class="p-8">
                            <div class="flex flex-col gap-1">
                                <span class="text-white font-black uppercase italic text-xs tracking-tight"><?= h($r['from_user']) ?></span>
                                <span class="text-gray-600 text-[9px] font-bold uppercase tracking-widest italic">An: <?= h($r['to_user']) ?></span>
                            </div>
                        </td>
                        <td class="p-8 text-center">
                            <div class="flex justify-center gap-1">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <div class="w-2 h-2 rounded-full <?= $i <= $r['rating'] ? 'bg-[#8A2BE2] shadow-[0_0_8px_rgba(138,43,226,0.6)]' : 'bg-white/5' ?>"></div>
                                <?php endfor; ?>
                            </div>
                            <span class="text-[9px] font-black text-white mt-2 block italic"><?= $r['rating'] ?>.0 / 5</span>
                        </td>
                        <td class="p-8">
                            <div class="bg-white/[0.03] p-5 rounded-2xl border border-white/5 group-hover:border-[#8A2BE2]/30 transition-all">
                                <p class="text-gray-300 text-xs italic leading-relaxed">"<?= h($r['comment']) ?>"</p>
                            </div>
                        </td>
                        <td class="p-8 text-right">
                            <a href="ratings.php?delete_id=<?= $r['id'] ?>" 
                               onclick="return confirm('Soll diese Bewertung permanent aus dem Reputationsverlauf entfernt werden?')" 
                               class="inline-flex items-center justify-center p-4 bg-red-600/10 border border-red-600/20 text-red-600 rounded-2xl hover:bg-red-600 hover:text-white transition-all shadow-lg group/del">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-12 opacity-30 text-center">
        <p class="text-[8px] text-gray-500 font-black uppercase tracking-[0.6em] italic">Authorized Admin Oversight - Security Protocol Active</p>
    </div>
</div>

<?php include('../includes/footer.php'); ?>