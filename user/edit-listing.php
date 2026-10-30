<?php
require_once('../config/db.php');
require_once('../config/functions.php');
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$user_id = $_SESSION['user_id'];
$listing_id = $_GET['id'] ?? 0;

// Prüfen, ob das Inserat dem User gehört
$stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ? AND seller_id = ?");
$stmt->execute([$listing_id, $user_id]);
$item = $stmt->fetch();

if (!$item) { die("Inserat nicht gefunden oder keine Berechtigung."); }

$success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $price = $_POST['price'];
    $desc  = $_POST['description'];

    $update = $pdo->prepare("UPDATE listings SET title = ?, price = ?, description = ? WHERE id = ?");
    if ($update->execute([$title, $price, $desc, $listing_id])) {
        $success = "Änderungen gespeichert!";
    }
}

include('../includes/header.php');
?>

<div class="max-w-2xl mx-auto px-4 py-20">
    <div class="glass-panel p-10 rounded-[40px]">
        <h1 class="text-2xl font-black text-white uppercase mb-8">Inserat <span class="text-[#8A2BE2]">bearbeiten</span></h1>
        
        <?php if($success): ?>
            <div class="bg-green-500/10 border border-green-500/50 text-green-500 p-4 rounded-xl mb-6 text-xs font-bold uppercase tracking-widest text-center"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="text-[10px] font-black text-gray-500 uppercase mb-2 block">Titel des Inserats</label>
                <input type="text" name="title" value="<?= h($item['title']) ?>" class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white outline-none focus:border-[#8A2BE2]">
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-500 uppercase mb-2 block">Preis (€)</label>
                <input type="number" step="0.01" name="price" value="<?= h($item['price']) ?>" class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white outline-none focus:border-[#8A2BE2]">
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-500 uppercase mb-2 block">Beschreibung</label>
                <textarea name="description" rows="5" class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white outline-none focus:border-[#8A2BE2]"><?= h($item['description']) ?></textarea>
            </div>
            <button type="submit" class="w-full btn-action py-4 rounded-xl text-white font-black uppercase tracking-widest text-xs">Änderungen übernehmen</button>
            <a href="dashboard.php" class="block text-center text-gray-600 text-[10px] font-black uppercase mt-4 hover:text-white transition">Abbrechen & Zurück</a>
        </form>
    </div>
</div>