<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$user = require_login($pdo); $userId = (int)$user['id'];
$query = $pdo->prepare("SELECT m.id,m.listing_id,m.sender_id,m.receiver_id,m.message_text,m.is_read,m.created_at,l.title,l.category_id,l.status,s.username AS sender_name,r.username AS receiver_name FROM market_messages m JOIN market_listings l ON l.id=m.listing_id JOIN users s ON s.id=m.sender_id JOIN users r ON r.id=m.receiver_id WHERE (m.sender_id=? OR m.receiver_id=?) AND l.status IN ('active','ended','sold') ORDER BY m.id DESC LIMIT 300");
$query->execute([$userId,$userId]); $threads = [];
foreach ($query->fetchAll() as $row) {
    if (!catalog_category_allowed($pdo,(int)$row['category_id'])) continue;
    $peerId = (int)$row['sender_id'] === $userId ? (int)$row['receiver_id'] : (int)$row['sender_id'];
    $key = $row['listing_id'] . ':' . $peerId;
    if (!isset($threads[$key])) {
        $row['peer_id'] = $peerId; $row['peer_name'] = (int)$row['sender_id'] === $userId ? $row['receiver_name'] : $row['sender_name'];
        $row['unread'] = false; $threads[$key] = $row;
    }
    if ((int)$row['receiver_id'] === $userId && !(bool)$row['is_read']) $threads[$key]['unread'] = true;
}
$pageTitle = 'Meine Nachrichten'; require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell"><div class="page-heading"><p class="eyebrow">Im Gespräch bleiben</p><h1>Meine Nachrichten</h1><p>Deine persönlichen Unterhaltungen zu Angeboten.</p></div>
<?php if (!$threads): ?><section class="panel"><h2>Hier beginnt der Austausch</h2><p>Kontaktiere einen Verkäufer direkt über sein Angebot.</p><a class="button" href="/marketplace/search.php">Angebote entdecken</a></section><?php endif; ?>
<div class="form-grid">
<?php foreach (array_slice($threads,0,100) as $thread): ?>
<article class="panel"><p class="eyebrow"><?= h($thread['peer_name']) ?><?php if ($thread['unread']): ?> · Ungelesen<?php endif; ?></p><h2><a href="/user/chat.php?listing_id=<?= (int)$thread['listing_id'] ?>&amp;user_id=<?= (int)$thread['peer_id'] ?>"><?= h($thread['title']) ?></a></h2><p><?= h(mb_strimwidth($thread['message_text'],0,150,'…')) ?></p><small><?= h($thread['created_at']) ?> UTC</small></article>
<?php endforeach; ?>
</div></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
