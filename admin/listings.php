<?php
require_once('../config/db.php');
require_once('../config/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * ADMIN SECURITY CHECK
 */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../marketplace/index.php");
    exit("Zugriff verweigert.");
}

$success_msg = "";
$error_msg = "";

/**
 * UPDATE-LOGIK (Bearbeiten)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_listing'])) {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $price = (float)$_POST['price'];
    $category = $_POST['category'];
    $description = trim($_POST['description']);

    $stmt = $pdo->prepare("UPDATE listings SET title = ?, price = ?, category = ?, description = ? WHERE id = ?");
    if ($stmt->execute([$title, $price, $category, $description, $id])) {
        $success_msg = "Einheit #$id erfolgreich modifiziert.";
    } else {
        $error_msg = "Fehler bei der Modifikation.";
    }
}

/**
 * LÖSCH-LOGIK
 */
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $image_path = "../assets/uploads/listings/" . $del_id . "_1.jpg";
    if (file_exists($image_path)) unlink($image_path);

    $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ?");
    if ($stmt->execute([$del_id])) {
        $success_msg = "Einheit #$del_id permanent aus Sektor entfernt.";
    }
}

/**
 * BEARBEITUNGS-DATEN LADEN (Falls im Edit-Modus)
 */
$edit_item = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_item = $stmt->fetch();
}

/**
 * HAUPTLISTE LADEN
 */
$sql = "SELECT l.*, u.username, u.email FROM listings l JOIN users u ON l.seller_id = u.id ORDER BY l.created_at DESC";
$listings = $pdo->query($sql)->fetchAll();

include('../includes/header.php');
?>

<div class="max-w-[1600px] mx-auto px-4 lg:px-10 py-12">
    
    <div class="mb-12 flex flex-col md:flex-row justify-between items-end gap-6 border-b border-white/10 pb-10">
        <div>
            <a href="index.php" class="text-[10px] text-gray-500 hover:text-[#8A2BE2] font-black uppercase tracking-[0.4em] transition flex items-center gap-2 mb-4 italic">
                ? Return to Command Center
            </a>
            <h1 class="text-5xl font-black uppercase italic text-white tracking-tighter">
                Inventory <span class="text-[#8A2BE2]">Control</span>
            </h1>
        </div>
    </div>

    <?php if($success_msg): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-500 text-[10px] font-black uppercase p-5 rounded-2xl mb-10 italic tracking-widest">
            SUCCESS: <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if($edit_item): ?>
    <div class="glass-panel p-10 rounded-[40px] border border-[#8A2BE2]/50 bg-[#8A2BE2]/5 mb-16 animate-in fade-in slide-in-from-top-4 duration-500">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-xl font-black uppercase italic text-white">Modifikations-Modus: <span class="text-[#8A2BE2]">ID #<?= $edit_item['id'] ?></span></h2>
            <a href="listings.php" class="text-[10px] font-black text-gray-500 uppercase hover:text-white transition italic">Abbrechen ?</a>
        </div>
        
        <form action="listings.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
            <div class="md:col-span-2">
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 block italic">Titel der Einheit</label>
                <input type="text" name="title" value="<?= h($edit_item['title']) ?>" class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 block italic">Preis (€)</label>
                <input type="number" step="0.01" name="price" value="<?= $edit_item['price'] ?>" class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
            </div>
            <div class="md:col-span-1">
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 block italic">Sektor (Kategorie)</label>
                <select name="category" class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition">
                    <option value="frei" <?= $edit_item['category'] == 'frei' ? 'selected' : '' ?>>Frei (18+)</option>
                    <option value="ewb" <?= $edit_item['category'] == 'ewb' ? 'selected' : '' ?>>EWB Pflicht</option>
                    <option value="optik" <?= $edit_item['category'] == 'optik' ? 'selected' : '' ?>>Optik/Zubehör</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 block italic">Technische Beschreibung</label>
                <textarea name="description" rows="4" class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white text-sm outline-none focus:border-[#8A2BE2] transition"><?= h($edit_item['description']) ?></textarea>
            </div>
            <div class="md:col-span-3">
                <button type="submit" name="update_listing" class="w-full bg-[#8A2BE2] py-4 rounded-xl text-white text-[10px] font-black uppercase tracking-[0.3em] hover:bg-[#9d44ff] transition shadow-lg shadow-[#8A2BE2]/20 italic">
                    Änderungen im System speichern
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="glass-panel rounded-[40px] border border-white/10 bg-white/[0.01] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5 text-[10px] text-gray-500 font-black uppercase tracking-widest italic">
                        <th class="p-6">Vorschau</th>
                        <th class="p-6">Objekt-Details</th>
                        <th class="p-6">Kategorie</th>
                        <th class="p-6 text-right">Preis</th>
                        <th class="p-6 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach($listings as $l): ?>
                    <tr class="hover:bg-white/[0.03] transition-all group <?= (isset($_GET['edit_id']) && $_GET['edit_id'] == $l['id']) ? 'bg-[#8A2BE2]/5' : '' ?>">
                        <td class="p-6">
                            <div class="w-16 h-16 rounded-xl overflow-hidden border border-white/10 bg-black">
                                <img src="../assets/uploads/listings/<?= $l['id'] ?>_1.jpg" onerror="this.src='../assets/img/no-image.png'" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition">
                            </div>
                        </td>
                        <td class="p-6">
                            <p class="text-white font-black uppercase italic text-sm tracking-tight"><?= h($l['title']) ?></p>
                            <p class="text-[9px] text-gray-600 font-bold uppercase tracking-widest mt-1">ID: #<?= str_pad($l['id'], 5, '0', STR_PAD_LEFT) ?> • By <?= h($l['username']) ?></p>
                        </td>
                        <td class="p-6">
                            <span class="px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-[9px] text-gray-400 font-black uppercase italic">
                                <?= h($l['category']) ?>
                            </span>
                        </td>
                        <td class="p-6 text-right">
                            <p class="text-lg font-black text-white italic tracking-tighter"><?= number_format($l['price'], 2, ',', '.') ?>€</p>
                        </td>
                        <td class="p-6 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="?edit_id=<?= $l['id'] ?>" class="p-3 bg-[#8A2BE2]/10 rounded-xl border border-[#8A2BE2]/20 hover:bg-[#8A2BE2] hover:text-white transition group/edit">
                                    <svg class="w-4 h-4 text-[#8A2BE2] group-hover/edit:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <a href="?delete_id=<?= $l['id'] ?>" onclick="return confirm('ACHTUNG: Dieses Inserat wird permanent gelöscht. Fortfahren?')" class="p-3 bg-red-600/10 rounded-xl border border-red-600/20 hover:bg-red-600 hover:text-white transition group/del">
                                    <svg class="w-4 h-4 text-red-600 group-hover/del:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>