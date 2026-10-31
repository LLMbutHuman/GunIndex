<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo); require_post(); require_csrf();
$id=$_POST['listing_id'] ?? null;
if (!is_string($id) || !ctype_digit($id)) abort_request(422,'Ungültige Angebotskennung.');
try { market_transition($pdo,(int)$id,(int)$user['id'],'archive'); redirect('/user/dashboard.php'); }
catch (MarketNotFoundException $error) { abort_request(404,$error->getMessage()); }
catch (MarketAccessException $error) { abort_request(403,$error->getMessage()); }
catch (MarketConflictException $error) { abort_request(409,$error->getMessage()); }
