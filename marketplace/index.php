<?php
require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * 1. AUTOMATISCHER AUKTIONS-CHECK (Lazy Cron)
 */
$pdo->query("UPDATE listings 
             SET status = 'ended' 
             WHERE type = 'auction' 
             AND status = 'active' 
             AND auction_end_datetime <= NOW()");

// Standard-Bild falls kein Upload vorhanden
$no_image = "../assets/img/no-image.png";

/**
 * ROTATIONS-LOGIK:
 * Wir berechnen eine Zahl, die sich nur alle 2 Stunden (7200 Sek) ändert.
 * Diese Zahl nutzen wir als Seed für RAND(), damit die "Zufälligkeit" 
 * für 2 Stunden statisch bleibt.
 */
$rotation_seed = floor(time() / 7200);

/**
 * 2. ECHTE AUKTIONEN (5 zufällige, rotierend)
 */
$stmtAuk = $pdo->prepare("SELECT l.*, u.username, u.is_verified,
                        COALESCE((SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id), l.price) as current_bid
                        FROM listings l 
                        JOIN users u ON l.seller_id = u.id 
                        WHERE l.type = 'auction' AND l.status = 'active'
                        ORDER BY RAND(?) LIMIT 5");
$stmtAuk->execute([$rotation_seed]);
$auctions = $stmtAuk->fetchAll();

/**
 * 3. FREIE WAFFEN (5 zufällige, rotierend)
 */
$stmtFrei = $pdo->prepare("SELECT l.*, u.username, u.is_verified 
                         FROM listings l 
                         JOIN users u ON l.seller_id = u.id 
                         WHERE l.category = 'frei' AND l.type = 'fixed' AND l.status = 'active'
                         ORDER BY RAND(?) LIMIT 5");
$stmtFrei->execute([$rotation_seed]);
$free_weapons = $stmtFrei->fetchAll();

/**
 * 4. EWB WAFFEN (5 zufällige, rotierend)
 */
$stmtEwb = $pdo->prepare("SELECT l.*, u.username, u.is_verified 
                        FROM listings l 
                        JOIN users u ON l.seller_id = u.id 
                        WHERE l.category = 'ewb' AND l.type = 'fixed' AND l.status = 'active'
                        ORDER BY RAND(?) LIMIT 5");
$stmtEwb->execute([$rotation_seed]);
$ewb_weapons = $stmtEwb->fetchAll();

include('../includes/header.php');
?>

<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
    <div class="absolute inset-0 opacity-[0.04]" 
         style="background-image: linear-gradient(#ffffff 1px, transparent 1px), linear-gradient(90deg, #ffffff 1px, transparent 1px); background-size: 45px 45px;">
    </div>
    <div class="absolute top-[-10%] left-[-10%] w-[700px] h-[700px] bg-[#8A2BE2]/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-[-10%] right-[-5%] w-[500px] h-[500px] bg-blue-600/5 blur-[100px] rounded-full"></div>
</div>

<header class="max-w-[1800px] mx-auto px-4 lg:px-10 pt-20 pb-12 relative">
    <div class="max-w-4xl">
        <div class="inline-block bg-[#8A2BE2]/10 border border-[#8A2BE2]/30 px-5 py-1.5 rounded-full mb-8 animate-pulse">
            <span class="text-[#8A2BE2] text-[10px] font-black uppercase tracking-[0.4em] italic">Deutschlands modernster Marktplatz</span>
        </div>
        <h1 class="text-6xl lg:text-9xl font-black mb-8 leading-[0.85] text-white uppercase italic tracking-tighter">
            Dein <br><span class="text-[#8A2BE2] drop-shadow-[0_0_40px_rgba(138,43,226,0.4)]">GUNDIRECT</span> Markt.
        </h1>
        <p class="text-gray-500 text-sm lg:text-xl max-w-2xl font-bold uppercase tracking-[0.2em] italic leading-relaxed">
            Handel ohne Umwege. Die direkte Verbindung zwischen. <span class="text-white">Käufer und Verkäufer</span>.
        </p>
    </div>
</header>

<main class="max-w-[1800px] mx-auto px-4 lg:px-10 py-10 relative">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">
        
        <div class="lg:col-span-4 space-y-8">
            <div class="flex items-center justify-between mb-4 px-2 border-l-2 border-red-600 pl-4">
                <h2 class="text-[12px] font-black tracking-[0.4em] uppercase text-white italic flex items-center">
                    <span class="w-2 h-2 bg-red-600 rounded-full mr-3 animate-ping"></span> Live Auktionen
                </h2>
            </div>

            <?php if(empty($auctions)): ?>
                <p class="text-[10px] text-gray-700 font-black uppercase italic p-6 border border-white/5 rounded-3xl">Momentan keine aktiven Gebote.</p>
            <?php endif; ?>

            <?php foreach($auctions as $item): ?>
            <a href="view-auction.php?id=<?= $item['id'] ?>" class="flex p-5 glass-panel rounded-[32px] border border-white/5 bg-white/[0.02] hover:bg-white/[0.06] hover:border-[#8A2BE2]/50 transition-all duration-500 group relative overflow-hidden">
                <div class="w-24 h-24 bg-black rounded-2xl shrink-0 overflow-hidden border border-white/10 shadow-2xl">
                    <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" 
                         onerror="this.src='<?= $no_image ?>'" 
                         class="w-full h-full object-cover group-hover:scale-110 transition duration-700 opacity-80 group-hover:opacity-100">
                </div>
                <div class="ml-6 flex-1 flex flex-col justify-between py-1">
                    <div>
                        <h3 class="font-black text-[13px] text-white uppercase group-hover:text-[#8A2BE2] transition truncate w-44 italic tracking-tight"><?= h($item['title']) ?></h3>
                        <p class="text-[9px] text-gray-600 mt-2 font-black uppercase tracking-widest italic"><?= h($item['username']) ?></p>
                    </div>
                    <div>
                        <span class="text-[8px] text-[#8A2BE2] font-black uppercase italic tracking-widest block mb-1">Aktuelles Gebot</span>
                        <p class="text-2xl font-black text-white italic tracking-tighter"><?= number_format($item['current_bid'], 2, ',', '.') ?>€</p>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="lg:col-span-4 space-y-8">
            <div class="flex items-center justify-between mb-4 px-2 border-l-2 border-[#8A2BE2] pl-4">
                <h2 class="text-[12px] font-black tracking-[0.4em] uppercase text-white italic">Freie Waffen (18+)</h2>
            </div>

            <?php foreach($free_weapons as $item): ?>
            <a href="view-item.php?id=<?= $item['id'] ?>" class="flex p-5 glass-panel rounded-[32px] border border-white/5 bg-white/[0.02] hover:bg-white/[0.06] hover:border-[#8A2BE2]/50 transition-all duration-500 group relative overflow-hidden">
                <div class="w-24 h-24 bg-black rounded-2xl shrink-0 overflow-hidden border border-white/10">
                    <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" 
                         onerror="this.src='<?= $no_image ?>'" 
                         class="w-full h-full object-cover group-hover:scale-110 transition duration-700 opacity-80 group-hover:opacity-100">
                </div>
                <div class="ml-6 flex-1 flex flex-col justify-between py-1">
                    <div>
                        <h3 class="font-black text-[13px] text-white uppercase group-hover:text-[#8A2BE2] transition truncate w-44 italic tracking-tight"><?= h($item['title']) ?></h3>
                        <p class="text-[9px] text-gray-600 mt-2 font-black uppercase tracking-widest italic"><?= h($item['username']) ?></p>
                    </div>
                    <div>
                        <span class="text-[8px] text-gray-600 font-black uppercase italic tracking-widest block mb-1">Festpreis</span>
                        <p class="text-2xl font-black text-white italic tracking-tighter"><?= number_format($item['price'], 2, ',', '.') ?>€</p>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="lg:col-span-4 space-y-8">
            <div class="flex items-center justify-between mb-4 px-2 border-l-2 border-red-800 pl-4">
                <h2 class="text-[12px] font-black tracking-[0.4em] uppercase text-white italic">EWB Erforderlich</h2>
            </div>

            <?php foreach($ewb_weapons as $item): ?>
            <a href="view-item.php?id=<?= $item['id'] ?>" class="flex p-5 glass-panel rounded-[32px] border border-white/5 bg-white/[0.02] hover:bg-white/[0.06] hover:border-red-600/40 transition-all duration-500 group relative overflow-hidden">
                <div class="w-24 h-24 bg-black rounded-2xl shrink-0 overflow-hidden border border-white/10">
                    <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" 
                         onerror="this.src='<?= $no_image ?>'" 
                         class="w-full h-full object-cover group-hover:scale-110 transition duration-700 opacity-80 group-hover:opacity-100">
                </div>
                <div class="ml-6 flex-1 flex flex-col justify-between py-1">
                    <div class="flex justify-between items-start">
                        <h3 class="font-black text-[13px] text-white uppercase group-hover:text-red-500 transition truncate w-36 italic tracking-tight"><?= h($item['title']) ?></h3>
                        <span class="text-[8px] bg-red-600 text-white px-2 py-0.5 rounded font-black italic tracking-widest shadow-lg shadow-red-600/20">EWB</span>
                    </div>
                    <div>
                        <span class="text-[8px] text-gray-600 font-black uppercase italic tracking-widest block mb-1">Angebot</span>
                        <p class="text-2xl font-black text-white italic tracking-tighter"><?= number_format($item['price'], 2, ',', '.') ?>€</p>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php include('../includes/footer.php'); ?>