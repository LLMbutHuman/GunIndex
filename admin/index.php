<?php
/**
 * COMMAND CENTER - ADMIN OVERVIEW
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * 1. STRENGER ADMIN-ZUGRIFFS-CHECK
 * (int) sorgt dafür, dass sowohl "1" als auch 1 akzeptiert werden.
 */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    // Falls du trotz Admin-Status hier landest: Logout und neu Login machen!
    header("Location: ../marketplace/index.php?access=denied");
    exit("ZUGRIFF VERWEIGERT.");
}

/**
 * 2. DATEN-AGGREGATION
 */
try {
    // Statistiken
    $stats = [
        'users'    => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'verified' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn(),
        'pending'  => $pdo->query("SELECT COUNT(*) FROM users WHERE verification_status = 'pending'")->fetchColumn(),
        'listings' => $pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn(),
        'ratings'  => $pdo->query("SELECT COUNT(*) FROM ratings")->fetchColumn(),
        'logs'     => $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn()
    ];

    // Letzte Operatoren (Benutzer)
    $recent_users = $pdo->query("SELECT username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Letztes Inventar (Inserate)
    $recent_listings = $pdo->query("SELECT l.title, l.price, u.username 
                                    FROM listings l 
                                    LEFT JOIN users u ON l.seller_id = u.id 
                                    ORDER BY l.created_at DESC LIMIT 5")->fetchAll();

    // System Protokolle (Admin Logs)
    $recent_logs = $pdo->query("SELECT al.*, u.username as admin_name 
                                FROM admin_logs al 
                                LEFT JOIN users u ON al.admin_id = u.id 
                                ORDER BY al.created_at DESC LIMIT 5")->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#111; color:red; padding:50px; font-family:sans-serif;'>
            <h1>CRITICAL_DATABASE_ERROR</h1>
            <p>{$e->getMessage()}</p>
         </div>");
}

include('../includes/header.php');
?>

<div class="fixed inset-0 -z-10 bg-[#050505]">
    <div class="absolute inset-0 opacity-[0.05]" style="background-image: linear-gradient(rgba(138, 43, 226, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(138, 43, 226, 0.1) 1px, transparent 1px); background-size: 50px 50px;"></div>
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-[500px] bg-[#8A2BE2]/5 blur-[120px] rounded-full"></div>
</div>

<div class="max-w-[1600px] mx-auto px-4 lg:px-10 py-12">
    
    <div class="mb-16 flex flex-col xl:flex-row justify-between items-start xl:items-end gap-8 border-b border-white/10 pb-12">
        <div>
            <div class="flex items-center gap-3 mb-4">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-ping"></span>
                <p class="text-[9px] text-green-500 font-black uppercase tracking-[0.5em] italic">System Status: Encrypted & Active</p>
            </div>
            <h1 class="text-7xl font-black uppercase italic text-white tracking-tighter leading-none">
                Command <span class="text-[#8A2BE2]">Center</span>
            </h1>
        </div>

        <nav class="flex flex-wrap gap-3">
            <a href="users.php" class="bg-white/5 border border-white/10 px-6 py-4 rounded-2xl text-[10px] font-black text-white uppercase hover:bg-[#8A2BE2] transition-all italic tracking-widest">Operator-DB</a>
            <a href="listings.php" class="bg-white/5 border border-white/10 px-6 py-4 rounded-2xl text-[10px] font-black text-white uppercase hover:bg-[#8A2BE2] transition-all italic tracking-widest">Inventar</a>
            <a href="verify-queue.php" class="bg-[#8A2BE2]/20 border border-[#8A2BE2]/50 px-6 py-4 rounded-2xl text-[10px] font-black text-[#8A2BE2] uppercase hover:bg-[#8A2BE2] hover:text-white transition-all italic tracking-widest relative">
                ID-Check
                <?php if($stats['pending'] > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-600 text-white w-5 h-5 rounded-full flex items-center justify-center text-[8px] animate-bounce"><?= $stats['pending'] ?></span>
                <?php endif; ?>
            </a>
            <a href="logs.php" class="bg-red-600/10 border border-red-600/20 px-6 py-4 rounded-2xl text-[10px] font-black text-red-500 uppercase hover:bg-red-600 hover:text-white transition-all italic tracking-widest">Audit-Log</a>
        </nav>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
        <div class="glass-panel p-10 rounded-[40px] border border-white/10 bg-white/[0.02]">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-4 italic">Operatoren</p>
            <h3 class="text-6xl font-black italic text-white tracking-tighter"><?= $stats['users'] ?></h3>
            <div class="mt-4"><span class="text-[9px] text-[#8A2BE2] font-black uppercase italic"><?= $stats['verified'] ?> Verifiziert</span></div>
        </div>

        <div class="glass-panel p-10 rounded-[40px] border border-white/10 bg-white/[0.02]">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-4 italic">Einheiten</p>
            <h3 class="text-6xl font-black italic text-white tracking-tighter"><?= $stats['listings'] ?></h3>
            <p class="text-[9px] text-gray-600 font-black mt-4 uppercase italic">Sektor: Marktplatz</p>
        </div>

        <div class="glass-panel p-10 rounded-[40px] border border-white/10 bg-white/[0.02]">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-4 italic">Reputation</p>
            <h3 class="text-6xl font-black italic text-white tracking-tighter"><?= $stats['ratings'] ?></h3>
            <p class="text-[9px] text-green-500 font-black mt-4 uppercase italic">Feedback Online</p>
        </div>

        <div class="glass-panel p-10 rounded-[40px] border border-white/10 bg-white/[0.02]">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-4 italic">Revisionen</p>
            <h3 class="text-6xl font-black italic text-white tracking-tighter"><?= $stats['logs'] ?></h3>
            <p class="text-[9px] text-red-600 font-black mt-4 uppercase italic">Logs gespeichert</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-10">
        
        <div class="glass-panel p-10 rounded-[48px] border border-white/10 bg-white/[0.01]">
            <h2 class="text-xs font-black text-white uppercase tracking-[0.4em] mb-10 italic border-l-4 border-[#8A2BE2] pl-4">Neue Operator</h2>
            <div class="space-y-6">
                <?php foreach($recent_users as $u): ?>
                    <div class="flex justify-between items-center group">
                        <div>
                            <p class="text-white font-black text-sm uppercase italic group-hover:text-[#8A2BE2] transition"><?= h($u['username']) ?></p>
                            <p class="text-[9px] text-gray-600 font-bold uppercase"><?= h($u['email']) ?></p>
                        </div>
                        <span class="text-[10px] font-mono text-gray-700"><?= date('H:i', strtotime($u['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="glass-panel p-10 rounded-[48px] border border-white/10 bg-white/[0.01]">
            <h2 class="text-xs font-black text-white uppercase tracking-[0.4em] mb-10 italic border-l-4 border-[#8A2BE2] pl-4">Frisches Inventar</h2>
            <div class="space-y-6">
                <?php foreach($recent_listings as $l): ?>
                    <div class="flex justify-between items-center group">
                        <div class="max-w-[180px]">
                            <p class="text-white font-black text-sm uppercase italic truncate group-hover:text-[#8A2BE2] transition"><?= h($l['title']) ?></p>
                            <p class="text-[9px] text-gray-600 font-bold uppercase italic">By: <?= h($l['username'] ?? 'Ghost') ?></p>
                        </div>
                        <span class="text-sm font-black text-white italic"><?= number_format($l['price'], 2, ',', '.') ?>€</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="glass-panel p-10 rounded-[48px] border border-red-600/20 bg-red-600/[0.02] flex flex-col">
            <h2 class="text-xs font-black text-red-600 uppercase tracking-[0.4em] mb-10 italic border-l-4 border-red-600 pl-4">System-Revision</h2>
            
            <div class="flex-1 space-y-6 mb-10">
                <?php foreach($recent_logs as $log): ?>
                    <div class="text-[10px]">
                        <div class="flex justify-between mb-1">
                            <span class="text-red-500 font-black uppercase italic"><?= h($log['admin_name'] ?? 'System') ?></span>
                            <span class="text-gray-700 font-mono"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                        </div>
                        <p class="text-gray-400 font-medium italic leading-relaxed bg-white/5 p-3 rounded-xl border border-white/5"><?= h($log['details']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="border-t border-red-600/20 pt-8 mt-auto">
                <form action="global-logout.php" method="POST" onsubmit="return confirm('ACHTUNG: Dies meldet JEDEN Benutzer sofort ab. Fortfahren?');">
                    <button type="submit" class="w-full bg-red-600/10 border border-red-600/50 py-4 rounded-2xl text-red-600 text-[10px] font-black uppercase italic tracking-widest hover:bg-red-600 hover:text-white transition-all">
                        Globaler Session-Reset
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include('../includes/footer.php'); ?>