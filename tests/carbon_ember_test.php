<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/editorial.php';
require_once __DIR__.'/../config/seo.php';

function ember_check(bool $result, string $failure): void
{
    if (!$result) throw new RuntimeException($failure);
}

function ember_reject(callable $operation, string $exception): void
{
    try { $operation(); } catch (Throwable $failure) {
        if ($failure instanceof $exception) return;
        throw $failure;
    }
    throw new RuntimeException('Invalid test input was accepted.');
}

$scope = 'A synthetic legal assessment used only for testing.';
$input = ['status'=>'exempt','reference'=>'Synthetic § 1','scope_note'=>$scope,'review_confirmed'=>'1'];
$now = strtotime('2026-11-27 12:00:00 UTC');
$review = ['status_code'=>'exempt','legal_reference'=>$input['reference'],'scope_note'=>$scope,'reviewed_at'=>'2026-11-27 11:00:00'];
ember_check(editorial_review_status(null, 'DE', $now) === 'pending','Missing entry became exempt.');
ember_check(editorial_review_status($review, 'DE', $now) === 'exempt','Current review incorrectly hidden.');
ember_check(editorial_review_status($review, 'XX', $now) === 'pending','A classification appeared without a jurisdiction source.');
foreach (['2025-11-27 12:00:00','2026-11-27 12:00:01','2026-02-30 11:00:00',''] as $date) {
    ember_check(editorial_review_status(array_replace($review,['reviewed_at'=>$date]),'DE',$now)==='pending','Invalid review date remained public.');
}
ember_check(editorial_review_form('DE',$input)===['exempt','Synthetic § 1',$scope],'Editorial form did not preserve the cited scope.');
ember_check(editorial_review_form('DE',array_replace($input,['status'=>'pending']))===['pending','',''],'Withdrawing a review left it published.');
ember_reject(fn()=>editorial_review_form('DE',array_replace($input,['review_confirmed'=>'0'])), InvalidArgumentException::class);
ember_reject(fn()=>editorial_review_form('DE',array_replace($input,['scope_note'=>''])), InvalidArgumentException::class);
ember_reject(fn()=>editorial_review_form('XX',$input), InvalidArgumentException::class);

$schema = ['seo_indexing_enabled'=>'1','maintenance_enabled'=>'0'];
$origin = 'https://market.example.org';
$base = require __DIR__.'/../locales/carbon_ember/de.php';
foreach (array_keys(i18n_locales()) as $locale) {
    $messages = require __DIR__.'/../locales/carbon_ember/'.$locale.'.php';
    ember_check(array_keys($base)===array_keys($messages) && !in_array('', $messages, true),'Incomplete localization '.$locale);
    foreach (editorial_topics() as $topic) {
        ember_check(isset(i18n_dictionary($locale)['ember.topic_'.$topic],i18n_dictionary($locale)['ember.about_'.$topic]),'Missing editorial topic.');
    }
    foreach (editorial_statuses() as $status) {
        ember_check(isset($messages['ember.status_'.$status],$messages['ember.help_'.$status]),'Missing status translation.');
    }
    $path = '/'.$locale.'/fachwissen';
    $seo = seo_page($schema,$path,null,$origin);
    ember_check($seo['canonical']===$origin.$path && count($seo['alternates'])===9 && $seo['structured']===null,'Invalid editorial canonical.');
    ember_check(!seo_page([],$path,null,$origin)['index'],'Unreleased editorial route allowed indexing.');
}
echo "PASS editorial reviews, fail-closed defaults, eight locales and canonical URLs\n";

if (!in_array('--database', $argv, true)) exit(0);
if (getenv('DB_DATABASE') !== 'gunindex_rebuild_test') throw new RuntimeException('Requires the isolated test database.');
require __DIR__.'/../config/bootstrap.php';
$topic = 'legal-basics';
$country = 'DE';
ember_check(!isset(editorial_reviews($pdo,$country)[$topic]), 'A synthetic review already exists.');
$ids = [];
try {
    foreach ([0,1] as $isAdmin) {
        $name = 'test_ember_'.bin2hex(random_bytes(7));
        $pdo->prepare('INSERT INTO users (username,email,password_hash,is_admin) VALUES (?,?,?,?)')
            ->execute([$name,$name.'@example.invalid','synthetic-disabled-password',$isAdmin]);
        $ids[$isAdmin] = (int)$pdo->lastInsertId();
    }
    $session = ['auth_started_at'=>time(),'admin_mfa_user_id'=>$ids[1]];
    ember_reject(fn()=>editorial_review_save($pdo,$ids[0],$session,$topic,$country,'0',$input),DomainException::class);
    ember_reject(fn()=>editorial_review_save($pdo,$ids[1],[],$topic,$country,'0',$input),DomainException::class);
    editorial_review_save($pdo,$ids[1],$session,$topic,$country,'0',$input);
    $entry = editorial_reviews($pdo,$country)[$topic];
    ember_check(editorial_review_status($entry,$country)==='exempt','Stored editorial classification unavailable.');
    ember_check(!isset(editorial_reviews($pdo,'AT')[$topic]),'The database exposed a review in another country.');
    ember_reject(fn()=>editorial_review_save($pdo,$ids[1],$session,$topic,$country,'0',$input),DomainException::class);
    editorial_review_save($pdo,$ids[1],$session,$topic,$country,(string)$entry['revision'],['status'=>'pending']);
    ember_check(editorial_review_status(editorial_reviews($pdo,$country)[$topic],$country)==='pending','Withdrawal failed.');
    $logs = $pdo->prepare("SELECT COUNT(*) FROM admin_logs WHERE admin_id=? AND action_type='editorial.reviewed'");
    $logs->execute([$ids[1]]);
    ember_check((int)$logs->fetchColumn()===2,'Review and withdrawal need independent audit records.');
    $categories = array_column(catalog_categories($pdo), null, 'slug');
    foreach (['jagd-revier'=>'jagdbekleidung','schiesssport'=>'gehoerschutz','optik'=>'fernglaeser','outdoor-camping'=>'rucksaecke','pflege-aufbewahrung'=>'transporttaschen'] as $parent=>$child) {
        ember_check(isset($categories[$parent],$categories[$child]) && $categories[$child]['parent_id']===$categories[$parent]['id'],'Missing additive equipment categories.');
    }
    ember_check(isset($categories['buecher-medien']) && !isset($categories['ammunition']) && !isset($categories['long-guns']),'Legacy catalog or commerce boundary changed.');
    echo "PASS isolated MySQL reviews, permissions, MFA, audit, concurrency guard and additive taxonomy\n";
} finally {
    if (isset($ids[1])) {
        $pdo->prepare('DELETE FROM market_editorial_reviews WHERE reviewed_by=?')->execute([$ids[1]]);
        $pdo->prepare('DELETE FROM admin_logs WHERE admin_id=?')->execute([$ids[1]]);
    }
    foreach ($ids as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
