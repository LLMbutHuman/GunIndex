<?php
require_once('../config/db.php');
require_once('../config/functions.php');

// Erzwingt UTF-8 für die PHP-Ausgabe (Fix für Encoding-Fehler)
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Artikel-Daten & Verkäufer-Details laden
$stmt = $pdo->prepare("
    SELECT l.*, 
           u.username as seller_name, 
           u.is_verified, 
           u.id as seller_id
    FROM listings l 
    JOIN users u ON l.seller_id = u.id 
    WHERE l.id = ?
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: index.php");
    exit;
}

// Umleitung falls Auktion
if ($item['type'] === 'auction') {
    header("Location: view-auction.php?id=" . $item['id']);
    exit;
}

// 2. Views tracker
$pdo->prepare("UPDATE listings SET views = views + 1 WHERE id = ?")->execute([$item_id]);

// 3. Durchschnittliche Sterne des Verkäufers berechnen
$stmtAvg = $pdo->prepare("SELECT AVG(stars) as avg_rating FROM ratings WHERE receiver_id = ?");
$stmtAvg->execute([$item['seller_id']]);
$avg_rating = round((float)$stmtAvg->fetch()['avg_rating'], 1);

// 4. Merklisten-Status
$is_watched = false;
if (isset($_SESSION['user_id'])) {
    $stmtW = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND listing_id = ?");
    $stmtW->execute([$_SESSION['user_id'], $item['id']]);
    $is_watched = (bool)$stmtW->fetch();
}

// 5. Bilder-Check: Prüfen welche der 5 Bilder existieren
$images = [];
for($i=1; $i<=5; $i++) {
    $imgPath = "../assets/uploads/listings/" . $item['id'] . "_" . $i . ".jpg";
    if(file_exists($imgPath)) {
        $images[] = $imgPath;
    }
}
// Fallback falls gar kein Bild existiert
if(empty($images)) { $images[] = "../assets/img/no-image.png"; }

include('../includes/header.php');
?>

<style>
    .btn-action { background: linear-gradient(135deg, #8A2BE2 0%, #4B0082 100%); transition: all 0.4s; }
    .glass-panel { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); }
    .rating-link:hover .star-icon { color: #8A2BE2; filter: drop-shadow(0 0 8px rgba(138,43,226,0.6)); }
    .thumb-active { border-color: #8A2BE2 !important; opacity: 1 !important; }
</style>

<div class="max-w-[1400px] mx-auto px-4 py-12 lg:py-20 relative">
    
    <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-[#8A2BE2]/5 blur-[150px] rounded-full pointer-events-none -z-10"></div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
        
        <div class="lg:col-span-7 space-y-6">
            <div class="glass-panel rounded-[48px] overflow-hidden border border-white/5 aspect-square relative bg-black">
                <img id="mainImage" src="<?= $images[0] ?>" class="w-full h-full object-cover transition duration-500 ease-in-out">
                
                <div class="absolute top-8 left-8 bg-black/60 backdrop-blur-xl px-5 py-2.5 rounded-full border border-white/10">
                    <span class="text-white font-black text-[10px] uppercase tracking-widest italic">ID: #<?= $item['id'] ?></span>
                </div>
            </div>

            <?php if(count($images) > 1): ?>
            <div class="flex gap-4 overflow-x-auto pb-2 custom-scrollbar">
                <?php foreach($images as $index => $img): ?>
                <div class="w-24 h-24 shrink-0 rounded-2xl overflow-hidden border-2 border-white/5 cursor-pointer opacity-50 transition-all hover:opacity-100 <?= $index === 0 ? 'thumb-active' : '' ?>" 
                     onclick="changeImage('<?= $img ?>', this)">
                    <img src="<?= $img ?>" class="w-full h-full object-cover">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-5 space-y-6">
            <div class="glass-panel p-10 lg:p-14 rounded-[48px] border border-white/10 relative overflow-hidden">
                <div class="relative z-10">
                    <h1 class="text-5xl font-black text-white uppercase tracking-tighter italic leading-none mb-4">
                        <?= h($item['title']) ?>
                    </h1>

                    <div class="flex items-center justify-between mb-10 pb-6 border-b border-white/5">
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest italic">Anbieter</span>
                            <div class="flex items-center gap-2">
                                <span class="text-white font-black uppercase text-sm italic tracking-tight"><?= h($item['seller_name']) ?></span>
                                <?php if($item['is_verified']): ?>
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="../user/rate-user.php?user_id=<?= $item['seller_id'] ?>" class="rating-link flex flex-col items-end gap-1 transition-all group">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest italic group-hover:text-white transition">Rating</span>
                            <div class="flex gap-1">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <svg class="star-icon w-4 h-4 transition-all <?= $i <= $avg_rating ? 'text-[#8A2BE2]' : 'text-white/10' ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <span class="text-[8px] text-gray-700 font-black italic uppercase"><?= $avg_rating > 0 ? $avg_rating . ' / 5.0' : 'No Data' ?></span>
                        </a>
                    </div>

                    <div class="grid grid-cols-2 gap-8 mb-12">
                        <div>
                            <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 italic">Festpreis</p>
                            <p class="text-5xl font-black text-white italic tracking-tighter"><?= number_format($item['price'], 2, ',', '.') ?> EUR</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 italic">Kategorie</p>
                            <p class="text-xl font-black text-white italic uppercase"><?= h($item['category']) ?></p>
                        </div>
                    </div>

                    <div class="mb-12">
                        <p class="text-[10px] font-black text-gray-500 uppercase mb-4 italic">Beschreibung</p>
                        <div class="text-gray-400 text-sm leading-relaxed bg-white/[0.03] p-6 rounded-3xl border border-white/5 italic">
                            <?= nl2br(h($item['description'])) ?>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <?php if($_SESSION['user_id'] != $item['seller_id']): ?>
                                <a href="../user/chat.php?user_id=<?= $item['seller_id'] ?>&listing_id=<?= $item['id'] ?>" 
                                   class="block w-full btn-action py-6 rounded-2xl text-white text-center font-black uppercase tracking-widest text-xs italic">
                                    Verkäufer kontaktieren
                                </a>

                                <a href="../user/toggle-watchlist.php?id=<?= $item['id'] ?>" 
                                   class="block w-full text-center py-4 rounded-2xl font-black uppercase text-[9px] border border-white/10 text-gray-500 hover:text-white transition-all">
                                   <?= $is_watched ? '★ Von Überwachung entfernen' : '☆ Auf Merkliste' ?>
                                </a>
                            <?php else: ?>
                                <div class="p-6 bg-white/5 border border-white/10 rounded-2xl text-center">
                                    <p class="text-[9px] text-gray-500 font-black uppercase italic">Eigene Einheit</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="../auth/login.php" class="block w-full btn-action py-6 rounded-2xl text-white text-center font-black uppercase italic text-xs">
                                Login für Kontakt
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="glass-panel p-8 rounded-[40px] border border-white/5 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-[#8A2BE2]/20 rounded-2xl flex items-center justify-center text-[#8A2BE2] font-black italic">
                        <?= strtoupper(substr($item['seller_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="text-xs text-white font-black uppercase italic"><?= h($item['seller_name']) ?></p>
                        <p class="text-[8px] text-gray-600 font-black uppercase tracking-widest italic">Operator Level 1</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[9px] text-gray-500 font-black uppercase italic mb-1">Views</p>
                    <p class="text-xs text-white font-black italic"><?= number_format($item['views'], 0, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Funktion zum Wechseln des Hauptbildes
    function changeImage(src, thumb) {
        const mainImage = document.getElementById('mainImage');
        
        // Sanfter Fade-Out/In Effekt
        mainImage.style.opacity = '0.3';
        
        setTimeout(() => {
            mainImage.src = src;
            mainImage.style.opacity = '1';
            
            // Thumbnail Styling anpassen
            document.querySelectorAll('.thumb-active').forEach(el => el.classList.remove('thumb-active'));
            thumb.classList.add('thumb-active');
        }, 200);
    }
</script>

<?php include('../includes/footer.php'); ?>