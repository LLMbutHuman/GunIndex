<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_post(); $user = require_login($pdo); require_csrf();
$value = $_POST['listing_id'] ?? $_POST['id'] ?? null;
$id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false) { abort_request(422, 'Bitte ein gültiges Angebot auswählen.'); }
$pdo->beginTransaction();
try {
    $lock = $pdo->prepare('SELECT is_active,auth_version FROM users WHERE id=? FOR UPDATE');
    $lock->execute([$user['id']]); $fresh = $lock->fetch();
    if (!$fresh || (int)$fresh['is_active'] !== 1 || (int)$fresh['auth_version'] !== (int)$user['auth_version']) {
        $pdo->rollBack(); abort_request(403, 'Bitte melde dich erneut an.');
    }
    $item = market_find($pdo, (int)$id, false);
    if (!$item) { $pdo->rollBack(); abort_request(404, 'Dieses Angebot ist nicht verfügbar.'); }
    $query = $pdo->prepare('SELECT user_id FROM market_watchlist WHERE user_id=? AND listing_id=?'); $query->execute([$user['id'], $id]);
    if ($query->fetchColumn() === false) {
        $pdo->prepare('INSERT INTO market_watchlist(user_id,listing_id) VALUES(?,?)')->execute([$user['id'], $id]);
    } else { $pdo->prepare('DELETE FROM market_watchlist WHERE user_id=? AND listing_id=?')->execute([$user['id'], $id]); }
    $pdo->commit(); redirect('/user/watchlist.php');
} catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
