<?php
require_once('../config/db.php');
require_once('../config/functions.php');

header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * 1. PARAMETER-ERFASSUNG
 */
$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$zip_in  = isset($_GET['zip']) ? trim((string)$_GET['zip']) : '';
$rad     = (!empty($_GET['rad']) && (int)$_GET['rad'] > 0) ? (int)$_GET['rad'] : 0;
$cat     = (!empty($_GET['type']) && $_GET['type'] !== 'all') ? $_GET['type'] : null;

// Filter: Nur anzeigen, wenn Abholung/Treffen explizit möglich ist
$pickup_only = (isset($_GET['pickup']) && ($_GET['pickup'] == '1' || $_GET['pickup'] == 'on'));

/**
 * 2. GEOGRAPHISCHE ANALYSE (RADIUS-SCAN)
 */
$select_dist = "";
$spatial_params = [];
$having = []; 

if (!empty($zip_in) && $rad > 0) {
    $stmtO = $pdo->prepare("SELECT lat, lon FROM zip_coordinates WHERE zip = ? LIMIT 1");
    $stmtO->execute([$zip_in]);
    $origin = $stmtO->fetch();

    if ($origin) {
        $uLat = (float)$origin['lon']; 
        $uLon = (float)$origin['lat']; 

        $select_dist = ", (6371 * acos(LEAST(1.0, GREATEST(-1.0, 
            sin(radians(?)) * sin(radians(z.lon)) + 
            cos(radians(?)) * cos(radians(z.lon)) * cos(radians(z.lat) - radians(?))
        )))) AS distance";
        
        $spatial_params = [$uLat, $uLat, $uLon];
        
        // ZWANG: Muss im Radius liegen
        $having[] = "distance <= " . (int)$rad;
    } else {
        // Falls PLZ nicht existiert, aber Radius gesetzt wurde -> 0 Ergebnisse erzwingen
        $having[] = "1=0";
    }
}

/**
 * 3. QUERY-KOMPOSITION (STRENGE FILTERUNG)
 */
$where = ["l.status = 'active'"];
$where_params = [];

// Text-Suche
if (!empty($q)) {
    $where[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $where_params[] = "%$q%"; 
    $where_params[] = "%$q%";
}

// Kategorie-Filter
if ($cat) {
    $where[] = "l.category = ?";
    $where_params[] = $cat;
}

/**
 * DER PICKUP-FIX:
 * Wenn 'pickup_only' aktiv ist, MUSS das Feld l.pickup den Wert 1 haben.
 * Alle Inserate mit 0 oder NULL fliegen hier raus.
 */
if ($pickup_only) {
    $where[] = "l.pickup = 1";
}

$sql = "SELECT l.*, u.username, u.is_verified, 
        (SELECT AVG(stars) FROM ratings WHERE receiver_id = l.seller_id) as avg_stars
        $select_dist
        FROM listings l 
        JOIN users u ON l.seller_id = u.id 
        LEFT JOIN zip_coordinates z ON TRIM(l.zip) = TRIM(z.zip) 
        WHERE " . implode(" AND ", $where);

// Having-Klausel (Radius)
$having_sql = !empty($having) ? " HAVING " . implode(" AND ", $having) : "";

// Sortierung
$order = (!empty($select_dist)) ? " ORDER BY distance ASC" : " ORDER BY l.created_at DESC";

$final_params = array_merge($spatial_params, $where_params);

try {
    $stmt = $pdo->prepare($sql . $having_sql . $order);
    $stmt->execute($final_params); 
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Search Error: " . $e->getMessage());
    $results = [];
}

include('../includes/header.php');
?>

<div class="max-w-[1400px] mx-auto px-4 py-12 lg:py-20">
    
    <div class="mb-12 border-b border-white/5 pb-10 flex flex-col md:flex-row justify-between items-end gap-6">
        <div>
            <h1 class="text-5xl font-black uppercase italic text-white tracking-tighter">
                Inserat <span class="text-[#8A2BE2]">Auflistung</span>
            </h1>
            <p class="text-gray-500 text-[10px] font-black uppercase tracking-[0.4em] mt-4 italic">
                Gefunden: <?= count($results) ?> Inserate
                <?php if($pickup_only): ?> | <span class="text-[#8A2BE2]">Nur Abholung erzwungen</span><?php endif; ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php if (empty($results)): ?>
            <div class="col-span-full glass-panel p-24 text-center rounded-[60px] border border-white/5 bg-white/[0.01]">
                <p class="text-gray-500 font-black uppercase text-xs tracking-[0.3em] italic">Keine Inserate im gewählten Bereich gefunden.</p>
            </div>
        <?php else: ?>
            <?php foreach ($results as $item): ?>
                <?php 
                    $target = ($item['type'] === 'auction') ? 'view-auction.php' : 'view-item.php'; 
                    $stars = round((float)$item['avg_stars']);
                ?>
                <div class="glass-panel rounded-[40px] border border-white/5 bg-white/[0.02] overflow-hidden group hover:border-[#8A2BE2]/40 transition-all duration-700">
                    
                    <a href="<?= $target ?>?id=<?= $item['id'] ?>" class="block aspect-square overflow-hidden relative">
                        <img src="../assets/uploads/listings/<?= $item['id'] ?>_1.jpg" onerror="this.src='../assets/img/no-image.png'" class="w-full h-full object-cover group-hover:scale-110 transition duration-1000">
                        
                        <?php if(isset($item['distance'])): ?>
                            <div class="absolute top-6 right-6 bg-black/80 backdrop-blur-xl px-4 py-2 rounded-2xl border border-white/10 text-[10px] font-black text-[#8A2BE2] italic">
                                <?= round($item['distance'], 1) ?> KM
                            </div>
                        <?php endif; ?>

                        <?php if($item['pickup']): ?>
                        <div class="absolute bottom-6 right-6 bg-green-500/20 backdrop-blur-md px-3 py-1 rounded border border-green-500/30 text-[7px] font-black uppercase text-green-500 italic">
                            Pickup OK
                        </div>
                        <?php endif; ?>
                    </a>

                    <div class="p-8">
                        <h3 class="text-white font-black uppercase text-sm truncate italic mb-2"><?= h($item['title']) ?></h3>
                        <div class="flex items-center gap-3 mb-6">
                            <span class="text-[9px] text-gray-600 font-black italic"><?= h($item['username']) ?></span>
                            <div class="flex gap-0.5">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <span class="<?= $i <= $stars ? 'text-[#8A2BE2]' : 'text-white/10' ?> text-[10px]">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="flex justify-between items-center border-t border-white/5 pt-6">
                            <span class="text-2xl font-black text-white italic"><?= number_format($item['price'], 2, ',', '.') ?> €</span>
                            <a href="<?= $target ?>?id=<?= $item['id'] ?>" class="w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-[#8A2BE2] transition-all">→</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include('../includes/footer.php'); ?>