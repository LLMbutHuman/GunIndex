<?php
require_once('../config/db.php');
require_once('../config/functions.php');

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');

if (session_status() === PHP_SESSION_NONE) session_start();

// Sicherheit: Zugriff nur f√ºr eingeloggte User
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";
$success = false;
$user_id = $_SESSION['user_id'];

// STANDORT-ABRUF F√úR DEN SEKTOR-SCAN
$userStmt = $pdo->prepare("SELECT zip, city FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$userData = $userStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category    = $_POST['category']; 
    $type        = $_POST['type']; 
    $caliber     = !empty($_POST['caliber']) ? trim($_POST['caliber']) : null;
    $pickup      = isset($_POST['pickup']) ? 1 : 0; // Checkbox f√ºr Treffen/Abholung
    
    // Standort-Zuweisung
    $zip  = !empty($userData['zip']) ? $userData['zip'] : null;
    $city = !empty($userData['city']) ? $userData['city'] : null;

    $price        = (!empty($_POST['price'])) ? (float)$_POST['price'] : 0.00;
    $buyout_price = (!empty($_POST['buyout_price'])) ? (float)$_POST['buyout_price'] : 0.00;

    $auction_end_datetime = null;
    if ($type === 'auction') {
        $duration_val = (int)$_POST['duration_value'];
        $duration_unit = $_POST['duration_unit']; 
        $end_timestamp = ($duration_unit === 'days') ? time() + ($duration_val * 86400) : time() + ($duration_val * 3600);
        $auction_end_datetime = date('Y-m-d H:i:s', $end_timestamp);
    }

    if (empty($title) || empty($description) || $price <= 0 || empty($zip)) {
        $error = "FEHLER: Titel, Beschreibung, Preis und ein hinterlegter Standort im Profil sind zwingend erforderlich.";
    } else {
        try {
            $sql = "INSERT INTO listings (seller_id, title, description, category, type, price, buyout_price, auction_end_datetime, caliber, zip, city, pickup, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$user_id, $title, $description, $category, $type, $price, $buyout_price, $auction_end_datetime, $caliber, $zip, $city, $pickup])) {
                $listingId = $pdo->lastInsertId();
                
                // BILDER-LOGISTIK
                $uploadDir = "../assets/uploads/listings/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $allowed_extensions = ['jpg', 'jpeg', 'png']; // DIE REGEL

                for ($i = 1; $i <= 5; $i++) {
                    $fieldName = "img_" . $i;
                    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                        
                        // VALIDIERUNG DER DATEIENDUNG
                        $fileExtension = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
                        
                        if (in_array($fileExtension, $allowed_extensions)) {
                            $destination = $uploadDir . $listingId . "_" . $i . ".jpg";
                            move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destination);
                        } else {
                            $error = "FEHLER: Nur Bilder (JPG, PNG) erlaubt. Andere Formate wurden blockiert.";
                        }
                    }
                }
                if(empty($error)) $success = true;
            }
        } catch (PDOException $e) {
            $error = "SYSTEM-FEHLER: " . $e->getMessage();
        }
    }
}

include('../includes/header.php');
?>

<div class="max-w-[1000px] mx-auto px-4 py-12 lg:py-20">
    <div class="mb-12">
        <h1 class="text-5xl font-black uppercase tracking-tighter italic text-white leading-none">
            Einheit <span class="text-[#8A2BE2]">registrieren</span>
        </h1>
        <div class="flex items-center gap-4 mt-4">
            <div class="h-[1px] w-12 bg-[#8A2BE2]"></div>
            <p class="text-gray-500 text-[10px] uppercase font-black tracking-[0.4em] italic">Standort-Sektor: <?= h($userData['zip'] ?? 'NICHT GEFUNDEN') ?></p>
        </div>
    </div>

    <?php if($success): ?>
        <div class="glass-panel p-20 rounded-[60px] text-center border border-[#8A2BE2]/30 bg-[#8A2BE2]/5">
            <div class="text-6xl mb-8">Ì†ΩÌª∞Ô∏è</div>
            <h2 class="text-3xl font-black uppercase text-white italic tracking-tighter">√úbertragung erfolgreich</h2>
            <p class="text-gray-500 text-xs mt-4 mb-10 italic">Die Einheit wurde im Sektor-Scan aktiviert.</p>
            <a href="../marketplace/index.php" class="inline-block bg-[#8A2BE2] px-12 py-5 rounded-2xl font-black uppercase text-xs text-white italic hover:scale-105 transition shadow-2xl shadow-[#8A2BE2]/20">Zum Marktplatz</a>
        </div>
    <?php else: ?>

    <form action="create-listing.php" method="POST" enctype="multipart/form-data" class="space-y-10 text-white">
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-500 text-[10px] font-black uppercase p-6 rounded-3xl text-center italic tracking-widest animate-pulse"><?= $error ?></div>
        <?php endif; ?>

        <div class="glass-panel p-10 lg:p-14 rounded-[50px] border border-white/5 space-y-10">
            <h3 class="text-[10px] font-black text-[#8A2BE2] uppercase tracking-[0.5em] italic border-l-4 border-[#8A2BE2] pl-6">Spezifikationen</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic ml-2">Modell / Bezeichnung</label>
                    <input type="text" name="title" required placeholder="z.B. Glock 17 Gen 5" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none focus:border-[#8A2BE2] transition-all">
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic ml-2">Sektor-Kategorie</label>
                    <select name="category" class="w-full bg-black/60 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none italic cursor-pointer focus:border-[#8A2BE2]">
                        <option value="frei">Freie Waffen</option>
                        <option value="ewb">EWB-pflichtig</option>
                        <option value="zubehoer">Zubeh√∂r / Optik</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic ml-2">Kaliber (Optional) </label>
                    <input type="text" name="caliber" placeholder="z.B. 9mm Parabellum" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none focus:border-[#8A2BE2]">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic ml-2">Beschreibung</label>
                    <textarea name="description" required placeholder="Zustand, Schussbelastung, Zubeh√∂r..." class="w-full h-44 bg-white/5 border border-white/10 rounded-[35px] px-8 py-6 text-sm text-white outline-none focus:border-[#8A2BE2] italic resize-none"></textarea>
                </div>
            </div>
        </div>

        <div class="glass-panel p-10 lg:p-14 rounded-[50px] border border-white/5 space-y-10">
            <h3 class="text-[10px] font-black text-[#8A2BE2] uppercase tracking-[0.5em] italic border-l-4 border-[#8A2BE2] pl-6">Bilder</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <?php for($i=1; $i<=5; $i++): ?>
                <div class="relative group aspect-square bg-white/[0.03] border-2 border-dashed border-white/10 rounded-[30px] overflow-hidden hover:border-[#8A2BE2] transition-all duration-500">
                    <input type="file" name="img_<?= $i ?>" accept="image/png, image/jpeg" onchange="previewImage(this, <?= $i ?>)" class="absolute inset-0 opacity-0 cursor-pointer z-20">
                    <img id="preview_<?= $i ?>" class="hidden w-full h-full object-cover relative z-10 pointer-events-none">
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-[8px] text-gray-700 font-black uppercase italic group-hover:text-[#8A2BE2] transition-colors">
                        <span class="text-xl mb-1">+</span>
                        Cam <?= $i ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="glass-panel p-10 lg:p-14 rounded-[50px] border border-white/5 space-y-10">
            <h3 class="text-[10px] font-black text-[#8A2BE2] uppercase tracking-[0.5em] italic border-l-4 border-[#8A2BE2] pl-6">Optionen</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic ml-2">Modus</label>
                    <select name="type" id="typeSelect" onchange="toggleAuctionFields()" class="w-full bg-black/60 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none italic cursor-pointer">
                        <option value="fixed">Direktkauf</option>
                        <option value="auction">Auktion</option>
                    </select>
                </div>

                <div>
                    <label id="priceLabel" class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic ml-2">Preis (EUR)</label>
                    <input type="number" step="0.01" name="price" value="0.00" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none focus:border-[#8A2BE2]">
                </div>

                <div id="auctionFields" class="hidden md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-10 animate-fade-in">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic">Laufzeit</label>
                            <input type="number" name="duration_value" value="7" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none">
                        </div>
                        <div class="w-1/3">
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic">Einheit</label>
                            <select name="duration_unit" class="w-full bg-black/60 border border-white/10 rounded-2xl px-4 py-5 text-[10px] text-white outline-none uppercase font-black italic">
                                <option value="days">Tage</option>
                                <option value="hours">Std.</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase mb-3 italic">Sofort-Kauf (Optional)</label>
                        <input type="number" step="0.01" name="buyout_price" value="0.00" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-sm text-white outline-none">
                    </div>
                </div>

                <div class="md:col-span-2 pt-4">
                    <label class="flex items-center gap-4 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="pickup" value="1" class="peer hidden">
                            <div class="w-6 h-6 border-2 border-white/10 rounded-lg peer-checked:bg-[#8A2BE2] peer-checked:border-[#8A2BE2] transition-all"></div>
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 peer-checked:opacity-100 text-white text-[10px]">‚úì</div>
                        </div>
                        <span class="text-[10px] font-black uppercase italic text-gray-500 group-hover:text-white transition-colors">Pers√∂nliche √úbergabe / Treffen m√∂glich</span>
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-[#8A2BE2] py-8 rounded-[2.5rem] text-white text-[14px] font-black uppercase tracking-[0.5em] italic shadow-[0_20px_50px_rgba(138,43,226,0.3)] hover:scale-[1.02] hover:bg-[#9d4edd] active:scale-95 transition-all duration-500">
            Inserat Hochladen
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
function toggleAuctionFields() {
    const type = document.getElementById('typeSelect').value;
    const auctionFields = document.getElementById('auctionFields');
    const priceLabel = document.getElementById('priceLabel');
    if(type === 'auction') {
        auctionFields.style.display = 'grid';
        priceLabel.innerText = 'Startgebot (EUR)';
    } else {
        auctionFields.style.display = 'none';
        priceLabel.innerText = 'Festpreis (EUR)';
    }
}

function previewImage(input, index) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('preview_' + index);
            img.src = e.target.result;
            img.classList.remove('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include('../includes/footer.php'); ?>