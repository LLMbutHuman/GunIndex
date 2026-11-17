<?php
declare(strict_types=1);
/** Isolated browser fixtures: ordinary synthetic goods, no payment/provider changes. */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY === 'Windows' || getenv('DB_DATABASE') !== 'gunindex_rebuild_test') {
    if (PHP_SAPI !== 'cli') http_response_code(404);
    exit(2);
}
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/trade.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gunindex_rebuild_test') exit(2);

function tbf_check(bool $condition): void
{
    if (!$condition) throw new RuntimeException('Synthetic trade fixture invariant failed.');
}
function tbf_user(PDO $pdo, string $fixture, string $role): array
{
    $username = 'tradeweb_'.$fixture.'_'.$role;
    $password = 'Synthetic trade '.bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale) VALUES (?,?,?,'Synthetic trade browser fixture','10115','Synthetic town','DE','de')")
        ->execute([$username,$username.'@example.invalid',auth_hash($password)]);
    return ['id'=>(int)$pdo->lastInsertId(),'username'=>$username,'password'=>$password];
}
function tbf_owners(PDO $pdo, mixed $fixture): array
{
    if (!is_string($fixture) || !preg_match('/\A[a-f0-9]{32}\z/',$fixture)) throw new InvalidArgumentException('Synthetic fixture identifier required.');
    $owners = [];
    foreach (['seller','buyer','outsider'] as $role) {
        $query = $pdo->prepare("SELECT id,username FROM users WHERE username=? AND email=CONCAT(username,'@example.invalid') AND full_name='Synthetic trade browser fixture' AND is_admin=0");
        $query->execute(['tradeweb_'.$fixture.'_'.$role]); $row = $query->fetch(); tbf_check(is_array($row));
        $owners[$role] = ['id'=>(int)$row['id'],'username'=>$row['username']];
    }
    return $owners;
}
function tbf_listings(PDO $pdo, string $fixture, int $seller): array
{
    $query = $pdo->prepare('SELECT l.id,l.title,l.type,l.status,l.price_cents FROM market_listings l JOIN market_categories c ON c.id=l.category_id WHERE l.seller_id=? AND c.slug=? ORDER BY l.id');
    $query->execute([$seller,'tradeweb-'.$fixture]); $listings = [];
    foreach ($query->fetchAll() as $row) {
        $role = substr($row['title'],strlen('Synthetic trade desk '));
        tbf_check(in_array($role,['fixed','negotiated','cancel','dispute'],true));
        $listings[$role] = ['id'=>(int)$row['id'],'type'=>$row['type'],'status'=>$row['status'],'price_cents'=>(int)$row['price_cents']];
    }
    tbf_check(count($listings) === 4);
    return $listings;
}

try {
    $raw = stream_get_contents(STDIN,16385);
    if (strlen($raw) > 16384) throw new InvalidArgumentException('Fixture request too large.');
    $input = json_decode($raw,true,12,JSON_THROW_ON_ERROR); tbf_check(is_array($input));
    $operation = $input['operation'] ?? '';
    if ($operation === 'setup') {
        tbf_check(geo_country_enabled($pdo,'DE'));
        $fixture = bin2hex(random_bytes(16)); $users = []; $listings = [];
        $pdo->beginTransaction();
        try {
            maintenance_assert_market_available($pdo);
            foreach (['seller','buyer','outsider'] as $role) $users[$role] = tbf_user($pdo,$fixture,$role);
            $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic trade desks','tradeweb-'.$fixture]);
            $category = (int)$pdo->lastInsertId();
            foreach (['fixed','negotiated','cancel','dispute'] as $role) {
                $type = $role === 'negotiated' ? 'negotiated' : 'fixed';
                $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,shipping_cents,status,zip,city,country_code,pickup,shipping,ends_at,trade_checkout_enabled) VALUES (?,?,?,'Ordinary synthetic folding desk for isolated trade browser tests.',?,4500,300,'active','10115','Synthetic town','DE',1,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY),1)")
                    ->execute([$users['seller']['id'],$category,'Synthetic trade desk '.$role,$type]);
                $listings[$role] = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
        $out = ['fixture'=>$fixture,'users'=>$users,'listings'=>$listings];
    } elseif (in_array($operation,['status','restore'],true)) {
        $owners = tbf_owners($pdo,$input['fixture'] ?? null);
        if ($operation === 'restore') {
            // No global configuration changed. Keep isolated synthetic rows for diagnostics.
            $out = ['restored'=>true,'global_changes'=>false];
        } else {
            $listings = tbf_listings($pdo,$input['fixture'],$owners['seller']['id']);
            $ids = array_column($listings,'id'); $placeholders = implode(',',array_fill(0,count($ids),'?'));
            $query = $pdo->prepare('SELECT id,listing_id,seller_id,buyer_id,offer_id,delivery,price_cents,shipping_cents,total_cents,status,version,seller_confirmed,buyer_confirmed FROM market_trades WHERE listing_id IN ('.$placeholders.') ORDER BY id');
            $query->execute($ids); $trades = $query->fetchAll();
            foreach ($trades as &$trade) {
                foreach (['id','listing_id','seller_id','buyer_id','price_cents','shipping_cents','total_cents','version','seller_confirmed','buyer_confirmed'] as $field) $trade[$field]=(int)$trade[$field];
                $trade['offer_id']=$trade['offer_id']===null?null:(int)$trade['offer_id'];
                $events=$pdo->prepare('SELECT action,from_status,to_status FROM market_trade_events WHERE trade_id=? ORDER BY id');
                $events->execute([$trade['id']]); $trade['events']=$events->fetchAll();
                $ratings=$pdo->prepare('SELECT sender_id,receiver_id,stars FROM market_ratings WHERE trade_id=? ORDER BY id');
                $ratings->execute([$trade['id']]); $trade['ratings']=$ratings->fetchAll();
            } unset($trade);
            $query=$pdo->prepare('SELECT id,listing_id,parent_id,proposer_id,buyer_id,seller_id,status,price_cents,shipping_cents,total_cents FROM market_trade_offers WHERE listing_id IN ('.$placeholders.') ORDER BY id');
            $query->execute($ids); $offers=$query->fetchAll();
            foreach ($offers as &$offer) foreach (['id','listing_id','parent_id','proposer_id','buyer_id','seller_id','price_cents','shipping_cents','total_cents'] as $field) $offer[$field]=$offer[$field]===null?null:(int)$offer[$field];
            unset($offer);
            $out = ['listings'=>$listings,'trades'=>$trades,'offers'=>$offers];
        }
    } else throw new InvalidArgumentException('Unsupported trade fixture operation.');
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'Synthetic trade fixture failed: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
    exit(1);
}
