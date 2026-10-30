<?php
require_once('../config/db.php');
require_once('../config/functions.php');

session_start();

// Sicherheit: Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Man kann sich nicht selbst bewerten
if ($receiver_id === $sender_id || $receiver_id === 0) {
    header("Location: ../marketplace/index.php");
    exit;
}

// Empfänger-Daten laden
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$receiver_id]);
$receiver = $stmt->fetch();

if (!$receiver) {
    die("Benutzer nicht gefunden.");
}

$error = "";
$success = false;

// Bewertung speichern Logik
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stars = isset($_POST['stars']) ? (int)$_POST['stars'] : 0;
    $comment = isset($_POST['comment']) ? trim((string)$_POST['comment']) : '';

    if ($stars < 1 || $stars > 5) {
        $error = "Bitte wähle eine Sterne-Bewertung zwischen 1 und 5.";
    } else {
        // Prüfen, ob bereits bewertet wurde
        $check = $pdo->prepare("SELECT id FROM ratings WHERE sender_id = ? AND receiver_id = ?");
        $check->execute([$sender_id, $receiver_id]);

        if ($check->rowCount() > 0) {
            $error = "Du hast diesen Nutzer bereits bewertet.";
        } else {
            $insert = $pdo->prepare("INSERT INTO ratings (sender_id, receiver_id, stars, comment) VALUES (?, ?, ?, ?)");
            if ($insert->execute([$sender_id, $receiver_id, $stars, $comment])) {
                $success = true;
            } else {
                $error = "Systemfehler beim Speichern.";
            }
        }
    }
}

include('../includes/header.php');
?>

<div class="max-w-[900px] mx-auto px-4 py-20">
    <div class="glass-panel p-10 md:p-16 rounded-[60px] border border-white/10 relative overflow-hidden bg-black/40 backdrop-blur-3xl shadow-2xl">
        
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-[#8A2BE2]/20 blur-[100px] rounded-full"></div>
        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-blue-500/10 blur-[100px] rounded-full"></div>

        <div class="relative z-10 text-center">
            <div class="inline-block bg-[#8A2BE2]/10 border border-[#8A2BE2]/30 px-6 py-2 rounded-full mb-6">
                 <p class="text-[#8A2BE2] text-[10px] font-black uppercase tracking-[0.4em] italic">Rezension System</p>
            </div>
            
            <h1 class="text-5xl font-black uppercase tracking-tighter text-white mb-4 italic">
                Nutzer <span class="text-[#8A2BE2]">bewerten</span>
            </h1>
            <p class="text-gray-400 text-sm font-bold uppercase tracking-widest mb-16 italic opacity-60">
                Wie hat die die Interatkion mit <span class="text-white"><?= h($receiver['username']) ?></span> Gefallen?
            </p>

            <?php if ($success): ?>
                <div class="bg-green-500/10 border border-green-500/30 p-12 rounded-[40px] animate-pulse">
                    <svg class="w-16 h-16 text-green-500 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-xl font-black text-white uppercase tracking-tighter mb-4 italic">Rezension erfolgreich</p>
                    <a href="../marketplace/index.php" class="inline-block bg-white text-black px-10 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-[#8A2BE2] hover:text-white transition-all italic">Zurück zum Dashboard</a>
                </div>
            <?php else: ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-500 p-5 rounded-2xl mb-10 text-[10px] font-black uppercase tracking-[0.3em] italic">
                        SYSTEM ERROR: <?= $error ?>
                    </div>
                <?php endif; ?>

                <form action="rate-user.php?user_id=<?= $receiver_id ?>" method="POST" class="space-y-12 text-left">
                    
                    <div>
                        <label class="text-[10px] font-black text-gray-500 uppercase mb-6 block tracking-[0.3em] italic ml-4">Zufriedenheit wählen</label>
                        <div class="grid grid-cols-5 gap-4">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                            <label class="cursor-pointer group">
                                <input type="radio" name="stars" value="<?= $i ?>" class="hidden peer" required>
                                <div class="bg-white/[0.03] border border-white/5 rounded-3xl p-6 text-center transition-all duration-500 group-hover:bg-white/[0.08] group-hover:border-[#8A2BE2]/50 peer-checked:bg-[#8A2BE2] peer-checked:border-[#8A2BE2] peer-checked:shadow-[0_0_30px_rgba(138,43,226,0.4)] peer-checked:scale-105">
                                    <div class="text-2xl mb-2 transition-transform duration-500 group-hover:scale-125">
                                        <svg class="w-8 h-8 mx-auto <?= $i <= 3 ? 'text-gray-400' : 'text-white' ?> peer-checked:text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-black block uppercase italic text-gray-500 peer-checked:text-white"><?= $i ?> ?</span>
                                </div>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="relative">
                        <label class="text-[10px] font-black text-gray-500 uppercase mb-4 block tracking-[0.3em] italic ml-4">Zusätzlicher Bericht (Optional)</label>
                        <textarea name="comment" rows="6" placeholder="Beschreibe die Abwicklung..." 
                                  class="w-full bg-white/[0.02] border border-white/5 rounded-[32px] p-8 text-sm text-white outline-none focus:border-[#8A2BE2] focus:bg-white/[0.05] transition-all duration-500 shadow-inner"></textarea>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="w-full bg-[#8A2BE2] py-6 rounded-3xl text-white font-black uppercase tracking-[0.5em] text-xs shadow-2xl shadow-[#8A2BE2]/30 hover:bg-[#9d44ff] hover:scale-[1.02] active:scale-95 transition-all italic relative overflow-hidden group">
                            <span class="relative z-10">Bewertung finalisieren</span>
                            <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-500"></div>
                        </button>
                        
                        <div class="flex items-center justify-center gap-4 mt-10 opacity-30">
                            <div class="h-[1px] w-12 bg-gray-500"></div>
                            <p class="text-[8px] text-gray-500 uppercase font-black tracking-[0.4em] italic text-center">
                                Bewertungs-Eintrag ist permanent
                            </p>
                            <div class="h-[1px] w-12 bg-gray-500"></div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>