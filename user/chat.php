<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$user = require_login($pdo); $userId = (int)$user['id'];
$listingId = is_string($_GET['listing_id'] ?? null) ? filter_var($_GET['listing_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
$peerId = is_string($_GET['user_id'] ?? null) ? filter_var($_GET['user_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
if (!$listingId || !$peerId || (int)$peerId === $userId) abort_request(404, 'Unterhaltung nicht gefunden.');
$item = market_find($pdo, (int)$listingId, true);
if (!$item) abort_request(404, 'Angebot nicht gefunden.');
$sellerId = (int)$item['seller_id'];
if ($userId !== $sellerId && (int)$peerId !== $sellerId) abort_request(403, 'Diese Unterhaltung ist privat.');
$query = $pdo->prepare('SELECT id,username,is_active FROM users WHERE id=?'); $query->execute([$peerId]); $peer = $query->fetch();
if (!$peer) abort_request(404, 'Unterhaltung nicht gefunden.');
$pairSql = 'listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))';
$pair = [(int)$listingId, $userId, (int)$peerId, (int)$peerId, $userId];
$existing = $pdo->prepare('SELECT 1 FROM market_messages WHERE ' . $pairSql . ' LIMIT 1');
$existing->execute($pair); $hasConversation = (bool)$existing->fetchColumn();
$winnerPair = $item['type'] === 'auction' && in_array($item['status'], ['ended','sold'], true)
    && (int)($item['highest_bidder_id'] ?? 0) > 0
    && (($userId === $sellerId && (int)$peerId === (int)$item['highest_bidder_id']) || ((int)$peerId === $sellerId && $userId === (int)$item['highest_bidder_id']));
if (!$hasConversation && !$winnerPair && !($item['status'] === 'active' && $userId !== $sellerId)) abort_request(403, 'Für dieses Angebot kann keine neue Unterhaltung begonnen werden.');
$error = ''; $text = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'send';
    if (!is_string($action) || !in_array($action, ['send','read'], true)) abort_request(422, 'Ungültige Aktion.');
    if ($action === 'send') {
        $text = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        if ($text === '' || mb_strlen($text) > 5000 || strlen($text) > 20000) { http_response_code(422); $error = 'Bitte schreibe eine Nachricht mit höchstens 5.000 Zeichen.'; }
        elseif (!(bool)$peer['is_active']) { http_response_code(409); $error = 'Dieses Konto nimmt aktuell keine neuen Nachrichten an.'; }
    }
    if ($error === '') {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT * FROM market_listings WHERE id=? FOR UPDATE'); $lock->execute([$listingId]); $fresh = $lock->fetch();
            if (!$fresh || !in_array($fresh['status'], ['active','ended','sold'], true) || !catalog_category_allowed($pdo, (int)$fresh['category_id'])) throw new DomainException('Dieses Angebot ist nicht mehr für Nachrichten verfügbar.');
            $existing->execute($pair); $hasConversation = (bool)$existing->fetchColumn();
            $winnerPair = $fresh['type'] === 'auction' && in_array($fresh['status'], ['ended','sold'], true)
                && (int)($fresh['highest_bidder_id'] ?? 0) > 0
                && (($userId === $sellerId && (int)$peerId === (int)$fresh['highest_bidder_id']) || ((int)$peerId === $sellerId && $userId === (int)$fresh['highest_bidder_id']));
            if (!$hasConversation && !$winnerPair && !($fresh['status'] === 'active' && $userId !== $sellerId)) throw new DomainException('Für dieses Angebot kann keine neue Unterhaltung begonnen werden.');
            if ($action === 'send') {
                $budgetLock = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $budgetLock->execute([$userId]);
                $budget = $pdo->prepare('SELECT COUNT(*) FROM market_messages WHERE sender_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 MINUTE'); $budget->execute([$userId]);
                if ((int)$budget->fetchColumn() >= 20) throw new OverflowException('Bitte warte kurz, bevor du weitere Nachrichten sendest.');
                $insert = $pdo->prepare('INSERT INTO market_messages(listing_id,sender_id,receiver_id,message_text,is_read,created_at) VALUES (?,?,?,?,0,UTC_TIMESTAMP())');
                $insert->execute([$listingId,$userId,$peerId,$text]);
            }
            $read = $pdo->prepare('UPDATE market_messages SET is_read=1 WHERE listing_id=? AND sender_id=? AND receiver_id=? AND is_read=0'); $read->execute([$listingId,$peerId,$userId]);
            $pdo->commit(); redirect('/user/chat.php?listing_id=' . (int)$listingId . '&user_id=' . (int)$peerId);
        } catch (DomainException $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(409); $error = $exception->getMessage(); }
        catch (OverflowException $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(429); header('Retry-After: 60'); $error = $exception->getMessage(); }
        catch (Throwable) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(503); $error = 'Die Nachricht konnte nicht gespeichert werden. Bitte versuche es erneut.'; }
    }
}
$history = $pdo->prepare('SELECT id,sender_id,receiver_id,message_text,is_read,created_at FROM market_messages WHERE ' . $pairSql . ' ORDER BY id DESC LIMIT 50');
$history->execute($pair); $messages = array_reverse($history->fetchAll());
$pageTitle = 'Nachricht an ' . $peer['username']; require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
<div class="page-heading"><p class="eyebrow">Direkter Kontakt</p><h1><?= h($peer['username']) ?></h1><p><?= h($item['title']) ?></p><a href="/marketplace/<?= $item['type'] === 'auction' ? 'view-auction' : 'view-item' ?>.php?id=<?= (int)$listingId ?>">Zum Angebot</a> · <a href="/user/messages.php">Alle Nachrichten</a></div>
<section class="panel" aria-label="Nachrichtenverlauf">
<?php if (!$messages): ?><p>Noch keine Nachrichten. Stelle deine Frage zum Angebot.</p><?php endif; ?>
<?php foreach ($messages as $message): ?>
<article class="panel"><strong><?= (int)$message['sender_id'] === $userId ? 'Du' : h($peer['username']) ?></strong><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($message['message_text']) ?></p><small><?= h($message['created_at']) ?> UTC</small></article>
<?php endforeach; ?>
<?php if ($messages): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><button class="button secondary" type="submit">Als gelesen markieren</button></form><?php endif; ?>
</section>
<section class="panel"><h2>Deine Nachricht</h2>
<?php if ($error): ?><p class="notice" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ((bool)$peer['is_active']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send"><label class="field">Nachricht<textarea name="message" rows="5" maxlength="5000" required><?= h($text) ?></textarea></label><button class="button" type="submit">Nachricht senden</button></form>
<?php else: ?><p>Dieses Konto nimmt aktuell keine neuen Nachrichten an.</p><?php endif; ?>
</section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
