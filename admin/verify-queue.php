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
 * 2. AKTIONEN VERARBEITEN (Annehmen / Ablehnen)
 */

// LOGIK FÜR BESTÄTIGUNG
if (isset($_GET['approve_id'])) {
    $target_id = (int)$_GET['approve_id'];
    
    $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_status = 'approved' WHERE id = ?");
    if ($stmt->execute([$target_id])) {
        // Log-Eintrag erstellen (Falls Funktion in functions.php existiert)
        if (function_exists('logAdminAction')) {
            logAdminAction($pdo, $admin_id, 'VERIFY_APPROVE', $target_id, "Identität wurde durch Admin bestätigt.");
        }
        header("Location: verify-queue.php?msg=approved");
        exit;
    }
}

// LOGIK FÜR ABLEHNUNG
if (isset($_GET['reject_id'])) {
    $target_id = (int)$_GET['reject_id'];
    
    $stmt = $pdo->prepare("UPDATE users SET is_verified = 0, verification_status = 'rejected' WHERE id = ?");
    if ($stmt->execute([$target_id])) {
        if (function_exists('logAdminAction')) {
            logAdminAction($pdo, $admin_id, 'VERIFY_REJECT', $target_id, "Identität wurde aufgrund mangelhafter Dokumente abgelehnt.");
        }
        header("Location: verify-queue.php?msg=rejected");
        exit;
    }
}

/**
 * 3. DATEN LADEN (Nur User mit Status 'pending')
 */
$stmt = $pdo->query("SELECT id, username, email, created_at FROM users WHERE verification_status = 'pending' ORDER BY created_at ASC");
$pending_users = $stmt->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1400px] mx-auto px-4 lg:px-10 py-12 relative">
    
    <div class="mb-12 flex flex-col md:flex-row justify-between items-end gap-6 border-b border-white/10 pb-10">
        <div>
            <a href="index.php" class="text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] mb-4 block italic transition">
                ← Return to Command Center
            </a>
            <h1 class="text-6xl font-black uppercase italic text-white tracking-tighter">
                Identity <span class="text-[#8A2BE2]">Validation</span>
            </h1>
            <p class="text-gray-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">Eingehende Identitäts-Datenpakete zur Analyse</p>
        </div>
        <div class="bg-white/5 px-8 py-4 rounded-[2rem] border border-white/10">
            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest italic mb-1">Warteschlange</p>
            <p class="text-2xl font-black text-white italic"><?= count($pending_users) ?> Operator</p>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="bg-[#8A2BE2]/10 border border-[#8A2BE2]/30 text-[#8A2BE2] text-[10px] font-black uppercase p-5 rounded-2xl mb-12 italic tracking-widest animate-pulse">
            SYSLOG: <?= $_GET['msg'] === 'approved' ? 'Operator-Status auf VERIFIZIERT gesetzt.' : 'Identitäts-Paket wurde verworfen.' ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-8">
        <?php if(empty($pending_users)): ?>
            <div class="glass-panel p-24 rounded-[3rem] border border-white/5 bg-white/[0.01] text-center">
                <p class="text-gray-600 font-black uppercase italic tracking-[0.5em] text-sm">Keine aktiven Datenübertragungen</p>
            </div>
        <?php endif; ?>

        <?php foreach($pending_users as $user): ?>
        <div class="glass-panel p-8 rounded-[3rem] border border-white/10 bg-white/[0.02] flex flex-col lg:flex-row items-center justify-between gap-8 group hover:border-[#8A2BE2]/40 transition-all duration-500">
            
            <div class="flex items-center gap-6 w-full lg:w-1/4">
                <div class="w-16 h-16 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center text-2xl font-black text-[#8A2BE2] italic">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div>
                    <h3 class="text-white font-black uppercase italic text-xl tracking-tight"><?= h($user['username']) ?></h3>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest italic"><?= h($user['email']) ?></p>
                    <p class="text-[8px] text-gray-700 mt-2 font-black uppercase italic tracking-tighter">ID: #<?= str_pad($user['id'], 6, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>

            <div class="flex gap-4 items-center">
                <?php 
                $doc_types = ['front' => 'Front', 'back' => 'Back', 'selfie' => 'Selfie'];
                foreach($doc_types as $key => $label): 
                    $img_path = "../assets/uploads/verification/" . $user['id'] . "_" . $key . ".jpg";
                ?>
                <div class="text-center group/img relative">
                    <div onclick="openLightbox('<?= $img_path ?>')" class="w-28 h-20 bg-black rounded-2xl border border-white/10 overflow-hidden cursor-zoom-in group-hover/img:border-[#8A2BE2] transition-all relative">
                        <img src="<?= $img_path ?>" onerror="this.src='../assets/img/no-image.png'" class="w-full h-full object-cover opacity-30 group-hover/img:opacity-100 transition-all duration-700">
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/img:opacity-100 transition-all bg-black/40">
                            <span class="text-[8px] text-white font-black uppercase italic">Scan</span>
                        </div>
                    </div>
                    <span class="text-[8px] text-gray-600 font-black uppercase mt-2 block tracking-widest italic"><?= $label ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="flex gap-4 w-full lg:w-auto">
                <a href="verify-queue.php?reject_id=<?= $user['id'] ?>" onclick="return confirm('Identität wirklich ablehnen?')" 
                   class="flex-1 lg:flex-none px-8 py-5 bg-red-600/10 text-red-600 border border-red-600/20 rounded-2xl text-[10px] font-black uppercase hover:bg-red-600 hover:text-white transition-all italic tracking-widest text-center">
                    Ablehnen
                </a>
                <a href="verify-queue.php?approve_id=<?= $user['id'] ?>" 
                   class="flex-1 lg:flex-none px-12 py-5 bg-[#8A2BE2] text-white rounded-2xl text-[10px] font-black uppercase shadow-2xl shadow-[#8A2BE2]/30 hover:bg-[#9d44ff] hover:scale-[1.05] transition-all italic tracking-widest text-center">
                    Bestätigen
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="tactical-lightbox" class="fixed inset-0 bg-black/98 z-[9999] hidden flex items-center justify-center p-10 cursor-zoom-out" onclick="closeLightbox()">
    <img id="lightbox-source" src="" class="max-w-full max-h-full rounded-2xl border border-white/10 shadow-[0_0_100px_rgba(138,43,226,0.2)]">
    <div class="absolute top-10 right-10 text-white font-black uppercase italic text-xs tracking-widest">Klicken zum Schließen ✕</div>
</div>

<script>
function openLightbox(src) {
    const lb = document.getElementById('tactical-lightbox');
    const img = document.getElementById('lightbox-source');
    img.src = src;
    lb.classList.remove('hidden');
    lb.classList.add('flex');
}

function closeLightbox() {
    const lb = document.getElementById('tactical-lightbox');
    lb.classList.add('hidden');
    lb.classList.remove('flex');
}

// Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLightbox();
});
</script>

<?php include('../includes/footer.php'); ?>