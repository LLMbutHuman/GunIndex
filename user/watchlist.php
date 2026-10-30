<?php
require_once('../config/db.php');
require_once('../config/functions.php');
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT l.* FROM listings l 
    JOIN watchlist w ON l.id = w.listing_id 
    WHERE w.user_id = ? ORDER BY w.created_at DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1200px] mx-auto px-4 py-12">
    <h1 class="text-3xl font-black uppercase tracking-tighter text-white mb-10 italic">Deine <span class="text-[#8A2BE2]">Merkliste</span></h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($items as $item): ?>
            <div class="glass-panel rounded-[32px] overflow-hidden border border-white/5 hover:border-[#8A2BE2]/50 transition group">
                <a href="../marketplace/view-item.php?id=<?= $item['id'] ?>">
                    <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" onerror="this.src='../assets/img/no-image.png'" class="w-full h-48 object-cover group-hover:scale-110 transition duration-700">
                </a>
                <div class="p-6">
                    <h3 class="text-white font-black uppercase text-sm mb-2 italic"><?= h($item['title']) ?></h3>
                    <div class="flex justify-between items-center">
                        <span class="text-[#8A2BE2] font-black"><?= formatPrice($item['price']) ?></span>
                        <a href="toggle-watchlist.php?id=<?= $item['id'] ?>" class="text-red-500 text-[10px] font-black uppercase italic">Entfernen</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include('../includes/footer.php'); ?>