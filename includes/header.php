<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/functions.php');

$unreadCount = 0; $watchlistCount = 0;
if (isset($_SESSION['user_id'])) {
    $u_id = (int)$_SESSION['user_id'];
    try {
        $unreadCount = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_id = $u_id AND is_read = 0")->fetchColumn();
        $watchlistCount = $pdo->query("SELECT COUNT(*) FROM watchlist WHERE user_id = $u_id")->fetchColumn();
    } catch(Exception $e) { }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>GunDirect.de</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --purple: #8A2BE2; }
        body { background: #030303; color: #e5e7eb; font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        @media (max-width: 767px) { body { padding-bottom: calc(85px + env(safe-area-inset-bottom)); } }

        .glass-nav { background: rgba(10, 10, 10, 0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.08); }
        
        #filterDrawer { 
            position: fixed; top: 0; right: 0; bottom: 0; width: 100%; 
            background: #080808; z-index: 10000; transform: translateX(100%); 
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
        }
        @media (min-width: 640px) { #filterDrawer { max-width: 480px; border-left: 1px solid rgba(255,255,255,0.1); } }
        #filterDrawer.active { transform: translateX(0); }
        
        #overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 9999; display: none; backdrop-filter: blur(10px); }

        input[type=range] { -webkit-appearance: none; background: rgba(255,255,255,0.1); height: 8px; border-radius: 4px; width: 100%; }
        input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; height: 26px; width: 26px; border-radius: 50%; background: var(--purple); cursor: pointer; border: 3px solid white; box-shadow: 0 0 15px rgba(138, 43, 226, 0.5); }
        
        input, select { font-size: 16px !important; }
    </style>
</head>
<body class="antialiased">

<nav class="sticky top-0 z-[100] glass-nav px-4 md:px-10 py-3 flex items-center justify-between gap-3">
    <a href="../marketplace/index.php" class="text-xl md:text-2xl font-black italic tracking-tighter shrink-0">
        <span class="text-white">GUN</span><span class="text-[#8A2BE2]">DIRECT</span>.de
    </a>

    <div class="flex-1 flex items-center gap-2 max-w-2xl">
        <button onclick="toggleF(true)" class="bg-white/5 p-2.5 rounded-xl border border-white/10 shrink-0 group">
            <svg class="w-5 h-5 text-[#8A2BE2] group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" stroke-width="2.5" stroke-linecap="round"/></svg>
        </button>

        <form action="../marketplace/search.php" method="GET" class="flex-1 flex gap-2">
            <input type="text" name="q" placeholder="Suche..." class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-[#8A2BE2]" value="<?= h($_GET['q'] ?? '') ?>">
            <button type="submit" class="bg-[#8A2BE2] p-2.5 rounded-xl shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="3" stroke-linecap="round"/></svg>
            </button>
        </form>
    </div>

    <div class="hidden md:flex items-center space-x-6 shrink-0">
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="../user/watchlist.php" class="text-[10px] font-black uppercase text-gray-400 hover:text-white transition">Merkliste (<?= $watchlistCount ?>)</a>
            <a href="../user/messages.php" class="text-[10px] font-black uppercase text-gray-400 hover:text-white transition relative">
                Chats
                <?php if($unreadCount > 0): ?><span class="absolute -top-1 -right-2 bg-[#8A2BE2] w-2 h-2 rounded-full animate-pulse"></span><?php endif; ?>
            </a>
            <a href="../user/dashboard.php" class="bg-[#8A2BE2] px-6 py-2.5 rounded-full text-[10px] font-black uppercase text-white shadow-lg italic transition hover:scale-105 active:scale-95">Dashboard</a>
        <?php else: ?>
            <a href="../auth/login.php" class="text-[10px] font-black uppercase text-gray-400">Login</a>
            <a href="../auth/register.php" class="border border-white/10 px-6 py-2.5 rounded-full text-[10px] font-black uppercase text-white hover:bg-white/5 transition">Join</a>
        <?php endif; ?>
    </div>
</nav>

<div class="fixed bottom-0 left-0 w-full glass-nav border-t border-white/5 flex md:hidden justify-around py-3 z-[200] pb-[calc(12px+env(safe-area-inset-bottom))]">
    <a href="../marketplace/index.php" class="flex flex-col items-center gap-1 text-gray-400">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" stroke-width="2"/></svg>
        <span class="text-[8px] font-bold uppercase tracking-widest">Shop</span>
    </a>
    <a href="../user/watchlist.php" class="flex flex-col items-center gap-1 text-gray-400 relative">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" stroke-width="2"/></svg>
        <?php if($watchlistCount > 0): ?><span class="absolute -top-1 -right-1 bg-white/20 text-[7px] px-1 rounded-full text-white font-black"><?= $watchlistCount ?></span><?php endif; ?>
        <span class="text-[8px] font-bold uppercase tracking-widest">Merkliste</span>
    </a>
    <a href="../user/messages.php" class="flex flex-col items-center gap-1 text-gray-400 relative">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke-width="2"/></svg>
        <?php if($unreadCount > 0): ?><span class="absolute -top-1 -right-1 bg-[#8A2BE2] w-2.5 h-2.5 rounded-full border-2 border-black animate-pulse"></span><?php endif; ?>
        <span class="text-[8px] font-bold uppercase tracking-widest">Chats</span>
    </a>
    <a href="../user/dashboard.php" class="flex flex-col items-center gap-1 text-[#8A2BE2]">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"/></svg>
        <span class="text-[8px] font-bold uppercase tracking-widest">Dashboard</span>
    </a>
</div>

<div id="overlay" onclick="toggleF(false)"></div>
<div id="filterDrawer">
    <div class="p-6 border-b border-white/5 flex justify-between items-center bg-[#080808]">
        <h2 class="text-xl md:text-2xl font-black text-white uppercase italic tracking-tighter">Direkt<span class="text-[#8A2BE2]">Filter</span></h2>
        <button onclick="toggleF(false)" class="text-white text-4xl p-2 transition hover:text-[#8A2BE2]">&times;</button>
    </div>

    <form action="../marketplace/search.php" method="GET" class="flex-1 overflow-y-auto p-6 md:p-10 space-y-8 pb-40">
        <input type="hidden" name="q" value="<?= h($_GET['q'] ?? '') ?>">

        <div class="space-y-3">
            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Preisspanne (€)</label>
            <div class="grid grid-cols-2 gap-4">
                <input type="number" name="min_p" placeholder="Von" class="bg-white/5 border border-white/10 rounded-2xl p-4 text-white outline-none focus:border-[#8A2BE2]">
                <input type="number" name="max_p" placeholder="Bis" class="bg-white/5 border border-white/10 rounded-2xl p-4 text-white outline-none focus:border-[#8A2BE2]">
            </div>
        </div>

        <div class="space-y-3">
            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Angebotstyp</label>
            <select name="type" onchange="this.value=='auktion'?document.getElementById('aucX').classList.remove('hidden'):document.getElementById('aucX').classList.add('hidden')" class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white outline-none appearance-none cursor-pointer focus:border-[#8A2BE2]">
                <option value="all" class="bg-[#080808]">Alle</option>
                <option value="frei" class="bg-[#080808]">Frei / F-Zeichen</option>
                <option value="ewb" class="bg-[#080808]">EWB-pflichtig</option>
                <option value="auktion" class="bg-[#080808]">Auktion</option>
            </select>
            <div id="aucX" class="hidden mt-4 p-4 bg-[#8A2BE2]/5 border border-[#8A2BE2]/20 rounded-2xl">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="buy_now" class="w-6 h-6 rounded accent-[#8A2BE2]">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">Sofort-Kauf möglich</span>
                </label>
            </div>
        </div>

        <div class="space-y-4">
            <label class="text-[10px] font-black text-[#8A2BE2] uppercase block italic tracking-widest">Radius: <span id="radLabel">25</span> km</label>
            <input type="text" name="zip" placeholder="PLZ eingeben" class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white mb-2 outline-none focus:border-[#8A2BE2]">
            <input type="range" name="rad" min="25" max="600" step="25" value="25" oninput="document.getElementById('radLabel').innerText = this.value">
        </div>

        <div class="space-y-4 pt-4 border-t border-white/5">
            <label class="flex items-center gap-4 cursor-pointer group">
                <input type="checkbox" name="verified" class="w-6 h-6 rounded-lg accent-[#8A2BE2]">
                <span class="text-[11px] font-black text-gray-400 uppercase group-hover:text-white transition">Verifizierte User</span>
            </label>
            <label class="flex items-center gap-4 cursor-pointer group">
                <input type="checkbox" name="pickup" class="w-6 h-6 rounded-lg accent-[#8A2BE2]">
                <span class="text-[11px] font-black text-gray-400 uppercase group-hover:text-white transition">Abholung möglich</span>
            </label>
        </div>

        <button type="submit" class="w-full bg-gradient-to-r from-[#8A2BE2] to-[#4B0082] py-5 rounded-[24px] text-white font-black uppercase italic tracking-[0.2em] shadow-2xl transition hover:scale-[1.02] active:scale-[0.98]">
            Filter anwenden
        </button>
    </form>
</div>

<script>
function toggleF(s) {
    const d = document.getElementById('filterDrawer');
    const o = document.getElementById('overlay');
    if(s) {
        o.style.display = 'block';
        setTimeout(() => d.classList.add('active'), 10);
        document.body.style.overflow = 'hidden';
    } else {
        d.classList.remove('active');
        setTimeout(() => o.style.display = 'none', 400);
        document.body.style.overflow = '';
    }
}
</script>