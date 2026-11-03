<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);
$pageTitle=t('Inserat erstellen');
$values=['country_code'=>(string)($user['country_code']??geo_country()),'zip'=>(string)($user['zip'] ?? ''),'city'=>(string)($user['city'] ?? ''),'status'=>'active'];
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf(); $values=$_POST;
    $values['pickup']=$_POST['pickup'] ?? '0'; $values['shipping']=$_POST['shipping'] ?? '0';
    try {
        $id=market_save_listing($pdo,(int)$user['id'],$_POST,$_FILES['images'] ?? []);
        redirect('/user/dashboard.php?saved='.$id);
    } catch (InvalidArgumentException|DomainException|PaymentUnavailable|MarketAccessException|MarketConflictException $exception) {
        http_response_code(422); $error=$exception->getMessage();
    }
}
$categories=catalog_categories($pdo,true);
include __DIR__.'/../includes/header.php';
?>
<main class="page"><div class="page-heading"><p class="eyebrow">Etwas Neues beginnt</p><h1>Dein nächstes Angebot.</h1><p class="muted">Beschreibe deinen Artikel, ergänze Bilder und entscheide dich für Festpreis oder Auktion.</p></div>
<?php if ($error!==''): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if (!$categories): ?><div class="notice">Aktuell ist keine Kategorie verfügbar. Bitte später erneut versuchen.</div><?php else: include __DIR__.'/../includes/market-form.php'; endif; ?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
