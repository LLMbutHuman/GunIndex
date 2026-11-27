<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require __DIR__.'/../config/bootstrap.php';

$key = 'business_promotions_enabled';
$namePattern = '/\Abanner_browser_[a-f0-9]{14}\z/';
$set = $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
$restore = static function (string|false $previous, int $actor) use ($pdo,$key,$set): void {
    $pdo->prepare("UPDATE market_campaigns SET status='disabled' WHERE operator_owned=1 AND user_id=?")->execute([$actor]);
    if ($previous === false) $pdo->prepare('DELETE FROM system_settings WHERE setting_key=?')->execute([$key]);
    else $set->execute([$key,$previous]);
};
$operation = $argv[1] ?? '';
if ($operation === 'restore' || $operation === 'stats') {
    $data = json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
    $q = $pdo->prepare('SELECT id FROM users WHERE username=? AND email=CONCAT(username,\'@example.invalid\')');
    if (!is_string($data['name']??null) || !preg_match($namePattern,$data['name'])) exit(2);
    $q->execute([$data['name']]);
    $actor = (int)$q->fetchColumn();
    if ($actor < 1) exit(2);
    if ($operation === 'restore') {
        $restore($data['previous'],$actor);
        echo '{}';
        exit;
    }
    $q = $pdo->prepare('SELECT c.id,COALESCE(SUM(s.impressions),0) AS impressions,COALESCE(SUM(s.clicks),0) AS clicks
        FROM market_campaigns c LEFT JOIN market_ad_daily_stats s ON s.campaign_id=c.id WHERE c.user_id=? GROUP BY c.id');
    $q->execute([$actor]);
    echo json_encode(array_column($q->fetchAll(),null,'id'),JSON_THROW_ON_ERROR);
    exit;
}
if ($operation !== 'seed') exit(2);
$previous = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='business_promotions_enabled'")->fetchColumn();
$name = 'banner_browser_'.bin2hex(random_bytes(7));
$pdo->prepare("INSERT INTO users(username,email,password_hash,is_admin,full_name) VALUES (?,?,?,1,'Synthetic banner browser fixture')")
    ->execute([$name,$name.'@example.invalid','synthetic-disabled-password']);
$actor = (int)$pdo->lastInsertId();
try {
    $set->execute([$key,'1']);
    site_settings($pdo,true);
    $ids = [];
    $select = $pdo->prepare('SELECT * FROM market_campaigns WHERE id=?');
    $routes = ['DE','PL','NL'];
    foreach ($routes as $country) {
        for ($slot=1; $slot<=3; $slot++) {
            $url = $slot===1 ? '/marketplace/profile.php?user_id='.$actor : 'https://shop.example.org/'.$country.'/'.$slot;
            $id = banner_ads_operator_save($pdo,$actor,null,['country_code'=>$country,'title'=>'Browser Banner '.$country.' '.$slot,
                'overlay_text'=>'Synthetic banner '.$country.' '.$slot,'overlay_style'=>$slot===3?'light':'dark','target_url'=>$url,
                'priority'=>(string)(10-$slot),'scheduled_from'=>gmdate('Y-m-d\TH:i',time()-3600),
                'scheduled_until'=>gmdate('Y-m-d\TH:i',time()+86400)],[]);
            $select->execute([$id]);
            banner_ads_review($pdo,$actor,$id,'approved','Synthetic browser fixture review',banner_ads_revision($select->fetch()));
            $ids[$country][] = $id;
        }
    }
    $category = catalog_categories($pdo)[0]['id'];
    echo json_encode(['name'=>$name,'previous'=>$previous,'ids'=>$ids,'profile'=>$actor,'category'=>$category],JSON_THROW_ON_ERROR);
} catch (Throwable $failure) {
    $restore($previous,$actor);
    throw $failure;
}
