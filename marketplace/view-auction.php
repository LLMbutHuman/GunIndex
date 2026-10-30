<?php
require_once('../config/db.php');
require_once('../config/functions.php');

// Erzwingt UTF-8 für die PHP-Ausgabe (Fix für Encoding-Fehler)
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

date_default_timezone_set('Europe/Berlin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Auktions-Daten laden + Namen des Höchstbieters (Leader) holen
$stmt = $pdo->prepare("
    SELECT l.*, u.username as seller_name, u.is_verified, u.id as seller_id,
           (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id) as current_high_bid,
           (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
           (SELECT user_id FROM bids WHERE listing_id = l.id ORDER BY bid_amount DESC LIMIT 1) as leader_id,
           (SELECT u2.username FROM bids b2 JOIN users u2 ON b2.user_id = u2.id WHERE b2.listing_id = l.id ORDER BY b2.bid_amount DESC LIMIT 1) as leader_name
    FROM listings l 
    JOIN users u ON l.seller_id = u.id 
    WHERE l.id = ? AND l.type = 'auction'
");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: index.php");
    exit;
}

$current_price = $item['current_high_bid'] ? (float)$item['current_high_bid'] : (float)$item['price'];
$min_bid = $current_price + 1;

$end_ts = !empty($item['auction_end_datetime']) ? strtotime($item['auction_end_datetime']) : 0;
$now_ts = time();
$is_ended = ($end_ts > 0 && $end_ts <= $now_ts) || ($item['status'] === 'ended');
$display_countdown = ($end_ts > 0);

include('../includes/header.php');
?>

<main class="max-w-[1600px] mx-auto px-4 lg:px-10 py-20 relative">
    <div class="fixed top-20 right-0 w-[500px] h-[500px] bg-red-600/5 blur-[120px] rounded-full pointer-events-none -z-10"></div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-16">
        
        <div class="lg:col-span-7 space-y-8">
            <div class="relative group">
                <div class="glass-panel rounded-[48px] overflow-hidden border border-white/5 bg-black aspect-video relative">
                    <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" 
                         onerror="this.src='../assets/img/no-image.png'" 
                         class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition duration-1000">
                    
                    <div class="absolute top-8 left-8 flex gap-3">
                        <div class="bg-black/60 backdrop-blur-xl px-5 py-2 rounded-full border border-white/10 text-[10px] font-black uppercase text-white italic tracking-widest">
                            ID: #<?= $item['id'] ?>
                        </div>
                        <?php if($is_ended): ?>
                            <div class="bg-red-600 text-white px-5 py-2 rounded-full text-[10px] font-black uppercase italic tracking-widest shadow-lg shadow-red-600/40">Auktion Beendet</div>
                        <?php else: ?>
                            <div class="bg-green-600 text-white px-5 py-2 rounded-full text-[10px] font-black uppercase italic tracking-widest animate-pulse shadow-lg shadow-green-600/20">Live Auktion</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="glass-panel p-10 rounded-[40px] border border-white/5 bg-white/[0.01]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.4em] mb-6 italic border-l-2 border-[#8A2BE2] pl-4">Beschreibung</h3>
                <p class="text-gray-400 leading-relaxed italic"><?= nl2br(h($item['description'])) ?></p>
            </div>
        </div>

        <div class="lg:col-span-5 space-y-6">
            <div class="glass-panel p-10 lg:p-14 rounded-[48px] border border-white/10 bg-white/[0.02] relative overflow-hidden">
                
                <h1 class="text-5xl font-black text-white uppercase italic tracking-tighter leading-none mb-8"><?= h($item['title']) ?></h1>

                <div class="grid grid-cols-2 gap-6 mb-10 pb-10 border-b border-white/5">
                    <div>
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 italic">Verbleibende Zeit</span>
                        <div id="countdown" class="text-2xl font-black text-white italic" 
                             data-end="<?= $item['auction_end_datetime'] ?>">
                            <?= $display_countdown ? '--:--:--' : 'Unbegrenzt' ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 italic">Gebote</span>
                        <div class="text-2xl font-black text-[#8A2BE2] italic"><?= $item['bid_count'] ?> <span class="text-[10px] text-gray-600 uppercase">Aktiv</span></div>
                    </div>
                </div>

                <div class="mb-12">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em] block mb-2 italic">Aktuelles Höchstgebot</span>
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-7xl font-black text-white italic tracking-tighter"><?= number_format($current_price, 2, ',', '.') ?> EUR</span>
                        <?php if($item['leader_name']): ?>
                            <div class="text-right">
                                <span class="text-[9px] text-gray-500 uppercase font-black block italic">Führender:</span>
                                <span class="text-[#8A2BE2] font-black italic uppercase text-sm"><?= h($item['leader_name']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if(!$is_ended): ?>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['user_id'] != $item['seller_id']): ?>
                            
                            <form action="../actions/place-bid.php" method="POST" class="space-y-4 mb-6">
                                <input type="hidden" name="listing_id" value="<?= $item['id'] ?>">
                                <div class="relative group">
                                    <input type="number" name="bid_amount" min="<?= $min_bid ?>" step="1" required
                                           placeholder="Dein Gebot (Min. <?= $min_bid ?> EUR)"
                                           class="w-full bg-white/5 border border-white/10 rounded-2xl py-6 px-8 text-white font-black italic focus:outline-none focus:border-[#8A2BE2] transition-all text-xl">
                                    <div class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-600 font-black italic text-sm">EUR</div>
                                </div>
                                <button type="submit" class="w-full bg-[#8A2BE2] hover:bg-[#9d4edd] text-white font-black uppercase italic tracking-[0.3em] py-6 rounded-2xl shadow-2xl transition-all active:scale-95">
                                    Gebot Bestätigen
                                </button>
                            </form>

                            <?php if($item['buyout_price'] > 0): ?>
                                <div class="relative py-4">
                                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-white/5"></div></div>
                                    <div class="relative flex justify-center text-[8px] uppercase font-black text-gray-600 tracking-widest"><span class="bg-[#0a0a0a] px-4">Alternativ-Option</span></div>
                                </div>
                                <a href="buyout-request.php?id=<?= $item['id'] ?>" class="block w-full text-center border border-[#8A2BE2]/40 hover:bg-[#8A2BE2]/10 text-white font-black uppercase italic text-[10px] tracking-[0.2em] py-5 rounded-xl transition-all">
                                    Sofort-Kauf Anfrage senden (<?= number_format($item['buyout_price'], 2, ',', '.') ?> EUR)
                                </a>
                            <?php endif; ?>

                            <?php if($item['leader_id'] == $_SESSION['user_id']): ?>
                                <div class="mt-4 p-4 bg-green-500/10 border border-green-500/20 rounded-xl text-green-500 text-[10px] font-black uppercase text-center italic tracking-widest">
                                    ? Du bist aktuell Höchstbietender
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="p-8 border border-white/5 rounded-3xl bg-white/[0.01] text-center italic text-gray-600 text-[10px] font-black uppercase tracking-widest">
                                Du kannst nicht auf dein eigenes Inserat bieten
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="../auth/login.php" class="block w-full bg-white/5 border border-white/10 text-center py-6 rounded-2xl text-white font-black uppercase italic text-xs tracking-[0.2em] hover:bg-[#8A2BE2] transition-all">
                            Anmelden zum Bieten
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-10 bg-[#8A2BE2]/10 border border-[#8A2BE2]/30 rounded-3xl text-center space-y-4">
                        <span class="text-[#8A2BE2] font-black uppercase italic tracking-[0.2em] block">Auktion Beendet</span>
                        <?php if($item['leader_id']): ?>
                             <p class="text-xs text-white uppercase font-black italic">Sieger: <span class="text-[#8A2BE2]"><?= h($item['leader_name']) ?></span></p>
                             <?php if(isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $item['leader_id'] || $_SESSION['user_id'] == $item['seller_id'])): ?>
                                <a href="../user/chat.php?listing_id=<?= $item['id'] ?>&user_id=<?= ($_SESSION['user_id'] == $item['seller_id']) ? $item['leader_id'] : $item['seller_id'] ?>" 
                                   class="inline-block bg-[#8A2BE2] text-white px-8 py-4 rounded-2xl font-black uppercase italic text-[10px] tracking-widest hover:scale-105 transition-all">
                                   Abwicklung / Chat öffnen
                                </a>
                             <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-12 pt-10 border-t border-white/5 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center text-[#8A2BE2] font-black italic text-lg border border-white/10">
                            <?= strtoupper(substr($item['seller_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <span class="text-[9px] text-gray-600 font-black uppercase block italic tracking-widest">Verkäufer</span>
                            <span class="text-white font-black text-sm italic tracking-tight"><?= h($item['seller_name']) ?></span>
                        </div>
                    </div>
                    <?php if($item['is_verified']): ?>
                        <div class="bg-[#8A2BE2]/10 px-4 py-2 rounded-xl border border-[#8A2BE2]/20">
                            <span class="text-[#8A2BE2] font-black text-[8px] uppercase italic tracking-widest">Verified Seller</span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
function updateCountdown() {
    const el = document.getElementById('countdown');
    if (!el || !el.getAttribute('data-end')) return;
    const endTimeStr = el.getAttribute('data-end');
    const endTime = new Date(endTimeStr.replace(/-/g, "/")).getTime();
    const now = new Date().getTime();
    const diff = endTime - now;
    if (diff <= 0) {
        el.innerHTML = "BEENDET";
        el.classList.add('text-red-600');
        return;
    }
    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const s = Math.floor((diff % (1000 * 60)) / 1000);
    el.innerHTML = `${d}d ${h.toString().padStart(2, '0')}h ${m.toString().padStart(2, '0')}m ${s.toString().padStart(2, '0')}s`;
}
setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<?php include('../includes/footer.php'); ?>